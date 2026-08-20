(function ($) {
  // NOSONAR
  "use strict";

  $(function () {
    const selected_selected = 'selected="selected"'; // NOSONAR
    const option_value = '<option value="'; // NOSONAR

    initQuote();
    initRegionsCommunes();
    initGeneral();
    initEvents();

    function initQuote() {
      let $state = false;
      let $city = false;
      let state_value = false;
      let city_value = false;

      if ($("div.edit_address #_billing_city").length) {
        $state = $("div.edit_address #_billing_state"); // NOSONAR
        $city = $("div.edit_address #_billing_city"); // NOSONAR
        state_value = $state.val(); // NOSONAR
        city_value = $city.val(); // NOSONAR
        const $state_parent = $state.parents("p"); // NOSONAR
        const $city_parent = $city.parents("p"); // NOSONAR
        const $new_state = $(
          '<select name="_billing_state" id="_billing_state" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_statex" placeholder="' +
            $state.attr("placeholder") +
            '" data-placeholder="' +
            $state.attr("placeholder") +
            '"><option value="' +
            state_value +
            '" ' +
            selected_selected +
            ">Cargando Región...</option></select>"
        ); // NOSONAR
        const $new_city = $(
          '<select name="_billing_city" id="_billing_city" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_cityx" placeholder="' +
            $city.attr("placeholder") +
            '" data-placeholder="' +
            $city.attr("placeholder") +
            '"><option value="' +
            city_value +
            '" ' +
            selected_selected +
            "> Cargando Comuna...</option></select>"
        ); // NOSONAR

        $state_parent.append($new_state);
        $state.remove();

        $city_parent.append($new_city);
        $city.remove();

        $new_state.select2({ minimumResultsForSearch: 5 });
        $new_city.select2({ minimumResultsForSearch: 5 });

        jQuery.ajax({
          type: "post",
          url: ajax_var.url,
          dataType: "json",
          data: "action=obtener_regiones",
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
              url: ajax_var.url,
              dataType: "json",
              data: "action=obtener_comunas_desde_region&region=" + state_value, // NOSONAR
              success: function (result2) {
                if (result2.comunas) {
                  let comunas_html = "";
                  for (let k2 in result2.comunas) {
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
              '" ' +
              selected_selected +
              "> Cargando Comuna...</option>"
          );

          jQuery.ajax({
            type: "post",
            url: ajax_var.url,
            dataType: "json",
            data: "action=obtener_comunas_desde_region&region=" + state_value, // NOSONAR
            success: function (result) {
              if (result.comunas) {
                let comunas_html = "";
                for (let k2 in result.comunas) {
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
        });
      }
      if ($(".edit_address #_shipping_city").length) {
        let $state2 = $("div.edit_address #_shipping_state");
        let $city2 = $(".edit_address #_shipping_city");
        let $state_parent2 = $state2.parents("p");
        let $city_parent2 = $city2.parents("p");
        let state_value2 = $state2.val();
        let city_value2 = $city2.val();
        let state_placeholder = $state ? $state.attr("placeholder") : ""; // NOSONAR
        let city_placeholder = $city ? $city.attr("placeholder") : ""; // NOSONAR
        let $new_state2 = $(
          '<select name="_shipping_state" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_statex" placeholder="' +
            state_placeholder +
            '" data-placeholder="' +
            state_placeholder +
            '"><option value="' +
            state_value +
            '" ' +
            selected_selected +
            ">Cargando Región...</option></select>"
        ); // NOSONAR
        let $new_city2 = $(
          '<select name="_shipping_city" style="width: 100%" class="wc-enhanced-select" id="calc_shipping_cityx" placeholder="' +
            city_placeholder +
            '" data-placeholder="' +
            city_placeholder +
            '"><option value="' +
            city_value +
            '" ' +
            selected_selected +
            "> Cargando Comuna...</option></select>"
        ); // NOSONAR

        $state_parent2.append($new_state2);
        $state2.remove();
        $city_parent2.append($new_city2);
        $city2.remove();
        $new_state2.select2({ minimumResultsForSearch: 5 });
        $new_city2.select2({ minimumResultsForSearch: 5 });

        jQuery.ajax({
          type: "post",
          url: ajax_var.url,
          dataType: "json",
          data: "action=obtener_regiones",
          success: function (result) {
            if (result.regiones) {
              let regiones_html = "";
              for (let k in result.regiones) {
                regiones_html +=
                  option_value +
                  k +
                  '" ' +
                  (state_value2 === k ? selected_selected : "") +
                  ">" +
                  result.regiones[k] +
                  "</option>";
              }
              $new_state2.html(regiones_html);
            } else {
              $new_state2.html("");
            }

            state_value2 = $new_state2.val();

            jQuery.ajax({
              type: "post",
              url: ajax_var.url,
              dataType: "json",
              data:
                "action=obtener_comunas_desde_region&region=" + state_value2, // NOSONAR
              success: function (result2) {
                if (result2.comunas) {
                  let comunas_html = "";
                  for (let k2 in result2.comunas) {
                    comunas_html +=
                      option_value +
                      k2 +
                      '" ' +
                      (city_value2 === k2 ? selected_selected : "") +
                      ">" +
                      k2 +
                      "</option>";
                  }
                  $new_city2.html(comunas_html);
                } else {
                  $new_city2.html("");
                }
              },
            });
          },
        });

        $new_state2.on("change", function (event) {
          state_value2 = $new_state2.val();
          city_value2 = $new_city2.val();
          $new_city2.html(
            option_value +
              city_value2 +
              '" ' +
              selected_selected +
              "> Cargando Comuna...</option>"
          );

          jQuery.ajax({
            type: "post",
            url: ajax_var.url,
            dataType: "json",
            data: "action=obtener_comunas_desde_region&region=" + state_value2, // NOSONAR
            success: function (result) {
              if (result.comunas) {
                let comunas_html = "";
                for (let k in result.comunas) {
                  comunas_html +=
                    option_value +
                    k +
                    '" ' +
                    (city_value2 === k ? selected_selected : "") +
                    ">" +
                    k +
                    "</option>";
                }
                $new_city2.html(comunas_html);
              } else {
                $new_city2.html("");
              }
            },
          });
        });
      }
    }

    function initRegionsCommunes() {
      const sinMarcar = $("input:checkbox:not(:first)").not(":checked").length;

      if (sinMarcar == 0) {
        $("#check_todo").prop("checked", true);
      } else {
        $("#check_todo").prop("checked", false);
      }
    }

    function initGeneral() {
      if (!getUrlVariable("tabs")) {
        $(".tab_content").hide();
        $("ul.tabs li:first").addClass("active").show();
        $(".tab_content:first").show();
      }
    }

    function initEvents() {
      /****COTIZADOR*****/
      $(".select-county,.select-city").select2();

      $(".select-county").on("change", function (ev) {
        let city_$ = null; // NOSONAR
        const county = $(ev.currentTarget).val();
        if ($(ev.currentTarget).data("city")) {
          city_$ = $(document.getElementById($(ev.currentTarget).data("city")));
        }
        if (city_$) {
          city_$.html(option_value + county + '">Cargando...</option>'); // NOSONAR
          jQuery.ajax({
            type: "post",
            url: ajax_var.url,
            dataType: "json",
            data:
              "action=obtener_comunas_desde_region&region=" +
              county +
              "&nonce=" +
              ajax_var.nonce, // NOSONAR
            success: function (result) {
              if (result.comunas) {
                let comunas_html = ""; // NOSONAR
                for (let k in result.comunas) {
                  comunas_html +=
                    option_value + result.comunas[k] + '">' + k + "</option>"; // NOSONAR
                }
                city_$.html(comunas_html);

                city_$.siblings("input").val(city_$.val());
              } else {
                city_$.html("");
              }
            },
          });
        }
      });

      $(".select-city").on("change", function (ev) {
        $(ev.currentTarget).siblings("input").val($(ev.currentTarget).val());
      });

      $(document).on("change", "tr.shipping .shipping_method", function () {
        const shippingMethod = $(this).val();
        const shippingCityForm = $("#calc_shipping_cityx").val();

        if (shippingMethod == "chilexpress_woo_oficial") {
          let orderID = $("#post_ID").val();

          jQuery.ajax({
            type: "post",
            url: ajax_var.url,
            dataType: "json",
            data:
              "action=get_cotizacion&orderID=" +
              orderID +
              "&shippingCityForm=" +
              shippingCityForm, // NOSONAR
            success: function (data) {
              if (data.status == "success") {
                let _html =
                  '<ul id="shipping_service_cxp" class="woocommerce-shipping-methods"> ';

                $.each(data.response, function (index, value) {
                  if (
                    value.serviceTypeCode != 8 ||
                    (value.serviceTypeCode == 8 && data.ampm == true)
                  ) {
                    _html =
                      _html +
                      '   <li> \
                                                              <input type="radio" name="shipping_method_cxp" id="shipping_method_cxp_' +
                      index +
                      '" value="chilexpress_woo_oficial:' +
                      value.serviceTypeCode +
                      '" class="shipping-service-cxp" data-serviceDescription="' +
                      value.serviceDescription +
                      '" data-serviceValue="' +
                      value.serviceValueDiscount +
                      '" /> \
                                                              <label for="shipping_method_cxp_' +
                      index +
                      '"> \
                                                                  <img src="' +
                      data.logo +
                      '" style="width: 120px; margin-right: 0.2em; margin-top:2px; margin-bottom:-2px;"> \
                                                                  - ' +
                      value.serviceDescription +
                      ": " +
                      value.serviceValueDiscountWithFormat +
                      " \
                                                              </label> \
                                                              <p><small>[ " +
                      value.sellerProcessingDays +
                      " ]</small></p> \
                                                          </li>";
                  }
                });

                _html = _html + " </ul>";

                $("#order_shipping_line_items .shipping select").after(_html);
              } else {
                alert(data.response);
              }
            },
            error: function (error) {
              console.log(error);
            },
          });
        } else {
          if ($("#shipping_service_cxp").length > 0)
            $("#shipping_service_cxp").remove();
        }
      });

      $(document).on("click", "tr.shipping .shipping-service-cxp", function () {
        const shippingMethod = $(this).val();
        const serviceDescription =
          "Chilexpress - " + $(this).attr("data-serviceDescription");
        const serviceValue = $(this).attr("data-serviceValue");

        $("#order_shipping_line_items tr.shipping .shipping_method_name").val(
          serviceDescription
        );
        $("#order_shipping_line_items tr.shipping .line_total").val(
          serviceValue
        );

        $(
          ".woocommerce_order_items .shipping_method option[value=chilexpress_woo_oficial]"
        ).val(shippingMethod);
      });
      /****COTIZADOR*****/

      /****HABILITAR-REGIONES-COMUNAS*****/
      $(".container-regiones-comunas .nombre-region").click(function () {
        const regionId = $(this).attr("id");
        $(".hijo-" + regionId).slideToggle("slow");
      });

      $(".check-region").click(function () {
        const regionId = $(this).attr("id");
        $(".check-comuna-" + regionId)
          .not(this)
          .prop("checked", this.checked);
      });

      $("#check_todo").click(function () {
        $("input:checkbox").not(this).prop("checked", this.checked);
      });

      $(".check-comuna").click(function () {
        const region_id = $(this).data("region_padre");
        let marcado = true;
        let contador = 0;

        $(".check-comuna-" + region_id).each(function () {
          if ($(this).prop("checked") === false) contador++;
        });

        if (contador == $(".check-comuna-" + region_id).length) marcado = false;

        $("#" + region_id).prop("checked", marcado);
      });
      /****HABILITAR-REGIONES-COMUNAS*****/

      /*****OPCIONES-GENERALES*****/
      $(".tracking a, .numero_ot a").on("click", function (ev) {
        const old_text = $(ev.currentTarget).text(); // NOSONAR
        if (old_text === "Cargando...") {
          return;
        }
        $(ev.currentTarget).text("Cargando...");
        jQuery.ajax({
          type: "post",
          url: ajax_var.url,
          dataType: "json",
          data:
            "action=track_order&pid=" +
            $(this).data("pid") +
            "&ot=" +
            $(this).data("ot"), // NOSONAR
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
                    "</td><td></td></tr>"; // NOSONAR
                });
                $("#wc-chilexpress-events > tbody").html(html);
              } else {
                $("#wc-chilexpress-events > tbody > tr > td").text(
                  "No existen eventos aún para este envio."
                );
              }
            }, 500);
          },
          error: function (error) {
            console.log(error);
          },
        });
      });

      $(".numero-ce").click(function () {
        const order_id = $(this).data("order_id");
        const numero_ce = $(this).data("numero_ce");

        jQuery.ajax({
          type: "post",
          url: ajax_var.url,
          dataType: "json",
          data:
            "action=close_certificate&order_id=" +
            order_id +
            "&numero_ce=" +
            numero_ce, // NOSONAR
          success: function (data) {
            let obj = data.response;

            if (obj.closedCertificate) {
              let a = document.createElement("a");
              a.href =
                "data:application/pdf;base64," + obj.closedCertificate.imagePdf;
              a.download =
                "certificate_" +
                obj.closedCertificate.certificateNumber +
                ".pdf";
              a.click();
            } else {
              alert("Hubo un error inesperado.!");
            }
          },
          error: function (error) {
            console.log(error);
          },
        });
      });

      $(".btn-asignar-dimension").click(function () {
        const product_id = $(this).data("product_id");
        const alto = $("#input_alto_" + product_id).val();
        const largo = $("#input_largo_" + product_id).val();
        const ancho = $("#input_ancho_" + product_id).val();
        const peso = $("#input_peso_" + product_id).val();
        let ampm = "";

        if ($("#input_ampm_" + product_id).prop("checked")) {
          ampm = $("#input_ampm_" + product_id).val();
        }

        jQuery.ajax({
          type: "post",
          url: ajax_var.url,
          dataType: "json",
          data:
            "action=set_dimension&alto=" +
            alto +
            "&largo=" +
            largo +
            "&ancho=" +
            ancho +
            "&peso=" +
            peso +
            "&ampm=" +
            ampm +
            "&product_id=" +
            product_id, // NOSONAR
          success: function (data) {
            if (data.status == "success") {
              $("#response_dimensions").html(
                '<div id="message" class="updated notice is-dismissible"><p>Las dimensiones fueron actualizada con éxito.</p></div>'
              );
            } else {
              $("#response_dimensions").html(
                '<div id="message" class="notice notice-error"><p>' +
                  data.response +
                  "</p></div>"
              );
            }
          },
          error: function (error) {
            console.log(error);
          },
        });
      });

      $(".btn-nota-tipo-prioridad").click(function () {
        $(".nota-tipo-prioridad").toggle("slow");
      });

      $(".btn-nota-dia-procesamiento").click(function () {
        $(".nota-dia-procesamiento").toggle("slow");
      });

      $(".btn-nota-porcentaje-descuento").click(function () {
        $(".nota-porcentaje-descuento").toggle("slow");
      });

      $(".btn-nota-corte-horario").click(function () {
        $(".nota-corte-horario").toggle("slow");
      });

      $(".btn-nota-dias-semana").click(function () {
        $(".nota-dias-semana").toggle("slow");
      });

      $("#input_porcentaje_descuento").on("input", function (evt) {
        // Allow only numbers.
        $(this).val(
          $(this)
            .val()
            .replace(/[^0-9]/g, "")
        );

        if (parseInt($(this).val()) < 0 || parseInt($(this).val()) > 100) {
          $(this).val("");
        }
      });
      /*****OPCIONES-GENERALES*****/
    }

    function getUrlVariable(variable) {
      const query = window.location.search.substring(1);
      const vars = query.split("&");
      for (let i = 0; i < vars.length; i++) {
        let pair = vars[i].split("=");
        if (pair[0] == variable) {
          return pair[1];
        }
      }
      return null;
    }
  });
})(jQuery);
