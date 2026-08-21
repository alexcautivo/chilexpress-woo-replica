(function ($) {
  'use strict';

  function field(name) {
    var $el = $('#' + name);
    return $el.length ? $el : $('[name="' + name + '"]');
  }

  function setText(name, value) {
    var $el = field(name);
    if (!$el.length) {
      return;
    }
    $el.val(value).trigger('input').trigger('change');
  }

  function setSelect(name, value, silent) {
    var $el = field(name);
    if (!$el.length) {
      return;
    }
    $el.val(value);
    if (!silent) {
      $el.trigger('change');
      $el.trigger({
        type: 'select2:select',
        params: { data: { id: value, text: value } }
      });
    }
  }

  function norm(value) {
    return String(value || '')
      .toUpperCase()
      .replace(/Á/g, 'A')
      .replace(/É/g, 'E')
      .replace(/Í/g, 'I')
      .replace(/Ó/g, 'O')
      .replace(/Ú/g, 'U')
      .replace(/Ñ/g, 'N');
  }

  function matchOption($el, wanted) {
    var list = wanted.map(norm).filter(Boolean);
    var matched = '';
    $el.find('option').each(function () {
      var v = norm(this.value);
      var t = norm(this.text);
      if (list.indexOf(v) !== -1 || list.indexOf(t) !== -1) {
        matched = this.value;
      }
    });
    return matched;
  }

  /**
   * Chilexpress llena región y comuna por AJAX. Fijar el valor de una sola vez
   * falla si las opciones todavía no existen: el campo queda vacío y el
   * checkout responde «Región es un campo requerido». Se espera a la opción.
   */
  function waitForOption(name, wanted, done) {
    var tries = 0;
    var timer = setInterval(function () {
      tries += 1;
      var $el = field(name);
      if (!$el.length) {
        if (tries > 40) {
          clearInterval(timer);
          done(false);
        }
        return;
      }
      var matched = matchOption($el, wanted);
      if (matched) {
        clearInterval(timer);
        if (String($el.val() || '') !== matched) {
          $el.val(matched);
          $el.trigger('change');
          $el.trigger({
            type: 'select2:select',
            params: { data: { id: matched, text: matched } }
          });
        }
        done(true, matched);
        return;
      }
      if (tries > 40) {
        clearInterval(timer);
        done(false);
      }
    }, 200);
  }

  function addressById(id) {
    var map = (window.cxpCheckoutLab && window.cxpCheckoutLab.addresses) || {};
    return map[id] || null;
  }

  function fillAddress(d) {
    if (!d) {
      return;
    }
    ['billing', 'shipping'].forEach(function (g) {
      setText(g + '_first_name', d.first_name);
      setText(g + '_last_name', d.last_name);
      setSelect(g + '_country', d.country || 'CL');
      setText(g + '_address_1', d.address_1);
      setText(g + '_address_2', d.address_2);
      setText(g + '_postcode', d.postcode || '');
      setText(g + '_phone', d.phone);
    });
    setText('billing_email', d.email);
    setText('billing_address_3', d.complement || 'Casa');
    setText('shipping_address_3', d.complement || 'Casa');
    $('#ship-to-different-address-checkbox').prop('checked', false).trigger('change');
    // La comuna depende de la región: primero se fija la región y solo después
    // se busca la comuna, que Chilexpress carga en respuesta a ese cambio.
    var state = [d.state || 'RM', d.state_name];
    var city = [d.city, d.county_code];
    // Facturación es lo que valida el checkout: no debe quedar esperando a los
    // campos de envío, que a veces ni siquiera se renderizan.
    waitForOption('billing_state', state, function () {
      waitForOption('billing_city', city, function () {
        $(document.body).trigger('update_checkout');
      });
    });
    waitForOption('shipping_state', state, function () {
      waitForOption('shipping_city', city, function () {});
    });
  }

  function showProbe($row, ok, text) {
    var $out = $row.find('.cxp-probe-out');
    $out.removeAttr('hidden')
      .toggleClass('is-ok', !!ok)
      .toggleClass('is-bad', !ok)
      .text(text);
  }

  function formatProbe(payload) {
    var data = payload.data || payload;
    var lines = [];
    lines.push(data.quote_ready ? 'Elige un tipo de envío. Esos precios pasan al checkout.' : 'Faltan datos para cotizar.');
    if (data.message) {
      lines.push(data.message);
    }
    if (data.origin && data.destination) {
      lines.push('Origen ' + data.origin + ' → destino ' + data.destination + ' (' + (data.city || '') + ')');
    }
    if (data.package) {
      lines.push(
        'Bulto ' +
          data.package.weight +
          ' kg · ' +
          data.package.length +
          '×' +
          data.package.width +
          '×' +
          data.package.height +
          ' cm · declarado $' +
          data.package.declared
      );
    }
    return lines.join('\n');
  }

  function renderProbeRadios($row, data) {
    $row.find('.cxp-api-rates').remove();
    var services = (data && data.services) || [];
    if (!services.length) {
      return;
    }
    var $box = $('<div class="cxp-api-rates"></div>');
    services.forEach(function (svc) {
      var price = svc.price_discount || svc.price || '—';
      var $lab = $('<label class="cxp-api-rate"></label>');
      $lab.append(
        $('<input type="radio" class="cxp-api-rate-input">')
          .attr('name', 'cxp_api_rate_' + $row.data('addr'))
          .attr('value', svc.code)
          .attr('data-addr', $row.data('addr'))
      );
      $lab.append(
        $('<span></span>').html(
          '<strong>' +
            (svc.name || 'Servicio ' + svc.code) +
            '</strong> · $' +
            price
        )
      );
      $box.append($lab);
    });
    $row.append($box);
  }

  function selectWooRate(code) {
    var idPart = String(code);
    var $radio = $('input.shipping_method').filter(function () {
      return String(this.value).indexOf(':' + idPart) !== -1 || String(this.value) === idPart;
    }).first();
    if ($radio.length) {
      $radio.prop('checked', true).trigger('change');
    }
  }

  function probeAddress(id, $row, chosen) {
    var lab = window.cxpCheckoutLab || {};
    var dest = addressById(id);
    if (dest) {
      fillAddress(dest);
      $row.addClass('is-picked').siblings().removeClass('is-picked');
    }
    showProbe($row, true, 'Consultando cotizador Chilexpress…');
    $.post(lab.ajax, {
      action: 'cxp_probe_rate',
      nonce: lab.nonce,
      addr: id,
      chosen: chosen || ''
    })
      .done(function (res) {
        var data = (res && res.data) || {};
        var ok = !!(res && res.success && data.quote_ready);
        showProbe($row, ok, formatProbe(res || {}));
        if (ok) {
          renderProbeRadios($row, data);
          if (chosen) {
            $row.find('.cxp-api-rate-input[value="' + chosen + '"]').prop('checked', true);
          }
          $(document.body).one('updated_checkout', function () {
            if (chosen) {
              selectWooRate(chosen);
            }
            goStep(2);
          });
          $(document.body).trigger('update_checkout');
        }
      })
      .fail(function (xhr) {
        var res = xhr.responseJSON || {};
        var data = res.data || {};
        showProbe($row, false, data.message || 'No se pudo consultar la API.');
      });
  }

  function val(name) {
    var $el = field(name);
    return $el.length ? String($el.val() || '').trim() : '';
  }

  function refreshSummary() {
    var name = [val('billing_first_name'), val('billing_last_name')].filter(Boolean).join(' ');
    var addr = [
      val('billing_address_1'),
      val('billing_address_2'),
      val('billing_city'),
      val('billing_state')
    ].filter(Boolean).join(', ');
    var $ship = $('input.shipping_method:checked');
    var shipLabel = '';
    if ($ship.length) {
      shipLabel = $('label[for="' + $ship.attr('id') + '"]').text().replace(/\s+/g, ' ').trim();
    }
    $('.js-sum-name').text(name || '—');
    $('.js-sum-email').text(val('billing_email') || '—');
    $('.js-sum-addr').text(addr || '—');
    $('.js-sum-ship').text(shipLabel || 'Elige un radio de Chilexpress');
  }

  function goStep(step) {
    var payment = String(step) === '2';
    document.body.classList.toggle('cxp-checkout-on-payment', payment);
    $('.cxp-checkout-steps__btn').removeClass('is-active');
    $('.cxp-checkout-steps__btn[data-cxp-step="' + (payment ? '2' : '1') + '"]').addClass('is-active');
    if (payment) {
      refreshSummary();
      $(document.body).trigger('update_checkout');
      window.location.hash = 'pago';
    } else {
      window.location.hash = 'datos';
    }
  }

  $(document).on('click', '.cxp-fill-addr', function (e) {
    e.preventDefault();
    var id = $(this).data('addr');
    fillAddress(addressById(id));
    $(this).closest('.cxp-addr').addClass('is-picked').siblings().removeClass('is-picked');
  });

  $(document).on('click', '.cxp-probe-addr', function (e) {
    e.preventDefault();
    var id = $(this).data('addr');
    probeAddress(id, $(this).closest('.cxp-addr'));
  });

  $(document).on('change', '.cxp-api-rate-input', function () {
    var id = $(this).data('addr');
    var code = $(this).val();
    probeAddress(id, $(this).closest('.cxp-addr'), code);
  });

  $(document).on('click', '.cxp-checkout-steps__btn, .cxp-go-payment, .cxp-go-data', function (e) {
    e.preventDefault();
    goStep($(this).data('cxp-step'));
  });

  $(document.body).on('updated_checkout', refreshSummary);
  $(document).on('change', 'input.shipping_method', refreshSummary);

  if (window.location.hash === '#pago') {
    goStep(2);
  }
})(jQuery);
