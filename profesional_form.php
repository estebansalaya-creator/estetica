<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('profesionales');

$id = (int)get('id', 0);
$p = $id ? q_row('SELECT * FROM profesionales WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    redirect(url('profesionales.php'));
}
$servicios = q_all('SELECT s.*, c.nombre AS categoria FROM servicios s LEFT JOIN categorias c ON c.id = s.categoria_id WHERE s.activo = 1 ORDER BY c.orden IS NULL, c.orden, s.orden, s.nombre');
$err = '';

// Horario actual: [dia][0|1] = [desde, hasta]
$franjas = [];
if ($id) {
    foreach (q_all('SELECT * FROM profesional_horarios WHERE profesional_id = ? ORDER BY dia, desde', [$id]) as $h) {
        $franjas[(int)$h['dia']][] = [substr($h['desde'], 0, 5), substr($h['hasta'], 0, 5)];
    }
}
$hace = $id ? array_map('intval', array_column(q_all('SELECT servicio_id FROM servicio_profesional WHERE profesional_id = ?', [$id]), 'servicio_id')) : [];
$categorias = q_all('SELECT * FROM categorias WHERE activo = 1 ORDER BY orden, nombre');
$enCats = $id ? categorias_de_profesional($id) : [];

if (es_post()) {
    csrf_check();
    if (post('accion') === 'eliminar' && $id) {
        if (q_val('SELECT COUNT(*) FROM turnos WHERE profesional_id = ?', [$id])) {
            q('UPDATE profesionales SET activo = 0 WHERE id = ?', [$id]);
            flash('ok', 'Tiene turnos, así que se desactivó en lugar de borrarse. Ya no aparece en la agenda.');
        } else {
            q('DELETE FROM profesionales WHERE id = ?', [$id]);
            flash('ok', 'Profesional eliminado.');
        }
        redirect(url('profesionales.php'));
    }
    $nombre = trim((string)post('nombre'));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)post('color')) ? post('color') : '#8e3f7e';

    // Horario
    $franjas = [];
    $hor = (array)post('h', []);
    foreach (range(1, 7) as $d) {
        for ($i = 0; $i < 2; $i++) {
            $a = isset($hor[$d][$i]['desde']) ? hora_valida($hor[$d][$i]['desde']) : null;
            $b = isset($hor[$d][$i]['hasta']) ? hora_valida($hor[$d][$i]['hasta']) : null;
            if (!$a && !$b) {
                continue;
            }
            if (!$a || !$b || $b <= $a) {
                $err = 'Revisá el horario del ' . DIAS[$d] . ': la hora de salida tiene que ser posterior a la de entrada.';
            }
            $franjas[$d][] = [$a, $b];
        }
        if (isset($franjas[$d][1]) && $franjas[$d][1][0] < $franjas[$d][0][1]) {
            $err = 'Revisá el horario del ' . DIAS[$d] . ': las dos franjas se pisan.';
        }
    }
    $hace = array_map('intval', (array)post('servicios', []));
    $enCats = array_map('intval', (array)post('categorias', []));

    if ($nombre === '') {
        $err = 'Poné el nombre.';
    }
    if (!$err) {
        $vals = [$nombre, trim((string)post('especialidad')) ?: null, trim((string)post('telefono')) ?: null, $color, (int)post('orden'), post('activo') ? 1 : 0];
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            q('UPDATE profesionales SET nombre = ?, especialidad = ?, telefono = ?, color = ?, orden = ?, activo = ? WHERE id = ?', array_merge($vals, [$id]));
        } else {
            q('INSERT INTO profesionales (nombre, especialidad, telefono, color, orden, activo) VALUES (?, ?, ?, ?, ?, ?)', $vals);
            $id = (int)$pdo->lastInsertId();
        }
        q('DELETE FROM profesional_horarios WHERE profesional_id = ?', [$id]);
        foreach ($franjas as $d => $fs) {
            foreach ($fs as $f) {
                q('INSERT INTO profesional_horarios (profesional_id, dia, desde, hasta) VALUES (?, ?, ?, ?)', [$id, $d, $f[0], $f[1]]);
            }
        }
        q('DELETE FROM profesional_categorias WHERE profesional_id = ?', [$id]);
        foreach ($enCats as $cid) {
            q('INSERT INTO profesional_categorias (profesional_id, categoria_id) VALUES (?, ?)', [$id, $cid]);
        }
        q('DELETE FROM servicio_profesional WHERE profesional_id = ?', [$id]);
        foreach ($hace as $sid) {
            q('INSERT INTO servicio_profesional (servicio_id, profesional_id) VALUES (?, ?)', [$sid, $id]);
        }
        $pdo->commit();
        flash('ok', 'Profesional guardado.');
        redirect(url('profesionales.php'));
    }
}

$v = function ($k, $def = '') use ($p) {
    return es_post() ? post($k, $def) : ($p ? $p[$k] : $def);
};
$colorActual = $v('color', colores_agenda()[count(q_all('SELECT id FROM profesionales')) % count(colores_agenda())]);
$paleta = colores_agenda();
if (!in_array($colorActual, $paleta, true)) {
    $paleta[] = $colorActual;
}
layout_header($p ? $p['nombre'] : 'Nuevo profesional', ['activo' => 'profesionales.php']);
?>
<form method="post">
  <?= csrf_field() ?>
  <?php if ($err): ?><div class="aviso aviso-error"><?= e($err) ?></div><?php endif; ?>
  <div class="grid-2">
    <div>
      <div class="card">
        <div class="form-fila">
          <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= e($v('nombre')) ?>" required maxlength="100" autofocus></div>
          <div class="campo"><label for="especialidad">Especialidad</label><input type="text" id="especialidad" name="especialidad" value="<?= e($v('especialidad')) ?>" maxlength="100" placeholder="Ej: Cosmetóloga"></div>
        </div>
        <div class="form-fila">
          <div class="campo"><label for="telefono">Teléfono</label><input type="tel" id="telefono" name="telefono" value="<?= e($v('telefono')) ?>" maxlength="40"></div>
          <div class="campo"><label for="orden">Orden en la agenda</label><input type="number" id="orden" name="orden" value="<?= e($v('orden', 0)) ?>"></div>
        </div>
        <div class="campo">
          <label>Color en la agenda</label>
          <div class="colores">
            <?php foreach ($paleta as $col): ?>
              <label title="<?= e($col) ?>"><input type="radio" name="color" value="<?= e($col) ?>"<?= $col === $colorActual ? ' checked' : '' ?>><span style="background: <?= e($col) ?>"></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="campo"><label class="check"><input type="checkbox" name="activo" value="1"<?= $v('activo', 1) ? ' checked' : '' ?>> Activo (aparece en la agenda)</label></div>
      </div>

      <div class="card">
        <h2>Horario de atención</h2>
        <p class="apagado">Dejá vacío el día que no trabaja. La segunda franja es para quien corta al mediodía.</p>
        <?php foreach (range(1, 7) as $d): ?>
          <div class="horario-dia">
            <b><?= e(ucfirst(DIAS[$d])) ?></b>
            <?php for ($i = 0; $i < 2; $i++):
                $fr = isset($franjas[$d][$i]) ? $franjas[$d][$i] : ['', '']; ?>
              <div class="franja">
                <input type="time" name="h[<?= $d ?>][<?= $i ?>][desde]" value="<?= e($fr[0]) ?>" aria-label="<?= e(DIAS[$d]) ?>, franja <?= $i + 1 ?>, desde">
                <span>a</span>
                <input type="time" name="h[<?= $d ?>][<?= $i ?>][hasta]" value="<?= e($fr[1]) ?>" aria-label="<?= e(DIAS[$d]) ?>, franja <?= $i + 1 ?>, hasta">
              </div>
            <?php endfor; ?>
          </div>
        <?php endforeach; ?>
        <p class="mt"><button class="link" type="button" id="copiar-lunes">Copiar el horario del lunes de martes a viernes</button></p>
      </div>
    </div>

    <div>
    <div class="card">
      <h2>Categorías</h2>
      <?php if (!$categorias): ?>
        <p class="apagado">Todavía no hay categorías. Se cargan en <a href="<?= url('abm.php?t=categorias') ?>">Categorías</a>.</p>
      <?php else: ?>
        <p class="apagado">Puede estar en varias. En la agenda se filtra por categoría, y un turno dado a «cualquier profesional libre» de una categoría se asigna entre quienes están en ella.</p>
        <div class="checks-col">
          <?php foreach ($categorias as $c): ?>
            <label class="check"><input type="checkbox" name="categorias[]" value="<?= (int)$c['id'] ?>" class="js-cat"<?= in_array((int)$c['id'], $enCats, true) ? ' checked' : '' ?>><?= e($c['nombre']) ?></label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Servicios que realiza</h2>
      <?php if (!$servicios): ?>
        <p class="apagado">Todavía no hay servicios cargados.</p>
      <?php else: $cat = false; ?>
        <?php foreach ($servicios as $s):
            if ($s['categoria'] !== $cat): echo $cat !== false ? '</div>' : ''; $cat = $s['categoria']; ?>
          <h3 class="mt"><?= e($cat ?: 'Sin categoría') ?></h3><div class="checks-col">
          <?php endif; ?>
          <label class="check"><input type="checkbox" name="servicios[]" value="<?= (int)$s['id'] ?>" data-cat="<?= (int)$s['categoria_id'] ?>"<?= in_array((int)$s['id'], $hace, true) ? ' checked' : '' ?>><?= e($s['nombre']) ?></label>
        <?php endforeach; echo '</div>'; ?>
      <?php endif; ?>
    </div>
    </div>
  </div>
  <div class="fila">
    <button class="btn btn-pri" type="submit">Guardar</button>
    <a class="btn" href="<?= url('profesionales.php') ?>">Volver</a>
    <span class="espacio"></span>
    <?php if ($p): ?><button class="btn btn-peligro" name="accion" value="eliminar" formnovalidate data-confirm="¿Eliminar a <?= e($p['nombre']) ?>? Si tiene turnos, se desactiva.">Eliminar</button><?php endif; ?>
  </div>
</form>
<script>
// Al marcar una categoría, si no tenía ningún servicio de ella, se marcan todos (después se pueden destildar)
document.querySelectorAll('.js-cat').forEach(function (c) {
  c.addEventListener('change', function () {
    if (!c.checked) return;
    var serv = document.querySelectorAll('input[name="servicios[]"][data-cat="' + c.value + '"]');
    var alguno = Array.prototype.some.call(serv, function (s) { return s.checked; });
    if (!alguno) serv.forEach(function (s) { s.checked = true; });
  });
});
document.getElementById('copiar-lunes').addEventListener('click', function () {
  for (var d = 2; d <= 5; d++) {
    for (var i = 0; i < 2; i++) {
      ['desde', 'hasta'].forEach(function (k) {
        var src = document.querySelector('[name="h[1][' + i + '][' + k + ']"]');
        document.querySelector('[name="h[' + d + '][' + i + '][' + k + ']"]').value = src.value;
      });
    }
  }
});
</script>
<?php layout_footer(); ?>
