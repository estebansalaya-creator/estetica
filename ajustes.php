<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('ajustes');

$CLAVES = [
    'nombre_centro' => ['Nombre del centro', 'Aparece en el menú, el ingreso y los recibos.'],
    'direccion'     => ['Dirección', 'Para el recibo y el recordatorio ({direccion}).'],
    'telefono'      => ['Teléfono', 'Para el recibo.'],
    'pie_recibo'    => ['Mensaje al pie del recibo', ''],
];

if (es_post()) {
    csrf_check();
    foreach ($CLAVES as $k => $x) {
        config_set($k, trim((string)post($k, '')));
    }
    $desde = hora_valida((string)post('agenda_desde')) ?: '08:00';
    $hasta = hora_valida((string)post('agenda_hasta')) ?: '21:00';
    if ($hasta <= $desde) {
        $hasta = '21:00';
    }
    config_set('agenda_desde', $desde);
    config_set('agenda_hasta', $hasta);
    config_set('agenda_intervalo', (string)(in_array((int)post('agenda_intervalo'), [5, 10, 15, 20, 30], true) ? (int)post('agenda_intervalo') : 15));
    config_set('msj_recordatorio', trim((string)post('msj_recordatorio')));
    flash('ok', 'Ajustes guardados.');
    redirect(url('ajustes.php'));
}

layout_header('Ajustes');
?>
<form method="post" class="grid-2">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <h2>Datos del centro</h2>
      <?php foreach ($CLAVES as $k => $x): ?>
        <div class="campo">
          <label for="<?= $k ?>"><?= e($x[0]) ?></label>
          <input type="text" id="<?= $k ?>" name="<?= $k ?>" value="<?= e(config_get($k)) ?>">
          <?php if ($x[1]): ?><small><?= e($x[1]) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h2>Agenda</h2>
      <div class="form-fila">
        <div class="campo"><label for="agenda_desde">Mostrar desde</label><input type="time" id="agenda_desde" name="agenda_desde" value="<?= e(config_get('agenda_desde', '08:00')) ?>"></div>
        <div class="campo"><label for="agenda_hasta">Hasta</label><input type="time" id="agenda_hasta" name="agenda_hasta" value="<?= e(config_get('agenda_hasta', '21:00')) ?>"></div>
        <div class="campo"><label for="agenda_intervalo">Al hacer clic, redondear a</label>
          <select id="agenda_intervalo" name="agenda_intervalo">
            <?php foreach ([5, 10, 15, 20, 30] as $m): ?><option value="<?= $m ?>"<?= (int)config_get('agenda_intervalo', '15') === $m ? ' selected' : '' ?>><?= $m ?> minutos</option><?php endforeach; ?>
          </select></div>
      </div>
      <small class="apagado">Si un profesional trabaja fuera de ese rango, la agenda se amplía sola.</small>
    </div>
  </div>
  <div>
    <div class="card">
      <h2>Recordatorio por WhatsApp</h2>
      <div class="campo">
        <label for="msj_recordatorio">Mensaje</label>
        <textarea id="msj_recordatorio" name="msj_recordatorio" rows="5"><?= e(config_get('msj_recordatorio')) ?></textarea>
        <small>Se reemplazan solos: {nombre}, {servicio}, {dia}, {hora}, {profesional}, {centro} y {direccion}. El mensaje se abre en WhatsApp para revisarlo antes de enviarlo.</small>
      </div>
    </div>
    <div class="card">
      <h2>Configuración</h2>
      <table class="tabla">
        <tr><td><a href="<?= url('usuarios.php') ?>">Usuarios</a></td><td class="apagado">Quién entra al sistema y qué puede hacer.</td></tr>
        <tr><td><a href="<?= url('profesionales.php') ?>">Profesionales</a></td><td class="apagado">Horarios y servicios de cada uno.</td></tr>
        <tr><td><a href="<?= url('servicios.php') ?>">Servicios</a></td><td class="apagado">Tratamientos, duración y precios.</td></tr>
        <tr><td><a href="<?= url('abm.php?t=categorias') ?>">Categorías</a></td><td class="apagado">Grupos de servicios y profesionales.</td></tr>
        <tr><td><a href="<?= url('abm.php?t=cabinas') ?>">Cabinas y equipos</a></td><td class="apagado">Lo que no se puede usar en dos turnos a la vez.</td></tr>
        <tr><td><a href="<?= url('abm.php?t=medios_pago') ?>">Medios de pago</a></td><td class="apagado">Efectivo, transferencia, Mercado Pago, tarjetas…</td></tr>
      </table>
    </div>
    <button class="btn btn-pri btn-grande" type="submit">Guardar ajustes</button>
  </div>
</form>
<?php layout_footer(); ?>
