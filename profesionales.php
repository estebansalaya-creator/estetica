<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('profesionales');

$profs = q_all(
    'SELECT p.*,
            (SELECT COUNT(*) FROM servicio_profesional sp JOIN servicios s ON s.id = sp.servicio_id WHERE sp.profesional_id = p.id AND s.activo = 1) AS servicios,
            (SELECT GROUP_CONCAT(c.nombre ORDER BY c.orden SEPARATOR ", ") FROM profesional_categorias pc JOIN categorias c ON c.id = pc.categoria_id WHERE pc.profesional_id = p.id) AS categorias,
            (SELECT GROUP_CONCAT(u.usuario SEPARATOR ", ") FROM usuarios u WHERE u.profesional_id = p.id AND u.activo = 1) AS usuarios,
            (SELECT COUNT(*) FROM turnos t WHERE t.profesional_id = p.id AND t.inicio >= CURDATE() AND t.estado IN ("reservado","confirmado")) AS proximos
       FROM profesionales p ORDER BY p.activo DESC, p.orden, p.nombre'
);
$horarios = [];
foreach (q_all('SELECT * FROM profesional_horarios ORDER BY dia, desde') as $h) {
    $horarios[(int)$h['profesional_id']][(int)$h['dia']][] = substr($h['desde'], 0, 5) . '–' . substr($h['hasta'], 0, 5);
}

/** "lun a vie 09:00–13:00 y 15:00–19:00 · sáb 09:00–13:00" agrupando días iguales seguidos */
function resumen_horario(array $porDia)
{
    $grupos = [];
    foreach (range(1, 7) as $d) {
        $txt = isset($porDia[$d]) ? implode(' y ', $porDia[$d]) : null;
        $ult = end($grupos);
        if ($txt !== null && $ult && $ult['txt'] === $txt && $ult['hasta'] === $d - 1) {
            $grupos[key($grupos)]['hasta'] = $d;
        } elseif ($txt !== null) {
            $grupos[] = ['desde' => $d, 'hasta' => $d, 'txt' => $txt];
        }
    }
    return implode(' · ', array_map(function ($g) {
        $dias = DIAS_CORTOS[$g['desde']] . ($g['hasta'] > $g['desde'] ? ($g['hasta'] - $g['desde'] === 1 ? ' y ' : ' a ') . DIAS_CORTOS[$g['hasta']] : '');
        return $dias . ' ' . $g['txt'];
    }, $grupos));
}

layout_header('Profesionales', ['acciones' => '<a class="btn btn-pri" href="' . url('profesional_form.php') . '">Nuevo profesional</a>']);
?>
<div class="card">
<?php if (!$profs): ?>
  <div class="vacio">Cargá a quienes atienden en el centro, con sus horarios y los servicios que hacen. Cada uno tiene su columna en la agenda.</div>
<?php else: ?>
  <div class="tabla-scroll">
  <table class="tabla">
    <thead><tr><th>Profesional</th><th>Categorías</th><th>Horario</th><th class="der">Servicios</th><th class="der">Turnos próximos</th><th>Usuario</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($profs as $p): ?>
      <tr class="<?= $p['activo'] ? '' : 'inactivo' ?>">
        <td><span class="punto" style="--c: <?= e($p['color']) ?>"></span><a href="<?= url('profesional_form.php?id=' . $p['id']) ?>"><?= e($p['nombre']) ?></a><?= $p['activo'] ? '' : ' <small>(inactivo)</small>' ?>
          <?php if ($p['especialidad']): ?><br><small><?= e($p['especialidad']) ?></small><?php endif; ?></td>
        <td><small><?= $p['categorias'] ? e($p['categorias']) : '<span style="color:var(--coral)">Sin categoría</span>' ?></small></td>
        <td><small><?= isset($horarios[(int)$p['id']]) ? e(resumen_horario($horarios[(int)$p['id']])) : '<span style="color:var(--coral)">Sin horario cargado</span>' ?></small></td>
        <td class="der"><?= (int)$p['servicios'] ?></td>
        <td class="der"><?= (int)$p['proximos'] ?></td>
        <td><small><?= e($p['usuarios']) ?></small></td>
        <td class="acciones">
          <a class="btn btn-chico" href="<?= url('agenda.php?vista=semana&prof=' . $p['id']) ?>">Agenda</a>
          <a class="btn btn-chico" href="<?= url('profesional_form.php?id=' . $p['id']) ?>">Editar</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
</div>
<p class="apagado">Para que un profesional entre al sistema y vea su propia agenda, creale un usuario con el rol «Profesional» en <a href="<?= url('usuarios.php') ?>">Ajustes → Usuarios</a>.</p>
<?php layout_footer(); ?>
