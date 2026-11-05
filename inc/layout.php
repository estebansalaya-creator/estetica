<?php
/*
 * Encabezado y pie comunes a todas las páginas.
 */

/** [archivo, texto, permiso, ícono, color del ícono] */
function menu_items()
{
    return [
        ['index.php',         'Inicio',                                   'inicio',        'inicio',        '#e7a6d6'],
        ['agenda.php',        es_profesional() ? 'Mi agenda' : 'Agenda',  'agenda',        'agenda',        '#6fd3bd'],
        ['clientes.php',      'Clientes',                                 'clientes',      'clientes',      '#8ab8f0'],
        ['caja.php',          'Caja',                                     'caja',          'caja',          '#f2c25c'],
        ['servicios.php',     'Servicios',                                'servicios',     'servicios',     '#f59ab8'],
        ['profesionales.php', 'Profesionales',                            'profesionales', 'profesionales', '#b9a3f5'],
        ['ajustes.php',       'Ajustes',                                  'ajustes',       'ajustes',       '#b3bccb'],
        ['ayuda.php',         'Ayuda',                                    'ayuda',         'ayuda',         '#9fdc9a'],
    ];
}

/** Íconos de línea (SVG, 24x24) dibujados con el color del texto */
function icono($nombre)
{
    static $trazos = [
        'inicio'        => '<path d="M3 10.2 12 3l9 7.2V20a1 1 0 0 1-1 1h-5v-6.5h-6V21H4a1 1 0 0 1-1-1z"/>',
        'agenda'        => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M8 2.5v4M16 2.5v4M3 10h18"/><path d="M7.5 14h.01M12 14h.01M16.5 14h.01M7.5 17.5h.01M12 17.5h.01"/>',
        'clientes'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20.5v-1a5.5 5.5 0 0 1 11 0v1"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a5.5 5.5 0 0 1 3.5 5.3v1"/>',
        'caja'          => '<rect x="2.5" y="6" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><path d="M16 14.5h2.5"/><path d="M5.5 6 15 3l1.2 3"/>',
        'servicios'     => '<path d="M11 3.5 12.7 8a2 2 0 0 0 1.2 1.2l4.6 1.8-4.6 1.8a2 2 0 0 0-1.2 1.2L11 18.5 9.3 14a2 2 0 0 0-1.2-1.2L3.5 11l4.6-1.8A2 2 0 0 0 9.3 8z"/><path d="M19 3v4M21 5h-4M18.5 17v3M20 18.5h-3"/>',
        'profesionales' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><circle cx="9" cy="11" r="2.3"/><path d="M5.5 16.5a3.6 3.6 0 0 1 7 0"/><path d="M15 10h3.5M15 13.5h3.5"/>',
        'ajustes'       => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'ayuda'         => '<circle cx="12" cy="12" r="9.5"/><path d="M9.3 9.2a2.8 2.8 0 0 1 5.4 1c0 1.9-2.7 2.5-2.7 4"/><path d="M12 17.6h.01"/>',
    ];
    if (!isset($trazos[$nombre])) {
        return '';
    }
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $trazos[$nombre] . '</svg>';
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
        <a href="<?= url($m[0]) ?>"<?= $on ?> style="--ic: <?= e($m[4]) ?>"><span class="mi"><?= icono($m[3]) ?></span><span class="m-txt"><?= e($m[1]) ?></span><?php
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
    <?php foreach (avisos_wa() as $a): $pn = explode(' ', trim($a['profesional']))[0]; ?>
      <div class="aviso aviso-wa" role="status">
        <span><?= e(aviso_wa_texto($a)) ?></span>
        <?php if ($a['link']): ?>
          <a class="btn btn-chico btn-wa" href="<?= e($a['link']) ?>" target="_blank" rel="noopener" onclick="var b=this; setTimeout(function(){ b.outerHTML='<small>Abierto en WhatsApp</small>'; }, 300)">Avisar a <?= e($pn) ?> por WhatsApp</a>
        <?php else: ?>
          <small><?= e($pn) ?> no tiene teléfono cargado. <?php if (puede('profesionales')): ?><a href="<?= url('profesional_form.php?id=' . $a['profesional_id']) ?>">Cargalo</a> para poder avisarle por WhatsApp.<?php endif; ?></small>
        <?php endif; ?>
      </div>
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
