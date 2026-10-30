<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('servicios');

$id = (int)get('id', 0);
$s = $id ? q_row('SELECT * FROM servicios WHERE id = ?', [$id]) : null;
if ($id && !$s) {
    redirect(url('servicios.php'));
}
$categorias = q_all('SELECT * FROM categorias WHERE activo = 1 OR id = ? ORDER BY orden, nombre', [$s ? (int)$s['categoria_id'] : 0]);
$cabinas = q_all('SELECT * FROM cabinas WHERE activo = 1 OR id = ? ORDER BY orden, nombre', [$s ? (int)$s['cabina_id'] : 0]);
$profs = q_all('SELECT * FROM profesionales WHERE activo = 1 ORDER BY orden, nombre');
$hacen = $id ? array_map('intval', array_column(q_all('SELECT profesional_id FROM servicio_profesional WHERE servicio_id = ?', [$id]), 'profesional_id')) : [];
$err = '';

if (es_post()) {
    csrf_check();
    if (post('accion') === 'eliminar' && $id) {
        if (q_val('SELECT COUNT(*) FROM turnos WHERE servicio_id = ?', [$id])) {
            q('UPDATE servicios SET activo = 0 WHERE id = ?', [$id]);
            flash('ok', 'El servicio tiene turnos, así que se desactivó en lugar de borrarse.');
        } else {
            q('DELETE FROM servicios WHERE id = ?', [$id]);
            flash('ok', 'Servicio eliminado.');
        }
        redirect(url('servicios.php'));
    }
    $nombre = trim((string)post('nombre'));
    $dur = (int)post('duracion');
    $precio = num(post('precio'));
    if ($nombre === '') {
        $err = 'Poné el nombre del servicio.';
    } elseif ($dur < 5 || $dur > 600) {
        $err = 'La duración tiene que estar entre 5 y 600 minutos.';
    } elseif ($precio < 0) {
        $err = 'El precio no puede ser negativo.';
    } else {
        $vals = [
            (int)post('categoria_id') ?: null, $nombre, trim((string)post('descripcion')) ?: null, $dur, $precio,
            (int)post('cabina_id') ?: null, post('online') ? 1 : 0, (int)post('orden'), post('activo') ? 1 : 0,
        ];
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            q('UPDATE servicios SET categoria_id = ?, nombre = ?, descripcion = ?, duracion = ?, precio = ?, cabina_id = ?, online = ?, orden = ?, activo = ? WHERE id = ?', array_merge($vals, [$id]));
        } else {
            q('INSERT INTO servicios (categoria_id, nombre, descripcion, duracion, precio, cabina_id, online, orden, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $vals);
            $id = (int)$pdo->lastInsertId();
        }
        q('DELETE FROM servicio_profesional WHERE servicio_id = ?', [$id]);
        foreach ((array)post('profesionales', []) as $pid) {
            q('INSERT INTO servicio_profesional (servicio_id, profesional_id) VALUES (?, ?)', [$id, (int)$pid]);
        }
        $pdo->commit();
        flash('ok', 'Servicio guardado.');
        redirect(url('servicios.php'));
    }
    $hacen = array_map('intval', (array)post('profesionales', []));
}

$v = function ($k, $def = '') use ($s) {
    return es_post() ? post($k, $def) : ($s ? $s[$k] : $def);
};
if (!$s && !es_post()) {
    $hacen = array_map('intval', array_column($profs, 'id'));
}
layout_header($s ? $s['nombre'] : 'Nuevo servicio', ['activo' => 'servicios.php']);
?>
<form method="post" class="grid-2">
  <?= csrf_field() ?>
  <div class="card">
    <?php if ($err): ?><div class="aviso aviso-error"><?= e($err) ?></div><?php endif; ?>
    <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= e($v('nombre')) ?>" required maxlength="100" autofocus></div>
    <div class="form-fila">
      <div class="campo"><label for="categoria_id">Categoría</label><select id="categoria_id" name="categoria_id"><option value="">Sin categoría</option><?= opciones($categorias, $v('categoria_id')) ?></select></div>
      <div class="campo"><label for="cabina_id">Ocupa la cabina o equipo</label><select id="cabina_id" name="cabina_id"><option value="">Ninguno en particular</option><?= opciones($cabinas, $v('cabina_id')) ?></select></div>
    </div>
    <div class="form-fila">
      <div class="campo"><label for="duracion">Duración (minutos)</label><input type="number" id="duracion" name="duracion" value="<?= e($v('duracion', 60)) ?>" min="5" max="600" step="5" required></div>
      <div class="campo"><label for="precio">Precio $</label><input type="text" inputmode="decimal" id="precio" name="precio" value="<?= e($s || es_post() ? (string)(float)$v('precio') : '') ?>" required></div>
      <div class="campo"><label for="orden">Orden en la lista</label><input type="number" id="orden" name="orden" value="<?= e($v('orden', 0)) ?>"></div>
    </div>
    <div class="campo"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="3" placeholder="Qué incluye y cuidados previos. Se va a mostrar en la reserva online."><?= e($v('descripcion')) ?></textarea></div>
    <div class="campo">
      <label class="check"><input type="checkbox" name="online" value="1"<?= $v('online', 1) ? ' checked' : '' ?>> Se puede reservar online</label>
      <label class="check"><input type="checkbox" name="activo" value="1"<?= $v('activo', 1) ? ' checked' : '' ?>> Activo</label>
    </div>
    <div class="fila">
      <button class="btn btn-pri" type="submit">Guardar</button>
      <a class="btn" href="<?= url('servicios.php') ?>">Volver</a>
      <span class="espacio"></span>
      <?php if ($s): ?><button class="btn btn-peligro" name="accion" value="eliminar" formnovalidate data-confirm="¿Eliminar este servicio? Si ya tiene turnos, se desactiva.">Eliminar</button><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h2>Quién lo hace</h2>
    <?php if (!$profs): ?>
      <p class="apagado">Todavía no hay profesionales cargados.</p>
    <?php else: ?>
      <p class="apagado">Al dar un turno de este servicio se sugieren sólo estos profesionales.</p>
      <?php foreach ($profs as $p): ?>
        <label class="check" style="display:flex; margin-bottom:8px"><input type="checkbox" name="profesionales[]" value="<?= (int)$p['id'] ?>"<?= in_array((int)$p['id'], $hacen, true) ? ' checked' : '' ?>>
          <span class="punto" style="--c: <?= e($p['color']) ?>"></span><?= e($p['nombre']) ?> <small>&nbsp;<?= e($p['especialidad']) ?></small></label>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</form>
<?php layout_footer(); ?>
