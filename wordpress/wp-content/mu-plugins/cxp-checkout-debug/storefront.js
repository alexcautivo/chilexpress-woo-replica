(function ($) {
  'use strict';

  var maxQty = (window.cxpStorefront && parseInt(window.cxpStorefront.maxQty, 10)) || 10;
  var canCart = !!(window.cxpStorefront && Number(window.cxpStorefront.canCart));
  var checkout = (window.cxpStorefront && window.cxpStorefront.checkout) || '/checkout/';

  function clamp(n) {
    n = parseInt(n, 10);
    if (isNaN(n) || n < 1) return 1;
    if (n > maxQty) return maxQty;
    return n;
  }

  function sync($box) {
    var n = clamp($box.find('.cxp-qty__input').val());
    $box.find('.cxp-qty__input').val(n);
    $box.closest('li.product, .product').find('a.add_to_cart_button, button.single_add_to_cart_button').attr('data-quantity', n);
    var $woo = $box.closest('form.cart').find('input.qty');
    if ($woo.length) {
      $woo.val(n).trigger('change');
    }
  }

  $(document).on('click', '.cxp-qty__btn', function (e) {
    e.preventDefault();
    var $box = $(this).closest('.cxp-qty');
    var dir = parseInt($(this).data('dir'), 10) || 0;
    var $input = $box.find('.cxp-qty__input');
    $input.val(clamp((parseInt($input.val(), 10) || 1) + dir));
    sync($box);
  });

  $(document).on('change input', '.cxp-qty__input', function () {
    sync($(this).closest('.cxp-qty'));
  });

  function bindCards() {
    $('li.product').each(function () {
      var $card = $(this);
      if (!$card.find('.cxp-qty').length) {
        return;
      }
      sync($card.find('.cxp-qty').first());
    });
  }

  function lockCartLinks() {
    if (canCart) {
      return;
    }
    $('a[href*="/cart"], a.cart-contents, .wd-header-cart a, a.wd-header-cart').each(function () {
      var href = this.getAttribute('href') || '';
      if (href.indexOf('checkout') !== -1) {
        return;
      }
      this.setAttribute('href', checkout);
      this.setAttribute('title', (window.cxpStorefront && window.cxpStorefront.cartLocked) || '');
    });
  }

  $(function () {
    bindCards();
    lockCartLinks();
  });

  $(document.body).on('updated_wc_div added_to_cart wc_fragments_refreshed', function () {
    bindCards();
    lockCartLinks();
  });
})(jQuery);
