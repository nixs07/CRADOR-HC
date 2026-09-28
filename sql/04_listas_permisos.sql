-- =====================================================================================================
-- Catálogos de SIHOS que faltaban: permisos (barra de pestañas por usuario), listas genéricas, Estado Ingreso e
-- Instituciones de Remisión. Estructuras REALES tomadas de docs/RESULTADO_CONSULTAS_SIHOS.md (SIHOS producción,
-- 26/09/2026). Se copian desde SIHOS con bin/actualizar_catalogos.php (no tienen datos de pacientes).
--
-- POR CONFIRMAR (el documento no trae los tipos exactos): priv_listas_tipos y priv_listas_elementos. Sus COLUMNAS
-- son las del documento; los TIPOS son una aproximación. Antes de producción, reemplazar por su SHOW CREATE TABLE.
-- Las columnas de auditoría de InstRemi y EstaIngr ("+ auditoría") se suponen las de siempre (FechDigi...UsuaModi).
-- Se crean con IF NOT EXISTS: correr este archivo no borra datos ya copiados.
-- =====================================================================================================

CREATE TABLE IF NOT EXISTS `UsuaGrup` (
  `CC` varchar(15) DEFAULT NULL,
  `Login` varchar(12) NOT NULL,
  `CodiGrup` int(3) NOT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`Login`, `CodiGrup`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `Permisos` (
  `CodiGrup` int(3) NOT NULL,
  `CodiModu` int(3) NOT NULL,
  `CodiObje` int(3) NOT NULL,
  `Valor` char(10) DEFAULT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`CodiGrup`, `CodiModu`, `CodiObje`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Un objeto puede tener VARIAS filas (varios Orden): la barra usa MIN(Orden); Orden >= 99 son reportes (se excluyen)
CREATE TABLE IF NOT EXISTS `ModuObje` (
  `CodiModu` int(2) NOT NULL,
  `CodiObje` int(3) NOT NULL,
  `Orden` int(3) NOT NULL,
  `OrdeImpr` int(3) DEFAULT NULL, `EsEpicri` int(1) DEFAULT NULL, `ImpFiHCE` int(1) DEFAULT NULL,
  `ReCaOrde` int(1) DEFAULT NULL, `ReCaFact` int(1) DEFAULT NULL, `ReCaLibr` int(1) DEFAULT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`CodiModu`, `CodiObje`, `Orden`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `Objetos` (
  `CodiObje` int(3) NOT NULL,
  `NombObje` varchar(50) DEFAULT NULL,
  `DescPagi` varchar(100) DEFAULT NULL, `UrlPagin` varchar(50) DEFAULT NULL, `UrlImpri` varchar(50) DEFAULT NULL,
  `Iso` varchar(30) DEFAULT NULL, `Version` varchar(30) DEFAULT NULL, `Fecha` date DEFAULT NULL,
  `TipoObje` int(2) DEFAULT NULL, `ClasObje` int(2) DEFAULT NULL, `CodiModu` int(3) DEFAULT NULL,
  `Activo` int(1) DEFAULT NULL, `ObjeHCED` int(1) DEFAULT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`CodiObje`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Estado Ingreso (Admision.EstaIngr): 1 Conciente (defecto) · 2 Inconsciente · 3 Muerto
CREATE TABLE IF NOT EXISTS `EstaIngr` (
  `CodiEsta` int(1) NOT NULL,
  `NombEsta` varchar(30) DEFAULT NULL,
  `ValoDefe` int(1) DEFAULT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`CodiEsta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Instituciones de Remisión: Remision.InstRemi guarda CodInsRe; la lista muestra NombInst
CREATE TABLE IF NOT EXISTS `InstRemi` (
  `CodiInst` varchar(12) NOT NULL DEFAULT '',
  `CodInsRe` varchar(10) NOT NULL DEFAULT '',
  `CoinReal` varchar(12) DEFAULT NULL,
  `NombInst` varchar(60) DEFAULT NULL,
  `TipoIden` char(2) DEFAULT NULL,
  `NitInsti` varchar(20) DEFAULT NULL,
  `CodiMuni` char(3) DEFAULT NULL,
  `CodiDepa` char(2) DEFAULT NULL,
  `DireInst` varchar(40) DEFAULT NULL,
  `TeleInst` varchar(10) DEFAULT NULL,
  `MailInst` varchar(30) DEFAULT NULL,
  `TipoPers` int(2) DEFAULT NULL,
  `ActiEcon` varchar(5) DEFAULT NULL,
  `CodiNive` int(2) DEFAULT NULL,
  `CodiProc` varchar(15) DEFAULT NULL,
  `KiloMetr` int(10) DEFAULT NULL,
  `GastGaso` decimal(6,2) DEFAULT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`CodiInst`, `CodInsRe`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Listas genéricas de SIHOS (tipos: 12 entornos, 13 tipos de alergia, 14 factores de riesgo, 15 tipo antecedente,
-- 33 tipo de conducta, 45 preguntas de antecedentes, 46/47/50 Código Dorado, 49 métodos de concepción...).
-- COLUMNAS del documento; TIPOS POR CONFIRMAR ("+ timestamps" = created_at, updated_at, deleted_at).
CREATE TABLE IF NOT EXISTS `priv_listas_tipos` (
  `id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `prv_lista_tipo_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL, `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `priv_listas_elementos` (
  `id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `prv_lista_tipo_id` bigint(20) unsigned DEFAULT NULL,
  `prv_lista_elemento_id` bigint(20) unsigned DEFAULT NULL,
  `favorito` tinyint(1) DEFAULT NULL,
  `metadato` longtext,
  `activo` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL, `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_priv_listas_elementos_tipo` (`prv_lista_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
