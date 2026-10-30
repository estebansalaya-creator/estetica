<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('clientes');

$id = (int)get('id', 0);
$c = q_row('SELECT * FROM clientes WHERE id = ?', [$id]);
if (!$c) {
    flash('error', 'La ficha no existe.');
    redirect(url('clientes.php'));
}
if (!puede_ver_cliente($id)) {
    sin_permiso('Sólo podés ver las fichas de las personas que atienden vos o tu categoría.');
}

// El profesional puede actualizar los antecedentes (recepción y admin editan toda la ficha)
if (es_post() && post('accion') === 'antecedentes' && puede('sesion')) {
    csrf_check();
    q('UPDATE clientes SET antecedentes = ? WHERE id = ?', [trim((string)post('antecedentes')) ?: null, $id]);
    flash('ok', 'Antecedentes actualizados.');
    redirect(url('cliente.php?id=' . $id));
}

$turnos = q_all(
    "SELECT t.*, p.nombre AS profesional, p.color FROM turnos t JOIN profesionales p ON p.id = t.profesional_id
      WHERE t.cliente_id = ? ORDER BY t.inicio DESC LIMIT 200",
    [$id]
);
$proximos = array_reverse(array_values(array_filter($turnos, function ($t) {
    return strtotime($t['inicio']) >= strtotime('today') && in_array($t['estado'], ['reservado', 'confirmado', 'en_atencion'], true);
})));
$historial = array_values(array_filter($turnos, function ($t) use ($proximos) {
    return !in_array($t, $proximos, true);
}));
$atendidos = array_filter($turnos, function ($t) { return $t['estado'] === 'atendido'; });
$ausentes = array_filter($turnos, function ($t) { return $t['estado'] === 'ausente'; });
$verPlata = puede('cobrar') || puede('caja');
$cobros = $verPlata ? q_all(
    "SELECT co.*, (SELECT GROUP_CONCAT(i.descripcion SEPARATOR ', ') FROM cobro_items i WHERE i.cobro_id = co.id) AS detalle
       FROM cobros co WHERE co.cliente_id = ? ORDER BY co.fecha DESC LIMIT 30",
    [$id]
) : [];
$gastado = array_sum(array_map(function ($x) { return $x['estado'] === 'valido' ? (float)$x['total'] : 0; }, $cobros));
$sinCobrar = $verPlata ? turnos_sin_cobrar($id) : [];
$wa = whatsapp_link($c['telefono']);

layout_header(nombre_cliente($c), ['activo' => 'clientes.php']);
?>
<div class="grid-2-1">
  <div>
    <?php if (trim((string)$c['antecedentes']) !== ''): ?>
      <div class="antecedentes"><b>Antecedentes</b><?= e($c['antecedentes']) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-cab">
        <h2>Historial</h2>
        <span class="apagado"><?= plural(count($atendidos), 'sesión', 'sesiones') ?><?= $ausentes ? ', ' . plural(count($ausentes), 'ausencia', 'ausencias') : '' ?></span>
      </div>
      <?php if (!$historial): ?>
        <p class="apagado">Todavía no tiene turnos pasados.</p>
      <?php else: ?>
      <div class="tabla-scroll">
      <table class="tabla">
        <thead><tr><th>Fecha</th><th>Servicio</th><th>Notas de la sesión</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($historial as $t): ?>
          <tr class="<?= $t['estado'] === 'cancelado' ? 'inactivo' : '' ?>">
            <td class="nowrap"><a href="<?= url('turno.php?id=' . $t['id']) ?>"><?= e(fecha($t['inicio'])) ?></a><br><small><?= hora($t['inicio']) ?></small></td>
            <td><?= e($t['servicio_nombre']) ?><br><small><span class="punto" style="--c: <?= e($t['color']) ?>"></span><?= e($t['profesional']) ?></small></td>
            <td style="white-space:pre-line; max-width:420px"><?= $t['notas_sesion'] ? e($t['notas_sesion']) : '' ?></td>
            <td class="der"><?= $t['estado'] !== 'atendido' ? badge_turno($t['estado']) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($verPlata): ?>
    <div class="card">
      <div class="card-cab"><h2>Cobros</h2><?php if ($gastado): ?><span class="apagado">Total: <?= money($gastado) ?></span><?php endif; ?></div>
      <?php if ($sinCobrar): ?>
        <div class="aviso aviso-alerta"><?= count($sinCobrar) === 1 ? 'Tiene 1 sesión sin cobrar' : 'Tiene ' . count($sinCobrar) . ' sesiones sin cobrar' ?>.
          <a href="<?= url('cobro.php?cliente=' . $id) ?>">Cobrar ahora</a></div>
      <?php endif; ?>
      <?php if (!$cobros): ?>
        <p class="apagado">Sin cobros registrados.</p>
      <?php else: ?>
      <table class="tabla">
        <?php foreach ($cobros as $x): ?>
          <tr class="<?= $x['estado'] === 'anulado' ? 'anulado' : '' ?>">
            <td class="nowrap"><a href="<?= url('recibo.php?id=' . $x['id']) ?>"><?= e(fecha($x['fecha'])) ?></a></td>
            <td><?= e($x['detalle']) ?></td>
            <td class="der num"><?= money($x['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <dl class="datos">
        <?php if ($c['telefono']): ?><dt>Teléfono</dt><dd><?= e($c['telefono']) ?><?php if ($wa): ?> · <a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd><?php endif; ?>
        <?php if ($c['email']): ?><dt>Email</dt><dd><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></dd><?php endif; ?>
        <?php if ($c['dni']): ?><dt>DNI</dt><dd><?= e($c['dni']) ?></dd><?php endif; ?>
        <?php if ($c['fecha_nac']): ?><dt>Nacimiento</dt><dd><?= e(fecha($c['fecha_nac'])) ?> (<?= edad($c['fecha_nac']) ?> años)</dd><?php endif; ?>
        <?php if ($c['direccion']): ?><dt>Dirección</dt><dd><?= e($c['direccion']) ?></dd><?php endif; ?>
        <?php if ($c['notas']): ?><dt>Notas</dt><dd style="white-space:pre-line"><?= e($c['notas']) ?></dd><?php endif; ?>
        <dt>Ficha creada</dt><dd class="apagado"><?= e(fecha($c['creado'])) ?></dd>
      </dl>
      <div class="fila mt">
        <?php if (puede('turnos')): ?><a class="btn btn-pri" href="<?= url('turno.php?cliente=' . $id) ?>">Dar turno</a><?php endif; ?>
        <?php if (puede('clientes_editar')): ?><a class="btn" href="<?= url('cliente_form.php?id=' . $id) ?>">Editar datos</a><?php endif; ?>
      </div>
      <?php if (!puede('clientes_editar') && puede('sesion')): ?>
        <form method="post" class="mt">
          <?= csrf_field() ?><input type="hidden" name="accion" value="antecedentes">
          <div class="campo"><label for="antecedentes">Antecedentes</label>
            <textarea id="antecedentes" name="antecedentes" rows="3" placeholder="Alergias, medicación, contraindicaciones"><?= e($c['antecedentes']) ?></textarea></div>
          <button class="btn" type="submit">Guardar antecedentes</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Próximos turnos</h2>
      <?php if (!$proximos): ?>
        <p class="apagado">No tiene turnos agendados.</p>
      <?php else: ?>
      <table class="tabla">
        <?php foreach ($proximos as $t): ?>
          <tr>
            <td class="nowrap"><a href="<?= url('turno.php?id=' . $t['id']) ?>"><?= e(ucfirst(fecha_relativa($t['inicio']))) ?></a><br><small><?= hora($t['inicio']) ?> hs</small></td>
            <td><?= e($t['servicio_nombre']) ?><br><small><?= e($t['profesional']) ?></small></td>
            <td class="der"><?= badge_turno($t['estado']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php layout_footer(); ?>
