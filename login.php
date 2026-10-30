<?php
require __DIR__ . '/inc/bootstrap.php';

$destino = function () {
    return url(es_profesional() ? 'agenda.php' : 'index.php');
};
if (usuario()) {
    redirect($destino());
}

$error = '';
if (es_post()) {
    csrf_check();
    if (login(post('usuario', ''), post('password', ''))) {
        redirect($destino());
    }
    $error = 'Usuario o contraseña incorrectos.';
}

layout_header('Ingresar', ['clase' => 'pagina-login']);
?>
<form method="post" class="login">
  <?= csrf_field() ?>
  <h1><?= e(config_get('nombre_centro', 'Centro de Estética')) ?></h1>
  <p>Ingresá con tu usuario para ver la agenda.</p>
  <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif; ?>
  <div class="campo">
    <label for="usuario">Usuario</label>
    <input type="text" id="usuario" name="usuario" value="<?= e(post('usuario', '')) ?>" autocomplete="username" autocapitalize="none" autofocus required>
  </div>
  <div class="campo">
    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-pri btn-grande btn-bloque" type="submit">Ingresar</button>
</form>
<?php layout_footer(); ?>
