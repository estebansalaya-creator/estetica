<?php
/*
 * Turnos y agenda: estados, horarios de los profesionales, superposiciones.
 */

const ESTADOS_TURNO = [
    'reservado'   => 'Reservado',
    'confirmado'  => 'Confirmado',
    'en_atencion' => 'En atención',
    'atendido'    => 'Atendido',
    'ausente'     => 'Ausente',
    'cancelado'   => 'Cancelado',
];

/** Estados que ocupan lugar en la agenda (ausente y cancelado liberan el horario) */
const ESTADOS_OCUPAN = ['reservado', 'confirmado', 'en_atencion', 'atendido'];

/** Estados que puede marcar un profesional en sus propios turnos */
const ESTADOS_PROFESIONAL = ['en_atencion', 'atendido', 'ausente'];

function estado_turno_txt($e)
{
    return isset(ESTADOS_TURNO[$e]) ? ESTADOS_TURNO[$e] : $e;
}

function badge_turno($e)
{
    return '<span class="badge est-' . e($e) . '">' . e(estado_turno_txt($e)) . '</span>';
}

function profesionales_activos()
{
    static $p = null;
    if ($p === null) {
        $p = q_all('SELECT * FROM profesionales WHERE activo = 1 ORDER BY orden, nombre');
    }
    return $p;
}

/** Categorías activas (las mismas para servicios y profesionales) */
function categorias_activas()
{
    static $c = null;
    if ($c === null) {
        $c = q_all('SELECT * FROM categorias WHERE activo = 1 ORDER BY orden, nombre');
    }
    return $c;
}

/** [profesional_id => [categoria_id, ...]] */
function mapa_categorias_profesionales()
{
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (q_all('SELECT profesional_id, categoria_id FROM profesional_categorias') as $r) {
            $m[(int)$r['profesional_id']][] = (int)$r['categoria_id'];
        }
    }
    return $m;
}

function categorias_de_profesional($profId)
{
    $m = mapa_categorias_profesionales();
    return isset($m[(int)$profId]) ? $m[(int)$profId] : [];
}

/** Profesionales activos de una categoría (en el orden de la agenda) */
function profesionales_de_categoria($catId)
{
    return array_values(array_filter(profesionales_activos(), function ($p) use ($catId) {
        return in_array((int)$catId, categorias_de_profesional($p['id']), true);
    }));
}

/** El profesional y quienes comparten con él alguna categoría */
function companeros_ids($profId)
{
    $mias = categorias_de_profesional($profId);
    $ids = [(int)$profId];
    foreach (mapa_categorias_profesionales() as $pid => $cats) {
        if (array_intersect($mias, $cats)) {
            $ids[] = (int)$pid;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Primer profesional de la categoría libre para el turno $d (servicio_id, cabina_id, inicio, fin):
 * hace el servicio, trabaja en ese horario y no tiene otro turno.
 * @return array ['profesional' => fila o null, 'motivos' => [nombre => por qué no]]
 */
function profesional_libre($catId, array $d, $excluirId = 0)
{
    $motivos = [];
    foreach (disponibilidad_categoria($catId, $d, $excluirId) as $x) {
        if ($x['motivo'] === null) {
            return ['profesional' => $x['profesional'], 'motivos' => $motivos];
        }
        $motivos[$x['profesional']['nombre']] = $x['motivo'];
    }
    return ['profesional' => null, 'motivos' => $motivos];
}

/** Cada profesional de la categoría con el motivo por el que no puede tomar el turno (null = está libre) */
function disponibilidad_categoria($catId, array $d, $excluirId = 0)
{
    $hacen = [];
    if (!empty($d['servicio_id'])) {
        $hacen = array_map('intval', array_column(q_all('SELECT profesional_id FROM servicio_profesional WHERE servicio_id = ?', [$d['servicio_id']]), 'profesional_id'));
    }
    $res = [];
    foreach (profesionales_de_categoria($catId) as $p) {
        $motivo = null;
        if ($hacen && !in_array((int)$p['id'], $hacen, true)) {
            $motivo = 'no hace ese servicio';
        } elseif (!en_horario($p['id'], $d['inicio'], $d['fin'])) {
            $motivo = 'no trabaja en ese horario';
        } elseif (superposiciones('profesional_id', $p['id'], $d['inicio'], $d['fin'], $excluirId)) {
            $motivo = 'tiene otro turno';
        }
        $res[] = ['profesional' => $p, 'motivo' => $motivo];
    }
    return $res;
}

/**
 * Franjas de trabajo de un profesional en una fecha, en minutos desde las 00:00.
 * @return array [[desde, hasta], ...]
 */
function horario_dia($profId, $fecha)
{
    static $todos = null;
    if ($todos === null) {
        $todos = [];
        foreach (q_all('SELECT profesional_id, dia, desde, hasta FROM profesional_horarios ORDER BY desde') as $h) {
            $todos[(int)$h['profesional_id']][(int)$h['dia']][] = [minutos($h['desde']), minutos($h['hasta'])];
        }
    }
    $dia = (int)date('N', strtotime($fecha));
    return isset($todos[(int)$profId][$dia]) ? $todos[(int)$profId][$dia] : [];
}

/** true si el turno entra completo dentro de una franja de trabajo del profesional */
function en_horario($profId, $inicio, $fin)
{
    $fecha = substr($inicio, 0, 10);
    if (substr($fin, 0, 10) !== $fecha && substr($fin, 11, 5) !== '00:00') {
        return false;
    }
    $a = minutos(substr($inicio, 11, 5));
    $b = substr($fin, 0, 10) === $fecha ? minutos(substr($fin, 11, 5)) : 1440;
    foreach (horario_dia($profId, $fecha) as $f) {
        if ($a >= $f[0] && $b <= $f[1]) {
            return true;
        }
    }
    return false;
}

/** Turnos activos que se pisan con [inicio, fin) según profesional_id, cabina_id o cliente_id */
function superposiciones($campo, $valor, $inicio, $fin, $excluirId = 0)
{
    if (!in_array($campo, ['profesional_id', 'cabina_id', 'cliente_id'], true) || !$valor) {
        return [];
    }
    $in = "'" . implode("','", ESTADOS_OCUPAN) . "'";
    return q_all(
        "SELECT t.*, c.nombre, c.apellido, p.nombre AS profesional
           FROM turnos t JOIN clientes c ON c.id = t.cliente_id JOIN profesionales p ON p.id = t.profesional_id
          WHERE t.$campo = ? AND t.estado IN ($in) AND t.inicio < ? AND t.fin > ? AND t.id <> ?
          ORDER BY t.inicio",
        [$valor, $fin, $inicio, (int)$excluirId]
    );
}

/**
 * Valida un turno antes de guardarlo.
 * $d: cliente_id, profesional_id, servicio_id, cabina_id, inicio, fin (Y-m-d H:i:s)
 * Devuelve errores (no se puede guardar) y avisos (se puede guardar confirmando).
 */
function validar_turno(array $d, $excluirId = 0)
{
    $err = [];
    $av = [];
    if (empty($d['cliente_id'])) {
        $err[] = 'Elegí la clienta o el cliente.';
    }
    if (empty($d['profesional_id'])) {
        $err[] = 'Elegí el profesional.';
    }
    if (empty($d['servicio_nombre'])) {
        $err[] = 'Elegí el servicio.';
    }
    if (empty($d['inicio']) || empty($d['fin']) || $d['fin'] <= $d['inicio']) {
        $err[] = 'Revisá la fecha, la hora y la duración.';
    }
    if ($err) {
        return ['errores' => $err, 'avisos' => []];
    }

    if (!empty($d['cabina_id'])) {
        foreach (superposiciones('cabina_id', $d['cabina_id'], $d['inicio'], $d['fin'], $excluirId) as $o) {
            $cab = q_val('SELECT nombre FROM cabinas WHERE id = ?', [$d['cabina_id']]);
            $err[] = '«' . $cab . '» ya está en uso de ' . hora($o['inicio']) . ' a ' . hora($o['fin']) . ' (' . nombre_cliente($o) . ' con ' . $o['profesional'] . ').';
        }
    }
    foreach (superposiciones('profesional_id', $d['profesional_id'], $d['inicio'], $d['fin'], $excluirId) as $o) {
        $av[] = 'Se superpone con otro turno del profesional: ' . hora($o['inicio']) . ' a ' . hora($o['fin']) . ', ' . nombre_cliente($o) . ' (' . $o['servicio_nombre'] . ').';
    }
    foreach (superposiciones('cliente_id', $d['cliente_id'], $d['inicio'], $d['fin'], $excluirId) as $o) {
        if ((int)$o['profesional_id'] !== (int)$d['profesional_id']) {
            $av[] = 'Ese horario ya tiene otro turno a su nombre: ' . hora($o['inicio']) . ' con ' . $o['profesional'] . ' (' . $o['servicio_nombre'] . ').';
        }
    }
    if (!en_horario($d['profesional_id'], $d['inicio'], $d['fin'])) {
        $franjas = horario_dia($d['profesional_id'], substr($d['inicio'], 0, 10));
        $txt = $franjas ? 'ese día trabaja ' . implode(' y ', array_map(function ($f) { return hhmm($f[0]) . ' a ' . hhmm($f[1]); }, $franjas)) : 'ese día no trabaja';
        $av[] = 'Queda fuera del horario del profesional (' . $txt . ').';
    }
    if (!empty($d['servicio_id'])) {
        $hace = q_val('SELECT COUNT(*) FROM servicio_profesional WHERE servicio_id = ? AND profesional_id = ?', [$d['servicio_id'], $d['profesional_id']]);
        $alguno = q_val('SELECT COUNT(*) FROM servicio_profesional WHERE servicio_id = ?', [$d['servicio_id']]);
        if ($alguno && !$hace) {
            $av[] = 'Este profesional no tiene cargado ese servicio entre los que realiza.';
        }
    }
    return ['errores' => $err, 'avisos' => $av];
}

/**
 * Reparte en carriles los turnos que se superponen en una misma columna de la agenda,
 * para dibujarlos uno al lado del otro. Devuelve [turno_id => [carril, total_de_carriles]].
 */
function carriles_agenda(array $turnos)
{
    $res = [];
    $grupo = [];
    $finesCarril = [];
    $finGrupo = 0;
    $cerrar = function () use (&$res, &$grupo, &$finesCarril, &$finGrupo) {
        foreach ($grupo as $id => $c) {
            $res[$id] = [$c, count($finesCarril)];
        }
        $grupo = [];
        $finesCarril = [];
        $finGrupo = 0;
    };
    usort($turnos, function ($a, $b) { return strcmp($a['inicio'], $b['inicio']); });
    foreach ($turnos as $t) {
        $a = strtotime($t['inicio']);
        $b = strtotime($t['fin']);
        if ($grupo && $a >= $finGrupo) {
            $cerrar();
        }
        $carril = null;
        foreach ($finesCarril as $i => $f) {
            if ($f <= $a) {
                $carril = $i;
                break;
            }
        }
        if ($carril === null) {
            $carril = count($finesCarril);
        }
        $finesCarril[$carril] = $b;
        $grupo[(int)$t['id']] = $carril;
        $finGrupo = max($finGrupo, $b);
    }
    $cerrar();
    return $res;
}

/** Turnos de un rango de fechas, con datos del cliente, para la agenda */
function turnos_rango($desde, $hasta, array $profIds = [])
{
    $params = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
    $filtro = '';
    if ($profIds) {
        $filtro = ' AND t.profesional_id IN (' . implode(',', array_map('intval', $profIds)) . ')';
    }
    return q_all(
        "SELECT t.*, c.nombre, c.apellido, c.telefono, (c.antecedentes IS NOT NULL AND c.antecedentes <> '') AS con_antecedentes,
                cb.nombre AS cabina
           FROM turnos t
           JOIN clientes c ON c.id = t.cliente_id
           LEFT JOIN cabinas cb ON cb.id = t.cabina_id
          WHERE t.inicio BETWEEN ? AND ? AND t.estado <> 'cancelado' $filtro
          ORDER BY t.inicio",
        $params
    );
}

/** Datos completos de un turno */
function turno_get($id)
{
    return q_row(
        "SELECT t.*, c.nombre, c.apellido, c.telefono, c.email, c.antecedentes, c.dni,
                p.nombre AS profesional, p.color, p.telefono AS profesional_telefono, cb.nombre AS cabina, u.nombre AS creado_por
           FROM turnos t
           JOIN clientes c ON c.id = t.cliente_id
           JOIN profesionales p ON p.id = t.profesional_id
           LEFT JOIN cabinas cb ON cb.id = t.cabina_id
           LEFT JOIN usuarios u ON u.id = t.usuario_id
          WHERE t.id = ?",
        [(int)$id]
    );
}

/** Mensaje de recordatorio a partir de la plantilla de Ajustes */
function mensaje_recordatorio(array $t)
{
    $tpl = config_get('msj_recordatorio', 'Hola {nombre}! Te recordamos tu turno de {servicio} el {dia} a las {hora} hs.');
    return strtr($tpl, [
        '{nombre}'      => $t['nombre'],
        '{servicio}'    => $t['servicio_nombre'],
        '{dia}'         => fecha_relativa($t['inicio']) === 'mañana' ? 'mañana ' . fecha_larga($t['inicio']) : fecha_larga($t['inicio']),
        '{hora}'        => hora($t['inicio']),
        '{profesional}' => isset($t['profesional']) ? $t['profesional'] : '',
        '{centro}'      => config_get('nombre_centro', ''),
        '{direccion}'   => config_get('direccion', ''),
    ]);
}

/* ---------- Aviso al profesional por WhatsApp ----------
 * Al dar, reprogramar o cancelar un turno queda un botón en la pantalla siguiente
 * que abre WhatsApp con el mensaje armado para el profesional (se envía a mano, como los recordatorios).
 */

/** "martes 5 de noviembre, 15:00 a 16:00 hs" */
function aviso_cuando(array $t)
{
    return fecha_larga($t['inicio']) . ', ' . hora($t['inicio']) . ' a ' . hora($t['fin']) . ' hs';
}

/** Qué cambió entre dos versiones del turno que le importe al profesional */
function turno_cambios(array $a, array $b)
{
    $c = [];
    if ($a['inicio'] !== $b['inicio'] || $a['fin'] !== $b['fin']) {
        $c[] = 'día y hora';
    }
    if ((string)$a['servicio_nombre'] !== (string)$b['servicio_nombre']) {
        $c[] = 'servicio';
    }
    if ((int)$a['cliente_id'] !== (int)$b['cliente_id']) {
        $c[] = 'cliente';
    }
    if ((int)$a['cabina_id'] !== (int)$b['cabina_id']) {
        $c[] = 'cabina';
    }
    if (trim((string)$a['notas']) !== trim((string)$b['notas'])) {
        $c[] = 'aclaraciones';
    }
    return $c;
}

/**
 * Texto del aviso. $tipo: nuevo | cambio | cancelado | pasado (lo atiende otro) | resumen
 * $t es el turno como quedó (turno_get) y $antes, el turno antes del cambio.
 */
function mensaje_aviso_profesional($tipo, array $t, $nombreProf, array $antes = null)
{
    $hola = 'Hola ' . explode(' ', trim($nombreProf))[0] . '!';
    $cli = nombre_cliente($t);
    $centro = config_get('nombre_centro', '');
    $firma = $centro !== '' ? "\n— " . $centro : '';

    if ($tipo === 'cancelado') {
        $m = $hola . ' Se canceló el turno de ' . $cli . ' del ' . aviso_cuando($t) . ' (' . $t['servicio_nombre'] . ').';
        if (trim((string)$t['motivo_cancelado']) !== '') {
            $m .= "\nMotivo: " . trim($t['motivo_cancelado']);
        }
        return $m . "\nEse horario te quedó libre." . $firma;
    }
    if ($tipo === 'pasado' && $antes) {
        return $hola . ' El turno de ' . nombre_cliente($antes) . ' del ' . aviso_cuando($antes) . ' (' . $antes['servicio_nombre'] . ')'
            . ' ahora lo atiende ' . $t['profesional'] . '. Ya no está en tu agenda.' . $firma;
    }

    $cambios = ($tipo === 'cambio' && $antes) ? turno_cambios($antes, $t) : [];
    $l = [];
    if ($tipo === 'nuevo') {
        $l[] = $hola . ' Tenés un turno nuevo:';
    } elseif ($tipo === 'cambio') {
        $l[] = $hola . ' Se modificó un turno tuyo' . ($cambios ? ' (cambió ' . implode(', ', $cambios) . ')' : '') . ':';
    } else {
        $l[] = $hola . ' Te paso los datos de este turno:';
    }
    if (in_array('día y hora', $cambios, true)) {
        $l[] = 'Antes: ' . aviso_cuando($antes);
        $l[] = '*Ahora: ' . aviso_cuando($t) . '*';
    } else {
        $l[] = '*' . ucfirst(aviso_cuando($t)) . '*';
    }
    $l[] = 'Cliente: ' . $cli;
    $l[] = 'Servicio: ' . $t['servicio_nombre'];
    if (!empty($t['cabina'])) {
        $l[] = 'Cabina: ' . $t['cabina'];
    }
    if (trim((string)$t['notas']) !== '') {
        $l[] = 'Aclaraciones: ' . trim($t['notas']);
    } elseif (in_array('aclaraciones', $cambios, true)) {
        $l[] = 'Aclaraciones: se borraron.';
    }
    if (trim((string)$t['antecedentes']) !== '') {
        $l[] = 'Tiene antecedentes en la ficha: revisalos antes de atender.';
    }
    return implode("\n", $l) . $firma;
}

/**
 * Deja el botón de aviso para la próxima pantalla. No avisa turnos que ya terminaron
 * ni al profesional que hizo el cambio él mismo.
 */
function aviso_profesional($tipo, array $t, array $antes = null)
{
    $ref = ($tipo === 'pasado' && $antes) ? $antes : $t;
    if (strtotime($ref['fin']) < time()) {
        return;
    }
    if (es_profesional() && (int)mi_profesional_id() === (int)$ref['profesional_id']) {
        return;
    }
    $_SESSION['avisos_wa'][] = [
        'tipo'           => $tipo,
        'profesional'    => $ref['profesional'],
        'profesional_id' => (int)$ref['profesional_id'],
        'cliente'        => nombre_cliente($t),
        'link'           => whatsapp_link($ref['profesional_telefono'], mensaje_aviso_profesional($tipo, $t, $ref['profesional'], $antes)),
    ];
    $_SESSION['avisos_wa'] = array_slice($_SESSION['avisos_wa'], -6);
}

/** Después de editar un turno: avisa si cambió algo (a los dos, si pasó a otro profesional) */
function avisar_cambios_turno(array $antes, $id)
{
    if (!in_array($antes['estado'], ['reservado', 'confirmado', 'en_atencion'], true)) {
        return;
    }
    $t = turno_get($id);
    if (!$t) {
        return;
    }
    if ((int)$t['profesional_id'] !== (int)$antes['profesional_id']) {
        aviso_profesional('pasado', $t, $antes);
        aviso_profesional('nuevo', $t);
    } elseif (turno_cambios($antes, $t)) {
        aviso_profesional('cambio', $t, $antes);
    }
}

/** Avisos pendientes de mostrar (se muestran una sola vez) */
function avisos_wa()
{
    $a = isset($_SESSION['avisos_wa']) ? $_SESSION['avisos_wa'] : [];
    unset($_SESSION['avisos_wa']);
    return $a;
}

/** Texto del cartel que acompaña al botón */
function aviso_wa_texto(array $a)
{
    switch ($a['tipo']) {
        case 'nuevo':     return 'Turno nuevo de ' . $a['cliente'] . ' con ' . $a['profesional'] . '.';
        case 'cambio':    return 'Cambió el turno de ' . $a['cliente'] . ' con ' . $a['profesional'] . '.';
        case 'cancelado': return 'Se canceló el turno de ' . $a['cliente'] . ' con ' . $a['profesional'] . '.';
        case 'pasado':    return 'El turno de ' . $a['cliente'] . ' ya no es de ' . $a['profesional'] . '.';
    }
    return '';
}

/** Servicios activos agrupados por categoría, con los profesionales que los hacen (para el formulario de turno) */
function servicios_para_turno()
{
    $serv = q_all(
        'SELECT s.*, c.nombre AS categoria FROM servicios s LEFT JOIN categorias c ON c.id = s.categoria_id
          WHERE s.activo = 1 ORDER BY c.orden IS NULL, c.orden, c.nombre, s.orden, s.nombre'
    );
    $rel = [];
    foreach (q_all('SELECT servicio_id, profesional_id FROM servicio_profesional') as $r) {
        $rel[(int)$r['servicio_id']][] = (int)$r['profesional_id'];
    }
    foreach ($serv as &$s) {
        $s['profesionales'] = isset($rel[(int)$s['id']]) ? $rel[(int)$s['id']] : [];
    }
    return $serv;
}
