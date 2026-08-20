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

  function setSelect(name, value) {
    var $el = field(name);
    if (!$el.length) {
      return;
    }
    $el.val(value);
    $el.trigger('change');
    $el.trigger({
      type: 'select2:select',
      params: { data: { id: value, text: value } }
    });
  }

  function waitForCity(name, city, done) {
    var tries = 0;
    var timer = setInterval(function () {
      tries += 1;
      var $city = field(name);
      var has = $city.find('option').filter(function () {
        return String($(this).val()).toUpperCase() === String(city).toUpperCase();
      }).length;
      if (has || (!$city.find('option').length && tries > 4)) {
        $city.val(city);
        $city.find('option').each(function () {
          if (String(this.value).toUpperCase() === String(city).toUpperCase()) {
            $city.val(this.value);
          }
        });
        $city.trigger('change');
        clearInterval(timer);
        done();
        return;
      }
      if (tries > 20) {
        clearInterval(timer);
        done();
      }
    }, 250);
  }

  function fillValid() {
    var d = window.cxpFillAddress || {};
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

    setSelect('billing_state', d.state);
    setSelect('shipping_state', d.state);

    waitForCity('billing_city', d.city, function () {
      waitForCity('shipping_city', d.city, function () {
        $(document.body).trigger('update_checkout');
      });
    });

    setText('cxp_card_number', '4242424242424242');
    setText('cxp_card_exp', '12/34');
    setText('cxp_card_cvc', '123');
  }

  $(document).on('click', '#cxp-fill-valid', function (e) {
    e.preventDefault();
    fillValid();
  });
})(jQuery);
