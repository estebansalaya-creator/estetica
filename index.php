<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('inicio');

$hoy = date('Y-m-d');
$manana = date('Y-m-d', strtotime('+1 day'));
$mio = es_profesional() ? mi_profesional_id() : null;
if (es_profesional() && !$mio) {
    sin_permiso('Tu usuario no está vinculado a ningún profesional. Pedile al administrador que lo vincule en Ajustes → Usuarios.');
}
$filtroProf = $mio ? [$mio] : [];

$hoyTurnos = q_all(
    "SELECT t.*, c.nombre, c.apellido, c.telefono, (c.antecedentes IS NOT NULL AND c.antecedentes <> '') AS con_antecedentes,
            p.nombre AS profesional, p.color
       FROM turnos t JOIN clientes c ON c.id = t.cliente_id JOIN profesionales p ON p.id = t.profesional_id
      WHERE t.inicio BETWEEN ? AND ? AND t.estado <> 'cancelado'" . ($mio ? ' AND t.profesional_id = ' . (int)$mio : '') . '
      ORDER BY t.inicio, p.orden',
    [$hoy . ' 00:00:00', $hoy . ' 23:59:59']
);
$activos = array_filter($hoyTurnos, function ($t) { return $t['estado'] !== 'ausente'; });
$porAtender = array_filter($hoyTurnos, function ($t) { return in_array($t['estado'], ['reservado', 'confirmado'], true); });
$atendidos = array_filter($hoyTurnos, function ($t) { return $t['estado'] === 'atendido'; });

$caja = puede('caja') ? caja_abierta() : null;
$res = $caja ? caja_resumen($caja['id']) : null;
$sinCobrar = puede('cobrar') ? turnos_sin_cobrar() : [];

$recordar = puede('turnos') ? q_all(
    "SELECT t.*, c.nombre, c.apellido, c.telefono, p.nombre AS profesional, p.color
       FROM turnos t JOIN clientes c ON c.id = t.cliente_id JOIN profesionales p ON p.id = t.profesional_id
      WHERE t.inicio BETWEEN ? AND ? AND t.estado IN ('reservado','confirmado')
      ORDER BY t.inicio",
    [$manana . ' 00:00:00', $manana . ' 23:59:59']
) : [];

$proximosMios = $mio ? q_all(
    "SELECT t.*, c.nombre, c.apellido FROM turnos t JOIN clientes c ON c.id = t.cliente_id
      WHERE t.profesional_id = ? AND t.inicio >= ? AND t.inicio < ? AND t.estado IN ('reservado','confirmado')
      ORDER BY t.inicio LIMIT 20",
    [$mio, $manana . ' 00:00:00', date('Y-m-d', strtotime('+8 days')) . ' 00:00:00']
) : [];

$saludo = (int)date('G') < 13 ? 'Buen día' : ((int)date('G') < 20 ? 'Buenas tardes' : 'Buenas noches');
layout_header($saludo . ', ' . explode(' ', usuario()['nombre'])[0], ['clase' => 'pagina-inicio']);
?>
<p class="apagado" style="margin-top:-12px"><?= e(ucfirst(fecha_larga($hoy))) ?></p>

<div class="cifras">
  <div class="cifra"><span class="valor"><?= count($activos) ?></span><span class="etiqueta"><?= $mio ? 'Tus turnos de hoy' : 'Turnos hoy' ?></span></div>
  <div class="cifra"><span class="valor"><?= count($porAtender) ?></span><span class="etiqueta">Por atender</span></div>
  <div class="cifra"><span class="valor"><?= count($atendidos) ?></span><span class="etiqueta">Atendidos</span></div>
  <?php if (puede('caja')): ?>
    <div class="cifra"><span class="valor"><?= $caja ? money($res['total_cobros']) : '—' ?></span><span class="etiqueta"><?= $caja ? 'Cobrado en la caja abierta' : 'La caja está cerrada' ?></span></div>
  <?php endif; ?>
</div>

<div class="grid-2-1">
  <div class="card">
    <div class="card-cab">
      <h2><?= $mio ? 'Tus turnos de hoy' : 'Turnos de hoy' ?></h2>
      <a href="<?= url('agenda.php') ?>">Ver agenda</a>
    </div>
    <?php if (!$hoyTurnos): ?>
      <div class="vacio">No hay turnos para hoy.<?php if (puede('turnos')): ?><br><a class="btn btn-pri mt" href="<?= url('turno.php') ?>">Dar un turno</a><?php endif; ?></div>
    <?php else: ?>
    <div class="tabla-scroll">
    <table class="tabla">
      <tbody>
      <?php foreach ($hoyTurnos as $t): ?>
        <tr class="<?= in_array($t['estado'], ['atendido', 'ausente'], true) ? 'inactivo' : '' ?>">
          <td class="hora"><?= hora($t['inicio']) ?></td>
          <td><a href="<?= url('turno.php?id=' . $t['id']) ?>"><b><?= e(nombre_cliente($t)) ?></b></a>
            <?php if ($t['con_antecedentes']): ?> <span class="t-marca m-alerta" title="Tiene antecedentes en la ficha">!</span><?php endif; ?>
            <br><small><?= e($t['servicio_nombre']) ?><?php if (!$mio): ?> · <span class="punto" style="--c: <?= e($t['color']) ?>"></span><?= e($t['profesional']) ?><?php endif; ?></small></td>
          <td class="der"><?= badge_turno($t['estado']) ?>
            <?php if (!$t['cobro_id'] && $t['estado'] === 'atendido' && $t['precio'] > 0 && puede('cobrar')): ?><br><a href="<?= url('cobro.php?cliente=' . $t['cliente_id'] . '&turno=' . $t['id']) ?>">Cobrar</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <?php if ($sinCobrar): ?>
    <div class="card">
      <div class="card-cab"><h2>Atendidos sin cobrar</h2><span class="apagado"><?= money(array_sum(array_column($sinCobrar, 'precio'))) ?></span></div>
      <table class="tabla">
        <?php foreach (array_slice($sinCobrar, 0, 8) as $t): ?>
          <tr>
            <td><?= e(nombre_cliente($t)) ?><br><small><?= e(fecha_relativa($t['inicio'])) ?> · <?= e($t['servicio_nombre']) ?></small></td>
            <td class="der num nowrap"><?= money($t['precio']) ?></td>
            <td class="acciones"><a class="btn btn-chico" href="<?= url('cobro.php?cliente=' . $t['cliente_id'] . '&turno=' . $t['id']) ?>">Cobrar</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php if (count($sinCobrar) > 8): ?><p class="apagado mt">Y <?= count($sinCobrar) - 8 ?> más en <a href="<?= url('caja.php') ?>">Caja</a>.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (puede('turnos')): ?>
    <div class="card">
      <div class="card-cab"><h2>Recordatorios para mañana</h2><span class="apagado"><?= plural(count($recordar), 'turno', 'turnos') ?></span></div>
      <?php if (!$recordar): ?>
        <p class="apagado">No hay turnos para mañana.</p>
      <?php else: ?>
      <table class="tabla">
        <?php foreach ($recordar as $t): ?>
          <tr>
            <td class="hora"><?= hora($t['inicio']) ?></td>
            <td><a href="<?= url('turno.php?id=' . $t['id']) ?>"><?= e(nombre_cliente($t)) ?></a><br><small><?= e($t['servicio_nombre']) ?></small></td>
            <td class="acciones">
              <?php if ($t['recordado']): ?>
                <small class="apagado" title="Enviado <?= e(fecha_hora($t['recordado'])) ?>">Enviado</small>
              <?php elseif ($t['telefono']): ?>
                <form method="post" action="<?= url('turno.php?id=' . $t['id']) ?>" class="inline" target="_blank" onsubmit="var b=this.querySelector('button'); setTimeout(function(){ b.outerHTML='<small class=apagado>Enviado</small>'; }, 300)">
                  <?= csrf_field() ?><input type="hidden" name="accion" value="whatsapp">
                  <button class="btn btn-chico btn-wa" type="submit">WhatsApp</button>
                </form>
              <?php else: ?>
                <small class="apagado">Sin teléfono</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($mio): ?>
    <div class="card">
      <h2>Próximos días</h2>
      <?php if (!$proximosMios): ?>
        <p class="apagado">No tenés turnos en los próximos 7 días.</p>
      <?php else: ?>
      <table class="tabla">
        <?php foreach ($proximosMios as $t): ?>
          <tr><td class="nowrap"><?= e(ucfirst(fecha_relativa($t['inicio']))) ?><br><small><?= hora($t['inicio']) ?> hs</small></td>
              <td><a href="<?= url('turno.php?id=' . $t['id']) ?>"><?= e(nombre_cliente($t)) ?></a><br><small><?= e($t['servicio_nombre']) ?></small></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php layout_footer(); ?>
