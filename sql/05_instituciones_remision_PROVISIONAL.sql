-- =====================================================================================================
-- ###  PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS antes de producción  ###
-- =====================================================================================================
-- Catálogo "Instituciones de Remisión" de SIHOS (pestaña Remisiones, campo Institución -> Remision.InstRemi,
-- varchar(10)). NO conocemos el nombre de la tabla ni sus columnas: "InstRemision", "CodiInre" y "NombInre"
-- son MARCADORES para que la lista funcione en la contingencia.
--
-- Cuando se sepa la tabla real (docs/consultas_sihos.sql, sección 3):
--   1. Reemplazar este CREATE TABLE por el SHOW CREATE TABLE de SIHOS (con su nombre real).
--   2. Ajustar la entrada 'InstRemi' de LISTAS en src/listas.php (tabla, código y nombre).
--   3. Correr bin/actualizar_catalogos.php --tablas=<nombre real>.
-- Mientras la tabla esté vacía la lista Institución queda vacía (campo opcional).
-- =====================================================================================================

CREATE TABLE IF NOT EXISTS `InstRemision` (
  `CodiInre` varchar(10) NOT NULL COMMENT 'PROVISIONAL: codigo de la institucion (se guarda en Remision.InstRemi)',
  `NombInre` varchar(150) NOT NULL DEFAULT '' COMMENT 'PROVISIONAL: nombre de la institucion',
  PRIMARY KEY (`CodiInre`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS';
