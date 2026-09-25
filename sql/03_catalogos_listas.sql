-- Catalogos de listas desplegables (paciente, admision, diagnostico, prescripcion, egreso, remision)
-- Generado desde sihosrecuperacion (copia de SIHOS) el 2026-09-25.
-- MISMOS nombres y columnas que SIHOS. Solo estructura, sin datos. Se llenan desde SIHOS.

SET NAMES latin1;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';  -- SIHOS usa fechas 0000-00-00
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `CausSali`;
CREATE TABLE `CausSali` (
  `CodiCaus` int(1) NOT NULL auto_increment,
  `NombCaus` varchar(20) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiCaus`),
  KEY `idx_caussali` (`CodiCaus`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiBarr`;
CREATE TABLE `CodiBarr` (
  `CodiBarr` char(3) NOT NULL DEFAULT '' COMMENT 'Codigo Barrios',
  `CodiComu` char(2) NOT NULL DEFAULT '' COMMENT 'Codigo Comuna',
  `CodiMuni` char(3) NOT NULL DEFAULT '' COMMENT 'Codigo Municipio',
  `CodiDepa` char(2) NOT NULL DEFAULT '' COMMENT 'Codigo Departamento',
  `CodiZona` char(1) NOT NULL DEFAULT '' COMMENT 'Codigo Zona Urbana/Rural CodiZona',
  `NombBarr` varchar(50) NOT NULL DEFAULT '' COMMENT 'Nombre del Barrio',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiBarr`,`CodiComu`,`CodiMuni`,`CodiDepa`,`CodiZona`),
  KEY `CodiComu` (`CodiComu`,`CodiMuni`,`CodiDepa`,`CodiZona`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiComu`;
CREATE TABLE `CodiComu` (
  `CodiComu` char(2) NOT NULL COMMENT 'Codigo Comuna',
  `CodiMuni` char(3) NOT NULL COMMENT 'Codigo Municipio de CodiMuni',
  `CodiDepa` char(2) NOT NULL COMMENT 'Codigo Departamento de CodiDepa',
  `CodiZona` char(1) NOT NULL COMMENT 'Codigo Zona de CodiZona',
  `NombComu` varchar(50) NOT NULL COMMENT 'Nombre de la Comuna',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiComu`,`CodiMuni`,`CodiDepa`,`CodiZona`),
  KEY `idx_CodiComu_CodiMuni` (`CodiDepa`,`CodiMuni`),
  KEY `idx_CodiComu_CodiZona` (`CodiZona`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiCons`;
CREATE TABLE `CodiCons` (
  `CodiInst` varchar(12) NOT NULL DEFAULT '868650001001' COMMENT 'Codigo Institucion',
  `CodiCons` varchar(5) NOT NULL DEFAULT '' COMMENT 'Codigo Consultorio',
  `NombCons` varchar(50) DEFAULT NULL COMMENT 'Nombre Consultorio',
  `UrgeCons` int(1) DEFAULT '0' COMMENT '0 = No es consultorio de urgencias; 1 = Es consultorio de urgencias',
  `EstaCons` int(1) DEFAULT NULL COMMENT 'Estado del Consultorio',
  `UsuaAsis` varchar(20) DEFAULT '' COMMENT 'Usuario Asistencial por Defecto para el Consultorio',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiInst`,`CodiCons`),
  KEY `idx_CodiCons` (`CodiCons`),
  KEY `idx_CodiCons_EstaCons` (`EstaCons`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiEsco`;
CREATE TABLE `CodiEsco` (
  `CodiEsco` int(1) NOT NULL DEFAULT '0',
  `NombEsco` varchar(40) DEFAULT NULL,
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiEsco`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiEsta`;
CREATE TABLE `CodiEsta` (
  `CodiEsta` int(1) NOT NULL auto_increment,
  `NombEsta` varchar(50) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiEsta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiSexo`;
CREATE TABLE `CodiSexo` (
  `CodiSexo` char(1) NOT NULL DEFAULT '',
  `sexo_id` bigint(20) DEFAULT NULL COMMENT 'Identificador del sexo (FK sexos.id)',
  `NombSexo` varchar(30) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiSexo`),
  KEY `idx_codisexo_sexo_id` (`sexo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiSino`;
CREATE TABLE `CodiSino` (
  `CodiSino` int(1) NOT NULL DEFAULT '0',
  `NombSino` varchar(20) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiSino`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiTiem`;
CREATE TABLE `CodiTiem` (
  `CodiTiem` int(1) unsigned NOT NULL auto_increment,
  `codigo` char(3) DEFAULT NULL COMMENT 'codigo de sispro',
  `NombTiem` varchar(20) NOT NULL DEFAULT '',
  `activo` tinyint(1) DEFAULT '1' COMMENT '1 activo 0 inactivo',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTiem`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiUnid`;
CREATE TABLE `CodiUnid` (
  `CodiUnid` char(2) NOT NULL COMMENT 'Codigo de Unidad',
  `NombUnid` varchar(30) NOT NULL COMMENT 'Nombre de Unidad',
  `TipoUnid` int(1) NOT NULL COMMENT 'Tipo de Unidad',
  `ViaAdmi` int(1) NOT NULL DEFAULT '1',
  `UnidSisPro` int(3) DEFAULT '0' COMMENT 'Unidad Sispro',
  `UPR` int(1) DEFAULT '0' COMMENT 'codigo de homologacion de sispro',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiUnid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `CodiZona`;
CREATE TABLE `CodiZona` (
  `CodiZona` char(1) NOT NULL DEFAULT '',
  `NombZona` varchar(10) NOT NULL DEFAULT '',
  `CodiSISPRO` char(2) DEFAULT NULL,
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiZona`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `DestSali`;
CREATE TABLE `DestSali` (
  `CodiDest` char(2) NOT NULL,
  `NombDest` varchar(50) NOT NULL,
  `Activo` int(1) NOT NULL DEFAULT '1',
  `IdCodiDestSis` char(2) DEFAULT NULL COMMENT 'Id que referencia la misma tabla el campo codidest para homologarlo',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiDest`),
  KEY `idx_destsali` (`CodiDest`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `EstaCivi`;
CREATE TABLE `EstaCivi` (
  `CodiEsta` char(1) NOT NULL DEFAULT '' COMMENT 'Codigo del Estado Civil',
  `NombEsta` varchar(20) NOT NULL DEFAULT '' COMMENT 'Nombre del Estado Civil',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiEsta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `EstaSali`;
CREATE TABLE `EstaSali` (
  `Codigo` int(1) NOT NULL auto_increment,
  `EstaSaliSisPro` char(2) DEFAULT NULL,
  `EstaSali` varchar(30) DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`Codigo`),
  KEY `EstaSaliSisPro` (`EstaSaliSisPro`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `FinaCons`;
CREATE TABLE `FinaCons` (
  `CodiFina` char(2) NOT NULL,
  `NombFina` varchar(80) NOT NULL,
  `FinaConsSisPro` int(2) DEFAULT NULL,
  `Abreviat` varchar(3) DEFAULT NULL,
  `SexoFina` char(1) DEFAULT NULL,
  `EdadMini` int(3) DEFAULT NULL,
  `EdadMaxi` int(3) DEFAULT NULL,
  `Formular` varchar(50) NOT NULL,
  `GuiaAten` varchar(120) DEFAULT '' COMMENT 'Indica el archivo de Guia de Atencion configurado en la Finalidad',
  `PendLiqu` int(11) DEFAULT '0' COMMENT 'Pendientes por Liquidar',
  `CodiDiag` char(8) DEFAULT 'Z000' COMMENT 'Codigo de diagnostico',
  `CitaWhat` int(1) DEFAULT '0',
  `Activo` int(1) DEFAULT '1',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiFina`),
  KEY `FinaConsSisPro` (`FinaConsSisPro`),
  KEY `idx_FinaCons_CodiDiag` (`CodiDiag`),
  KEY `idx_FinaCons_SexoFina` (`SexoFina`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `FinaProc`;
CREATE TABLE `FinaProc` (
  `CodiFina` int(1) NOT NULL auto_increment,
  `NombFina` varchar(50) NOT NULL DEFAULT '',
  `FinaProcSispro` int(2) DEFAULT '0' COMMENT 'Codigo homologado para la finalidad del procedimiento segun SISPRO',
  `Activo` int(1) NOT NULL DEFAULT '1',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiFina`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `GrupAten`;
CREATE TABLE `GrupAten` (
  `CodiGrup` char(4) NOT NULL,
  `NombGrup` varchar(150) NOT NULL DEFAULT '',
  `CodiCirc` char(2) DEFAULT NULL COMMENT 'Codigo Circular 003 MinSalud (homologacion FUR)',
  `ValoDefe` int(1) NOT NULL DEFAULT '0',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  `CodiHL7group` int(2) DEFAULT '0',
  PRIMARY KEY (`CodiGrup`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ModaSoli`;
CREATE TABLE `ModaSoli` (
  `CodiModa` int(2) NOT NULL,
  `NombModa` varchar(255) NOT NULL,
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiModa`),
  KEY `CodiModa` (`CodiModa`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `MotiRemi`;
CREATE TABLE `MotiRemi` (
  `CodiMoti` int(2) NOT NULL,
  `NombMoti` varchar(255) NOT NULL,
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiMoti`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `Parentes`;
CREATE TABLE `Parentes` (
  `CodiPare` int(2) NOT NULL auto_increment,
  `codigo` char(3) DEFAULT NULL COMMENT 'codigo de homologacion',
  `NombPare` varchar(100) NOT NULL,
  `activo` int(1) NOT NULL DEFAULT '0' COMMENT '1 si 0 no',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiPare`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `PertEtni`;
CREATE TABLE `PertEtni` (
  `CodiPert` int(2) NOT NULL COMMENT 'Codigo de la Pertenencia Etnica',
  `NombPert` varchar(100) DEFAULT NULL COMMENT 'Nombre de la Pertenencia Etnica',
  `FechDigi` date NOT NULL COMMENT 'Fecha Digitacion',
  `HoraDigi` time NOT NULL COMMENT 'Hora Digitacion',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL COMMENT 'Fecha Modificacion',
  `HoraModi` time NOT NULL COMMENT 'Hora Modificacion',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  `CodiRazaHl7` int(2) DEFAULT '0',
  PRIMARY KEY (`CodiPert`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoAcom`;
CREATE TABLE `TipoAcom` (
  `CodiTipo` int(1) NOT NULL auto_increment,
  `NombTipo` varchar(30) NOT NULL DEFAULT '',
  `ValoDefe` int(1) NOT NULL DEFAULT '0',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoAfil`;
CREATE TABLE `TipoAfil` (
  `CodiTipo` char(1) NOT NULL DEFAULT '',
  `NombTipo` varchar(15) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoAten`;
CREATE TABLE `TipoAten` (
  `CodiAten` int(1) NOT NULL auto_increment,
  `NombAten` varchar(30) NOT NULL DEFAULT '',
  `TipoAtenSisPro` char(2) DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiAten`),
  KEY `TipoAtenSisPro` (`TipoAtenSisPro`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoCama`;
CREATE TABLE `TipoCama` (
  `TipoCama` int(11) NOT NULL,
  `NombCama` varchar(40) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`TipoCama`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoDiag`;
CREATE TABLE `TipoDiag` (
  `CodiDiag` int(1) NOT NULL auto_increment,
  `NombDiag` varchar(25) NOT NULL DEFAULT '0',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiDiag`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoDisc`;
CREATE TABLE `TipoDisc` (
  `CodiTipo` varchar(30) NOT NULL DEFAULT '' COMMENT 'Codigo del Tipo de Discapacidad',
  `CateDisc` char(2) DEFAULT NULL COMMENT 'Homologación categoría discapacidad (FK categorias_discapacidades.id)',
  `NombDisc` varchar(200) NOT NULL COMMENT 'Nombre delTipo de Discapacidad',
  `activo` int(1) DEFAULT NULL COMMENT 'Estado: 1=Activo, 0=Inactivo',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00' COMMENT 'Fecha Digitacion',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00' COMMENT 'Hora Digitacion',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL COMMENT 'Fecha Modificacion',
  `HoraModi` time NOT NULL COMMENT 'Hora Modificacion',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoDocu`;
CREATE TABLE `TipoDocu` (
  `CodiTipo` char(2) NOT NULL,
  `NombTipo` varchar(50) DEFAULT '',
  `LongMini` int(2) DEFAULT '0' COMMENT 'Longitud minima del documento',
  `LongMaxi` int(2) DEFAULT '0' COMMENT 'Longitud maxima del documento',
  `EdadMini` int(3) DEFAULT '0' COMMENT 'Edad minima del documento',
  `EdadMaxi` int(3) DEFAULT '0' COMMENT 'Edad maxima del documento',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoEgre`;
CREATE TABLE `TipoEgre` (
  `CodiTipo` int(1) NOT NULL COMMENT 'Codigo del tipo de egreso',
  `NombTipo` varchar(30) DEFAULT NULL COMMENT 'Nombre del Tipo de egreso',
  `FechDigi` date NOT NULL COMMENT 'Fecha Digitacion',
  `HoraDigi` time NOT NULL COMMENT 'Hora Digitacion',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL COMMENT 'Fecha Modificacion',
  `HoraModi` time NOT NULL COMMENT 'Hora Modificacion',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoInca`;
CREATE TABLE `TipoInca` (
  `CodiTipo` int(1) NOT NULL DEFAULT '1',
  `NombTipo` varchar(30) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoMedi`;
CREATE TABLE `TipoMedi` (
  `CodiTipo` int(1) NOT NULL auto_increment,
  `NombTipo` varchar(30) DEFAULT NULL,
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoNota`;
CREATE TABLE `TipoNota` (
  `CodiTipo` int(2) NOT NULL DEFAULT '0',
  `NombTipo` varchar(50) NOT NULL DEFAULT '',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `TipoSign`;
CREATE TABLE `TipoSign` (
  `Id` int(2) NOT NULL auto_increment COMMENT 'Id Unico',
  `Nombre` varchar(30) NOT NULL COMMENT 'Nombre del Tipo de Signo Vital',
  `Grafica` varchar(30) DEFAULT '' COMMENT 'Grafica por Defecto',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `UnidFunc`;
CREATE TABLE `UnidFunc` (
  `CodiUnid` int(2) NOT NULL DEFAULT '0' COMMENT 'Codigo de unidad funcional',
  `SiglUnid` varchar(8) NOT NULL COMMENT 'Sigla de la Unidad Funcional',
  `NombUnid` varchar(60) NOT NULL COMMENT 'Nombre de la Unidad Funcional',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00' COMMENT 'Fecha digitacion',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00' COMMENT 'Hora digitacion',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00' COMMENT 'Fecha modificacion',
  `HoraModi` time NOT NULL DEFAULT '00:00:00' COMMENT 'Hora modificacion',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiUnid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `UnidMedi`;
CREATE TABLE `UnidMedi` (
  `CodiUnid` int(2) NOT NULL auto_increment,
  `NombUnid` varchar(20) NOT NULL COMMENT 'Nombre de la Unidad',
  `ViaAdmi` int(1) NOT NULL DEFAULT '1' COMMENT 'Via de Administracion',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiUnid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ViaAdmi`;
CREATE TABLE `ViaAdmi` (
  `CodiVia` int(1) NOT NULL auto_increment,
  `codigo` char(3) DEFAULT NULL COMMENT 'codigo de sispro',
  `NombVia` varchar(30) NOT NULL DEFAULT '' COMMENT 'Nombre de la Via de Administracion',
  `activo` tinyint(1) DEFAULT '1' COMMENT '1 activo 0 inactivo',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiVia`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ViaIngre`;
CREATE TABLE `ViaIngre` (
  `CodiVia` int(1) NOT NULL auto_increment,
  `ViaIngreSisPro` char(2) DEFAULT NULL,
  `NombVia` varchar(30) NOT NULL,
  `ValoDefe` int(1) NOT NULL DEFAULT '0',
  `FechDigi` date NOT NULL DEFAULT '0000-00-00',
  `HoraDigi` time NOT NULL DEFAULT '00:00:00',
  `UsuaDigi` varchar(12) DEFAULT '' COMMENT 'Usuario Digito',
  `FechModi` date NOT NULL DEFAULT '0000-00-00',
  `HoraModi` time NOT NULL DEFAULT '00:00:00',
  `UsuaModi` varchar(12) DEFAULT '' COMMENT 'Usuario Modifico',
  PRIMARY KEY (`CodiVia`),
  KEY `ViaIngreSisPro` (`ViaIngreSisPro`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

SET FOREIGN_KEY_CHECKS=1;
