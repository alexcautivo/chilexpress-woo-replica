(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  var HELPS = {
    'cxp-dbg-copy': 'Copia versiones, plugins y el final del debug.log para pegar en el ticket.',
    'cxp-dbg-copy-ver': 'Copia solo la pila de versiones (WP, Woo, PHP, Chilexpress).',
    'cxp-dbg-copy-plugins': 'Copia la lista de plugins con versión y estado.',
    'cxp-dbg-del-all': 'Borra todos los pedidos de esta réplica. No se puede deshacer.',
    'cxp-dbg-close': 'Cierra el panel. La barra de abajo sigue visible.',
    'cxp-dbg-search': 'Enfoca el buscador del encabezado. Escribe un producto y pulsa Buscar o Enter.',
    'cxp-sr-walk': 'Recorre el ticket SR-108688 y simula la ventana del update. No edita Chilexpress.',
    'cxp-sr-copy': 'Copia la evidencia de la última réplica (stack y marcadores).',
    'cxp-sr-pdf': 'Baja el plan de acción en lenguaje simple para el cliente.',
    'cxp-sr-repro': 'Replica el fatal, captura el stack y restaura el enum altiro.',
    'cxp-sr-break': 'Deja el enum oculto: el siguiente reload cae como producción.',
    'cxp-sr-restore': 'Vuelve a poner ProductTaxStatus.php. Usa esto si el sitio quedó caído.',
    'cxp-stack-php-go': 'Prepara otro runtime PHP. Hay que reiniciar el servidor después.',
    'cxp-docs-all': 'Copia identificación, diagnóstico, respuesta e instrucciones juntas.'
  };

  var BY_TEXT = {
    'Tienda': 'Abre el catálogo con peso, medidas y cantidad 1 a 10.',
    'Buscar': 'Enfoca el buscador del encabezado. Escribe un producto y pulsa Buscar o Enter.',
    'Checkout': 'Checkout clásico: elige un destino real y cotiza Chilexpress.',
    'Carrito': 'Revisa cantidades. Solo se habilita después de pasar por el checkout.',
    'Pedidos locales (Generar OTs)': 'Lista HPOS de pedidos locales para generar la orden de transporte.',
    'Pedidos tienda remota': 'Misma pantalla de pedidos en la tienda remota, si está configurada.',
    'Borrar todos los pedidos': 'Elimina todos los pedidos de esta réplica. Definitivo.',
    'Copiar todo': 'Copia versiones, plugins y el final del debug.log.',
    'Copiar solo versiones': 'Copia solo la pila de versiones de este laboratorio.',
    'Copiar todos los plugins': 'Copia nombre, versión y estado de cada plugin.',
    'Cerrar': 'Cierra el panel. La barra inferior sigue visible.',
    'Recorrer ticket y replicar el fatal': 'Simula la ventana del update de Woo y replica el error del cliente.',
    'Copiar evidencia': 'Copia el stack y los marcadores de la última réplica.',
    'PDF plan de acción (para el cliente)': 'Documento corto, sin jerga, para el dueño de la tienda.',
    'Solo replicar (capturar y restaurar)': 'Provoca el fatal, captura evidencia y restaura el archivo altiro.',
    'Dejar el sitio caído': 'Oculta el enum. El siguiente reload cae como en producción.',
    'Restaurar ProductTaxStatus': 'Devuelve el archivo del enum. Úsalo si el sitio quedó en error crítico.',
    'Abrir admin-ajax.php': 'Abre la misma entrada que usó WordPress en el correo del 11 de agosto.',
    'Estado enum': 'Muestra si ProductTaxStatus.php está presente o oculto.',
    'Detalle': 'Abre el pedido en wp-admin para generar o imprimir la OT.',
    'Generar OT': 'Llama al flujo oficial de Chilexpress para crear la orden de transporte.',
    'Imprimir OT': 'Abre la etiqueta si la OT ya fue creada.',
    'Borrar': 'Elimina este pedido de forma definitiva.',
    'Copiar': 'Copia este texto al portapapeles.',
    'Descargar .md': 'Baja este documento en Markdown.',
    'Descargar informe PDF': 'Informe técnico: por qué cae en producción y no en la réplica.',
    'Descargar pack .md': 'Baja identificación, diagnóstico, respuesta e instrucciones juntos.',
    'FAQ réplica': 'Preguntas frecuentes de este laboratorio.',
    'Guía de uso': 'Cómo usar la réplica y la consola.',
    'Guardar keys pegadas': 'Guarda las subscription keys que pegaste. Vacío no cambia nada.',
    'Cargar keys del entorno (Dokploy)': 'Toma CXP_API_KEY_* del contenedor.',
    'Cargar defaults del plugin 1.4.0': 'Vuelve a las keys de staging que trae el ZIP oficial.',
    'Snapshot': 'Copia el plugin actual por si hay que volver atrás.',
    'Desactivar': 'Apaga este plugin. Chilexpress Oficial no se parchea.',
    'Activar': 'Enciende este plugin.',
    'Preparar esta PHP': 'Descarga el runtime. Hay que reiniciar el servidor después.',
    'Copiar JSON para el cliente': 'Copia la plantilla. El cliente la rellena y la devuelve. Con ese JSON se crea el ticket.',
    'Crear ticket con este JSON': 'Valida el JSON pegado y lo guarda en incidents/tickets/.',
    'Ver JSON': 'Muestra el JSON de este ticket ya creado.',
  };

  var STORE_TABS = [
    { id: 'tienda', label: 'Tienda', icon: 'store', hint: 'Catálogo y checkout. El carrito solo se abre después de pasar por el checkout.', panels: ['cxp-dbg-shortcuts'] },
    { id: 'pedidos', label: 'Pedidos', icon: 'package', hint: 'Pedidos de la réplica, detalle y generar OT Chilexpress.', panels: ['cxp-dbg-ot', 'cxp-dbg-orders'] },
    { id: 'ticket', label: 'Ticket', icon: 'bug', hint: 'Réplica SR-108688: recorrer el fatal, copiar evidencia y PDF para el cliente.', panels: ['cxp-dbg-sr'] },
    { id: 'incidencias', label: 'Incidencias', icon: 'clipboard-list', hint: 'JSON para el cliente y alta de tickets. Sirve para SR-108688 y para cualquier incidencia nueva.', panels: ['cxp-dbg-tickets'] },
    { id: 'docs', label: 'Ayuda', icon: 'book-open', hint: 'Textos y PDF para el cliente. Chilexpress Oficial no se parchea.', panels: ['cxp-dbg-docs', 'cxp-dbg-about'] },
    { id: 'mas', label: 'Más', icon: 'settings', hint: 'APIs, versiones y plugins. En la tienda quedan atrás; en wp-admin son el trabajo diario.', panels: ['cxp-dbg-apis', 'cxp-dbg-stack', 'cxp-dbg-lab', 'cxp-dbg-plugins'] }
  ];

  var ADMIN_TABS = [
    { id: 'ticket', label: 'Ticket', icon: 'bug', hint: 'Primero: recorrer SR-108688, copiar evidencia y bajar el PDF de plan de acción.', panels: ['cxp-dbg-sr'] },
    { id: 'incidencias', label: 'Incidencias', icon: 'clipboard-list', hint: 'Pegar el JSON del cliente y crear un ticket. Planes en incidents/ del repo.', panels: ['cxp-dbg-tickets'] },
    { id: 'pedidos', label: 'Pedidos y OT', icon: 'package', hint: 'Lista HPOS, generar OT e imprimir. No uses acciones masivas sin marcar el pedido.', panels: ['cxp-dbg-shortcuts', 'cxp-dbg-ot', 'cxp-dbg-orders'] },
    { id: 'apis', label: 'APIs', icon: 'key', hint: 'Keys de Cobertura, Cotizador y Envíos. Staging del ZIP 1.4.0 o las de Dokploy.', panels: ['cxp-dbg-apis'] },
    { id: 'lab', label: 'Laboratorio', icon: 'flask-conical', hint: 'Cambiar versiones, ZIP, snapshot y rollback. Chilexpress 1.4.0 se restaura intacto.', panels: ['cxp-dbg-stack', 'cxp-dbg-lab'] },
    { id: 'docs', label: 'Documentos', icon: 'file-text', hint: 'Informe técnico, plan de acción y pack para el cliente.', panels: ['cxp-dbg-docs'] },
    { id: 'sistema', label: 'Sistema', icon: 'clipboard-list', hint: 'Plugins instalados, copiar evidencia y créditos.', panels: ['cxp-dbg-plugins', 'cxp-dbg-about'] }
  ];

  function icon(name) {
    return '<i data-lucide="' + name + '"></i>';
  }

  function paintIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  function textOf(el) {
    return (el.getAttribute('aria-label') || el.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function helpFor(el) {
    if (el.id && HELPS[el.id]) {
      return HELPS[el.id];
    }
    var titled = el.getAttribute('title');
    if (titled && titled.length > 8) {
      return titled;
    }
    var label = textOf(el);
    if (BY_TEXT[label]) {
      return BY_TEXT[label];
    }
    var hint = el.getAttribute('data-hint') || el.getAttribute('data-cxp-help');
    if (hint) {
      return hint;
    }
    return label ? ('Acción: ' + label) : 'Más información de esta acción.';
  }

  function attachInfo(el, text) {
    if (!el || !text || el.closest('.cxp-dbg-with-info') || el.classList.contains('cxp-dbg-info')) {
      return;
    }
    var wrap = document.createElement('span');
    wrap.className = 'cxp-dbg-with-info';
    el.parentNode.insertBefore(wrap, el);
    wrap.appendChild(el);
    var info = document.createElement('button');
    info.type = 'button';
    info.className = 'cxp-dbg-info';
    info.setAttribute('aria-label', 'Qué hace: ' + text);
    info.setAttribute('data-tip', text);
    info.innerHTML = icon('info');
    wrap.appendChild(info);
    el.setAttribute('title', text);
  }

  function decorate(root) {
    var nodes = root.querySelectorAll(
      '#cxp-dbg-actions a.cxp-dbg-btn, #cxp-dbg-actions button, #cxp-dbg-sr button, #cxp-dbg-sr a.cxp-dbg-btn, #cxp-dbg-ot a, #cxp-dbg-orders a, #cxp-dbg-orders button, #cxp-dbg-plugins button, #cxp-dbg-docs button, #cxp-dbg-docs a.cxp-dbg-btn, #cxp-dbg-lab button, #cxp-dbg-stack button, #cxp-dbg-apis button[type="submit"], #cxp-dbg-tabs button'
    );
    nodes.forEach(function (el) {
      attachInfo(el, helpFor(el));
    });
  }

  function bindTip(root) {
    var tip = document.getElementById('cxp-dbg-float-tip');
    if (!tip) {
      tip = document.createElement('div');
      tip.id = 'cxp-dbg-float-tip';
      tip.hidden = true;
      root.appendChild(tip);
    }
    function hide() {
      tip.hidden = true;
    }
    function show(el) {
      var text = el.getAttribute('data-tip');
      if (!text) {
        return;
      }
      tip.textContent = text;
      tip.hidden = false;
      var r = el.getBoundingClientRect();
      var tr = root.getBoundingClientRect();
      var left = r.left - tr.left + r.width / 2;
      var top = r.top - tr.top - 10;
      tip.style.left = Math.max(12, Math.min(left, tr.width - 12)) + 'px';
      tip.style.top = Math.max(8, top) + 'px';
      tip.style.transform = 'translate(-50%, -100%)';
    }
    root.addEventListener('mouseover', function (e) {
      var info = e.target.closest('.cxp-dbg-info');
      if (info && root.contains(info)) {
        show(info);
      }
    });
    root.addEventListener('mouseout', function (e) {
      var info = e.target.closest('.cxp-dbg-info');
      if (!info) {
        return;
      }
      var next = e.relatedTarget;
      if (next && info.contains(next)) {
        return;
      }
      hide();
    });
    root.addEventListener('click', function (e) {
      var info = e.target.closest('.cxp-dbg-info');
      if (!info) {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      if (tip.hidden) {
        show(info);
      } else {
        hide();
      }
    });
  }

  function showTab(root, tabs, id) {
    var tab = tabs.filter(function (t) { return t.id === id; })[0] || tabs[0];
    root.querySelectorAll('#cxp-dbg-tabs button[data-tab]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-tab') === tab.id);
    });
    var hint = document.getElementById('cxp-dbg-tabhint');
    if (hint) {
      hint.textContent = tab.hint;
    }
    var wanted = {};
    tab.panels.forEach(function (pid) { wanted[pid] = true; });
    tabs.forEach(function (t) {
      t.panels.forEach(function (pid) {
        var el = document.getElementById(pid);
        if (!el) {
          return;
        }
        el.setAttribute('data-cxp-tab-panel', pid);
        el.classList.toggle('is-on', !!wanted[pid]);
      });
    });
    var copyAll = document.getElementById('cxp-dbg-copy-all');
    if (copyAll) {
      var h3 = copyAll.previousElementSibling;
      var showCopy = tab.id === 'sistema' || tab.id === 'mas';
      copyAll.classList.toggle('is-on', showCopy);
      copyAll.setAttribute('data-cxp-tab-panel', 'cxp-dbg-copy-all');
      if (h3 && h3.tagName === 'H3') {
        h3.setAttribute('data-cxp-tab-panel', 'cxp-dbg-copy-h3');
        h3.classList.toggle('is-on', showCopy);
      }
    }
  }

  ready(function () {
    var root = document.getElementById('cxp-dbg');
    if (!root) {
      return;
    }
    root.classList.add('cxp-dbg-shell');
    var isAdmin = root.classList.contains('cxp-dbg--admin') || document.body.classList.contains('wp-admin');
    var tabs = isAdmin ? ADMIN_TABS : STORE_TABS;
    var nav = document.getElementById('cxp-dbg-tabs');
    if (nav) {
      nav.innerHTML = tabs.map(function (t, i) {
        return '<button type="button" data-tab="' + t.id + '" data-tip="' + t.hint.replace(/"/g, '&quot;') + '"' + (i === 0 ? ' class="is-on"' : '') + '>' + icon(t.icon) + ' ' + t.label + '</button>';
      }).join('');
      nav.addEventListener('click', function (e) {
        if (e.target.closest('.cxp-dbg-info')) {
          return;
        }
        var btn = e.target.closest('button[data-tab]');
        if (!btn) {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        showTab(root, tabs, btn.getAttribute('data-tab'));
        paintIcons();
      });
    }
    decorate(root);
    bindTip(root);
    showTab(root, tabs, tabs[0].id);
    paintIcons();
  });
})();
