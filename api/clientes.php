<?php
/*
 * Búsqueda de clientes para el formulario de turnos y cobros.
 * GET ?q=texto  -> lista
 * GET ?id=N     -> una clienta con su resumen
 */
require __DIR__ . '/../inc/bootstrap.php';
requiere('clientes');

$sel = "SELECT c.id, c.nombre, c.apellido, c.telefono, c.dni, c.antecedentes,
               (SELECT MAX(t.inicio) FROM turnos t WHERE t.cliente_id = c.id AND t.estado = 'atendido') AS ultimo,
               (SELECT COUNT(*) FROM turnos t WHERE t.cliente_id = c.id AND t.estado = 'atendido') AS sesiones
          FROM clientes c";

$fmt = function (array $c) {
    return [
        'id'           => (int)$c['id'],
        'nombre'       => nombre_cliente($c),
        'telefono'     => (string)$c['telefono'],
        'dni'          => (string)$c['dni'],
        'antecedentes' => trim((string)$c['antecedentes']),
        'resumen'      => $c['sesiones'] ? plural($c['sesiones'], 'sesión', 'sesiones') . ', la última el ' . fecha($c['ultimo']) : 'Todavía no vino al centro.',
    ];
};

$id = (int)get('id', 0);
if ($id) {
    if (!puede_ver_cliente($id)) {
        json_out(['ok' => false, 'error' => 'Sin acceso a esa ficha.'], 403);
    }
    $c = q_row($sel . ' WHERE c.id = ?', [$id]);
    json_out($c ? ['ok' => true, 'cliente' => $fmt($c)] : ['ok' => false, 'error' => 'No existe.']);
}

$q = trim((string)get('q', ''));
if (mb_strlen($q) < 2) {
    json_out(['ok' => true, 'clientes' => []]);
}
$where = ['c.activo = 1'];
$params = [];
foreach (preg_split('/\s+/', $q) as $palabra) {
    $tel = preg_replace('/\D/', '', $palabra);
    $cond = '(c.nombre LIKE ? OR c.apellido LIKE ?';
    array_push($params, "%$palabra%", "%$palabra%");
    if (strlen($tel) >= 3) {
        $cond .= " OR REPLACE(REPLACE(c.telefono, ' ', ''), '-', '') LIKE ? OR c.dni LIKE ?";
        array_push($params, "%$tel%", "%$tel%");
    }
    $where[] = $cond . ')';
}
$vis = profesionales_visibles();
if ($vis !== null) {
    $where[] = $vis ? 'EXISTS (SELECT 1 FROM turnos t WHERE t.cliente_id = c.id AND t.profesional_id IN (' . implode(',', $vis) . '))' : '0 = 1';
}
$filas = q_all($sel . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY c.apellido, c.nombre LIMIT 12', $params);
json_out(['ok' => true, 'clientes' => array_map($fmt, $filas)]);
