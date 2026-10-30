-- =============================================================
--  Gestión para centro de estética
--  Esquema MySQL 5.7+ / MariaDB 10.3+
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Configuración general (clave/valor) ----------
CREATE TABLE IF NOT EXISTS configuracion (
  clave VARCHAR(50) PRIMARY KEY,
  valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Profesionales ----------
CREATE TABLE IF NOT EXISTS profesionales (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  especialidad VARCHAR(100) NULL,
  telefono     VARCHAR(40)  NULL,
  color        VARCHAR(7)   NOT NULL DEFAULT '#8a5a9e' COMMENT 'Color de sus turnos en la agenda',
  orden        INT NOT NULL DEFAULT 0,
  activo       TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horario semanal. dia: 1 = lunes ... 7 = domingo. Puede haber dos franjas por día (mañana y tarde).
CREATE TABLE IF NOT EXISTS profesional_horarios (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  profesional_id INT NOT NULL,
  dia            TINYINT NOT NULL,
  desde          TIME NOT NULL,
  hasta          TIME NOT NULL,
  KEY idx_prof_dia (profesional_id, dia),
  CONSTRAINT fk_ph_prof FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Usuarios ----------
-- rol: admin (todo) / recepcion (agenda, clientes, cobros, caja) / profesional (su agenda y fichas)
CREATE TABLE IF NOT EXISTS usuarios (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(100) NOT NULL,
  usuario        VARCHAR(50)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  rol            ENUM('admin','recepcion','profesional') NOT NULL DEFAULT 'recepcion',
  profesional_id INT NULL COMMENT 'Para el rol profesional: qué agenda ve',
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  creado         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuario (usuario),
  CONSTRAINT fk_u_prof FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cabinas, salas o aparatos que no pueden usarse en dos turnos a la vez ----------
CREATE TABLE IF NOT EXISTS cabinas (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL,
  orden  INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Servicios (tratamientos) ----------
CREATE TABLE IF NOT EXISTS categorias (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL,
  orden  INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id  INT NULL,
  nombre        VARCHAR(100) NOT NULL,
  descripcion   TEXT NULL COMMENT 'Para la reserva online',
  duracion      INT NOT NULL DEFAULT 60 COMMENT 'Minutos',
  precio        DECIMAL(12,2) NOT NULL DEFAULT 0,
  cabina_id     INT NULL COMMENT 'Cabina o aparato que ocupa',
  online        TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se puede reservar online',
  orden         INT NOT NULL DEFAULT 0,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_cat (categoria_id),
  CONSTRAINT fk_s_cat FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
  CONSTRAINT fk_s_cab FOREIGN KEY (cabina_id) REFERENCES cabinas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué profesionales hacen cada servicio
CREATE TABLE IF NOT EXISTS servicio_profesional (
  servicio_id    INT NOT NULL,
  profesional_id INT NOT NULL,
  PRIMARY KEY (servicio_id, profesional_id),
  CONSTRAINT fk_sp_serv FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_prof FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Clientes ----------
CREATE TABLE IF NOT EXISTS clientes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(80)  NOT NULL,
  apellido     VARCHAR(80)  NOT NULL DEFAULT '',
  dni          VARCHAR(15)  NULL,
  fecha_nac    DATE NULL,
  telefono     VARCHAR(40)  NULL,
  email        VARCHAR(120) NULL,
  direccion    VARCHAR(200) NULL,
  antecedentes TEXT NULL COMMENT 'Alergias, medicación, embarazo, contraindicaciones',
  notas        TEXT NULL COMMENT 'Preferencias, cómo nos conoció, etc.',
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  creado       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_nombre (apellido, nombre),
  KEY idx_tel (telefono),
  KEY idx_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Caja ----------
CREATE TABLE IF NOT EXISTS medios_pago (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(50) NOT NULL,
  es_efectivo TINYINT(1) NOT NULL DEFAULT 0,
  orden       INT NOT NULL DEFAULT 0,
  activo      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cajas (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  fecha_apertura      DATETIME NOT NULL,
  usuario_apertura_id INT NOT NULL,
  monto_inicial       DECIMAL(12,2) NOT NULL DEFAULT 0,
  fecha_cierre        DATETIME NULL,
  usuario_cierre_id   INT NULL,
  efectivo_esperado   DECIMAL(12,2) NULL,
  efectivo_contado    DECIMAL(12,2) NULL,
  observaciones       TEXT NULL,
  estado              ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingresos/egresos manuales (compra de insumos, retiro, cambio, etc.)
CREATE TABLE IF NOT EXISTS caja_movimientos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  caja_id       INT NOT NULL,
  fecha         DATETIME NOT NULL,
  tipo          ENUM('ingreso','egreso') NOT NULL,
  medio_pago_id INT NOT NULL,
  monto         DECIMAL(12,2) NOT NULL,
  concepto      VARCHAR(200) NOT NULL,
  usuario_id    INT NOT NULL,
  KEY idx_caja (caja_id),
  CONSTRAINT fk_cm_caja FOREIGN KEY (caja_id) REFERENCES cajas(id),
  CONSTRAINT fk_cm_mp FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cobros ----------
CREATE TABLE IF NOT EXISTS cobros (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  caja_id          INT NOT NULL,
  numero           INT NOT NULL COMMENT 'Número de recibo correlativo',
  fecha            DATETIME NOT NULL,
  cliente_id       INT NULL,
  subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento        DECIMAL(12,2) NOT NULL DEFAULT 0,
  total            DECIMAL(12,2) NOT NULL DEFAULT 0,
  vuelto           DECIMAL(12,2) NOT NULL DEFAULT 0,
  observaciones    VARCHAR(255) NULL,
  estado           ENUM('valido','anulado') NOT NULL DEFAULT 'valido',
  motivo_anulacion VARCHAR(255) NULL,
  usuario_id       INT NOT NULL,
  KEY idx_caja (caja_id),
  KEY idx_fecha (fecha),
  KEY idx_cliente (cliente_id),
  UNIQUE KEY uk_numero (numero),
  CONSTRAINT fk_c_caja FOREIGN KEY (caja_id) REFERENCES cajas(id),
  CONSTRAINT fk_c_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué se cobró: turnos u otros conceptos (productos, seña, etc.)
CREATE TABLE IF NOT EXISTS cobro_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  cobro_id        INT NOT NULL,
  turno_id        INT NULL,
  descripcion     VARCHAR(150) NOT NULL,
  cantidad        INT NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(12,2) NOT NULL,
  subtotal        DECIMAL(12,2) NOT NULL,
  KEY idx_cobro (cobro_id),
  KEY idx_turno (turno_id),
  CONSTRAINT fk_ci_cobro FOREIGN KEY (cobro_id) REFERENCES cobros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cómo se pagó: puede dividirse en varios medios (ej. parte efectivo, parte transferencia)
CREATE TABLE IF NOT EXISTS cobro_pagos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  cobro_id      INT NOT NULL,
  medio_pago_id INT NOT NULL,
  monto         DECIMAL(12,2) NOT NULL,
  KEY idx_cobro (cobro_id),
  CONSTRAINT fk_cp_cobro FOREIGN KEY (cobro_id) REFERENCES cobros(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_mp FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Turnos ----------
CREATE TABLE IF NOT EXISTS turnos (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id       INT NOT NULL,
  profesional_id   INT NOT NULL,
  servicio_id      INT NULL,
  servicio_nombre  VARCHAR(100) NOT NULL COMMENT 'Copia del nombre al dar el turno',
  cabina_id        INT NULL,
  inicio           DATETIME NOT NULL,
  fin              DATETIME NOT NULL,
  precio           DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado           ENUM('reservado','confirmado','en_atencion','atendido','ausente','cancelado') NOT NULL DEFAULT 'reservado',
  origen           ENUM('interno','online') NOT NULL DEFAULT 'interno',
  notas            VARCHAR(255) NULL COMMENT 'Aclaraciones de la reserva',
  notas_sesion     TEXT NULL COMMENT 'Lo que anota el profesional: parámetros, productos, evolución',
  cobro_id         INT NULL,
  motivo_cancelado VARCHAR(255) NULL,
  recordado        DATETIME NULL COMMENT 'Cuándo se envió el recordatorio',
  usuario_id       INT NULL,
  creado           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado      DATETIME NULL,
  KEY idx_prof_inicio (profesional_id, inicio),
  KEY idx_cab_inicio (cabina_id, inicio),
  KEY idx_inicio (inicio),
  KEY idx_cliente (cliente_id, inicio),
  KEY idx_cobro (cobro_id),
  CONSTRAINT fk_t_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_t_prof FOREIGN KEY (profesional_id) REFERENCES profesionales(id),
  CONSTRAINT fk_t_serv FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  CONSTRAINT fk_t_cab FOREIGN KEY (cabina_id) REFERENCES cabinas(id) ON DELETE SET NULL,
  CONSTRAINT fk_t_cobro FOREIGN KEY (cobro_id) REFERENCES cobros(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
--  Datos base
-- =============================================================
INSERT INTO configuracion (clave, valor) VALUES
 ('db_version', '1'),
 ('nombre_centro', 'Mi Centro de Estética'),
 ('direccion', ''),
 ('telefono', ''),
 ('agenda_desde', '08:00'),
 ('agenda_hasta', '21:00'),
 ('agenda_intervalo', '15'),
 ('pie_recibo', '¡Gracias por elegirnos!'),
 ('msj_recordatorio', 'Hola {nombre}! Te recordamos tu turno de {servicio} el {dia} a las {hora} hs en {centro}. Si no podés venir avisanos por favor. ¡Te esperamos!');

INSERT INTO medios_pago (nombre, es_efectivo, orden) VALUES
 ('Efectivo', 1, 1),
 ('Transferencia', 0, 2),
 ('Mercado Pago', 0, 3),
 ('Débito', 0, 4),
 ('Crédito', 0, 5);

-- Usuario inicial: admin / admin123  (el instalador le pone la contraseña elegida)
INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES
 ('Administrador', 'admin', '$2y$10$a38VaQ8bd96UD.v1Dd5jieFyw6zLV0HXzHr639u07ACJpGmc1KRJO', 'admin');
