<?php
require __DIR__ . '/inc/bootstrap.php';
requiere('clientes');

$buscar = trim((string)get('buscar', ''));
$params = [];
$where = ['c.activo = 1'];
if ($buscar !== '') {
    foreach (preg_split('/\s+/', $buscar) as $palabra) {
        $tel = preg_replace('/\D/', '', $palabra);
        $cond = '(c.nombre LIKE ? OR c.apellido LIKE ? OR c.email LIKE ?';
        array_push($params, "%$palabra%", "%$palabra%", "%$palabra%");
        if (strlen($tel) >= 3) {
            $cond .= " OR REPLACE(REPLACE(c.telefono, ' ', ''), '-', '') LIKE ? OR c.dni LIKE ?";
            array_push($params, "%$tel%", "%$tel%");
        }
        $where[] = $cond . ')';
    }
}
$vis = profesionales_visibles();
if ($vis !== null) {
    // Profesional: personas con turnos suyos o de su categoría
    $where[] = $vis ? 'EXISTS (SELECT 1 FROM turnos tp WHERE tp.cliente_id = c.id AND tp.profesional_id IN (' . implode(',', $vis) . '))' : '0 = 1';
}
$clientes = q_all(
    "SELECT c.*,
            (SELECT COUNT(*) FROM turnos t WHERE t.cliente_id = c.id AND t.estado = 'atendido') AS sesiones,
            (SELECT MAX(t.inicio) FROM turnos t WHERE t.cliente_id = c.id AND t.estado = 'atendido') AS ultimo,
            (SELECT MIN(t.inicio) FROM turnos t WHERE t.cliente_id = c.id AND t.estado IN ('reservado','confirmado') AND t.inicio >= NOW()) AS proximo
       FROM clientes c
      WHERE " . implode(' AND ', $where) . "
      ORDER BY c.apellido, c.nombre LIMIT 400",
    $params
);

layout_header('Clientes');
?>
<form class="filtros" method="get">
  <div class="campo" style="flex:1; max-width:420px"><label for="buscar">Buscar</label><input type="search" id="buscar" name="buscar" value="<?= e($buscar) ?>" placeholder="Nombre, apellido, teléfono, DNI o email" autofocus></div>
  <button class="btn" type="submit">Buscar</button>
  <span class="espacio"></span>
  <?php if (puede('clientes_editar')): ?><a class="btn btn-pri" href="<?= url('cliente_form.php') ?>">Nueva clienta o cliente</a><?php endif; ?>
</form>
<div class="card">
<?php if (!$clientes): ?>
  <div class="vacio"><?= $buscar !== '' ? 'No hay fichas que coincidan con «' . e($buscar) . '».' : (es_profesional() ? 'Acá vas a ver las fichas de las personas que atiendas vos o tu categoría.' : 'Todavía no hay fichas. Se crean solas al dar un turno a alguien nuevo, o con «Nueva clienta o cliente».') ?></div>
<?php else: ?>
  <div class="tabla-scroll">
  <table class="tabla">
    <thead><tr><th>Nombre</th><th>Teléfono</th><th>DNI</th><th class="der">Sesiones</th><th>Última visita</th><th>Próximo turno</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($clientes as $c): ?>
      <tr>
        <td><a href="<?= url('cliente.php?id=' . $c['id']) ?>"><b><?= e($c['apellido']) ?></b><?= $c['apellido'] !== '' ? ', ' : '' ?><?= e($c['nombre']) ?></a>
          <?php if (trim((string)$c['antecedentes']) !== ''): ?> <span class="t-marca m-alerta" title="<?= e($c['antecedentes']) ?>">!</span><?php endif; ?></td>
        <td class="nowrap"><?= e($c['telefono']) ?></td>
        <td><?= e($c['dni']) ?></td>
        <td class="der"><?= (int)$c['sesiones'] ?: '' ?></td>
        <td><?= e(fecha($c['ultimo'])) ?></td>
        <td><?= $c['proximo'] ? e(fecha($c['proximo'])) . ' ' . hora($c['proximo']) : '' ?></td>
        <td class="acciones"><?php if (puede('turnos')): ?><a class="btn btn-chico" href="<?= url('turno.php?cliente=' . $c['id']) ?>">Dar turno</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if (count($clientes) === 400): ?><p class="apagado mt">Se muestran las primeras 400. Usá el buscador para encontrar a alguien.</p><?php endif; ?>
<?php endif; ?>
</div>
<?php layout_footer(); ?>
