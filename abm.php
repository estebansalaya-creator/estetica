<?php
/*
 * Altas y modificaciones de tablas simples: categorías, cabinas, medios de pago.
 * abm.php?t=categorias | cabinas | medios_pago
 */
require __DIR__ . '/inc/bootstrap.php';
requiere('ajustes');

$TABLAS = [
    'categorias' => [
        'titulo' => 'Categorías', 'singular' => 'categoría', 'volver' => 'servicios.php',
        'ayuda'  => 'Agrupan los servicios y los profesionales (un profesional puede estar en varias). Sirven para filtrar la agenda y dar turnos a cualquier profesional libre de la categoría. El orden define cómo aparecen.',
        'campos' => ['nombre' => 'Nombre', 'orden' => 'Orden'],
        'checks' => ['activo' => 'Activa'],
        'orden'  => 'orden, nombre',
        'uso'    => 'SELECT (SELECT COUNT(*) FROM servicios WHERE categoria_id = ?) + (SELECT COUNT(*) FROM profesional_categorias WHERE categoria_id = ?)',
    ],
    'cabinas' => [
        'titulo' => 'Cabinas y equipos', 'singular' => 'cabina o equipo', 'volver' => 'servicios.php',
        'ayuda'  => 'Si un servicio ocupa una cabina o un aparato (ej. el equipo láser), el sistema no deja dar dos turnos a la vez con él, aunque sean de profesionales distintos.',
        'campos' => ['nombre' => 'Nombre', 'orden' => 'Orden'],
        'checks' => ['activo' => 'Activa'],
        'orden'  => 'orden, nombre',
        'uso'    => 'SELECT (SELECT COUNT(*) FROM servicios WHERE cabina_id = ?) + (SELECT COUNT(*) FROM turnos WHERE cabina_id = ?)',
    ],
    'medios_pago' => [
        'titulo' => 'Medios de pago', 'singular' => 'medio de pago', 'volver' => 'ajustes.php',
        'ayuda'  => 'Marcá como efectivo sólo los que entran a la caja física (para el arqueo y el vuelto).',
        'campos' => ['nombre' => 'Nombre', 'orden' => 'Orden'],
        'checks' => ['es_efectivo' => 'Es efectivo', 'activo' => 'Activo'],
        'orden'  => 'orden, nombre',
        'uso'    => 'SELECT (SELECT COUNT(*) FROM cobro_pagos WHERE medio_pago_id = ?) + (SELECT COUNT(*) FROM caja_movimientos WHERE medio_pago_id = ?)',
    ],
];

$t = get('t');
if (!isset($TABLAS[$t])) {
    redirect(url('ajustes.php'));
}
$cfg = $TABLAS[$t];

if (es_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('accion') === 'eliminar') {
        $usos = (int)q_val($cfg['uso'], substr_count($cfg['uso'], '?') === 2 ? [$id, $id] : [$id]);
        if ($usos > 0) {
            flash('error', 'Está en uso: no se puede eliminar, desactivalo.');
        } else {
            q("DELETE FROM `$t` WHERE id = ?", [$id]);
            flash('ok', ucfirst($cfg['singular']) . ' eliminado/a.');
        }
        redirect(url('abm.php?t=' . $t));
    }
    $vals = [];
    foreach ($cfg['campos'] as $k => $lbl) {
        $vals[$k] = $k === 'orden' ? (int)post($k) : trim((string)post($k));
    }
    foreach ($cfg['checks'] as $k => $lbl) {
        $vals[$k] = post($k) ? 1 : 0;
    }
    if ($vals['nombre'] === '') {
        flash('error', 'El nombre es obligatorio.');
    } else {
        $cols = array_keys($vals);
        if ($id) {
            $set = implode(', ', array_map(function ($c) { return "`$c` = ?"; }, $cols));
            q("UPDATE `$t` SET $set WHERE id = ?", array_merge(array_values($vals), [$id]));
        } else {
            $ph = implode(', ', array_fill(0, count($cols), '?'));
            q("INSERT INTO `$t` (`" . implode('`, `', $cols) . "`) VALUES ($ph)", array_values($vals));
        }
        flash('ok', 'Guardado.');
    }
    redirect(url('abm.php?t=' . $t));
}

$filas = q_all("SELECT * FROM `$t` ORDER BY " . $cfg['orden']);
layout_header($cfg['titulo'], ['activo' => $cfg['volver']]);
?>
<p class="apagado"><?= e($cfg['ayuda']) ?> <a href="<?= url($cfg['volver']) ?>">Volver</a></p>
<div class="card" style="max-width:860px">
  <div class="tabla-scroll">
  <table class="tabla">
    <thead><tr>
      <?php foreach ($cfg['campos'] as $k => $lbl): ?><th><?= e($lbl) ?></th><?php endforeach; ?>
      <?php foreach ($cfg['checks'] as $k => $lbl): ?><th><?= e($lbl) ?></th><?php endforeach; ?>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $fid = 'f' . $f['id']; ?>
      <tr>
        <?php foreach ($cfg['campos'] as $k => $lbl): ?>
          <td><input form="<?= $fid ?>" type="<?= $k === 'orden' ? 'number' : 'text' ?>" name="<?= $k ?>" value="<?= e($f[$k]) ?>" <?= $k === 'orden' ? 'style="width:80px"' : '' ?> aria-label="<?= e($lbl) ?>"></td>
        <?php endforeach; ?>
        <?php foreach ($cfg['checks'] as $k => $lbl): ?>
          <td><input form="<?= $fid ?>" type="checkbox" name="<?= $k ?>" value="1"<?= $f[$k] ? ' checked' : '' ?> aria-label="<?= e($lbl) ?>"></td>
        <?php endforeach; ?>
        <td class="acciones">
          <form id="<?= $fid ?>" method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-chico" type="submit">Guardar</button>
            <button class="link link-peligro" type="submit" name="accion" value="eliminar" data-confirm="¿Eliminar <?= e($f['nombre']) ?>?">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <?php foreach ($cfg['campos'] as $k => $lbl): ?>
          <td><input form="nuevo" type="<?= $k === 'orden' ? 'number' : 'text' ?>" name="<?= $k ?>" placeholder="<?= $k === 'nombre' ? 'Agregar ' . e($cfg['singular']) : '' ?>" <?= $k === 'orden' ? 'style="width:80px" value="' . (count($filas) + 1) . '"' : '' ?> aria-label="<?= e($lbl) ?>"></td>
        <?php endforeach; ?>
        <?php foreach ($cfg['checks'] as $k => $lbl): ?>
          <td><input form="nuevo" type="checkbox" name="<?= $k ?>" value="1"<?= $k === 'activo' ? ' checked' : '' ?> aria-label="<?= e($lbl) ?>"></td>
        <?php endforeach; ?>
        <td class="acciones"><form id="nuevo" method="post" class="inline"><?= csrf_field() ?><button class="btn btn-chico btn-pri" type="submit">Agregar</button></form></td>
      </tr>
    </tbody>
  </table>
  </div>
</div>
<?php layout_footer(); ?>
