<?php
/*
 * Encabezado y pie comunes a todas las páginas.
 */

function menu_items()
{
    return [
        ['index.php',         'Inicio',                                   'inicio'],
        ['agenda.php',        es_profesional() ? 'Mi agenda' : 'Agenda',  'agenda'],
        ['clientes.php',      'Clientes',                                 'clientes'],
        ['caja.php',          'Caja',                                     'caja'],
        ['servicios.php',     'Servicios',                                'servicios'],
        ['profesionales.php', 'Profesionales',                            'profesionales'],
        ['ajustes.php',       'Ajustes',                                  'ajustes'],
        ['ayuda.php',         'Ayuda',                                    'ayuda'],
    ];
}

/** URL de un archivo de assets con su fecha de modificación, para que el navegador no use una copia vieja al actualizar */
function asset_url($ruta)
{
    $archivo = __DIR__ . '/../' . $ruta;
    return url($ruta) . '?v=' . (is_file($archivo) ? filemtime($archivo) : '1');
}

/** Botón "?" que abre la ayuda en el tema de la pantalla actual */
function ayuda_link()
{
    $script = basename($_SERVER['SCRIPT_NAME']);
    if (!puede('ayuda') || $script === 'ayuda.php' || http_response_code() === 403) {
        return '';
    }
    $prof = es_profesional();
    $mapa = [
        'index.php'            => $prof ? 'mi-agenda' : 'dia',
        'agenda.php'           => $prof ? 'mi-agenda' : 'agenda',
        'turno.php'            => $prof ? 'atender' : (get('id') && get('editar') === null ? 'estados' : 'dar-turno'),
        'clientes.php'         => $prof ? 'fichas-prof' : 'clientes',
        'cliente.php'          => $prof ? 'fichas-prof' : 'clientes',
        'cliente_form.php'     => 'clientes',
        'cobro.php'            => 'cobrar',
        'recibo.php'           => es_admin() ? 'anular' : 'cobrar',
        'caja.php'             => es_admin() ? 'caja-admin' : 'caja',
        'servicios.php'        => 'servicios',
        'servicio_form.php'    => 'servicios',
        'profesionales.php'    => 'profesionales',
        'profesional_form.php' => 'profesionales',
        'ajustes.php'          => 'ajustes',
        'usuarios.php'         => 'usuarios',
        'abm.php'              => get('t') === 'cabinas' ? 'cabinas' : (get('t') === 'categorias' ? 'categorias' : 'ajustes'),
    ];
    $ancla = isset($mapa[$script]) ? $mapa[$script] : '';
    return '<a class="btn-ayuda" href="' . url('ayuda.php') . ($ancla !== '' ? '#' . e($ancla) : '') . '" title="Ayuda de esta pantalla" aria-label="Ayuda de esta pantalla">?</a>';
}

/**
 * @param string $titulo
 * @param array  $op  ['activo' => 'agenda.php', 'clase' => 'pagina-agenda', 'sin_titulo' => true, 'acciones' => html]
 */
function layout_header($titulo, array $op = [])
{
    $u = usuario();
    $activo = isset($op['activo']) ? $op['activo'] : basename($_SERVER['SCRIPT_NAME']);
    $clase = isset($op['clase']) ? $op['clase'] : '';
    $centro = config_get('nombre_centro', 'Centro de Estética');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> | <?= e($centro) ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/app.css') ?>">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="base" content="<?= e(url()) ?>">
<script src="<?= asset_url('assets/app.js') ?>"></script>
</head>
<body class="<?= e($clase) ?>">
<?php if ($u): ?>
<div class="app">
  <aside class="lateral" id="lateral">
    <a class="marca" href="<?= url('index.php') ?>"><?= e($centro) ?></a>
    <nav class="menu">
      <?php foreach (menu_items() as $m):
          if (!puede($m[2])) continue;
          $on = ($activo === $m[0]) ? ' class="on" aria-current="page"' : ''; ?>
        <a href="<?= url($m[0]) ?>"<?= $on ?>><?= e($m[1]) ?><?php
          if ($m[0] === 'caja.php') {
              $n = count(turnos_sin_cobrar());
              if ($n > 0) echo ' <span class="cuenta" title="Turnos atendidos sin cobrar">' . $n . '</span>';
          } ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="usuario">
      <span><?= e($u['nombre']) ?><small><?= e(ROLES[$u['rol']]) ?></small></span>
      <a href="<?= url('logout.php') ?>">Salir</a>
    </div>
  </aside>
  <main class="contenido">
    <?php if (empty($op['sin_titulo'])): ?>
    <header class="barra">
      <button class="btn-menu" type="button" onclick="document.getElementById('lateral').classList.toggle('abierto')" aria-label="Menú">☰</button>
      <h1><?= e($titulo) ?></h1>
      <?= isset($op['acciones']) ? $op['acciones'] : '' ?>
      <?= estado_caja_pill() ?>
      <?= ayuda_link() ?>
    </header>
    <?php endif; ?>
    <?php foreach (flashes() as $f): ?>
      <div class="aviso aviso-<?= e($f['tipo']) ?>" role="status"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
<?php else: ?>
<main class="solo">
<?php endif;
}

function estado_caja_pill()
{
    if (!puede('caja')) {
        return '';
    }
    $c = caja_abierta();
    if ($c) {
        return '<a class="pill pill-ok" href="' . url('caja.php') . '">Caja abierta</a>';
    }
    return '<a class="pill pill-off" href="' . url('caja.php') . '">Caja cerrada</a>';
}

function layout_footer(array $scripts = [])
{
    if (usuario()) {
        echo "  </main>\n</div>\n";
    } else {
        echo "</main>\n";
    }
    foreach ($scripts as $s) {
        echo '<script src="' . asset_url($s) . '"></script>' . "\n";
    }
    echo "</body>\n</html>";
}
