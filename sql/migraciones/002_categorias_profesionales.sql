-- =============================================================
--  Versión 2: categorías de profesionales y asignación automática
--  Se aplica sola al abrir la app (ver inc/migraciones.php).
-- =============================================================

-- Un profesional puede estar en varias categorías (las mismas de los servicios)
CREATE TABLE IF NOT EXISTS profesional_categorias (
  profesional_id INT NOT NULL,
  categoria_id   INT NOT NULL,
  PRIMARY KEY (profesional_id, categoria_id),
  KEY idx_cat (categoria_id),
  CONSTRAINT fk_pc_prof FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_cat FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- De entrada, cada profesional queda en las categorías de los servicios que ya hace
INSERT IGNORE INTO profesional_categorias (profesional_id, categoria_id)
SELECT DISTINCT sp.profesional_id, s.categoria_id
  FROM servicio_profesional sp JOIN servicios s ON s.id = sp.servicio_id
 WHERE s.categoria_id IS NOT NULL;

-- Turnos dados a "cualquier profesional libre" de una categoría
ALTER TABLE turnos ADD COLUMN asignado_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER origen;
