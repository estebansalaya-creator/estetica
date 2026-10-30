/* Formulario de turno: buscar clienta, completar duración/precio/cabina según el servicio y mostrar el día del profesional */
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var form = $('form-turno');
  if (!form) return;

  var cliId = $('cliente_id'), buscar = $('buscar-cliente'), sug = $('sugerencias');
  var elegido = $('cliente-elegido'), buscador = $('buscador-cliente'), nueva = $('cliente-nueva');
  var serv = $('servicio_id'), prof = $('profesional_id'), cab = $('cabina_id');
  var fecha = $('fecha'), hora = $('hora'), dur = $('duracion'), precio = $('precio');
  var termina = $('termina'), diaProf = $('dia-prof');
  var panel = $('panel-cliente'), ficha = $('ficha-cliente'), resumen = $('ficha-resumen');
  var excluir = new URLSearchParams(location.search).get('id') || '';

  /* ---------- Clienta ---------- */
  var timer = null, lista = [], activo = -1;

  function mostrarFicha(c) {
    panel.hidden = false;
    ficha.innerHTML = c.antecedentes ? '<div class="antecedentes"><b>Antecedentes</b>' + App.esc(c.antecedentes) + '</div>' : '';
    resumen.textContent = c.resumen || '';
  }
  function elegir(c) {
    cliId.value = c.id;
    $('cliente-nombre').textContent = c.nombre;
    $('cliente-tel').textContent = c.telefono || '';
    elegido.hidden = false;
    buscador.hidden = true;
    nueva.hidden = true;
    ['cli_nombre', 'cli_apellido', 'cli_telefono'].forEach(function (n) { $(n).value = ''; });
    sug.hidden = true;
    mostrarFicha(c);
    serv.focus();
  }
  function pintar() {
    if (!lista.length) {
      sug.innerHTML = '<button type="button" data-nueva>No la encontramos. Cargarla como nueva</button>';
    } else {
      sug.innerHTML = lista.map(function (c, i) {
        return '<button type="button" data-i="' + i + '"' + (i === activo ? ' class="activo"' : '') + '><b>' + App.esc(c.nombre) + '</b>'
          + '<small>' + App.esc([c.telefono, c.dni ? 'DNI ' + c.dni : ''].filter(Boolean).join(' · ')) + (c.antecedentes ? ' · <span style="color:var(--coral)">con antecedentes</span>' : '') + '</small></button>';
      }).join('');
    }
    sug.hidden = false;
  }
  if (buscar) {
    buscar.addEventListener('input', function () {
      clearTimeout(timer);
      var q = buscar.value.trim();
      if (q.length < 2) { sug.hidden = true; return; }
      timer = setTimeout(function () {
        App.api('api/clientes.php?q=' + encodeURIComponent(q)).then(function (r) {
          lista = r.ok ? r.clientes : [];
          activo = lista.length ? 0 : -1;
          pintar();
        });
      }, 220);
    });
    buscar.addEventListener('keydown', function (ev) {
      if (sug.hidden) return;
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        activo = Math.max(0, Math.min(lista.length - 1, activo + (ev.key === 'ArrowDown' ? 1 : -1)));
        pintar();
      } else if (ev.key === 'Enter') {
        ev.preventDefault();
        if (lista[activo]) elegir(lista[activo]);
      } else if (ev.key === 'Escape') {
        sug.hidden = true;
      }
    });
    sug.addEventListener('click', function (ev) {
      var b = ev.target.closest('button');
      if (!b) return;
      if (b.hasAttribute('data-nueva')) { abrirNueva(); return; }
      elegir(lista[+b.dataset.i]);
    });
    document.addEventListener('click', function (ev) { if (!ev.target.closest('#buscador-cliente')) sug.hidden = true; });
  }
  function abrirNueva() {
    nueva.hidden = false;
    sug.hidden = true;
    var partes = buscar.value.trim().split(/\s+/);
    if (partes[0] && !/\d/.test(buscar.value)) {
      $('cli_nombre').value = partes.shift();
      $('cli_apellido').value = partes.join(' ');
    } else if (/\d/.test(buscar.value)) {
      $('cli_telefono').value = buscar.value.trim();
    }
    $('cli_nombre').focus();
  }
  var btnNueva = $('btn-nueva');
  if (btnNueva) btnNueva.addEventListener('click', abrirNueva);
  var cambiar = $('cambiar-cliente');
  if (cambiar) cambiar.addEventListener('click', function () {
    cliId.value = '';
    elegido.hidden = true;
    buscador.hidden = false;
    panel.hidden = true;
    buscar.value = '';
    buscar.focus();
  });
  if (cliId.value) {
    App.api('api/clientes.php?id=' + cliId.value).then(function (r) { if (r.ok) mostrarFicha(r.cliente); });
  }

  /* ---------- Categoría, servicio y profesional ---------- */
  var cat = $('categoria_id');
  var optAuto = prof.querySelector('option[data-auto]');
  function opcionServ() { return serv.options[serv.selectedIndex]; }
  function opcionProfesional() { return prof.options[prof.selectedIndex]; }

  /* Muestra sólo los servicios y profesionales de la categoría elegida */
  function filtrarCategoria() {
    var c = cat ? cat.value : '0';
    Array.prototype.forEach.call(serv.querySelectorAll('option[data-cat]'), function (o) {
      var ver = c === '0' || o.dataset.cat === c;
      o.hidden = !ver; o.disabled = !ver;
    });
    Array.prototype.forEach.call(serv.querySelectorAll('optgroup'), function (g) {
      g.hidden = c !== '0' && g.dataset.cat !== c;
    });
    if (opcionServ() && opcionServ().disabled) serv.value = '';
    Array.prototype.forEach.call(prof.options, function (o) {
      if (o === optAuto) return;
      var cats = o.dataset.cats ? o.dataset.cats.split(',') : [];
      var ver = c === '0' || cats.indexOf(c) >= 0;
      o.hidden = !ver; o.disabled = !ver;
    });
    optAuto.hidden = c === '0';
    optAuto.disabled = c === '0';
    var nombreCat = cat && c !== '0' ? cat.options[cat.selectedIndex].textContent : '';
    optAuto.textContent = 'Cualquier profesional libre' + (nombreCat ? ' de ' + nombreCat : '');
    if (opcionProfesional() && opcionProfesional().disabled) {
      if (!optAuto.disabled) { prof.value = 'auto'; }
      else {
        var primero = Array.prototype.find.call(prof.options, function (o) { return !o.disabled; });
        if (primero) prof.value = primero.value;
      }
    }
  }

  /* Marca "(no lo hace)" y, si hace falta, elige alguien que haga el servicio */
  function marcarProfesionales(autoElegir) {
    var o = opcionServ();
    var hacen = o && o.dataset.profs ? o.dataset.profs.split(',') : [];
    Array.prototype.forEach.call(prof.options, function (p) {
      if (p === optAuto) return;
      var hace = !hacen.length || hacen.indexOf(p.value) >= 0;
      p.textContent = p.textContent.replace(/ \(no lo hace\)$/, '') + (hace ? '' : ' (no lo hace)');
    });
    if (autoElegir && prof.value !== 'auto' && hacen.length && hacen.indexOf(prof.value) < 0) {
      var cand = hacen.filter(function (id) { var op = prof.querySelector('option[value="' + id + '"]'); return op && !op.disabled; });
      if (cand.length) prof.value = cand[0];
      else if (!optAuto.disabled) prof.value = 'auto';
    }
  }

  if (cat) {
    cat.addEventListener('change', function () { filtrarCategoria(); marcarProfesionales(true); actualizar(); });
  }
  serv.addEventListener('change', function () {
    var o = opcionServ();
    if (!o || !o.value) return;
    // Si no había categoría, toma la del servicio (así se puede usar "cualquier profesional libre")
    if (cat && cat.value === '0' && o.dataset.cat !== '0' && cat.querySelector('option[value="' + o.dataset.cat + '"]')) {
      cat.value = o.dataset.cat;
      filtrarCategoria();
    }
    dur.value = o.dataset.dur;
    if (precio && !precio.disabled) precio.value = Number(o.dataset.precio) || '';
    cab.value = o.dataset.cabina !== '0' ? o.dataset.cabina : '';
    marcarProfesionales(true);
    actualizar();
  });
  filtrarCategoria();
  marcarProfesionales(false);
  if (serv.value && !dur.value) {
    var o0 = opcionServ();
    dur.value = o0.dataset.dur;
    if (precio && !precio.value) precio.value = Number(o0.dataset.precio) || '';
    if (!cab.value && o0.dataset.cabina !== '0') cab.value = o0.dataset.cabina;
  }

  /* ---------- Hora de fin y disponibilidad ---------- */
  function hhmm(m) { m = ((m % 1440) + 1440) % 1440; return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); }
  function mostrarFin() {
    if (!hora.value || !dur.value) { termina.textContent = ''; return; }
    var p = hora.value.split(':');
    termina.textContent = 'Termina a las ' + hhmm(+p[0] * 60 + +p[1] + +dur.value);
  }
  var pedido = 0;
  function cargarDia() {
    if (!prof.value || !fecha.value) { diaProf.hidden = true; return; }
    var n = ++pedido;
    if (prof.value === 'auto') {
      // Quién de la categoría puede tomarlo a esa hora
      if (!hora.value || !dur.value) {
        diaProf.innerHTML = 'Elegí la hora y el servicio para ver quién está libre.';
        diaProf.hidden = false;
        return;
      }
      App.api('api/libres.php?categoria=' + cat.value + '&servicio=' + serv.value + '&fecha=' + fecha.value + '&hora=' + hora.value + '&dur=' + dur.value + '&excluir=' + excluir).then(function (r) {
        if (n !== pedido || !r.ok) return;
        var h = '<b>' + App.esc(r.categoria) + ' a las ' + r.hora + ':</b> ';
        if (r.libres.length) {
          h += (r.libres.length === 1 ? 'está libre ' : 'están libres ') + App.esc(r.libres.join(', ')) + '. Se asigna a <b>' + App.esc(r.libres[0]) + '</b>.';
        } else {
          h += 'no hay nadie libre';
          if (r.ocupados.length) h += ' (' + r.ocupados.map(function (o) { return App.esc(o.nombre + ' ' + o.motivo); }).join('; ') + ')';
          h += '. Probá otro horario.';
        }
        diaProf.innerHTML = h;
        diaProf.hidden = false;
      });
      return;
    }
    App.api('api/dia.php?prof=' + prof.value + '&fecha=' + fecha.value + '&excluir=' + excluir).then(function (r) {
      if (n !== pedido || !r.ok) return;
      var nombre = prof.options[prof.selectedIndex].textContent.replace(/ \(no lo hace\)$/, '');
      var h = '<b>' + App.esc(nombre) + ', ' + App.esc(r.dia) + ':</b> ';
      h += r.horario.length ? 'trabaja ' + r.horario.join(' y ') + '.' : 'no trabaja ese día.';
      if (r.turnos.length) {
        h += ' Ocupado: ' + r.turnos.map(function (t) { return '<span title="' + App.esc(t.cliente + ', ' + t.servicio) + '">' + t.desde + '–' + t.hasta + '</span>'; }).join(', ') + '.';
      } else if (r.horario.length) {
        h += ' Todavía no tiene turnos.';
      }
      diaProf.innerHTML = h;
      diaProf.hidden = false;
    });
  }
  function actualizar() { mostrarFin(); cargarDia(); }
  var espera = null;
  [hora, dur].forEach(function (el) {
    el.addEventListener('input', function () {
      mostrarFin();
      if (prof.value === 'auto') { clearTimeout(espera); espera = setTimeout(cargarDia, 300); }
    });
  });
  [prof, fecha].forEach(function (el) { el.addEventListener('change', cargarDia); });
  actualizar();

  form.addEventListener('submit', function (ev) {
    if (!cliId.value && !$('cli_nombre').value.trim()) {
      ev.preventDefault();
      alert('Elegí la clienta o cargá sus datos si es nueva.');
      (buscar || $('cli_nombre')).focus();
    }
  });
  if (buscar && !cliId.value && nueva.hidden) buscar.focus();
})();
