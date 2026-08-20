/**
 * Enhance the public storefront while preserving theme markup.
 */
(function () {
  'use strict';

  var initialized = false;

  function getLabels() {
    return (typeof chilexpressStorefront !== 'undefined' && chilexpressStorefront.labels) || {};
  }

  function applyFavicon() {
    var faviconUrl =
      typeof chilexpressStorefront !== 'undefined' && chilexpressStorefront.faviconUrl
        ? chilexpressStorefront.faviconUrl
        : '';

    if (!faviconUrl) {
      return;
    }

    var links = document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]');
    if (links.length) {
      Array.prototype.forEach.call(links, function (link) {
        link.href = faviconUrl;
        link.type = 'image/x-icon';
      });
      return;
    }

    var link = document.createElement('link');
    link.rel = 'icon';
    link.type = 'image/x-icon';
    link.href = faviconUrl;
    document.head.appendChild(link);
  }

  function applyTitle() {
    var pageTitle =
      typeof chilexpressStorefront !== 'undefined' && chilexpressStorefront.pageTitle
        ? chilexpressStorefront.pageTitle
        : 'Chilexpress WooCommerce Test';
    var current = (document.title || '').trim();
    var generic =
      !current ||
      /^home$/i.test(current) ||
      /^chilexpress woo test$/i.test(current);

    if (generic || current.indexOf('Chilexpress') === -1) {
      document.title = pageTitle;
      return;
    }

    if (current.indexOf(pageTitle) === -1) {
      document.title = current.replace(/\s*[|\-·].*$/, '') + ' · ' + pageTitle;
    }
  }

  function getHeader() {
    return (
      document.querySelector('body .wp-site-blocks > header') ||
      document.querySelector('header.wp-block-template-part') ||
      document.querySelector('.wp-block-template-part > header') ||
      document.querySelector('.site-header') ||
      document.querySelector('header')
    );
  }

  function findBrandSlot(header) {
    var selectors = [
      '.wp-block-site-title',
      '.wp-block-site-logo',
      '.site-title',
      '.custom-logo-link',
      '.site-branding',
      '.wp-block-group > .wp-block-site-title'
    ];
    for (var i = 0; i < selectors.length; i++) {
      var el = header.querySelector(selectors[i]);
      if (el) {
        return el;
      }
    }
    return header.firstElementChild || header;
  }

  function brandHeader() {
    if (
      document.body.classList.contains('cxp-storefront-branded') ||
      document.querySelector('.cxp-storefront-logo') ||
      typeof chilexpressStorefront === 'undefined'
    ) {
      return;
    }

    var header = getHeader();
    if (!header) {
      return;
    }

    var logoUrl = chilexpressStorefront.logoUrl;
    var homeUrl = chilexpressStorefront.homeUrl || '/';
    var labels = getLabels();
    if (!logoUrl) {
      return;
    }

    var slot = findBrandSlot(header);
    var anchor = document.createElement('a');
    anchor.className = 'cxp-storefront-logo';
    anchor.href = homeUrl;
    anchor.setAttribute('aria-label', labels.logoAria || 'Chilexpress');

    var img = document.createElement('img');
    img.src = logoUrl;
    img.alt = labels.logoAria || 'Chilexpress';
    img.width = 180;
    img.height = 29;
    img.decoding = 'async';
    anchor.appendChild(img);

    if (slot && slot.parentNode) {
      slot.parentNode.insertBefore(anchor, slot);
    } else {
      header.insertBefore(anchor, header.firstChild);
    }

    document.body.classList.add('cxp-storefront-branded');
  }

  function createTopbar() {
    if (
      document.querySelector('.cxp-storefront-topbar') ||
      typeof chilexpressStorefront === 'undefined'
    ) {
      return;
    }

    var labels = getLabels();
    var topbar = document.createElement('aside');
    var text = document.createElement('p');
    var header = getHeader();

    topbar.className = 'cxp-storefront-topbar';
    topbar.setAttribute(
      'aria-label',
      labels.topbarAria || 'Ambiente de la tienda'
    );
    text.textContent = labels.topbar || 'Ambiente de prueba · Integración Chilexpress';
    topbar.appendChild(text);

    if (header && header.parentNode) {
      header.parentNode.insertBefore(topbar, header);
    } else if (document.body.firstChild) {
      document.body.insertBefore(topbar, document.body.firstChild);
    } else {
      document.body.appendChild(topbar);
    }
  }

  function findProductGrid() {
    return (
      document.querySelector('.wc-block-product-template') ||
      document.querySelector('.wp-block-woocommerce-product-template') ||
      document.querySelector('ul.products')
    );
  }

  function placeBlockThemeFallbacks() {
    if (!document.body.classList.contains('cxp-storefront-catalog')) {
      return;
    }

    var hero = document.querySelector('.cxp-catalog-hero[data-cxp-fallback="true"]');
    var header = getHeader();
    var productGrid = findProductGrid();
    var benefits = document.querySelector(
      '.cxp-catalog-benefits[data-cxp-fallback="true"]'
    );

    if (hero && header && header.parentNode && !hero.dataset.cxpPlaced) {
      header.insertAdjacentElement('afterend', hero);
      hero.removeAttribute('data-cxp-fallback');
      hero.dataset.cxpPlaced = 'true';
    }

    if (productGrid && !productGrid.id) {
      productGrid.id = 'cxp-product-catalog';
    }

    if (
      benefits &&
      productGrid &&
      productGrid.parentNode &&
      !benefits.dataset.cxpPlaced
    ) {
      productGrid.insertAdjacentElement('afterend', benefits);
      benefits.removeAttribute('data-cxp-fallback');
      benefits.dataset.cxpPlaced = 'true';
    }
  }

  function markClassicCatalog() {
    if (!document.body.classList.contains('cxp-storefront-catalog')) {
      return;
    }

    var productGrid = findProductGrid();
    if (productGrid && !productGrid.id) {
      productGrid.id = 'cxp-product-catalog';
    }
  }

  function initializeStorefront() {
    if (
      initialized ||
      !document.body.classList.contains('cxp-storefront-enhanced')
    ) {
      return;
    }

    initialized = true;
    applyFavicon();
    applyTitle();
    createTopbar();
    brandHeader();
    placeBlockThemeFallbacks();
    markClassicCatalog();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeStorefront);
  } else {
    initializeStorefront();
  }
})();
