<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('agenda');

$id = (int)get('id', 0);
$t = $id ? turno_get($id) : null;
if ($id && !$t) {
    flash('error', 'El turno no existe.');
    redirect(url('agenda.php'));
}
if ($t && !puede_ver_turno($t)) {
    sin_permiso('Ese turno es de un profesional fuera de tu categoría.');
}
if (!$t && !puede('turnos')) {
    sin_permiso('Tu usuario no puede dar turnos.');
}
$editar = !$t || get('editar') !== null;
if ($editar && !puede('turnos')) {
    $editar = false;
}

$errores = [];
$avisos = [];
$volverAgenda = function ($fechaHora, $profId) {
    return url('agenda.php?fecha=' . substr($fechaHora, 0, 10));
};

if (es_post()) {
    csrf_check();
    $accion = post('accion');

    // ---------- Cambiar estado ----------
    if ($accion === 'estado' && $t) {
        $nuevo = (string)post('estado');
        if (!isset(ESTADOS_TURNO[$nuevo])) {
            redirect(url('turno.php?id=' . $id));
        }
        if (!puede_operar_turno($t) || (!puede('turnos') && !in_array($nuevo, ESTADOS_PROFESIONAL, true))) {
            sin_permiso('No podés pasar el turno a ese estado.');
        }
        if (in_array($nuevo, ['cancelado', 'ausente'], true) && $t['cobro_id']) {
            flash('error', 'El turno está cobrado. Para cancelarlo o marcarlo ausente, primero anulá el cobro desde Caja.');
        } else {
            $motivo = $nuevo === 'cancelado' ? trim((string)post('motivo')) : null;
            q('UPDATE turnos SET estado = ?, motivo_cancelado = ?, actualizado = NOW() WHERE id = ?', [$nuevo, $motivo ?: null, $id]);
            flash('ok', 'Turno ' . mb_strtolower(estado_turno_txt($nuevo)) . '.');
            if ($nuevo === 'cancelado') {
                redirect($volverAgenda($t['inicio'], $t['profesional_id']));
            }
        }
        redirect(url('turno.php?id=' . $id));
    }

    // ---------- Notas de la sesión ----------
    if ($accion === 'notas_sesion' && $t) {
        if (!puede_operar_turno($t)) {
            sin_permiso('Sólo el profesional del turno puede escribir sus notas.');
        }
        q('UPDATE turnos SET notas_sesion = ?, actualizado = NOW() WHERE id = ?', [trim((string)post('notas_sesion')) ?: null, $id]);
        flash('ok', 'Notas de la sesión guardadas.');
        redirect(url('turno.php?id=' . $id));
    }

    // ---------- Recordatorio por WhatsApp ----------
    if ($accion === 'whatsapp' && $t && puede('turnos')) {
        $link = whatsapp_link($t['telefono'], mensaje_recordatorio($t));
        if ($link) {
            q('UPDATE turnos SET recordado = NOW() WHERE id = ?', [$id]);
            redirect($link);
        }
        redirect(url('turno.php?id=' . $id));
    }

    // ---------- Guardar (nuevo o editar) ----------
    if ($accion === 'guardar' && puede('turnos')) {
        $servicio = q_row('SELECT * FROM servicios WHERE id = ?', [(int)post('servicio_id')]);
        $fechaP = fecha_valida((string)post('fecha'));
        $horaP = hora_valida((string)post('hora'));
        $dur = max(5, (int)post('duracion'));
        $inicio = ($fechaP && $horaP) ? $fechaP . ' ' . $horaP . ':00' : '';
        $finP = $inicio ? date('Y-m-d H:i:s', strtotime($inicio) + $dur * 60) : '';
        $clienteId = (int)post('cliente_id');
        $nuevoCli = null;
        if (!$clienteId && trim((string)post('cli_nombre')) !== '') {
            $nuevoCli = [
                'nombre'   => trim((string)post('cli_nombre')),
                'apellido' => trim((string)post('cli_apellido')),
                'telefono' => trim((string)post('cli_telefono')),
            ];
        }
        $d = [
            'cliente_id'      => $clienteId ?: ($nuevoCli ? -1 : 0),
            'profesional_id'  => (int)post('profesional_id'),
            'servicio_id'     => $servicio ? (int)$servicio['id'] : null,
            'servicio_nombre' => $servicio ? $servicio['nombre'] : '',
            'cabina_id'       => (int)post('cabina_id') ?: null,
            'inicio'          => $inicio,
            'fin'             => $finP,
        ];
        if ($t && $t['servicio_id'] === null && !$servicio) {
            $d['servicio_nombre'] = $t['servicio_nombre']; // servicio borrado: conserva el nombre
        }
        // "Cualquier profesional libre" de la categoría: se asigna al primero que pueda
        $catId = (int)post('categoria_id');
        $auto = post('profesional_id') === 'auto';
        $asignado = null;
        if ($auto) {
            if (!$catId) {
                $errores[] = 'Para asignarlo a cualquier profesional libre, elegí la categoría.';
            } elseif ($d['inicio'] && $d['fin'] && $d['servicio_nombre'] !== '') {
                $lib = profesional_libre($catId, $d, $id);
                if ($lib['profesional']) {
                    $asignado = $lib['profesional'];
                    $d['profesional_id'] = (int)$asignado['id'];
                } else {
                    $det = [];
                    foreach ($lib['motivos'] as $nom => $mot) {
                        $det[] = $nom . ' ' . $mot;
                    }
                    $errores[] = 'No hay nadie de ' . q_val('SELECT nombre FROM categorias WHERE id = ?', [$catId]) . ' libre a esa hora'
                        . ($det ? ': ' . implode('; ', $det) : ' (la categoría no tiene profesionales activos)') . '.';
                }
            }
        }
        $val = ($auto && !$asignado && $errores) ? ['errores' => [], 'avisos' => []] : validar_turno($d, $id);
        $errores = array_merge($errores, $val['errores']);
        $avisos = $val['avisos'];
        if ($nuevoCli && $nuevoCli['telefono'] !== '') {
            $tel = preg_replace('/[^0-9]/', '', $nuevoCli['telefono']);
            $existe = $tel !== '' ? q_row("SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?", ['%' . substr($tel, -8)]) : null;
            if ($existe) {
                $errores[] = 'Ya hay una ficha con ese teléfono: ' . nombre_cliente($existe) . '. Buscala por el nombre en lugar de crear una nueva.';
            }
        }
        if (!$errores && (!$avisos || post('forzar'))) {
            $pdo = db();
            $pdo->beginTransaction();
            if ($nuevoCli) {
                q('INSERT INTO clientes (nombre, apellido, telefono) VALUES (?, ?, ?)', [$nuevoCli['nombre'], $nuevoCli['apellido'], $nuevoCli['telefono'] ?: null]);
                $d['cliente_id'] = (int)$pdo->lastInsertId();
            }
            $precio = max(0, num(post('precio')));
            $notas = trim((string)post('notas')) ?: null;
            if ($t) {
                $cobrado = (bool)$t['cobro_id'];
                $autoFlag = $asignado ? 1 : ((int)$d['profesional_id'] === (int)$t['profesional_id'] ? (int)$t['asignado_auto'] : 0);
                q(
                    'UPDATE turnos SET cliente_id = ?, profesional_id = ?, servicio_id = ?, servicio_nombre = ?, cabina_id = ?, inicio = ?, fin = ?,
                            precio = ?, notas = ?, asignado_auto = ?, actualizado = NOW() WHERE id = ?',
                    [$d['cliente_id'], $d['profesional_id'], $d['servicio_id'], $d['servicio_nombre'], $d['cabina_id'], $d['inicio'], $d['fin'],
                     $cobrado ? $t['precio'] : $precio, $notas, $autoFlag, $id]
                );
                $pdo->commit();
                flash('ok', 'Turno actualizado.' . ($asignado ? ' Quedó con ' . $asignado['nombre'] . ' (asignado automáticamente).' : ''));
                redirect(url('turno.php?id=' . $id));
            }
            q(
                "INSERT INTO turnos (cliente_id, profesional_id, servicio_id, servicio_nombre, cabina_id, inicio, fin, precio, estado, notas, asignado_auto, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$d['cliente_id'], $d['profesional_id'], $d['servicio_id'], $d['servicio_nombre'], $d['cabina_id'], $d['inicio'], $d['fin'],
                 $precio, post('confirmado') ? 'confirmado' : 'reservado', $notas, $asignado ? 1 : 0, usuario_id()]
            );
            $nuevoId = (int)$pdo->lastInsertId();
            $pdo->commit();
            flash('ok', 'Turno agendado: ' . fecha_relativa($d['inicio']) . ' a las ' . hora($d['inicio']) . ($asignado ? ' con ' . $asignado['nombre'] . ' (asignado automáticamente)' : '') . '.');
            redirect(url(post('otro')
                ? 'turno.php?cliente=' . $d['cliente_id'] . '&fecha=' . $fechaP . '&hora=' . hora($d['fin']) . ($catId ? '&categoria=' . $catId : '')
                : 'agenda.php?fecha=' . $fechaP . ($catId ? '&cat=' . $catId : '') . '#t' . $nuevoId));
        }
        $editar = true;
    }
}

// ---------- Valores del formulario ----------
$servicios = servicios_para_turno();
$profs = profesionales_activos();
$cabinas = q_all('SELECT * FROM cabinas WHERE activo = 1 ORDER BY orden, nombre');

if (es_post()) {
    $f = [
        'cliente_id' => (int)post('cliente_id'), 'servicio_id' => (int)post('servicio_id'),
        'profesional_id' => post('profesional_id') === 'auto' ? 'auto' : (int)post('profesional_id'), 'categoria_id' => (int)post('categoria_id'),
        'fecha' => (string)post('fecha'), 'hora' => (string)post('hora'), 'duracion' => (int)post('duracion'),
        'cabina_id' => (int)post('cabina_id'), 'precio' => (string)post('precio'), 'notas' => (string)post('notas'),
        'cli_nombre' => (string)post('cli_nombre'), 'cli_apellido' => (string)post('cli_apellido'), 'cli_telefono' => (string)post('cli_telefono'),
        'confirmado' => (bool)post('confirmado'),
    ];
} elseif ($t) {
    $f = [
        'cliente_id' => (int)$t['cliente_id'], 'servicio_id' => (int)$t['servicio_id'], 'profesional_id' => (int)$t['profesional_id'],
        'categoria_id' => (int)q_val('SELECT categoria_id FROM servicios WHERE id = ?', [(int)$t['servicio_id']]),
        'fecha' => substr($t['inicio'], 0, 10), 'hora' => hora($t['inicio']), 'duracion' => (int)round((strtotime($t['fin']) - strtotime($t['inicio'])) / 60),
        'cabina_id' => (int)$t['cabina_id'], 'precio' => (string)(float)$t['precio'], 'notas' => (string)$t['notas'],
        'cli_nombre' => '', 'cli_apellido' => '', 'cli_telefono' => '', 'confirmado' => false,
    ];
} else {
    $f = [
        'cliente_id' => (int)get('cliente', 0), 'servicio_id' => (int)get('servicio', 0), 'profesional_id' => (int)get('profesional', 0),
        'categoria_id' => (int)get('categoria', 0),
        'fecha' => fecha_valida((string)get('fecha', '')) ?: date('Y-m-d'), 'hora' => hora_valida((string)get('hora', '')) ?: '',
        'duracion' => 0, 'cabina_id' => 0, 'precio' => '', 'notas' => '',
        'cli_nombre' => '', 'cli_apellido' => '', 'cli_telefono' => '', 'confirmado' => false,
    ];
}
$clienteSel = $f['cliente_id'] > 0 ? q_row('SELECT * FROM clientes WHERE id = ?', [$f['cliente_id']]) : null;
$categorias = categorias_activas();
if (!in_array($f['categoria_id'], array_map('intval', array_column($categorias, 'id')), true)) {
    $f['categoria_id'] = 0;
}
// Al reprogramar, si el profesional del turno no está en la categoría del servicio, no se filtra (para no cambiarlo sin querer)
if ($t && !es_post() && $f['categoria_id'] && !in_array($f['categoria_id'], categorias_de_profesional($f['profesional_id']), true)) {
    $f['categoria_id'] = 0;
}

// Ficha resumida de la clienta (vista de detalle)
$historial = $proximos = [];
if ($t) {
    $historial = q_all(
        "SELECT t.*, p.nombre AS profesional FROM turnos t JOIN profesionales p ON p.id = t.profesional_id
          WHERE t.cliente_id = ? AND t.id <> ? AND t.inicio < ? AND t.estado = 'atendido' ORDER BY t.inicio DESC LIMIT 4",
        [$t['cliente_id'], $id, $t['inicio']]
    );
    $proximos = q_all(
        "SELECT t.*, p.nombre AS profesional FROM turnos t JOIN profesionales p ON p.id = t.profesional_id
          WHERE t.cliente_id = ? AND t.id <> ? AND t.inicio >= NOW() AND t.estado IN ('reservado','confirmado') ORDER BY t.inicio LIMIT 4",
        [$t['cliente_id'], $id]
    );
}

$titulo = $t ? ($editar ? 'Reprogramar turno' : 'Turno') : 'Nuevo turno';
layout_header($titulo, ['activo' => 'agenda.php']);
?>

<?php if ($t && !$editar):
    $pasado = strtotime($t['inicio']) < time();
    $cliTxt = nombre_cliente($t); ?>
<div class="grid-2-1">
  <div>
    <div class="card">
      <div class="turno-cab">
        <div style="flex:1">
          <div class="turno-cuando"><?= e(ucfirst(fecha_relativa($t['inicio']))) ?>, <?= hora($t['inicio']) ?> a <?= hora($t['fin']) ?></div>
          <p class="apagado" style="margin:2px 0 0"><?= e(ucfirst(fecha_larga($t['inicio']))) ?> · <?= duracion_txt((strtotime($t['fin']) - strtotime($t['inicio'])) / 60) ?></p>
        </div>
        <?= badge_turno($t['estado']) ?>
      </div>
      <?php if (trim((string)$t['antecedentes']) !== ''): ?>
        <div class="antecedentes mt"><b>Antecedentes</b><?= e($t['antecedentes']) ?></div>
      <?php endif; ?>
      <dl class="datos mt">
        <dt>Cliente</dt><dd><a href="<?= url('cliente.php?id=' . $t['cliente_id']) ?>"><?= e($cliTxt) ?></a><?= $t['telefono'] ? ' · ' . e($t['telefono']) : '' ?></dd>
        <dt>Servicio</dt><dd><?= e($t['servicio_nombre']) ?></dd>
        <dt>Profesional</dt><dd><span class="punto" style="--c: <?= e($t['color']) ?>"></span><?= e($t['profesional']) ?><?= !empty($t['asignado_auto']) ? ' <small>(asignado automáticamente)</small>' : '' ?></dd>
        <?php if ($t['cabina']): ?><dt>Cabina</dt><dd><?= e($t['cabina']) ?></dd><?php endif; ?>
        <?php if (!es_profesional()): ?>
        <dt>Precio</dt><dd><?= money($t['precio']) ?>
          <?php if ($t['cobro_id']): ?> <span class="badge b-cobrado">Cobrado</span> <a href="<?= url('recibo.php?id=' . $t['cobro_id']) ?>">ver recibo</a>
          <?php elseif ($t['precio'] > 0 && $t['estado'] !== 'cancelado'): ?> <span class="badge b-impago">Sin cobrar</span><?php endif; ?></dd>
        <?php endif; ?>
        <?php if ($t['notas']): ?><dt>Aclaraciones</dt><dd><?= e($t['notas']) ?></dd><?php endif; ?>
        <?php if ($t['estado'] === 'cancelado' && $t['motivo_cancelado']): ?><dt>Motivo</dt><dd><?= e($t['motivo_cancelado']) ?></dd><?php endif; ?>
        <?php if ($t['recordado']): ?><dt>Recordatorio</dt><dd>Enviado <?= e(fecha_relativa($t['recordado'])) ?> a las <?= hora($t['recordado']) ?></dd><?php endif; ?>
        <dt>Agendado</dt><dd class="apagado"><?= e(fecha_hora($t['creado'])) ?><?= $t['creado_por'] ? ' por ' . e($t['creado_por']) : '' ?><?= $t['origen'] === 'online' ? ' (reserva online)' : '' ?></dd>
      </dl>

      <?php if ($t['estado'] !== 'cancelado'): ?>
      <div class="mt">
        <form method="post" class="estados">
          <?= csrf_field() ?><input type="hidden" name="accion" value="estado">
          <?php if (puede('turnos')): ?>
            <?php if ($t['estado'] === 'reservado'): ?><button class="btn btn-ok" name="estado" value="confirmado">Confirmar</button><?php endif; ?>
          <?php endif; ?>
          <?php if (puede_operar_turno($t)): ?>
            <?php if (in_array($t['estado'], ['reservado', 'confirmado'], true)): ?><button class="btn<?= $pasado || substr($t['inicio'], 0, 10) === date('Y-m-d') ? ' btn-pri' : '' ?>" name="estado" value="en_atencion">Llegó, pasa a atención</button><?php endif; ?>
            <?php if (in_array($t['estado'], ['reservado', 'confirmado', 'en_atencion'], true)): ?><button class="btn<?= $t['estado'] === 'en_atencion' ? ' btn-pri' : '' ?>" name="estado" value="atendido">Marcar atendido</button><?php endif; ?>
            <?php if (in_array($t['estado'], ['reservado', 'confirmado'], true) && !$t['cobro_id']): ?><button class="btn btn-peligro" name="estado" value="ausente" data-confirm="¿Marcar que no vino?">No vino</button><?php endif; ?>
          <?php endif; ?>
          <?php if (puede('turnos') && in_array($t['estado'], ['en_atencion', 'atendido', 'ausente'], true)): ?>
            <button class="link" name="estado" value="confirmado">Volver a confirmado</button>
          <?php endif; ?>
        </form>
      </div>
      <?php endif; ?>

      <?php if (puede('turnos')): ?>
      <div class="fila mt">
        <?php if (!$t['cobro_id'] && $t['estado'] !== 'cancelado' && puede('cobrar')): ?>
          <a class="btn btn-pri" href="<?= url('cobro.php?cliente=' . $t['cliente_id'] . '&turno=' . $id) ?>">Cobrar</a>
        <?php endif; ?>
        <?php if ($t['estado'] !== 'cancelado'): ?><a class="btn" href="<?= url('turno.php?id=' . $id . '&editar') ?>">Reprogramar o editar</a><?php endif; ?>
        <?php if ($t['telefono'] && in_array($t['estado'], ['reservado', 'confirmado'], true)): ?>
          <form method="post" class="inline" target="_blank"><?= csrf_field() ?><input type="hidden" name="accion" value="whatsapp">
            <button class="btn btn-wa" type="submit"><?= $t['recordado'] ? 'Volver a enviar recordatorio' : 'Recordatorio por WhatsApp' ?></button></form>
        <?php endif; ?>
        <a class="btn" href="<?= url('turno.php?cliente=' . $t['cliente_id']) ?>">Darle otro turno</a>
        <span class="espacio"></span>
        <?php if ($t['estado'] !== 'cancelado' && !$t['cobro_id']): ?><button class="btn btn-peligro" type="button" data-abrir="modal-cancelar">Cancelar turno</button><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Notas de la sesión</h2>
      <?php if (puede_operar_turno($t) && $t['estado'] !== 'cancelado'): ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="accion" value="notas_sesion">
        <div class="campo">
          <textarea name="notas_sesion" rows="5" placeholder="Qué se hizo, parámetros del equipo, productos usados, reacción de la piel, indicaciones para la próxima sesión"><?= e($t['notas_sesion']) ?></textarea>
        </div>
        <button class="btn" type="submit">Guardar notas</button>
      </form>
      <?php elseif ($t['notas_sesion']): ?>
        <p style="white-space:pre-line"><?= e($t['notas_sesion']) ?></p>
      <?php else: ?>
        <p class="apagado">Sin notas.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-cab"><h2><?= e($cliTxt) ?></h2><a href="<?= url('cliente.php?id=' . $t['cliente_id']) ?>">Ver ficha</a></div>
    <h3>Últimas sesiones</h3>
    <?php if (!$historial): ?>
      <p class="apagado">Es su primera vez.</p>
    <?php else: ?>
      <table class="tabla">
        <?php foreach ($historial as $h): ?>
          <tr><td><a href="<?= url('turno.php?id=' . $h['id']) ?>"><?= e(fecha($h['inicio'])) ?></a><br><small><?= e($h['profesional']) ?></small></td>
              <td><?= e($h['servicio_nombre']) ?><?php if ($h['notas_sesion']): ?><br><small><?= e(mb_strimwidth($h['notas_sesion'], 0, 110, '…')) ?></small><?php endif; ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <?php if ($proximos): ?>
      <h3 class="mt">Próximos turnos</h3>
      <table class="tabla">
        <?php foreach ($proximos as $h): ?>
          <tr><td class="nowrap"><a href="<?= url('turno.php?id=' . $h['id']) ?>"><?= e(fecha($h['inicio'])) ?> <?= hora($h['inicio']) ?></a></td><td><?= e($h['servicio_nombre']) ?><br><small><?= e($h['profesional']) ?></small></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="modal-fondo" id="modal-cancelar">
  <form class="modal" method="post">
    <?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="estado" value="cancelado">
    <h2>Cancelar turno</h2>
    <p><?= e($cliTxt) ?>, <?= e($t['servicio_nombre']) ?>, <?= e(fecha_relativa($t['inicio'])) ?> a las <?= hora($t['inicio']) ?>. El horario queda libre.</p>
    <div class="campo"><label for="motivo">Motivo (opcional)</label><input type="text" id="motivo" name="motivo" maxlength="255" placeholder="Ej: avisó que está enferma"></div>
    <div class="fila"><button class="btn btn-peligro" type="submit">Cancelar turno</button><button class="btn" type="button" data-cerrar>Volver</button></div>
  </form>
</div>

<?php else: /* ---------- Formulario: nuevo o reprogramar ---------- */ ?>
<form method="post" class="grid-2-1" id="form-turno" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="accion" value="guardar">
  <div class="card">
    <?php if ($errores): ?>
      <div class="aviso aviso-error"><?= count($errores) === 1 ? e($errores[0]) : '<ul><li>' . implode('</li><li>', array_map('e', $errores)) . '</li></ul>' ?></div>
    <?php endif; ?>
    <?php if (!$errores && $avisos): ?>
      <div class="aviso aviso-alerta avisos-turno">
        <b>Revisá antes de guardar:</b>
        <ul><?php foreach ($avisos as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul>
        <label class="check mt"><input type="checkbox" name="forzar" value="1"> Entendido, guardar igual</label>
      </div>
    <?php endif; ?>

    <div class="campo" id="bloque-cliente">
      <label for="buscar-cliente">Cliente</label>
      <input type="hidden" name="cliente_id" id="cliente_id" value="<?= $clienteSel ? (int)$clienteSel['id'] : '' ?>">
      <div class="cliente-elegido" id="cliente-elegido"<?= $clienteSel ? '' : ' hidden' ?>>
        <b id="cliente-nombre"><?= $clienteSel ? e(nombre_cliente($clienteSel)) : '' ?></b>
        <small id="cliente-tel"><?= $clienteSel ? e($clienteSel['telefono']) : '' ?></small>
        <?php if (!$t): ?><button class="link" type="button" id="cambiar-cliente">Cambiar</button><?php endif; ?>
      </div>
      <div class="rel" id="buscador-cliente"<?= $clienteSel ? ' hidden' : '' ?>>
        <input type="search" id="buscar-cliente" placeholder="Buscá por nombre, apellido, teléfono o DNI" >
        <div class="sugerencias" id="sugerencias" hidden></div>
        <small><button class="link" type="button" id="btn-nueva">Es nueva: cargar sus datos</button></small>
      </div>
      <div id="cliente-nueva"<?= $f['cli_nombre'] !== '' && !$clienteSel ? '' : ' hidden' ?> class="mt">
        <div class="form-fila">
          <div class="campo"><label for="cli_nombre">Nombre</label><input type="text" id="cli_nombre" name="cli_nombre" value="<?= e($f['cli_nombre']) ?>" maxlength="80"></div>
          <div class="campo"><label for="cli_apellido">Apellido</label><input type="text" id="cli_apellido" name="cli_apellido" value="<?= e($f['cli_apellido']) ?>" maxlength="80"></div>
          <div class="campo"><label for="cli_telefono">Teléfono</label><input type="tel" id="cli_telefono" name="cli_telefono" value="<?= e($f['cli_telefono']) ?>" maxlength="40" placeholder="11 5555-1234"></div>
        </div>
        <small class="apagado">El resto de la ficha (DNI, antecedentes) se completa después desde Clientes.</small>
      </div>
    </div>

    <div class="form-fila fila-servicio">
      <div class="campo">
        <label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id">
          <option value="0">Todas</option>
          <?= opciones($categorias, $f['categoria_id']) ?>
        </select>
      </div>
      <div class="campo">
        <label for="servicio_id">Servicio</label>
        <select id="servicio_id" name="servicio_id" required>
          <option value="">Elegí el servicio</option>
          <?php $grupo = false; foreach ($servicios as $s):
              if ($s['categoria'] !== $grupo) { echo $grupo !== false ? '</optgroup>' : ''; $grupo = $s['categoria']; echo '<optgroup label="' . e($grupo ?: 'Sin categoría') . '" data-cat="' . (int)$s['categoria_id'] . '">'; } ?>
            <option value="<?= (int)$s['id'] ?>" data-cat="<?= (int)$s['categoria_id'] ?>" data-dur="<?= (int)$s['duracion'] ?>" data-precio="<?= (float)$s['precio'] ?>" data-cabina="<?= (int)$s['cabina_id'] ?>" data-profs="<?= e(implode(',', $s['profesionales'])) ?>"<?= (int)$s['id'] === $f['servicio_id'] ? ' selected' : '' ?>><?= e($s['nombre']) ?> · <?= duracion_txt($s['duracion']) ?> · <?= money($s['precio']) ?></option>
          <?php endforeach; echo $grupo !== false ? '</optgroup>' : ''; ?>
        </select>
      </div>
    </div>

    <div class="form-fila">
      <div class="campo">
        <label for="profesional_id">Profesional</label>
        <select id="profesional_id" name="profesional_id" required>
          <option value="auto" data-auto<?= $f['profesional_id'] === 'auto' ? ' selected' : '' ?>>Cualquier profesional libre</option>
          <?php foreach ($profs as $p): ?><option value="<?= (int)$p['id'] ?>" data-cats="<?= e(implode(',', categorias_de_profesional($p['id']))) ?>"<?= $f['profesional_id'] !== 'auto' && (int)$p['id'] === (int)$f['profesional_id'] ? ' selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="cabina_id">Cabina o equipo</label>
        <select id="cabina_id" name="cabina_id">
          <option value="">No ocupa ninguna</option>
          <?= opciones($cabinas, $f['cabina_id']) ?>
        </select>
      </div>
    </div>

    <div class="form-fila">
      <div class="campo"><label for="fecha">Fecha</label><input type="date" id="fecha" name="fecha" value="<?= e($f['fecha']) ?>" required></div>
      <div class="campo"><label for="hora">Hora</label><input type="time" id="hora" name="hora" value="<?= e($f['hora']) ?>" step="300" required></div>
      <div class="campo"><label for="duracion">Duración (min)</label><input type="number" id="duracion" name="duracion" value="<?= $f['duracion'] ?: '' ?>" min="5" max="600" step="5" required>
        <small id="termina"></small></div>
    </div>
    <div id="dia-prof" class="aviso aviso-info" hidden></div>

    <div class="form-fila">
      <div class="campo">
        <label for="precio">Precio $</label>
        <input type="text" inputmode="decimal" id="precio" name="precio" value="<?= e($f['precio']) ?>"<?= $t && $t['cobro_id'] ? ' disabled' : '' ?>>
        <?php if ($t && $t['cobro_id']): ?><small>Ya está cobrado: el precio no se cambia.</small><?php endif; ?>
      </div>
      <div class="campo" style="grid-column: span 2"><label for="notas">Aclaraciones</label><input type="text" id="notas" name="notas" value="<?= e($f['notas']) ?>" maxlength="255" placeholder="Ej: viene con su hija, trae sus productos"></div>
    </div>

    <?php if (!$t): ?>
      <div class="campo"><label class="check"><input type="checkbox" name="confirmado" value="1"<?= $f['confirmado'] ? ' checked' : '' ?>> Ya está confirmado</label></div>
    <?php endif; ?>

    <div class="fila">
      <button class="btn btn-pri btn-grande" type="submit"><?= $t ? 'Guardar cambios' : 'Agendar turno' ?></button>
      <?php if (!$t): ?><button class="btn" type="submit" name="otro" value="1" title="Guarda y abre otro turno para la misma persona a continuación">Agendar y sumar otro servicio</button><?php endif; ?>
      <a class="btn" href="<?= $t ? url('turno.php?id=' . $id) : url('agenda.php?fecha=' . e($f['fecha'])) ?>">Volver</a>
    </div>
  </div>

  <div class="card" id="panel-cliente"<?= $clienteSel ? '' : ' hidden' ?>>
    <h2>Ficha</h2>
    <div id="ficha-cliente">
      <?php if ($clienteSel && trim((string)$clienteSel['antecedentes']) !== ''): ?>
        <div class="antecedentes"><b>Antecedentes</b><?= e($clienteSel['antecedentes']) ?></div>
      <?php endif; ?>
    </div>
    <p class="apagado" id="ficha-resumen"></p>
  </div>
</form>
<?php endif; ?>

<?php layout_footer($editar ? ['assets/turno.js'] : []); ?>
