-- Tablas de CONTROL propias de CRADOR-HC (no existen en SIHOS).
-- Todas llevan prefijo cont_ para que nunca choquen con tablas de SIHOS
-- y NUNCA se exportan a SIHOS.
-- Se crean con IF NOT EXISTS: ejecutar de nuevo este script no borra nada.

SET NAMES utf8;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------
-- Actualizacion de catalogos: una fila por cada ejecucion completa
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cont_catalogo_ejecucion` (
  `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
  `inicio`        datetime NOT NULL COMMENT 'Fecha y hora de inicio',
  `fin`           datetime DEFAULT NULL COMMENT 'Fecha y hora de fin',
  `usuario`       varchar(12) NOT NULL DEFAULT '' COMMENT 'Login que la ejecuto (CLI = tarea programada)',
  `origen`        varchar(10) NOT NULL DEFAULT 'web' COMMENT 'web / cli',
  `resultado`     varchar(10) NOT NULL DEFAULT 'en_curso' COMMENT 'en_curso / ok / parcial / error',
  `tablas_ok`     int(11) NOT NULL DEFAULT '0',
  `tablas_error`  int(11) NOT NULL DEFAULT '0',
  `filas_total`   int(11) NOT NULL DEFAULT '0',
  `mensaje`       text,
  PRIMARY KEY (`id`),
  KEY `idx_cont_catejec_inicio` (`inicio`),
  KEY `idx_cont_catejec_resultado` (`resultado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CRADOR: ejecuciones de actualizacion de catalogos';

-- ---------------------------------------------------------------------
-- Actualizacion de catalogos: una fila por cada tabla copiada
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cont_catalogo_actualizacion` (
  `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ejecucion_id`  int(11) unsigned DEFAULT NULL COMMENT 'cont_catalogo_ejecucion.id',
  `tabla`         varchar(64) NOT NULL COMMENT 'Nombre de la tabla de catalogo',
  `filas`         int(11) NOT NULL DEFAULT '0' COMMENT 'Filas copiadas',
  `fecha`         datetime NOT NULL COMMENT 'Fecha y hora en que termino la tabla',
  `usuario`       varchar(12) NOT NULL DEFAULT '',
  `resultado`     varchar(10) NOT NULL COMMENT 'ok / error / omitida',
  `segundos`      decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Duracion',
  `mensaje`       text,
  PRIMARY KEY (`id`),
  KEY `idx_cont_catact_tabla` (`tabla`,`fecha`),
  KEY `idx_cont_catact_ejecucion` (`ejecucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CRADOR: detalle por tabla de la actualizacion de catalogos';

-- ---------------------------------------------------------------------
-- Cargas a SIHOS (fase 3): una fila por admision registrada en contingencia
--   ConsAdmiTemp  = numero temporal asignado en CRADOR-HC
--   ConsAdmiSihos = numero definitivo asignado al cargar en SIHOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cont_carga_sihos` (
  `id`             int(11) unsigned NOT NULL AUTO_INCREMENT,
  `CodiInst`       varchar(12) NOT NULL DEFAULT '868650001001',
  `ConsAdmiTemp`   varchar(12) NOT NULL COMMENT 'Admision temporal (CRADOR-HC)',
  `ConsAdmiSihos`  varchar(12) DEFAULT NULL COMMENT 'Admision definitiva en SIHOS',
  `estado`         varchar(10) NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente / cargada / error',
  `fecha_registro` datetime NOT NULL COMMENT 'Cuando se creo la admision en contingencia',
  `usuario_registro` varchar(12) NOT NULL DEFAULT '',
  `fecha_carga`    datetime DEFAULT NULL COMMENT 'Ultimo intento de carga',
  `usuario_carga`  varchar(12) DEFAULT NULL,
  `intentos`       int(11) NOT NULL DEFAULT '0',
  `mensaje`        text COMMENT 'Resultado o error del ultimo intento',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cont_carga_temp` (`CodiInst`,`ConsAdmiTemp`),
  KEY `idx_cont_carga_estado` (`estado`),
  KEY `idx_cont_carga_sihos` (`ConsAdmiSihos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CRADOR: control de cargas de admisiones a SIHOS';

-- ---------------------------------------------------------------------
-- Registro de ingresos a la app (auditoria y bloqueo por intentos fallidos)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cont_acceso` (
  `id`      int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fecha`   datetime NOT NULL,
  `login`   varchar(12) NOT NULL DEFAULT '',
  `ip`      varchar(45) NOT NULL DEFAULT '',
  `exito`   tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = ingreso correcto, 0 = fallido',
  `detalle` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_cont_acceso_login` (`login`,`fecha`),
  KEY `idx_cont_acceso_ip` (`ip`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CRADOR: registro de ingresos';

-- ---------------------------------------------------------------------
-- Pacientes creados o modificados en la contingencia (fase 2).
-- Al cargar a SIHOS (fase 3): 'nuevo' -> INSERT en Paciente si no existe en SIHOS.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cont_paciente` (
  `id`       int(11) unsigned NOT NULL AUTO_INCREMENT,
  `TipoDocu` char(2) NOT NULL,
  `NumeUsua` varchar(20) NOT NULL,
  `accion`   varchar(10) NOT NULL DEFAULT 'nuevo' COMMENT 'nuevo / modificado',
  `fecha`    datetime NOT NULL,
  `usuario`  varchar(12) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_cont_paciente_doc` (`TipoDocu`,`NumeUsua`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CRADOR: pacientes creados en la contingencia';
