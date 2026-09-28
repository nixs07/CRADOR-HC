-- =====================================================================================================
-- Tablas clínicas que faltaban para que los Antecedentes no queden en gris (docs/VALIDACIONES_SIHOS.md §1 y
-- docs/RESULTADO_CONSULTAS_SIHOS.md §1). Se llenan en la contingencia y se cargan a SIHOS en la fase 3.
-- Mismos nombres de tabla y de columnas que SIHOS. Se crean con IF NOT EXISTS.
-- =====================================================================================================

-- RecoMedi (Reconciliación Medicamentosa): estructura REAL de SIHOS. `id` lo asigna el AUTO_INCREMENT
-- (al cargar a SIHOS se deja al AUTO_INCREMENT de SIHOS).
CREATE TABLE IF NOT EXISTS `RecoMedi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `CodiInst` varchar(12) DEFAULT NULL,
  `ConsAdmi` varchar(12) DEFAULT NULL,
  `NombSumi` varchar(200) DEFAULT NULL,
  `CantSumi` decimal(20,2) DEFAULT NULL,
  `ViaAdmin` int(1) DEFAULT NULL,
  `FrecApli` int(2) DEFAULT NULL,
  `NotaSumi` varchar(300) DEFAULT NULL,
  `FechDigi` date DEFAULT NULL, `HoraDigi` time DEFAULT NULL, `UsuaDigi` varchar(12) DEFAULT NULL,
  `FechModi` date DEFAULT NULL, `HoraModi` time DEFAULT NULL, `UsuaModi` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_RecoMedi_admi` (`CodiInst`, `ConsAdmi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- comu_antecedentes_multiples: lo "múltiple" de cada antecedente, ligado a Antecede.id (antecedente_id).
-- COLUMNAS del documento (confirmadas); TIPOS POR CONFIRMAR con SHOW CREATE TABLE antes de producción.
-- tipo_antecedente_id (valores reales): 34 Familiares, 35 Alérgicos, 36 Factor de riesgo, 128 Farmacológicos,
-- 500 Patológicos (ventana), 501 Obstétricos (ventana).
CREATE TABLE IF NOT EXISTS `comu_antecedentes_multiples` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `antecedente_id` bigint(20) DEFAULT NULL,
  `tipo_antecedente_id` bigint(20) unsigned DEFAULT NULL,
  `parentesco_id` int(2) DEFAULT NULL,
  `diagnostico_id` bigint(20) DEFAULT NULL,
  `tipo_alergia_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_medicamento_id` int(3) DEFAULT NULL,
  `factor_riesgo_id` bigint(20) unsigned DEFAULT NULL,
  `farmacologico_id` int(3) DEFAULT NULL,
  `preguntas_antecedentes_id` bigint(20) unsigned DEFAULT NULL,
  `respuesta_id` bigint(20) unsigned DEFAULT NULL,
  `descripcion` text,
  `activo` int(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL, `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cam_antecedente` (`antecedente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
