-- =============================================================
--  Datos de ejemplo (opcional). Correr DESPUÉS de database.sql
--  Los turnos se cargan alrededor de la fecha de hoy.
-- =============================================================
SET NAMES utf8mb4;

UPDATE configuracion SET valor = 'Lumière Estética' WHERE clave = 'nombre_centro';
UPDATE configuracion SET valor = 'Av. Cabildo 2150, CABA' WHERE clave = 'direccion';
UPDATE configuracion SET valor = '11 4785-2210' WHERE clave = 'telefono';

INSERT INTO profesionales (id, nombre, especialidad, telefono, color, orden) VALUES
 (1, 'Lucía Ferreyra', 'Cosmetóloga', '11 6120-4471', '#9b4d8a', 1),
 (2, 'Carla Benítez',  'Depilación y láser', '11 5833-9025', '#2f7d6d', 2),
 (3, 'Martín Sosa',    'Masoterapeuta', '11 4410-7782', '#c06b2c', 3),
 (4, 'Agustina Ríos',  'Manos, pies y mirada', '11 3398-1150', '#3f6fa8', 4);

-- Lunes a viernes de corrido o en dos franjas, sábados por la mañana
INSERT INTO profesional_horarios (profesional_id, dia, desde, hasta) VALUES
 (1,1,'09:00','13:00'),(1,1,'15:00','19:00'),(1,2,'09:00','13:00'),(1,2,'15:00','19:00'),(1,3,'09:00','13:00'),(1,3,'15:00','19:00'),
 (1,4,'09:00','13:00'),(1,4,'15:00','19:00'),(1,5,'09:00','13:00'),(1,5,'15:00','19:00'),(1,6,'09:00','13:00'),
 (2,1,'10:00','19:00'),(2,2,'10:00','19:00'),(2,3,'10:00','19:00'),(2,4,'10:00','19:00'),(2,5,'10:00','19:00'),(2,6,'09:00','14:00'),
 (3,2,'12:00','20:00'),(3,3,'12:00','20:00'),(3,4,'12:00','20:00'),(3,5,'12:00','20:00'),(3,6,'10:00','16:00'),
 (4,1,'09:00','17:00'),(4,3,'09:00','17:00'),(4,5,'09:00','17:00'),(4,6,'09:00','14:00');

INSERT INTO cabinas (id, nombre, orden) VALUES
 (1, 'Cabina 1', 1),
 (2, 'Cabina 2', 2),
 (3, 'Equipo láser', 3);

INSERT INTO categorias (id, nombre, orden) VALUES
 (1, 'Faciales', 1),
 (2, 'Depilación', 2),
 (3, 'Corporales y masajes', 3),
 (4, 'Manos, pies y mirada', 4);

INSERT INTO servicios (id, categoria_id, nombre, duracion, precio, cabina_id, orden) VALUES
 (1,  1, 'Limpieza facial profunda',          75, 38000, NULL, 1),
 (2,  1, 'Peeling químico',                   45, 45000, NULL, 2),
 (3,  1, 'Dermaplaning',                      45, 32000, NULL, 3),
 (4,  1, 'Hidratación facial con ácido hialurónico', 60, 36000, NULL, 4),
 (5,  2, 'Láser: rostro completo',            20, 16000, 3, 1),
 (6,  2, 'Láser: axilas',                     15, 15000, 3, 2),
 (7,  2, 'Láser: piernas completas',          45, 42000, 3, 3),
 (8,  2, 'Láser: cuerpo completo',            90, 85000, 3, 4),
 (9,  2, 'Cera: piernas completas',           40, 18000, NULL, 5),
 (10, 3, 'Masaje descontracturante 60''',     60, 32000, NULL, 1),
 (11, 3, 'Drenaje linfático',                 60, 34000, NULL, 2),
 (12, 3, 'Maderoterapia',                     50, 30000, NULL, 3),
 (13, 4, 'Manicura semipermanente',           60, 16000, NULL, 1),
 (14, 4, 'Pedicura completa',                 60, 18000, NULL, 2),
 (15, 4, 'Lifting de pestañas',               60, 25000, NULL, 3),
 (16, 4, 'Perfilado y laminado de cejas',     40, 14000, NULL, 4);

INSERT INTO servicio_profesional (servicio_id, profesional_id) VALUES
 (1,1),(2,1),(3,1),(4,1),
 (5,2),(6,2),(7,2),(8,2),(9,2),
 (10,3),(11,3),(12,3),(11,1),
 (13,4),(14,4),(15,4),(16,4),(16,1);

INSERT INTO clientes (id, nombre, apellido, dni, fecha_nac, telefono, email, antecedentes, notas) VALUES
 (1,  'Valentina', 'Gómez',     '38455120', '1994-03-12', '11 5623-8841', 'vale.gomez@gmail.com', 'Alergia a la lidocaína.', 'Prefiere turnos a la mañana.'),
 (2,  'Florencia', 'Paz',       '35120887', '1990-11-02', '11 4478-2290', NULL, NULL, 'Vino por Instagram.'),
 (3,  'Camila',    'Rodríguez', '40221334', '1997-07-21', '11 3365-1102', 'cami.rod@hotmail.com', 'Piel sensible, rosácea leve.', NULL),
 (4,  'Sofía',     'Martínez',  '33870451', '1988-01-30', '11 6012-7745', NULL, 'Toma isotretinoína hasta diciembre: no hacer peeling ni láser.', NULL),
 (5,  'Julieta',   'López',     '42115879', '1999-09-09', '11 5590-3318', NULL, NULL, NULL),
 (6,  'Mariana',   'Acosta',    '29554013', '1982-05-17', '11 4123-6650', 'mariana.acosta@yahoo.com', 'Hipertensa, medicada.', 'Paquete de drenaje: avisar cuando le queden 2 sesiones.'),
 (7,  'Lucas',     'Herrera',   '36998214', '1992-12-04', '11 3874-0912', NULL, NULL, NULL),
 (8,  'Paula',     'Giménez',   '31002457', '1985-08-25', '11 6650-2238', NULL, 'Embarazada (5 meses).', NULL),
 (9,  'Agostina',  'Torres',    '43501226', '2001-02-14', '11 2290-4417', NULL, NULL, 'Estudiante, consulta por promos.'),
 (10, 'Rocío',     'Fernández', '37745102', '1993-06-11', '11 5047-3381', 'rocio.f@gmail.com', NULL, NULL),
 (11, 'Natalia',   'Romero',    '30418876', '1983-10-03', '11 4936-5520', NULL, 'Várices en piernas: no hacer maderoterapia en miembros inferiores.', NULL),
 (12, 'Daniela',   'Suárez',    '39887120', '1996-04-27', '11 3011-8864', NULL, NULL, NULL);

-- Usuarios de prueba: recepcion / recepcion123  y  lucia / lucia123 (profesional)
INSERT INTO usuarios (nombre, usuario, password_hash, rol, profesional_id) VALUES
 ('Recepción', 'recepcion', '$2y$10$V7QgfSI/QT.iMPW7A/gLcOcHbqSB6rWQem4BVjcuczU9y163dXTOu', 'recepcion', NULL),
 ('Lucía Ferreyra', 'lucia', '$2y$10$sdkm3U6VqE3dDKTJ3SZ/suFNdweVK6H75CAZVT8Ul.12df8QmagSW', 'profesional', 1);

-- Turnos: dia = días desde hoy (0 = hoy, -7 = hace una semana)
CREATE TEMPORARY TABLE tmp_turnos (cli INT, prof INT, serv INT, dia INT, hora TIME, estado VARCHAR(20), notas_sesion TEXT NULL);
INSERT INTO tmp_turnos VALUES
 (1, 1, 1,  -14, '10:00', 'atendido', 'Piel mixta, comedones en zona T. Extracción + máscara de arcilla. Recomendé protector solar FPS 50.'),
 (4, 1, 4,  -7,  '16:00', 'atendido', 'Hidratación sin ácidos por tratamiento con isotretinoína. Buena tolerancia.'),
 (6, 3, 11, -7,  '13:00', 'atendido', 'Sesión 3 de 8. Retención en tobillos menor que la semana pasada.'),
 (2, 2, 7,  -21, '11:00', 'atendido', 'Sesión 2. Fototipo III, fluencia 18 J/cm². Sin reacciones.'),
 (9, 4, 13, -3,  '10:00', 'ausente',  NULL),
 (1, 1, 2,  0,   '09:00', 'atendido', 'Peeling mandélico 30%. Leve eritema esperado 24 h.'),
 (3, 1, 4,  0,   '10:30', 'confirmado', NULL),
 (8, 1, 1,  0,   '15:00', 'reservado', NULL),
 (2, 2, 7,  0,   '10:00', 'atendido', 'Sesión 3. Misma fluencia, buena evolución.'),
 (5, 2, 6,  0,   '11:00', 'confirmado', NULL),
 (10,2, 9,  0,   '12:00', 'reservado', NULL),
 (12,2, 5,  0,   '16:30', 'reservado', NULL),
 (6, 3, 11, 0,   '13:00', 'confirmado', NULL),
 (7, 3, 10, 0,   '15:00', 'reservado', NULL),
 (11,3, 12, 0,   '17:00', 'reservado', NULL),
 (9, 4, 13, 0,   '09:30', 'atendido', NULL),
 (10,4, 15, 0,   '11:00', 'confirmado', NULL),
 (3, 4, 16, 0,   '14:00', 'reservado', NULL),
 (4, 1, 3,  1,   '10:00', 'reservado', NULL),
 (2, 2, 8,  1,   '10:00', 'reservado', NULL),
 (6, 3, 11, 1,   '13:00', 'reservado', NULL),
 (5, 4, 14, 1,   '10:00', 'reservado', NULL),
 (7, 3, 10, 3,   '18:00', 'reservado', NULL),
 (1, 1, 1,  4,   '09:30', 'reservado', NULL),
 (12,2, 7,  4,   '15:00', 'reservado', NULL),
 (11,4, 13, 5,   '11:00', 'reservado', NULL);

INSERT INTO turnos (cliente_id, profesional_id, servicio_id, servicio_nombre, cabina_id, inicio, fin, precio, estado, notas_sesion, usuario_id, creado)
SELECT t.cli, t.prof, s.id, s.nombre, s.cabina_id,
       TIMESTAMP(CURDATE() + INTERVAL t.dia DAY, t.hora),
       TIMESTAMP(CURDATE() + INTERVAL t.dia DAY, t.hora) + INTERVAL s.duracion MINUTE,
       s.precio, t.estado, t.notas_sesion, 1,
       TIMESTAMP(CURDATE() + INTERVAL LEAST(t.dia, 0) - 2 DAY, '12:00')
  FROM tmp_turnos t JOIN servicios s ON s.id = t.serv;

DROP TEMPORARY TABLE tmp_turnos;

-- Turnos ya atendidos en días anteriores: quedan cobrados (por transferencia) en cajas cerradas
INSERT INTO cajas (fecha_apertura, usuario_apertura_id, monto_inicial, fecha_cierre, usuario_cierre_id, efectivo_esperado, efectivo_contado, estado)
SELECT DISTINCT TIMESTAMP(DATE(inicio), '08:30'), 1, 10000, TIMESTAMP(DATE(inicio), '20:30'), 1, 10000, 10000, 'cerrada'
  FROM turnos WHERE inicio < CURDATE() AND estado = 'atendido';

INSERT INTO cobros (caja_id, numero, fecha, cliente_id, subtotal, descuento, total, usuario_id)
SELECT c.id, t.id, t.fin, t.cliente_id, t.precio, 0, t.precio, 1
  FROM turnos t JOIN cajas c ON DATE(c.fecha_apertura) = DATE(t.inicio)
 WHERE t.inicio < CURDATE() AND t.estado = 'atendido';

INSERT INTO cobro_items (cobro_id, turno_id, descripcion, cantidad, precio_unitario, subtotal)
SELECT co.id, t.id, t.servicio_nombre, 1, t.precio, t.precio FROM cobros co JOIN turnos t ON t.id = co.numero;

INSERT INTO cobro_pagos (cobro_id, medio_pago_id, monto) SELECT id, 2, total FROM cobros;

UPDATE turnos t JOIN cobros co ON co.numero = t.id SET t.cobro_id = co.id;
