<?php
/*
 * Quién de una categoría puede tomar un turno (para "Cualquier profesional libre").
 * GET ?categoria=N&servicio=N&fecha=Y-m-d&hora=HH:MM&dur=min&excluir=turno_id
 */
require __DIR__ . '/../inc/bootstrap.php';
requiere('turnos');

$cat = (int)get('categoria', 0);
$fecha = fecha_valida((string)get('fecha', ''));
$hora = hora_valida((string)get('hora', ''));
$dur = max(5, (int)get('dur', 0));
if (!$cat || !$fecha || !$hora || !(int)get('dur', 0)) {
    json_out(['ok' => false, 'error' => 'Faltan datos.'], 400);
}
$inicio = $fecha . ' ' . $hora . ':00';
$d = [
    'servicio_id' => (int)get('servicio', 0) ?: null,
    'inicio'      => $inicio,
    'fin'         => date('Y-m-d H:i:s', strtotime($inicio) + $dur * 60),
];
$libres = [];
$ocupados = [];
foreach (disponibilidad_categoria($cat, $d, (int)get('excluir', 0)) as $x) {
    if ($x['motivo'] === null) {
        $libres[] = $x['profesional']['nombre'];
    } else {
        $ocupados[] = ['nombre' => $x['profesional']['nombre'], 'motivo' => $x['motivo']];
    }
}
json_out([
    'ok'        => true,
    'categoria' => (string)q_val('SELECT nombre FROM categorias WHERE id = ?', [$cat]),
    'hora'      => $hora,
    'libres'    => $libres,
    'ocupados'  => $ocupados,
]);
