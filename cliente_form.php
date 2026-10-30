<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('clientes_editar');

$id = (int)get('id', 0);
$c = $id ? q_row('SELECT * FROM clientes WHERE id = ?', [$id]) : null;
if ($id && !$c) {
    redirect(url('clientes.php'));
}
$err = '';
$CAMPOS = ['nombre', 'apellido', 'dni', 'fecha_nac', 'telefono', 'email', 'direccion', 'antecedentes', 'notas'];

if (es_post()) {
    csrf_check();
    if (post('accion') === 'eliminar' && $id) {
        if (q_val('SELECT COUNT(*) FROM turnos WHERE cliente_id = ?', [$id]) || q_val('SELECT COUNT(*) FROM cobros WHERE cliente_id = ?', [$id])) {
            q('UPDATE clientes SET activo = 0 WHERE id = ?', [$id]);
            flash('ok', 'Tiene turnos o cobros, así que no se borra: quedó archivada y ya no aparece en las búsquedas.');
        } else {
            q('DELETE FROM clientes WHERE id = ?', [$id]);
            flash('ok', 'Ficha eliminada.');
        }
        redirect(url('clientes.php'));
    }
    $d = [];
    foreach ($CAMPOS as $k) {
        $d[$k] = trim((string)post($k, ''));
    }
    $d['dni'] = preg_replace('/\D/', '', $d['dni']);
    $d['fecha_nac'] = fecha_valida($d['fecha_nac']);
    $tel = preg_replace('/\D/', '', $d['telefono']);
    if ($d['nombre'] === '') {
        $err = 'Poné el nombre.';
    } elseif ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $err = 'El email no parece válido.';
    } elseif ($d['dni'] !== '' && ($otro = q_row('SELECT * FROM clientes WHERE dni = ? AND id <> ?', [$d['dni'], $id]))) {
        $err = 'Ya hay una ficha con ese DNI: ' . nombre_cliente($otro) . '.';
    } elseif (strlen($tel) >= 8 && ($otro = q_row("SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ? AND id <> ?", ['%' . substr($tel, -8), $id]))) {
        $err = 'Ya hay una ficha con ese teléfono: ' . nombre_cliente($otro) . '.';
    } else {
        $vals = array_map(function ($v) { return $v === '' ? null : $v; }, $d);
        $vals['apellido'] = $d['apellido'];
        if ($id) {
            $set = implode(', ', array_map(function ($k) { return "$k = ?"; }, $CAMPOS));
            q("UPDATE clientes SET $set, activo = 1 WHERE id = ?", array_merge(array_values($vals), [$id]));
        } else {
            q('INSERT INTO clientes (' . implode(', ', $CAMPOS) . ') VALUES (' . implode(', ', array_fill(0, count($CAMPOS), '?')) . ')', array_values($vals));
            $id = (int)db()->lastInsertId();
        }
        flash('ok', 'Ficha guardada.');
        redirect(url('cliente.php?id=' . $id));
    }
}

$v = function ($k) use ($c) {
    return es_post() ? post($k, '') : ($c ? (string)$c[$k] : '');
};
layout_header($c ? 'Editar ficha' : 'Nueva clienta o cliente', ['activo' => 'clientes.php']);
?>
<form method="post" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <?php if ($err): ?><div class="aviso aviso-error"><?= e($err) ?></div><?php endif; ?>
  <div class="form-fila">
    <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= e($v('nombre')) ?>" required maxlength="80" autofocus></div>
    <div class="campo"><label for="apellido">Apellido</label><input type="text" id="apellido" name="apellido" value="<?= e($v('apellido')) ?>" maxlength="80"></div>
    <div class="campo"><label for="dni">DNI</label><input type="text" inputmode="numeric" id="dni" name="dni" value="<?= e($v('dni')) ?>" maxlength="15"></div>
  </div>
  <div class="form-fila">
    <div class="campo"><label for="telefono">Teléfono (celular)</label><input type="tel" id="telefono" name="telefono" value="<?= e($v('telefono')) ?>" maxlength="40" placeholder="11 5555-1234">
      <small>Con código de área, sin 0 ni 15: así funciona el recordatorio por WhatsApp.</small></div>
    <div class="campo"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($v('email')) ?>" maxlength="120"></div>
    <div class="campo"><label for="fecha_nac">Fecha de nacimiento</label><input type="date" id="fecha_nac" name="fecha_nac" value="<?= e($v('fecha_nac')) ?>"></div>
  </div>
  <div class="campo"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" value="<?= e($v('direccion')) ?>" maxlength="200"></div>
  <div class="campo">
    <label for="antecedentes">Antecedentes</label>
    <textarea id="antecedentes" name="antecedentes" rows="3" placeholder="Alergias, medicación, embarazo, marcapasos, enfermedades de la piel, cirugías recientes"><?= e($v('antecedentes')) ?></textarea>
    <small>Lo que se escriba acá aparece resaltado en cada turno y en la agenda, para que el profesional lo vea antes de atender.</small>
  </div>
  <div class="campo"><label for="notas">Notas</label><textarea id="notas" name="notas" rows="2" placeholder="Preferencias, cómo nos conoció"><?= e($v('notas')) ?></textarea></div>
  <div class="fila">
    <button class="btn btn-pri" type="submit">Guardar</button>
    <a class="btn" href="<?= $c ? url('cliente.php?id=' . $id) : url('clientes.php') ?>">Volver</a>
    <span class="espacio"></span>
    <?php if ($c): ?><button class="btn btn-peligro" name="accion" value="eliminar" formnovalidate data-confirm="¿Eliminar esta ficha? Si tiene turnos o cobros, se archiva en lugar de borrarse.">Eliminar</button><?php endif; ?>
  </div>
</form>
<?php layout_footer(); ?>
