# Gestión para centro de estética

App web en PHP + MySQL para manejar la **agenda de turnos** por profesional, las **fichas de clientes** con antecedentes y notas de cada sesión, los **cobros** (con pago dividido en varios medios) y la **caja diaria**.

Misma base que el sistema del bar: corre en **IIS** o **XAMPP** en la PC del centro, y también publicada en internet (Railway o cualquier hosting con PHP y MySQL). Desde la red del centro se entra desde cualquier PC, tablet o celular por el navegador.

---

## Requisitos

- PHP 7.4 o superior (8.x recomendado) con la extensión `pdo_mysql`
- MySQL 5.7+ o MariaDB 10.3+
- Un servidor web: **IIS** o **XAMPP** (Apache)

## Instalación

### Con IIS (sitio propio en el puerto 8020)

1. Tener PHP configurado en IIS (FastCGI) con `pdo_mysql`, como para el bar.
2. Doble clic en **`iis\configurar-iis.bat`** (pide permiso de administrador). El script:
   - crea el grupo de aplicaciones y el sitio **estetica** en el puerto **8020**, apuntando a esta carpeta;
   - verifica que el sitio tome PHP (si no le llega desde la configuración general de IIS, le agrega el manejador);
   - da permiso de lectura a IIS sobre la carpeta y de escritura en la carpeta de sesiones de PHP;
   - abre el puerto 8020 en el Firewall sólo para redes privadas y de dominio;
   - si falta `config.local.php`, ofrece copiar el del bar cambiando la base a `estetica`;
   - arranca el sitio y prueba que responda.

   Se puede volver a correr sin problema. Para otro puerto: `configurar-iis.bat -Puerto 8021`.
3. Entrar a `http://localhost:8020/instalar.php`, elegir el nombre del centro y la contraseña del usuario **admin**.
   Dejar tildado "Cargar datos de ejemplo" para probar con profesionales, servicios, clientes y turnos armados; para arrancar en limpio, destildarlo.
4. **Borrar o renombrar `instalar.php`** una vez instalado.
5. Ingresar en `http://localhost:8020/` con `admin` y la contraseña elegida. Desde otra PC de la red: `http://IP-DE-ESTA-PC:8020/`.

El `web.config` incluido bloquea el acceso desde el navegador a las carpetas `inc`, `sql` e `iis` y define `index.php` como página de inicio.

A mano, sin el script: en el Administrador de IIS, **Agregar sitio web** → nombre `estetica`, ruta física `D:\estetica`, enlace http puerto 8020; en su grupo de aplicaciones poner **Sin código administrado**; copiar `config.local.example.php` como `config.local.php` con la contraseña de MySQL (puede ser el mismo MySQL del bar: la base es otra, `estetica`).

Si algo no conecta, `http://localhost:8020/diagnostico.php` revisa PHP, la extensión, el puerto y el usuario de MySQL, y dice qué corregir.

### Con XAMPP

1. Copiar la carpeta `estetica` dentro de `C:\xampp\htdocs\`.
2. Iniciar **Apache** y **MySQL** en el panel de XAMPP.
3. Copiar `config.local.example.php` como `config.local.php` con la contraseña de MySQL.
4. Abrir `http://localhost/estetica/instalar.php` y seguir como en IIS (la app queda en `http://localhost/estetica/`).

### Instalación manual de la base (opcional)

En lugar de `instalar.php` se pueden importar los scripts con HeidiSQL, phpMyAdmin o la consola:

```sql
CREATE DATABASE estetica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE estetica;
SOURCE sql/database.sql;
SOURCE sql/datos_ejemplo.sql;   -- opcional
```

El usuario inicial queda `admin` / `admin123`: cambiarle la contraseña en **Ajustes → Usuarios**.
Con los datos de ejemplo también quedan `recepcion` / `recepcion123` y `lucia` / `lucia123` (profesional).

## Subir a GitHub

Doble clic en **`iis\subir-github.bat`**:

- **La primera vez** crea el repositorio local, guarda todo y crea el repositorio **privado** en GitHub (con GitHub CLI `gh`; si no lo tenés, te pide la URL de un repositorio vacío creado a mano en github.com).
- **Las siguientes veces** muestra qué archivos cambiaron, te pide una descripción y los sube. Railway vuelve a desplegar solo con cada subida.

La contraseña de MySQL vive en `config.local.php`, que está en `.gitignore`: nunca se sube (el script además lo verifica). `config.php` no tiene contraseñas: en la nube lee las variables de entorno.

A mano, desde la carpeta del proyecto:

```bat
git init -b main
git add .
git commit -m "Gestión para centro de estética"
gh repo create estetica --private --source . --push
```

## Publicar en Railway

El repositorio incluye un `Dockerfile` (PHP 8.3 + Apache). La app queda en `https://TU-DOMINIO.up.railway.app/estetica/`.

1. En el proyecto de Railway: **New → GitHub Repo** → elegir el repositorio.
2. En el servicio nuevo → **Variables**:

   | Variable | Valor |
   |---|---|
   | `MYSQL_URL` | `${{MySQL.MYSQL_URL}}` (referencia al servicio MySQL; cambiar `MySQL` por el nombre que tenga) |
   | `DB_NAME` | `estetica` (base propia, para no mezclar con el bar si usan el mismo MySQL) |
   | `INSTALL_KEY` | una clave cualquiera, sólo para instalar |

3. **Settings → Networking → Generate Domain**.
4. Abrir `https://TU-DOMINIO.up.railway.app/estetica/instalar.php`, poner la `INSTALL_KEY`, el nombre del centro y la contraseña del admin.

Igual que en el bar: `instalar.php` exige la clave y no instala sobre una base con tablas de otro sistema; una vez instalada queda bloqueado; `diagnostico.php` sólo muestra detalles a un administrador logueado; `inc/` y `sql/` no son accesibles desde el navegador.

## Usar desde otras PCs, tablets o celulares

1. En la PC servidor, ver su IP local (`ipconfig`, por ejemplo `192.168.0.10`).
2. Desde el otro equipo, en la misma red: `http://192.168.0.10:8020/`.
3. Si no conecta, revisar que la red de Windows esté como **privada** (la regla del Firewall que crea el script no abre el puerto en redes públicas).

Las profesionales pueden ver su agenda desde el celular, entrando con su propio usuario.

## Usuarios y permisos

| Rol | Puede |
|---|---|
| **Administrador** | Todo: servicios y precios, profesionales y horarios, usuarios, anular cobros, ajustes |
| **Recepción** | Agenda de todos, dar y reprogramar turnos, clientes, cobrar, abrir y cerrar caja |
| **Profesional** | Su agenda y la de su categoría (sólo para ver), las fichas de las personas que atienden; en sus propios turnos: marcar llegó/atendido/no vino y escribir las notas de la sesión; actualizar antecedentes |

Al crear un usuario con rol **Profesional** se elige qué profesional es. Ve también la agenda de quienes comparten alguna categoría con él.

## Categorías

Las **categorías** (Faciales, Depilación, Masajes…) agrupan los servicios y los profesionales. Un servicio tiene una categoría; un profesional puede estar en varias (se marcan en su ficha, en **Profesionales**). Se usan para:

- **Filtrar la agenda**: elegís la categoría y quedan sólo las columnas de sus profesionales.
- **Dar turno por categoría**: al elegir la categoría, la lista de servicios y de profesionales se reduce a los de esa categoría.
- **Turno a la categoría**: con «Cualquier profesional libre», el sistema asigna el turno al primero (en el orden de la agenda) que hace ese servicio, trabaja en ese horario y no tiene otro turno. Antes de guardar muestra quién está libre y a quién se va a asignar. El turno queda marcado como «asignado automáticamente».

## Cómo se usa en el día

1. **Abrir caja** con el efectivo inicial.
2. En **Inicio** están los turnos del día, lo atendido sin cobrar y los **recordatorios para mañana**: un botón por turno abre WhatsApp con el mensaje armado (se edita en Ajustes).
3. **Agenda**: una columna por profesional, con filtro por categoría. Clic en un espacio libre para dar un turno a esa hora. Las zonas rayadas son fuera de horario.
   - Borde punteado: reservado sin confirmar. Relleno: confirmado. Color pleno: en atención. Gris: atendido. Rayado rojo: no vino.
   - **!** la ficha tiene antecedentes. **$** ya está cobrado.
   - Desde el nombre del profesional se pasa a su **semana**.
4. **Dar un turno**: buscar la clienta (o cargarla nueva con nombre y teléfono), elegir la categoría y el servicio y se completan solos la duración, el precio, la cabina y los profesionales que lo hacen. Abajo se ve el horario y lo ocupado de ese día.
   - Si la cabina o el equipo (ej. el láser) ya está en uso, no deja guardar.
   - Si se superpone con otro turno del profesional, queda fuera de su horario o el profesional no hace ese servicio, avisa y deja guardar igual confirmando.
   - "Agendar y sumar otro servicio" guarda y abre otro turno para la misma persona a continuación.
5. **En el turno**: Llegó → Atendido, o No vino; reprogramar; cancelar; recordatorio por WhatsApp; notas de la sesión.
6. **Cobrar**: se ven todos los turnos sin cobrar de esa persona (también futuros, para pago adelantado). Se pueden sumar otros conceptos (productos, seña), aplicar descuento en monto o porcentaje (`10%`) y **dividir el pago** en varios medios. Calcula el vuelto. Genera un recibo imprimible (no válido como factura).
7. **Cerrar caja** al final del día contando el efectivo: registra la diferencia.

## Actualizaciones de la base

Cuando una versión nueva necesita cambios en la base, trae un archivo en `sql/migraciones/` (ej. `002_categorias_profesionales.sql`). Al abrir cualquier página, la app ve que la base está atrasada (`db_version` en la tabla `configuracion`) y aplica sola lo que falta, una sola vez, sin tocar los datos existentes. Conviene hacer un backup antes de copiar una versión nueva.

## Copias de seguridad

Backup diario de la base con una tarea programada:

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" -u root -p estetica > D:\backups\estetica_%date:~-4%%date:~3,2%%date:~0,2%.sql
```

## Estructura

```
config.php            Configuración (lee config.local.php o variables de entorno; sin contraseñas)
config.local.php      Contraseña de MySQL de tu PC (no se sube a GitHub)
Dockerfile            Imagen para Railway (PHP 8.3 + Apache)
web.config / .htaccess  IIS / Apache
iis/                  configurar-iis.bat (sitio de IIS en el puerto 8020) y subir-github.bat
instalar.php          Instalador (borrar después de usar)
diagnostico.php       Revisa la conexión a MySQL y dice qué corregir
index.php             Inicio: turnos del día, sin cobrar, recordatorios de mañana
agenda.php            Agenda por día (todos los profesionales) o semana (uno)
turno.php             Dar turno, ver, reprogramar, estados, notas de sesión, WhatsApp
clientes.php          Lista y búsqueda de fichas
cliente.php           Ficha: antecedentes, historial de sesiones, cobros, próximos turnos
cliente_form.php      Alta y edición de ficha
cobro.php             Cobro de turnos y otros conceptos, pago dividido
recibo.php            Recibo imprimible y anulación
caja.php              Apertura, ingresos/egresos, cierre e historial
servicios.php         Servicios por categoría y actualización masiva de precios
servicio_form.php     Alta/edición de servicio
profesionales.php     Profesionales y sus horarios
profesional_form.php  Datos, color, horario semanal y servicios que hace
ajustes.php           Datos del centro, agenda, mensaje de recordatorio
usuarios.php          Usuarios y roles
abm.php               Categorías, cabinas/equipos, medios de pago
api/                  Búsqueda de clientes y día del profesional (JSON)
inc/                  Lógica: conexión, permisos, agenda, caja, cobros
sql/                  Scripts de base de datos; sql/migraciones/ trae las actualizaciones
assets/               Estilos, scripts y tipografías (todo local, funciona sin internet)
```

## Próximas fases

1. **Paquetes de sesiones** (ej. 8 sesiones de drenaje prepagas, con saldo), consentimientos firmados, fotos de antes y después, reportes y ayuda dentro de la app.
2. **Reserva online**: página pública donde la clienta elige servicio, profesional y horario libre.
3. **Facturación electrónica ARCA** (WSFE) desde el cobro.
