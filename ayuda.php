<?php
/*
 * Manual de uso. Cada rol ve sólo las secciones que le corresponden
 * (el filtro es del lado del servidor). El botón "?" de cada pantalla abre el tema que corresponde.
 */
require __DIR__ . '/inc/bootstrap.php';
requiere('ayuda');

$rol = usuario()['rol'];
$ver = [
    'recepcion'   => in_array($rol, ['admin', 'recepcion'], true),
    'profesional' => true,
    'admin'       => $rol === 'admin',
    'tecnico'     => $rol === 'admin',
];

layout_header('Ayuda');
?>
<div class="ayuda">
  <aside class="ayuda-indice" aria-label="Índice">
    <input type="search" id="ayuda-buscar" placeholder="Buscar en la ayuda" autocomplete="off">
    <p class="apagado" id="ayuda-sin" hidden>No hay temas con esas palabras.</p>
    <details id="ayuda-nav" open>
    <summary>Índice</summary>
    <nav>
      <?php if ($ver['recepcion']): ?>
        <strong>Recepción</strong>
        <a href="#dia">Empezar el día</a>
        <a href="#agenda">La agenda</a>
        <a href="#dar-turno">Dar un turno</a>
        <a href="#cualquiera">Turno a cualquier profesional libre</a>
        <a href="#estados">Estados del turno</a>
        <a href="#reprogramar">Reprogramar o cancelar</a>
        <a href="#recordatorios">Recordatorios por WhatsApp</a>
        <a href="#aviso-profesional">Avisar al profesional</a>
        <a href="#clientes">Fichas de clientes</a>
        <a href="#cobrar">Cobrar</a>
        <a href="#cobro-sin-turno">Cobro sin turno</a>
        <a href="#caja">Caja: movimientos y cierre</a>
        <a href="#celular">Usar desde otra PC o el celular</a>
        <a href="#dudas">Dudas frecuentes</a>
      <?php endif; ?>
      <strong>Profesionales</strong>
      <a href="#mi-agenda">Mi agenda</a>
      <a href="#atender">Atender un turno</a>
      <a href="#fichas-prof">Fichas y antecedentes</a>
      <?php if ($ver['admin']): ?>
        <strong>Administrador</strong>
        <a href="#servicios">Servicios</a>
        <a href="#precios">Actualizar precios</a>
        <a href="#categorias">Categorías</a>
        <a href="#profesionales">Profesionales y horarios</a>
        <a href="#cabinas">Cabinas y equipos</a>
        <a href="#anular">Anular un cobro</a>
        <a href="#caja-admin">Control de cajas</a>
        <a href="#ajustes">Ajustes</a>
        <a href="#usuarios">Usuarios y permisos</a>
      <?php endif; ?>
      <?php if ($ver['tecnico']): ?>
        <strong>Técnico</strong>
        <a href="#tec-estructura">Cómo está armada</a>
        <a href="#tec-iis">Servidor local (IIS)</a>
        <a href="#tec-red">Acceso desde la red</a>
        <a href="#tec-railway">GitHub y Railway</a>
        <a href="#tec-actualizar">Actualizar la app</a>
        <a href="#tec-backup">Copias de seguridad</a>
        <a href="#tec-problemas">Problemas comunes</a>
      <?php endif; ?>
    </nav>
    </details>
    <button class="btn btn-chico no-print mt" type="button" onclick="window.print()">Imprimir el manual</button>
  </aside>

  <div class="ayuda-contenido">

<?php if ($ver['recepcion']): ?>
    <section class="ayuda-seccion">
      <h2>Recepción</h2>

      <article class="tema card" id="dia">
        <h3>Empezar el día</h3>
        <ol>
          <li>Entrá a <b>Caja</b>, contá el efectivo del cajón y escribilo en <b>Efectivo inicial</b>. El sistema sugiere lo que se contó al cerrar la caja anterior. Tocá <b>Abrir caja</b>.</li>
          <li>En <b>Inicio</b> tenés el resumen del día:
            <ul>
              <li><b>Turnos de hoy</b>, en orden de hora, con su estado. El signo <span class="t-marca m-alerta">!</span> avisa que la persona tiene antecedentes en la ficha.</li>
              <li><b>Atendidos sin cobrar</b>: sesiones ya hechas que falta cobrar, con su botón <b>Cobrar</b>.</li>
              <li><b>Recordatorios para mañana</b>: un botón de WhatsApp por cada turno (ver <a href="#recordatorios">Recordatorios</a>).</li>
            </ul>
          </li>
        </ol>
        <p class="apagado">Arriba a la derecha de cada pantalla está el estado de la caja (abierta o cerrada). El número junto a <b>Caja</b> en el menú son los turnos atendidos que todavía no se cobraron.</p>
      </article>

      <article class="tema card" id="agenda">
        <h3>La agenda</h3>
        <p>Cada columna es un profesional y cada fila, un horario. Se muestran los profesionales que trabajan ese día o que tienen algún turno.</p>
        <ul>
          <li><b>Cambiar de día</b>: con <b>‹</b> y <b>›</b>, con <b>Hoy</b> o eligiendo la fecha en el calendario.</li>
          <li><b>Filtrar por categoría</b>: en el selector de categorías quedan sólo las columnas de los profesionales de esa categoría.</li>
          <li><b>Ver la semana de alguien</b>: tocá su nombre arriba de la columna, o usá <b>Semana</b>. Para volver a un día, tocá el nombre del día.</li>
          <li><b>Zonas rayadas</b>: el profesional no trabaja en ese horario.</li>
          <li><b>Línea roja</b>: la hora actual.</li>
        </ul>
        <p><b>Cómo se ve cada turno</b>:</p>
        <p class="leyenda" style="margin-top:0">
          <?= badge_turno('reservado') ?> borde punteado, todavía no confirmó
          <?= badge_turno('confirmado') ?> relleno suave
          <?= badge_turno('en_atencion') ?> color pleno, está siendo atendida
          <?= badge_turno('atendido') ?> gris
          <?= badge_turno('ausente') ?> rayado rojo
        </p>
        <p>Los cancelados no aparecen. <span class="t-marca m-alerta">!</span> la ficha tiene antecedentes; <span class="t-marca m-pago">$</span> ya está cobrado. Si dos turnos se superponen se dibujan uno al lado del otro. Pasando el mouse por un turno se ve el detalle; tocándolo se abre.</p>
        <p><b>Dar un turno desde la agenda</b>: hacé clic en un espacio libre de la columna del profesional. Se abre el formulario con ese profesional, ese día y esa hora ya cargados.</p>
        <details><summary>No encuentro a una profesional en la agenda</summary>
          <p>Puede que no trabaje ese día: abajo de la agenda está el enlace <b>Ver también a los que no trabajan este día</b>. También revisá que no haya un filtro de categoría puesto, y que la profesional esté activa (en <b>Profesionales</b>).</p>
        </details>
      </article>

      <article class="tema card" id="dar-turno">
        <h3>Dar un turno</h3>
        <p>Desde <b>Nuevo turno</b> (arriba en la agenda), con un clic en un espacio libre de la agenda, o desde la ficha de la persona con <b>Dar turno</b>.</p>
        <ol>
          <li><b>Cliente</b>: escribí parte del nombre, apellido, teléfono o DNI y elegila de la lista (con las flechas y <kbd>Enter</kbd> o con el mouse).
            Si es nueva, tocá <b>Es nueva: cargar sus datos</b> y completá nombre, apellido y teléfono. El resto de la ficha se completa después.</li>
          <li><b>Categoría y servicio</b>: al elegir la categoría, la lista de servicios y de profesionales se reduce a los de esa categoría. Al elegir el servicio se completan solos la duración, el precio y la cabina o equipo que ocupa.</li>
          <li><b>Profesional</b>: los que no hacen ese servicio aparecen con <i>(no lo hace)</i>. También podés dejar <b>Cualquier profesional libre</b> (ver el tema siguiente).</li>
          <li><b>Fecha, hora y duración</b>: abajo aparece a qué hora termina y un recuadro azul con el horario del profesional ese día y lo que ya tiene ocupado.</li>
          <li><b>Precio</b> se puede cambiar para este turno. <b>Aclaraciones</b> es para avisos de la reserva ("viene con su hija").</li>
          <li>Tildá <b>Ya está confirmado</b> si la persona ya confirmó. Tocá <b>Agendar turno</b>.</li>
        </ol>
        <p><b>Dos servicios seguidos</b>: <b>Agendar y sumar otro servicio</b> guarda el turno y abre otro para la misma persona, empezando a la hora en que termina el primero.</p>
        <p><b>Controles al guardar</b>:</p>
        <ul>
          <li>Si la <b>cabina o el equipo</b> (ej. el láser) ya está en uso a esa hora, <b>no deja guardar</b>: hay que cambiar el horario.</li>
          <li>Si se <b>superpone</b> con otro turno del profesional, queda <b>fuera de su horario</b>, el profesional <b>no hace</b> ese servicio o la persona ya tiene otro turno a esa hora, aparece un aviso amarillo. Leelo y, si está bien así, tildá <b>Entendido, guardar igual</b> y volvé a guardar.</li>
          <li>Si al cargar una persona nueva ya hay una ficha con ese teléfono, avisa para no duplicarla: buscala por el nombre.</li>
        </ul>
      </article>

      <article class="tema card" id="cualquiera">
        <h3>Turno a cualquier profesional libre</h3>
        <p>Sirve cuando a la clienta le da lo mismo quién la atienda.</p>
        <ol>
          <li>Elegí la <b>categoría</b> (o directamente el servicio: la categoría se completa sola).</li>
          <li>En <b>Profesional</b> elegí <b>Cualquier profesional libre</b>, que aparece con el nombre de la categoría.</li>
          <li>Elegí fecha y hora. El recuadro azul muestra quién está libre a esa hora y a quién se le va a asignar, o por qué no hay nadie ("no trabaja en ese horario", "tiene otro turno").</li>
          <li>Tocá <b>Agendar turno</b>.</li>
        </ol>
        <p>Se asigna al primero, en el orden de la agenda, que hace ese servicio, trabaja a esa hora y no tiene otro turno. En el turno queda indicado <i>(asignado automáticamente)</i>. Si después hace falta, se cambia con <b>Reprogramar o editar</b>.</p>
      </article>

      <article class="tema card" id="estados">
        <h3>Estados del turno</h3>
        <p>Abrí el turno desde la agenda o desde Inicio. Los botones cambian según el estado:</p>
        <div class="tabla-scroll">
        <table class="tabla">
          <thead><tr><th>Botón</th><th>Cuándo</th><th>Queda</th></tr></thead>
          <tr><td><b>Confirmar</b></td><td>La persona confirmó que viene</td><td><?= badge_turno('confirmado') ?></td></tr>
          <tr><td><b>Llegó, pasa a atención</b></td><td>Llegó al centro</td><td><?= badge_turno('en_atencion') ?></td></tr>
          <tr><td><b>Marcar atendido</b></td><td>Terminó la sesión</td><td><?= badge_turno('atendido') ?></td></tr>
          <tr><td><b>No vino</b></td><td>No se presentó ni avisó</td><td><?= badge_turno('ausente') ?> (el horario queda libre)</td></tr>
          <tr><td><b>Cancelar turno</b></td><td>Avisó que no viene</td><td>Desaparece de la agenda; se puede anotar el motivo</td></tr>
        </table>
        </div>
        <p class="mt">Si te equivocaste, <b>Volver a confirmado</b> deshace los estados "en atención", "atendido" y "no vino". Al cobrar un turno que ya pasó, queda atendido solo.</p>
        <p>En el mismo turno: la ficha resumida de la persona (antecedentes, últimas sesiones, próximos turnos), las <b>notas de la sesión</b> y los botones <b>Cobrar</b>, <b>Recordatorio por WhatsApp</b> y <b>Darle otro turno</b>.</p>
      </article>

      <article class="tema card" id="reprogramar">
        <h3>Reprogramar o cancelar</h3>
        <ul>
          <li><b>Reprogramar</b>: en el turno, <b>Reprogramar o editar</b>. Cambiá día, hora, profesional, servicio o duración y tocá <b>Guardar cambios</b>. Valen los mismos controles que al darlo.</li>
          <li><b>Cancelar</b>: <b>Cancelar turno</b>, escribí el motivo si querés y confirmá. El horario queda libre.</li>
        </ul>
        <p class="apagado">Un turno ya cobrado no se puede cancelar ni marcar como "no vino": primero hay que anular el cobro (lo hace un administrador). Tampoco se le cambia el precio.</p>
      </article>

      <article class="tema card" id="recordatorios">
        <h3>Recordatorios por WhatsApp</h3>
        <p>El botón <b>WhatsApp</b> abre WhatsApp (Web o la aplicación) con el mensaje ya escrito para esa persona. Revisalo y envialo. El sistema anota que el recordatorio se mandó.</p>
        <ul>
          <li>En <b>Inicio → Recordatorios para mañana</b> están todos los turnos del día siguiente: andá tocando uno por uno. Los ya enviados dicen <i>Enviado</i>.</li>
          <li>En cada turno, <b>Recordatorio por WhatsApp</b> (o <b>Volver a enviar recordatorio</b>).</li>
        </ul>
        <p>Para que funcione, el teléfono de la ficha tiene que tener el código de área, sin 0 ni 15: <code>11 5555-1234</code>, <code>351 555-1234</code>. El texto del mensaje se cambia en <b>Ajustes</b>.</p>
      </article>

      <article class="tema card" id="aviso-profesional">
        <h3>Avisar al profesional por WhatsApp</h3>
        <p>Cuando das un turno, lo reprogramás o lo cancelás, arriba de la pantalla aparece un cartel verde con el botón <b>Avisar a … por WhatsApp</b>. Abre WhatsApp con el mensaje ya escrito para el profesional: día y hora, cliente, servicio, cabina y aclaraciones. Revisalo y envialo.</p>
        <ul>
          <li><b>Reprogramar</b>: el mensaje dice qué cambió y, si cambió el horario, cómo era antes y cómo quedó.</li>
          <li><b>Si el turno pasa a otro profesional</b> aparecen dos botones: uno para avisarle al que lo tenía y otro para el que lo recibe.</li>
          <li><b>Cancelar</b>: avisa que el horario le quedó libre, con el motivo si lo escribiste.</li>
          <li>Si cerraste el cartel, en el turno está el botón <b>Avisar a …</b> para mandarle los datos cuando quieras.</li>
        </ul>
        <p>No avisa turnos que ya pasaron ni cambios de precio. Si el cambio lo hace el mismo profesional desde su usuario, tampoco. El mensaje sale desde el WhatsApp que esté abierto en esa compu o celular: para que salga desde el número del local, dejá abierto WhatsApp Web con esa cuenta.</p>
        <p>Para que funcione, el profesional tiene que tener el teléfono cargado en <b>Profesionales</b>, con código de área y sin 0 ni 15. Si no lo tiene, el cartel lo avisa.</p>
      </article>

      <article class="tema card" id="clientes">
        <h3>Fichas de clientes</h3>
        <p>En <b>Clientes</b> se busca por nombre, apellido, teléfono, DNI o email. Las fichas también se crean solas al dar un turno a alguien nuevo.</p>
        <p>La <b>ficha</b> de cada persona tiene:</p>
        <ul>
          <li><b>Antecedentes</b> resaltados en rojo: alergias, medicación, embarazo, contraindicaciones. Lo que se escriba acá aparece en cada turno y en la agenda con <span class="t-marca m-alerta">!</span>, para que el profesional lo vea antes de atender.</li>
          <li><b>Historial</b> de sesiones con las notas que dejó cada profesional.</li>
          <li><b>Cobros</b> y, si tiene sesiones sin cobrar, el aviso con <b>Cobrar ahora</b>.</li>
          <li>Datos, edad, <b>Próximos turnos</b> y los botones <b>Dar turno</b> y <b>Editar datos</b>. El teléfono tiene un enlace para escribirle por WhatsApp.</li>
        </ul>
        <p class="apagado">Al eliminar una ficha que tiene turnos o cobros, no se borra: queda archivada y deja de aparecer en las búsquedas. No deja cargar dos fichas con el mismo DNI o el mismo teléfono.</p>
      </article>

      <article class="tema card" id="cobrar">
        <h3>Cobrar</h3>
        <p>Con la caja abierta, tocá <b>Cobrar</b> en el turno, en Inicio, en Caja o en la ficha. Se abre la pantalla de cobro de esa persona:</p>
        <ol>
          <li><b>Turnos</b>: aparecen todos sus turnos sin cobrar, también los futuros (pago adelantado). Vienen tildados el que elegiste y los que ya pasaron. El precio de cada uno se puede ajustar.</li>
          <li><b>Otros conceptos</b>: con <b>Agregar concepto</b> se suman productos (una crema), una seña o una gift card, con cantidad y precio.</li>
          <li><b>Descuento</b>: en pesos (<code>5000</code>) o en porcentaje (<code>10%</code>).</li>
          <li><b>Pago</b>: elegí el medio. Si paga con dos medios (parte efectivo, parte transferencia), tocá <b>Dividir en otro medio de pago</b>: el último renglón se completa solo con lo que falta.</li>
          <li>Si paga en efectivo, escribí en <b>Paga con</b> el billete que te da y el sistema calcula el vuelto.</li>
          <li>Tocá <b>Registrar cobro</b>. Se abre el <b>recibo</b> para imprimir.</li>
        </ol>
        <p class="apagado">El recibo dice "Comprobante no válido como factura". Los turnos que ya pasaron quedan como atendidos al cobrarlos; los futuros conservan su estado y aparecen con <span class="t-marca m-pago">$</span> en la agenda.</p>
      </article>

      <article class="tema card" id="cobro-sin-turno">
        <h3>Cobro sin turno</h3>
        <p>Para vender un producto o cobrar algo que no es un turno: <b>Caja → Cobro sin turno</b>. Podés buscar a la persona (si la elegís, se ven sus turnos pendientes) o dejarlo sin cliente, y cargar los conceptos en <b>Otros conceptos</b>.</p>
      </article>

      <article class="tema card" id="caja">
        <h3>Caja: movimientos y cierre</h3>
        <p>En <b>Caja</b> se ve lo cobrado en el día por medio de pago, la lista de cobros con su recibo y cuánto efectivo debería haber en el cajón.</p>
        <ul>
          <li><b>Registrar ingreso o egreso</b>: plata que entra o sale de la caja sin ser un cobro (compra de insumos, retiro, cambio agregado). Elegí el tipo, monto, medio y concepto.</li>
          <li><b>Cerrar caja</b> al final del día: contá el efectivo, escribilo en <b>Efectivo contado</b> y tocá <b>Cerrar caja</b>. El sistema guarda la diferencia con lo que debería haber.</li>
        </ul>
        <p class="apagado">Si quedan turnos atendidos sin cobrar, avisa antes de cerrar; se pueden cobrar en la caja del día siguiente. Una vez cerrada, sus cobros no se pueden anular.</p>
      </article>

      <article class="tema card" id="celular">
        <h3>Usar desde otra PC o el celular</h3>
        <p>Desde cualquier equipo conectado a la red del centro se entra con el navegador a la dirección de la PC que hace de servidor, por ejemplo <code>http://192.168.0.10:8020/</code> (el administrador te la pasa). Si la app está publicada en internet, se entra con su dirección web desde cualquier lugar.</p>
        <p>Cada persona entra con su usuario. Al terminar, tocá <b>Salir</b> (abajo en el menú), sobre todo en equipos compartidos.</p>
      </article>

      <article class="tema card" id="dudas">
        <h3>Dudas frecuentes</h3>
        <details><summary>No me deja guardar el turno</summary>
          <p>Leé el mensaje rojo: si la cabina o el equipo está en uso, hay que cambiar el horario o el profesional. Si falta la clienta, elegila de la lista o cargá sus datos con <b>Es nueva</b>.</p></details>
        <details><summary>Me aparece un aviso amarillo</summary>
          <p>No es un error: avisa que el turno se superpone, queda fuera de horario o el profesional no hace ese servicio. Si es correcto, tildá <b>Entendido, guardar igual</b> y guardá de nuevo.</p></details>
        <details><summary>Dejó una seña</summary>
          <p>Cobrala en <b>Cobrar</b> como otro concepto ("Seña turno del 5/11"), sin tildar el turno. Cuando venga, cobrá el turno y poné el importe de la seña en <b>Descuento</b>.</p></details>
        <details><summary>Me equivoqué en un cobro</summary>
          <p>Pedile a un administrador que lo anule desde el recibo (sólo se puede mientras la caja de ese día siga abierta). Los turnos vuelven a quedar sin cobrar y lo cobrás de nuevo bien.</p></details>
        <details><summary>No vino o avisó que no viene</summary>
          <p>Si no vino ni avisó: <b>No vino</b>. Si avisó: <b>Cancelar turno</b> con el motivo. En los dos casos el horario queda libre.</p></details>
        <details><summary>El WhatsApp abre otro número o no lo encuentra</summary>
          <p>Corregí el teléfono en la ficha: código de área sin 0 ni 15, por ejemplo <code>11 5555-1234</code>.</p></details>
        <details><summary>No puedo cobrar: "La caja está cerrada"</summary>
          <p>Abrí la caja en <b>Caja</b> con el efectivo inicial.</p></details>
      </article>
    </section>
<?php endif; ?>

    <section class="ayuda-seccion">
      <h2>Profesionales</h2>

      <article class="tema card" id="mi-agenda">
        <h3>Mi agenda</h3>
        <p>Al entrar con tu usuario vas directo a <b>Mi agenda</b>: tu columna primero y al lado las de quienes comparten alguna categoría con vos. Con el selector de categorías ves sólo una. Tocando tu nombre arriba de la columna ves tu <b>semana</b>.</p>
        <p>En <b>Inicio</b> están tus turnos de hoy y los de los próximos 7 días.</p>
        <p class="apagado">Los turnos los da recepción. Podés ver los turnos de tus compañeros de categoría, pero sólo podés cambiar el estado y escribir notas en los tuyos.</p>
      </article>

      <article class="tema card" id="atender">
        <h3>Atender un turno</h3>
        <ol>
          <li>Tocá el turno en la agenda. Si la persona tiene <b>antecedentes</b> (alergias, medicación, contraindicaciones), aparecen arriba en rojo: leelos antes de empezar.</li>
          <li>Cuando llega, <b>Llegó, pasa a atención</b>. Al terminar, <b>Marcar atendido</b>. Si no se presentó, <b>No vino</b>.</li>
          <li>En <b>Notas de la sesión</b> escribí lo que hiciste: parámetros del equipo, productos usados, reacción de la piel, indicaciones para la próxima vez. Tocá <b>Guardar notas</b>.</li>
        </ol>
        <p>A la derecha ves las últimas sesiones de esa persona con sus notas, aunque las haya hecho otra profesional, y sus próximos turnos.</p>
      </article>

      <article class="tema card" id="fichas-prof">
        <h3>Fichas y antecedentes</h3>
        <p>En <b>Clientes</b> ves las fichas de las personas que atendieron vos o tu categoría, con todo su historial de sesiones.</p>
        <p>Si te enterás de algo importante (una alergia, un embarazo, una medicación nueva), actualizalo en <b>Antecedentes</b> dentro de la ficha y tocá <b>Guardar antecedentes</b>: desde ese momento lo ven todos en cada turno.</p>
      </article>
    </section>

<?php if ($ver['admin']): ?>
    <section class="ayuda-seccion">
      <h2>Administrador</h2>

      <article class="tema card" id="servicios">
        <h3>Servicios y precios</h3>
        <p>En <b>Servicios</b> están todos los tratamientos agrupados por categoría, con duración, precio y quién los hace. Los que dicen <i>Nadie asignado</i> en rojo no tienen profesional.</p>
        <p><b>Nuevo servicio</b> o <b>Editar</b>:</p>
        <ul>
          <li><b>Duración</b> en minutos: es la que se propone al dar el turno (se puede cambiar en cada turno).</li>
          <li><b>Ocupa la cabina o equipo</b>: elegilo si el servicio necesita un recurso que no se puede usar en dos turnos a la vez (ej. el láser).</li>
          <li><b>Quién lo hace</b>: tildá los profesionales. Al dar un turno se sugieren sólo ellos.</li>
          <li><b>Orden</b> define la posición en las listas. <b>Activo</b> lo muestra u oculta al dar turnos.</li>
          <li><b>Descripción</b> y <b>Se puede reservar online</b> quedan listos para la futura reserva online.</li>
        </ul>
        <p class="apagado">Un servicio con turnos no se borra: se desactiva. Los turnos ya dados conservan su nombre y precio.</p>
      </article>

      <article class="tema card" id="precios">
        <h3>Actualizar precios</h3>
        <p>En <b>Servicios → Actualizar precios</b>: escribí el porcentaje (<code>8</code> sube un 8 %, <code>-5</code> baja un 5 %), elegí el redondeo ($ 100, $ 500 o $ 1.000) y si es para todos los servicios o una categoría. Los turnos ya dados mantienen el precio con el que se dieron.</p>
      </article>

      <article class="tema card" id="categorias">
        <h3>Categorías</h3>
        <p>En <b>Servicios → Categorías</b> (o en <b>Ajustes</b>). Agrupan los servicios y los profesionales: Faciales, Depilación, Masajes… Cada servicio tiene una categoría; un profesional puede estar en varias.</p>
        <p>Se usan para filtrar la agenda, para dar turnos por categoría y para el "cualquier profesional libre". Además, cada profesional ve la agenda de quienes comparten su categoría. Una categoría en uso no se borra: se desactiva destildando <b>Activa</b>.</p>
      </article>

      <article class="tema card" id="profesionales">
        <h3>Profesionales y horarios</h3>
        <p>En <b>Profesionales</b> está la lista con sus categorías, su horario y cuántos turnos tienen por delante. En cada profesional:</p>
        <ul>
          <li><b>Datos</b>: nombre, especialidad, teléfono (para <a href="#aviso-profesional">avisarle por WhatsApp</a> de sus turnos), <b>color</b> de sus turnos en la agenda y <b>orden</b> de su columna (también decide a quién se asigna primero un turno de "cualquier profesional libre").</li>
          <li><b>Horario de atención</b>: hasta dos franjas por día (para quien corta al mediodía). Día vacío = no trabaja. <b>Copiar el horario del lunes de martes a viernes</b> ahorra tiempo.</li>
          <li><b>Categorías</b>: tildá todas en las que trabaja. Si marcás una categoría y no tenía ningún servicio de ella, se tildan todos; después destildá los que no hace.</li>
          <li><b>Servicios que realiza</b>.</li>
          <li><b>Activo</b>: destildado, deja de aparecer en la agenda. Quien tiene turnos no se borra: se desactiva.</li>
        </ul>
        <p>Para que un profesional entre al sistema, creale un usuario con el rol <b>Profesional</b> (ver <a href="#usuarios">Usuarios</a>).</p>
      </article>

      <article class="tema card" id="cabinas">
        <h3>Cabinas y equipos</h3>
        <p>En <b>Servicios → Cabinas y equipos</b>. Cargá las cabinas o aparatos que no pueden usarse en dos turnos a la vez. Si un servicio ocupa uno, el sistema no deja dar dos turnos superpuestos con ese recurso, aunque sean de profesionales distintos.</p>
      </article>

      <article class="tema card" id="anular">
        <h3>Anular un cobro</h3>
        <p>Abrí el recibo (desde <b>Caja</b>, la ficha o el turno) y tocá <b>Anular cobro</b>, con el motivo. Se descuenta de la caja y los turnos vuelven a quedar sin cobrar. Sólo se pueden anular cobros de la caja que está abierta.</p>
      </article>

      <article class="tema card" id="caja-admin">
        <h3>Control de cajas</h3>
        <p>En <b>Caja → Cajas anteriores</b> está cada día con lo cobrado, el efectivo esperado, el contado y la diferencia (en rojo si no coincide). Con <b>Ver</b> se abre el detalle: cobros por medio de pago, ingresos y egresos, la lista de recibos y las observaciones del cierre.</p>
      </article>

      <article class="tema card" id="ajustes">
        <h3>Ajustes</h3>
        <ul>
          <li><b>Datos del centro</b>: nombre (menú, ingreso y recibos), dirección, teléfono y mensaje al pie del recibo.</li>
          <li><b>Agenda</b>: desde y hasta qué hora se muestra (se amplía sola si alguien trabaja fuera de ese rango) y de a cuántos minutos se redondea al hacer clic.</li>
          <li><b>Recordatorio por WhatsApp</b>: el texto del mensaje. Se reemplazan solos <code>{nombre}</code>, <code>{servicio}</code>, <code>{dia}</code>, <code>{hora}</code>, <code>{profesional}</code>, <code>{centro}</code> y <code>{direccion}</code>.</li>
          <li><b>Medios de pago</b>: marcá como <b>Es efectivo</b> sólo los que entran al cajón (para el arqueo y el vuelto).</li>
        </ul>
      </article>

      <article class="tema card" id="usuarios">
        <h3>Usuarios y permisos</h3>
        <div class="tabla-scroll">
        <table class="tabla">
          <thead><tr><th>Rol</th><th>Puede</th></tr></thead>
          <tr><td><b>Administrador</b></td><td>Todo: servicios y precios, profesionales y horarios, categorías, cabinas, usuarios, anular cobros, ajustes.</td></tr>
          <tr><td><b>Recepción</b></td><td>Agenda de todos, dar y reprogramar turnos, clientes, cobrar, abrir y cerrar caja.</td></tr>
          <tr><td><b>Profesional</b></td><td>Su agenda y la de su categoría; estados y notas de sus turnos; fichas y antecedentes de las personas que atienden. No ve precios ni caja.</td></tr>
        </table>
        </div>
        <p class="mt">En <b>Ajustes → Usuarios</b>: nombre, usuario para ingresar (sin espacios), contraseña (mínimo 6 caracteres) y rol. Para el rol Profesional hay que elegir qué profesional es. Para cambiar una contraseña, escribí la nueva; vacía no se cambia. Destildando <b>Activo</b> el usuario ya no puede entrar. Siempre tiene que quedar al menos un administrador activo.</p>
      </article>
    </section>
<?php endif; ?>

<?php if ($ver['tecnico']): ?>
    <section class="ayuda-seccion">
      <h2>Técnico</h2>

      <article class="tema card" id="tec-estructura">
        <h3>Cómo está armada</h3>
        <p>PHP (7.4 o superior) con MySQL o MariaDB, sin frameworks. Todo lo que usa el navegador (estilos, scripts, tipografías) está en la carpeta, así que funciona sin internet en la red del centro.</p>
        <ul>
          <li><code>config.php</code>: configuración sin contraseñas. En la PC lee <code>config.local.php</code>; en la nube, las variables de entorno.</li>
          <li><code>inc/</code>: lógica (conexión, permisos, agenda, caja, cobros, actualizaciones de la base). <code>api/</code>: respuestas JSON para la búsqueda de clientes y la disponibilidad.</li>
          <li><code>sql/database.sql</code>: tablas iniciales. <code>sql/migraciones/</code>: cambios de versiones posteriores. <code>sql/datos_ejemplo.sql</code>: datos de prueba.</li>
          <li><code>iis/</code>: scripts para crear el sitio de IIS y subir a GitHub. <code>inc</code>, <code>sql</code> e <code>iis</code> no se pueden abrir desde el navegador.</li>
        </ul>
      </article>

      <article class="tema card" id="tec-iis">
        <h3>Servidor local (IIS)</h3>
        <ol>
          <li>PHP tiene que estar configurado en IIS (FastCGI) con la extensión <code>pdo_mysql</code>.</li>
          <li>Doble clic en <code>iis\configurar-iis.bat</code>: crea el sitio <b>estetica</b> en el puerto <b>8020</b> apuntando a la carpeta, da los permisos, abre el puerto en el firewall (redes privadas) y prueba que responda. Se puede volver a correr sin problema. Otro puerto: <code>configurar-iis.bat -Puerto 8021</code>.</li>
          <li><code>config.local.php</code> (copia de <code>config.local.example.php</code>) con los datos de MySQL y <code>DB_NAME</code> = <code>estetica</code>.</li>
          <li>Primera vez: <code>http://localhost:8020/instalar.php</code>. Después borrá o renombrá <code>instalar.php</code>.</li>
        </ol>
        <p><code>diagnostico.php</code> revisa PHP, la extensión, el puerto, el usuario y la base, y dice qué corregir.</p>
      </article>

      <article class="tema card" id="tec-red">
        <h3>Acceso desde la red</h3>
        <ul>
          <li>En la PC servidor, <code>ipconfig</code> da la IP (ej. <code>192.168.0.10</code>). Desde otro equipo: <code>http://192.168.0.10:8020/</code>.</li>
          <li>La red de Windows tiene que estar como <b>Privada</b>: la regla del firewall que crea el script no abre el puerto en redes públicas.</li>
          <li>Conviene reservar esa IP en el router (DHCP) para que no cambie.</li>
        </ul>
      </article>

      <article class="tema card" id="tec-railway">
        <h3>GitHub y Railway</h3>
        <ol>
          <li><code>iis\subir-github.bat</code> sube los cambios al repositorio privado (la primera vez lo crea). <code>config.local.php</code> nunca se sube.</li>
          <li>En Railway, el servicio de la app está conectado al repositorio y se vuelve a desplegar solo con cada subida, usando el <code>Dockerfile</code>.</li>
          <li>Variables del servicio: <code>MYSQL_URL</code> = <code>${{MySQL.MYSQL_URL}}</code> (referencia al servicio MySQL del mismo proyecto), <code>DB_NAME</code> = <code>estetica</code>, <code>INSTALL_KEY</code> = clave para instalar.</li>
          <li>La app queda en <code>https://DOMINIO/estetica/</code>. Primera vez: <code>/estetica/instalar.php</code> con la <code>INSTALL_KEY</code>; después queda bloqueado.</li>
        </ol>
        <p class="apagado">La base de Railway y la de la PC son independientes: se sincroniza el código, no los datos.</p>
      </article>

      <article class="tema card" id="tec-actualizar">
        <h3>Actualizar la app</h3>
        <p>Hacé un backup de la base y reemplazá los archivos (sin tocar <code>config.local.php</code>). En Railway, subí los cambios con <code>subir-github.bat</code>.</p>
        <p>Si la versión nueva necesita cambios en la base, trae un archivo en <code>sql/migraciones/</code>. Al abrir cualquier página, la app ve que la base está atrasada (<code>db_version</code> en la tabla <code>configuracion</code>) y aplica lo que falta, una sola vez y sin tocar los datos.</p>
      </article>

      <article class="tema card" id="tec-backup">
        <h3>Copias de seguridad</h3>
        <p>Backup diario de la base con una tarea programada de Windows:</p>
        <pre>"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" -u root -p estetica &gt; D:\backups\estetica_%date:~-4%%date:~3,2%%date:~0,2%.sql</pre>
        <p>En Railway: pestaña <b>Backups</b> del servicio MySQL.</p>
      </article>

      <article class="tema card" id="tec-problemas">
        <h3>Problemas comunes</h3>
        <div class="tabla-scroll">
        <table class="tabla">
          <thead><tr><th>Síntoma</th><th>Causa probable</th><th>Solución</th></tr></thead>
          <tr><td>"No se pudo conectar a MySQL"</td><td>Servicio detenido, clave o puerto incorrectos</td><td>Abrir <code>diagnostico.php</code>: dice el paso que falla y qué hacer.</td></tr>
          <tr><td>La página se ve sin formato</td><td>Archivos viejos en caché o código desactualizado</td><td>Recargar con <kbd>Ctrl</kbd>+<kbd>F5</kbd>; en IIS, reciclar el grupo de aplicaciones <b>estetica</b>.</td></tr>
          <tr><td>"Usuario o contraseña incorrectos" con la clave correcta</td><td>Chrome autocompleta la clave de otro sistema</td><td>Escribir usuario y clave a mano.</td></tr>
          <tr><td>Olvido de la clave de admin</td><td>—</td><td>Resetearla con la consulta de abajo.</td></tr>
          <tr><td>Otra PC o el celular no abre la app</td><td>Otra red, firewall o la IP de la PC cambió</td><td>Revisar Wi‑Fi, red Privada, regla del puerto 8020 e <code>ipconfig</code>.</td></tr>
          <tr><td>"La base se está actualizando"</td><td>Se está aplicando una migración</td><td>Esperar unos segundos y recargar. Si sigue, ver el mensaje de error que muestra.</td></tr>
          <tr><td>Railway: el diagnóstico muestra servidor 127.0.0.1</td><td>No llegan las variables de MySQL</td><td>Ver "Variables de entorno" en el diagnóstico; revisar el nombre del servicio en la referencia <code>${{...}}</code>.</td></tr>
          <tr><td>El recibo sale cortado</td><td>Configuración de impresión</td><td>En el diálogo de impresión: márgenes Ninguno, sin encabezados ni pies de página.</td></tr>
        </table>
        </div>
        <p class="mt"><b>Resetear la clave de admin</b> a <code>admin123</code> (en HeidiSQL, Workbench o la consola), y después cambiarla en Usuarios:</p>
        <pre>UPDATE usuarios
   SET password_hash = '$2y$10$a38VaQ8bd96UD.v1Dd5jieFyw6zLV0HXzHr639u07ACJpGmc1KRJO', activo = 1
 WHERE usuario = 'admin';</pre>
      </article>
    </section>
<?php endif; ?>

  </div>
</div>

<script>
(function () {
  // Buscador: oculta los temas que no contienen todas las palabras
  var input = document.getElementById('ayuda-buscar');
  var temas = Array.prototype.slice.call(document.querySelectorAll('.ayuda .tema'));
  var secciones = Array.prototype.slice.call(document.querySelectorAll('.ayuda-seccion'));
  var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
  temas.forEach(function (t) { t._texto = norm(t.textContent); });
  input.addEventListener('input', function () {
    var palabras = norm(input.value).split(/\s+/).filter(Boolean);
    var visibles = 0;
    temas.forEach(function (t) {
      var ok = palabras.every(function (p) { return t._texto.indexOf(p) >= 0; });
      t.hidden = !ok;
      if (ok) visibles++;
      if (ok && palabras.length) t.querySelectorAll('details').forEach(function (d) { d.open = true; });
    });
    secciones.forEach(function (s) { s.hidden = !s.querySelector('.tema:not([hidden])'); });
    document.getElementById('ayuda-sin').hidden = visibles > 0;
  });
  // En pantallas chicas el índice arranca cerrado
  if (window.matchMedia('(max-width: 1100px)').matches) document.getElementById('ayuda-nav').open = false;
  // Resaltar el tema al llegar desde el botón "?" de otra pantalla
  if (location.hash) {
    var t = document.querySelector(location.hash);
    if (t) t.classList.add('tema-destacado');
  }
  // Al imprimir se abren todas las respuestas
  window.addEventListener('beforeprint', function () { document.querySelectorAll('.ayuda details').forEach(function (d) { d.open = true; }); });
})();
</script>
<?php layout_footer(); ?>
