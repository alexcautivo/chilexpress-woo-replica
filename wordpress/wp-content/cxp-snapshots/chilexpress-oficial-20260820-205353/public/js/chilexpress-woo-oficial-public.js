(function ($) { // NOSONAR
  "use strict";
  let state_input =
    ".woocommerce-shipping-calculator input[type=text][name=calc_shipping_state]"; // NOSONAR
  let city_input =
    ".woocommerce-shipping-calculator input[type=text][name=calc_shipping_city]"; // NOSONAR
  let option_value = '<option value="'; // NOSONAR
  let cargando_comunas = '" selected="selected">Cargando comunas...</option>'; // NOSONAR
  let selected_selected = 'selected="selected"'; // NOSONAR
  let obtener_comuna_desde_region =
    "action=obtener_comunas_desde_region&region="; // NOSONAR
  function transform_woo_shipping_calculator() { // NOSONAR

    let $state,
      $city,
      $state_parent,
      $city_parent,
      state_value,
      city_value,
      $new_state,
      $new_city;

    if ($(state_input).length) {
      $state = $(state_input);
      $city = $(city_input);
      $state_parent = $state.parents("p");
      $city_parent = $city.parents("p");

      state_value = $state.val();
      city_value = $city.val();

      $new_state = $(
        '<select name="calc_shipping_state" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_statex" placeholder="' +
          $state.attr("placeholder") +
          '" data-placeholder="' +
          $state.attr("placeholder") +
          '"><option value="' +
          state_value +
          '" selected="selected">Cargando Región...</option></select>'
      ); // NOSONAR
      $new_city = $(
        '<select name="calc_shipping_city" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_cityx" placeholder="' +
          $city.attr("placeholder") +
          '" data-placeholder="' +
          $city.attr("placeholder") +
          '"><option value="' +
          city_value +
          '" selected="selected"> Cargando Comuna...</option></select>'
      ); // NOSONAR

      $state_parent.append($new_state);
      $state.remove();

      $city_parent.append($new_city);
      $city.remove();

      $new_state.select2({ minimumResultsForSearch: 5 });
      $new_city.select2({ minimumResultsForSearch: 5 });

      jQuery.ajax({
        type: "post",
        url: woocommerce_params.ajax_url,
        dataType: "json",
        data: "action=obtener_regiones&nonce=" + woocommerce_chilexpress.nonce,
        success: function (result) {
          if (result.regiones) {
            let regiones_html = "";
            for (let k in result.regiones) {
              regiones_html +=
                option_value +
                k +
                '" ' +
                (state_value === k ? selected_selected : "") +
                ">" +
                result.regiones[k] +
                "</option>";
            }
            $new_state.html(regiones_html);
          } else {
            $new_state.html("");
          }

          state_value = $new_state.val();

          jQuery.ajax({
            type: "post",
            url: woocommerce_params.ajax_url,
            dataType: "json",
            data:
              obtener_comuna_desde_region +
              state_value +
              "&nonce=" +
              woocommerce_chilexpress.nonce,
            success: function (iresult) {
              if (iresult.comunas) {
                let comunas_html = "";
                for (let k2 in iresult.comunas) {
                  comunas_html +=
                    option_value +
                    k2 +
                    '" ' +
                    (city_value === k2 ? selected_selected : "") +
                    ">" +
                    k2 +
                    "</option>";
                }
                $new_city.html(comunas_html);
              } else {
                $new_city.html("");
              }
            },
          });
        },
      });

      $new_state.on("change", function (event) {
        state_value = $new_state.val();
        city_value = $new_city.val();
        $new_city.html(
          option_value +
            city_value +
            '" selected="selected"> Cargando Comuna...</option>'
        );

        jQuery.ajax({
          type: "post",
          url: woocommerce_params.ajax_url,
          dataType: "json",
          data:
            obtener_comuna_desde_region +
            state_value +
            "&nonce=" +
            woocommerce_chilexpress.nonce,
          success: function (result) {
            if (result.comunas) {
              let comunas_html = [];
              for (let k in result.comunas) {
                comunas_html.push(
                  option_value +
                    k +
                    '" ' +
                    (city_value === k ? selected_selected : "") +
                    ">" +
                    k +
                    "</option>"
                );
              }
              $new_city.html(comunas_html.join(""));
            } else {
              $new_city.html("");
            }
          },
        });
      });
      // A veces solo a veces, solo cambia el county pero no el city asi que en ese caso debemos solo trabajar con el city
    } else if ($(city_input).length) {
      $city = $(city_input);
      $state = $(
        ".woocommerce-shipping-calculator select[name=calc_shipping_state]"
      );
      $city_parent = $city.parents("p");
      city_value = $city.val();
      $new_city = $(
        '<select name="calc_shipping_city" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_cityx" placeholder="' +
          $city.attr("placeholder") +
          '" data-placeholder="' +
          $city.attr("placeholder") +
          '"><option value="' +
          city_value +
          '" selected="selected"> Cargando Comuna...</option></select>'
      );

      $city_parent.append($new_city);
      $city.remove();

      state_value = $("#calc_shipping_state,#calc_shipping_statex").val();

      jQuery.ajax({
        type: "post",
        url: woocommerce_params.ajax_url,
        dataType: "json",
        data:
          obtener_comuna_desde_region +
          state_value +
          "&nonce=" +
          woocommerce_chilexpress.nonce,
        success: function (result) {
          if (result.comunas) {
            let comunas_html = "";
            for (let k in result.comunas) {
              comunas_html +=
                option_value +
                k +
                '" ' +
                (city_value === k ? selected_selected : "") +
                ">" +
                k +
                "</option>";
            }
            $new_city.html(comunas_html);
          } else {
            $new_city.html("");
          }
        },
      });

      $("#calc_shipping_state,#calc_shipping_statex").on(
        "change",
        function (event) {
          state_value = $state.val();
          city_value = $new_city.val();
          $new_city.html(
            option_value +
              city_value +
              '" selected="selected"> Cargando Comuna...</option>'
          );

          jQuery.ajax({
            type: "post",
            url: woocommerce_params.ajax_url,
            dataType: "json",
            data:
              obtener_comuna_desde_region +
              state_value +
              "&nonce=" +
              woocommerce_chilexpress.nonce,
            success: function (result) {
              // NOSONAR
              if (result.comunas) {
                let comunas_html = "";
                for (let k in result.comunas) {
                  comunas_html +=
                    option_value +
                    k +
                    '" ' +
                    (city_value === k ? selected_selected : "") +
                    ">" +
                    k +
                    "</option>";
                }
                $new_city.html(comunas_html);
              } else {
                $new_city.html("");
              }
            },
          });
        }
      );
      $("#calc_shipping_cityx").select2();
    }
  }
  $(function () { // NOSONAR

    transform_woo_shipping_calculator();

    $(document).on("click", "a.shipping-calculator-button", function (ev) {
      // NOSONAR
      if ($(state_input).length || $(city_input).length) {
        transform_woo_shipping_calculator();
      }
    });

    /** SECCION PARA CHECKOUT **/
    /** Se hicieron cambios adecuando el codigo al select2 que ahora viene integrado en woocommerce llamado selectWoo **/

    const fillCityByRegion = (type, region) => {
      let city_value = $("#" + type + "_city").val();
      jQuery.ajax({
        type: "post",
        url: woocommerce_params.ajax_url,
        dataType: "json",
        data:
          obtener_comuna_desde_region +
          region +
          "&nonce=" +
          woocommerce_chilexpress.nonce,
        success: function (result) {
          if (result.comunas) {
            
            let comunas_html = "";
            
            for (let k in result.comunas) {
              comunas_html +=
                option_value +
                k +
                '" ' +
                (city_value === k ? selected_selected : "") +
                ">" +
                k +
                "</option>";
            }
            $("#" + type + "_city").html(comunas_html);
            $("#" + type + "_city").trigger("change");
          } else {
            $("#" + type + "_city").html("");
          }
        },
      });
    }

    ['billing', 'shipping'].forEach((type) => {
      const $state = $("#" + type + "_state");
      const $city = $("#" + type + "_city");
      try {
        const selectedRegionByDefault = $state.select2('data');
        if (selectedRegionByDefault.length > 0 && selectedRegionByDefault[0].id === '') {
          $city.html('');
          $city.trigger("change");
          $state.val('RM');
          $state.trigger('change');
        }
      } catch (e) {
        $state.val('RM');
        $state.trigger("change");
        $city.html('');
        $city.trigger("change");
        fillCityByRegion(type,'RM');
      }
      $state.on('select2:select', (ev) => {
          const region = ev.params.data.id;
          $city.html('');
          fillCityByRegion(type,region);   
      });
  
    });
    /** FIN - SECCION PARA CHECKOUT **/



    $("a.tracking-link").on("click", function (ev) {
      // NOSONAR

      let old_text = $(ev.currentTarget).text();
      if (old_text === "Cargando...") {
        return;
      }
      $(ev.currentTarget).text("Cargando...");
      jQuery.ajax({
        type: "post",
        url: woocommerce_params.ajax_url,
        dataType: "json",
        data:
          "action=track_order&nonce=" +
          woocommerce_chilexpress.nonce +
          "&pid=" +
          $(this).data("pid") +
          "&ot=" +
          $(this).data("ot"),
        success: function (result) {
          let data = {};
          $(ev.currentTarget).text(old_text);
          if (result.error) {
            alert(result.error);
            return;
          }
          if (result.response && result.response.data) {
            data = result.response;
          } else {
            data = result;
          }
          $(this).WCBackboneModal({
            template: "wc-modal-track-order",
            variable: data,
          });

          setTimeout(function () {
            if (data.data.trackingEvents.length) {
              let html = "";
              $.each(data.data.trackingEvents, function (index, item) {
                html +=
                  "<tr><td>" +
                  item.eventDate +
                  "</td><td>" +
                  item.eventHour +
                  "</td><td>" +
                  item.description +
                  "</td><td></td></tr>";
              });
              $("#wc-chilexpress-events > tbody").html(html);
            } else {
              $("#wc-chilexpress-events > tbody > tr > td").text(
                "No existen eventos aún para este envio."
              );
            }
          }, 500);
        },
      });
    });

    function updateShippingCartCalculatorLabel() {
      $("#shipping_method label").each(function (index, el) {
        if ($(el).text().indexOf("Chilexpress") > -1) {
          $(el).html(
            $(el)
              .text()
              .replace(
                "Chilexpress",
                '<img src="' +
                  woocommerce_chilexpress.base_url +
                  'imgs/logo-chilexpress-negro.png" style="width: 120px; margin-right: 0.2em; margin-top:0px; margin-bottom:-4px;" />'
              )
          );
        }
      });
    }

    $(document.body).on(
      "change",
      "#shipping_method input[type=radio].shipping_method",
      function (ev) {
        for (let i = 100; i < 2000; i = i + 50) {
          setTimeout(function () {
            updateShippingCartCalculatorLabel();
          }, i);
        }
      }
    );

    $(document.body).on("updated_wc_div", function (ev) {
      updateShippingCartCalculatorLabel();
    });

    $(document.body).on("updated_checkout", function (ev) {
      updateShippingCartCalculatorLabel();
    });

    updateShippingCartCalculatorLabel();
  });
})(jQuery);
