-- =====================================================================================================
-- Catálogo HoraApli: "Cada" de la prescripción hospitalaria (DetaPres.HoraApli, comentario de la columna:
-- "Frecuencia de Aplicacion - Cada - Tabla HoraApli"). Verificado con el Claude local (28/09/2026): 0 = AHORA,
-- 1..24 = horas; máximo 24 horas ("No es posible prescribir para mas de 24 Horas").
--
-- PROVISIONAL: no se tiene la estructura real de la tabla en SIHOS. Las COLUMNAS CodiHora / NombHora son un supuesto
-- (por eso este archivo NO está en la lista de catálogos que se copian de SIHOS: bin/actualizar_catalogos.php solo copia
-- sql/02, 03 y 04). Antes de producción: SHOW CREATE TABLE HoraApli y SELECT * FROM HoraApli en SIHOS
-- (docs/consultas_sihos.sql) y reemplazar. El valor que se guarda en DetaPres.HoraApli es el número de horas (0..24).
-- Se crea con IF NOT EXISTS: correr este archivo no borra datos.
-- =====================================================================================================

CREATE TABLE IF NOT EXISTS `HoraApli` (
  `CodiHora` int(2) NOT NULL COMMENT 'Horas entre aplicaciones (0 = AHORA)',
  `NombHora` varchar(20) NOT NULL DEFAULT '' COMMENT 'Nombre que se muestra en "Cada"',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '',
  PRIMARY KEY (`CodiHora`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `HoraApli` (`CodiHora`, `NombHora`) VALUES
 (0, 'AHORA'), (1, '1 HORA'), (2, '2 HORAS'), (3, '3 HORAS'), (4, '4 HORAS'), (5, '5 HORAS'), (6, '6 HORAS'),
 (7, '7 HORAS'), (8, '8 HORAS'), (9, '9 HORAS'), (10, '10 HORAS'), (11, '11 HORAS'), (12, '12 HORAS'), (13, '13 HORAS'),
 (14, '14 HORAS'), (15, '15 HORAS'), (16, '16 HORAS'), (17, '17 HORAS'), (18, '18 HORAS'), (19, '19 HORAS'),
 (20, '20 HORAS'), (21, '21 HORAS'), (22, '22 HORAS'), (23, '23 HORAS'), (24, '24 HORAS');
