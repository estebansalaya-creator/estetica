<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('servicios');

$categorias = q_all('SELECT * FROM categorias ORDER BY orden, nombre');

if (es_post() && post('accion') === 'precios') {
    csrf_check();
    $pct = num(post('porcentaje'));
    $redondeo = max(1, (int)post('redondeo'));
    $cat = (int)post('categoria_id');
    if ($pct == 0 || $pct < -90 || $pct > 500) {
        flash('error', 'Poné un porcentaje entre -90 y 500.');
    } else {
        $filas = q_all('SELECT id, precio FROM servicios WHERE activo = 1' . ($cat ? ' AND categoria_id = ?' : ''), $cat ? [$cat] : []);
        foreach ($filas as $s) {
            $nuevo = round($s['precio'] * (1 + $pct / 100) / $redondeo) * $redondeo;
            q('UPDATE servicios SET precio = ? WHERE id = ?', [$nuevo, $s['id']]);
        }
        flash('ok', 'Precios actualizados ' . ($pct > 0 ? '+' : '') . str_replace('.', ',', (string)$pct) . '% en ' . plural(count($filas), 'servicio', 'servicios') . '. Los turnos ya dados conservan su precio.');
    }
    redirect(url('servicios.php'));
}

$verInactivos = (bool)get('inactivos');
$servicios = q_all(
    'SELECT s.*, c.nombre AS categoria, cb.nombre AS cabina,
            (SELECT GROUP_CONCAT(p.nombre ORDER BY p.orden SEPARATOR ", ") FROM servicio_profesional sp JOIN profesionales p ON p.id = sp.profesional_id
              WHERE sp.servicio_id = s.id AND p.activo = 1) AS profesionales
       FROM servicios s LEFT JOIN categorias c ON c.id = s.categoria_id LEFT JOIN cabinas cb ON cb.id = s.cabina_id'
    . ($verInactivos ? '' : ' WHERE s.activo = 1') .
    ' ORDER BY c.orden IS NULL, c.orden, c.nombre, s.orden, s.nombre'
);

layout_header('Servicios', ['acciones' => '<a class="btn btn-pri" href="' . url('servicio_form.php') . '">Nuevo servicio</a>']);
?>
<div class="grid-2-1">
  <div class="card">
    <?php if (!$servicios): ?>
      <div class="vacio">Todavía no hay servicios. Cargá los tratamientos que ofrece el centro con su duración y precio.</div>
    <?php else: ?>
    <div class="tabla-scroll">
    <table class="tabla">
      <thead><tr><th>Servicio</th><th class="der">Duración</th><th class="der">Precio</th><th>Quién lo hace</th><th></th></tr></thead>
      <tbody>
      <?php $cat = false; foreach ($servicios as $s):
          if ($s['categoria'] !== $cat): $cat = $s['categoria']; ?>
          <tr class="sep"><td colspan="5"><?= e($cat ?: 'Sin categoría') ?></td></tr>
        <?php endif; ?>
        <tr class="<?= $s['activo'] ? '' : 'inactivo' ?>">
          <td><a href="<?= url('servicio_form.php?id=' . $s['id']) ?>"><?= e($s['nombre']) ?></a><?= $s['activo'] ? '' : ' <small>(inactivo)</small>' ?>
            <?php if ($s['cabina']): ?><br><small>Ocupa <?= e($s['cabina']) ?></small><?php endif; ?></td>
          <td class="der nowrap"><?= duracion_txt($s['duracion']) ?></td>
          <td class="der num nowrap"><?= money($s['precio']) ?></td>
          <td><small><?= $s['profesionales'] ? e($s['profesionales']) : '<span style="color:var(--coral)">Nadie asignado</span>' ?></small></td>
          <td class="acciones"><a class="btn btn-chico" href="<?= url('servicio_form.php?id=' . $s['id']) ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
    <p class="mt"><a href="<?= url('servicios.php' . ($verInactivos ? '' : '?inactivos=1')) ?>"><?= $verInactivos ? 'Ocultar los inactivos' : 'Ver también los inactivos' ?></a></p>
  </div>

  <div>
    <div class="card">
      <h2>Actualizar precios</h2>
      <p class="apagado">Aumenta (o baja, con un número negativo) todos los precios de una vez. Los turnos ya dados mantienen su precio.</p>
      <form method="post" data-confirm="¿Actualizar los precios?">
        <?= csrf_field() ?><input type="hidden" name="accion" value="precios">
        <div class="form-fila">
          <div class="campo"><label for="porcentaje">Porcentaje %</label><input type="text" inputmode="decimal" id="porcentaje" name="porcentaje" placeholder="Ej: 8" required></div>
          <div class="campo"><label for="redondeo">Redondear a</label>
            <select id="redondeo" name="redondeo"><option value="100">$ 100</option><option value="500" selected>$ 500</option><option value="1000">$ 1.000</option></select></div>
        </div>
        <div class="campo"><label for="categoria_id">Servicios</label>
          <select id="categoria_id" name="categoria_id"><option value="0">Todos</option><?= opciones($categorias) ?></select></div>
        <button class="btn" type="submit">Actualizar precios</button>
      </form>
    </div>
    <div class="card">
      <h2>Agrupar y organizar</h2>
      <p><a href="<?= url('abm.php?t=categorias') ?>">Categorías</a> <span class="apagado">agrupan servicios y profesionales; filtran la agenda.</span></p>
      <p><a href="<?= url('abm.php?t=cabinas') ?>">Cabinas y equipos</a> <span class="apagado">evitan dar dos turnos a la vez en la misma cabina o con el mismo aparato.</span></p>
    </div>
  </div>
</div>
<?php layout_footer(); ?>
