-- =====================================================================================================
-- ###  PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS antes de producción  ###
-- =====================================================================================================
-- Catálogos de permisos de SIHOS para armar la barra de pestañas por usuario (docs/RECORRIDO_SIHOS.md §0.1).
-- Estas 4 tablas NO estaban en las estructuras copiadas de SIHOS y NO conocemos sus columnas exactas.
--
--   * Confirmado por el recorrido: ModuObje.Orden y CodiObje (código del objeto/pestaña).
--   * NO confirmado (marcadores mínimos para que el código funcione): Objetos.NombObje, ModuObje.CodiModu,
--     UsuaGrup.Login, UsuaGrup.CodiGrup, Permisos.CodiGrup, Permisos.CodiObje.
--
-- Pasos cuando lleguen las estructuras reales (docs/consultas_sihos.sql, sección 1):
--   1. Reemplazar cada CREATE TABLE de este archivo por el SHOW CREATE TABLE de SIHOS (mismos nombres).
--   2. Ajustar la consulta de pestanas_usuario() en src/formulario.php si las columnas son otras.
--   3. Correr bin/actualizar_catalogos.php --tablas=UsuaGrup,Permisos,ModuObje,Objetos
--
-- Mientras estén vacías, CRADOR-HC usa la lista fija de pestañas por módulo (pestanas_lista()).
-- Se crean con IF NOT EXISTS: correr este archivo no borra datos ya copiados.
-- =====================================================================================================

CREATE TABLE IF NOT EXISTS `Objetos` (
  `CodiObje` int(11) NOT NULL COMMENT 'Codigo del objeto (pestana) - confirmado',
  `NombObje` varchar(100) NOT NULL DEFAULT '' COMMENT 'PROVISIONAL: nombre de la columna sin confirmar',
  PRIMARY KEY (`CodiObje`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS';

CREATE TABLE IF NOT EXISTS `ModuObje` (
  `CodiModu` int(2) NOT NULL COMMENT 'PROVISIONAL: nombre de la columna sin confirmar',
  `CodiObje` int(11) NOT NULL COMMENT 'Codigo del objeto - confirmado',
  `Orden` int(11) NOT NULL DEFAULT '0' COMMENT 'Orden de la pestana - confirmado',
  KEY `idx_ModuObje_modu` (`CodiModu`, `CodiObje`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS';

CREATE TABLE IF NOT EXISTS `UsuaGrup` (
  `Login` varchar(12) NOT NULL COMMENT 'PROVISIONAL: nombre de la columna sin confirmar',
  `CodiGrup` int(11) NOT NULL COMMENT 'PROVISIONAL: nombre de la columna sin confirmar',
  KEY `idx_UsuaGrup_login` (`Login`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS';

CREATE TABLE IF NOT EXISTS `Permisos` (
  `CodiGrup` int(11) NOT NULL COMMENT 'PROVISIONAL: nombre de la columna sin confirmar',
  `CodiObje` int(11) NOT NULL COMMENT 'Codigo del objeto - confirmado',
  KEY `idx_Permisos_grup` (`CodiGrup`, `CodiObje`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='PROVISIONAL: reemplazar por SHOW CREATE TABLE de SIHOS';
