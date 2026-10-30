/* Funciones comunes a todas las páginas */
(function () {
  var meta = function (n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; };

  window.App = {
    base: meta('base'),
    csrf: meta('csrf'),

    /** Formato $ 12.500 / $ 12.500,50 */
    money: function (n) {
      n = Number(n) || 0;
      var dec = Math.abs(n - Math.round(n)) > 0.004 ? 2 : 0;
      return (n < -0.004 ? '-' : '') + '$ ' + Math.abs(n).toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    },

    /** Convierte "1.234,50", "12.500" o "1234.5" a número */
    num: function (v) {
      v = String(v == null ? '' : v).replace(/[$\s]/g, '');
      if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
      else if (/^\d{1,3}(\.\d{3})+$/.test(v)) v = v.replace(/\./g, '');
      var n = parseFloat(v);
      return isNaN(n) ? 0 : n;
    },

    esc: function (s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    },

    /** Llamada a la API: GET si data es null, POST JSON si no */
    api: function (ruta, data) {
      var op = { headers: { 'X-CSRF': App.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' };
      if (data != null) {
        op.method = 'POST';
        op.headers['Content-Type'] = 'application/json';
        op.body = JSON.stringify(data);
      }
      return fetch(App.base + ruta, op).then(function (r) {
        return r.json().catch(function () { return { ok: false, error: 'Respuesta inválida del servidor (' + r.status + ')' }; });
      });
    }
  };

  // Confirmación en formularios/botones con data-confirm
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var msg = (ev.submitter && ev.submitter.getAttribute('data-confirm')) || f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[data-confirm]');
    if (a && !window.confirm(a.getAttribute('data-confirm'))) ev.preventDefault();
  });

  // Abrir / cerrar modales: data-abrir="idModal", data-cerrar
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-abrir]');
    if (b) {
      var m = document.getElementById(b.getAttribute('data-abrir'));
      if (m) { m.classList.add('abierto'); var i = m.querySelector('input:not([type=hidden]),select,textarea'); if (i) i.focus(); }
    }
    if (ev.target.closest('[data-cerrar]') || ev.target.classList.contains('modal-fondo')) {
      var f = ev.target.closest('.modal-fondo');
      if (f) f.classList.remove('abierto');
    }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      document.querySelectorAll('.modal-fondo.abierto').forEach(function (m) { m.classList.remove('abierto'); });
    }
  });
})();
