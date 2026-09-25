-- =====================================================================
-- DATOS DE PRUEBA (inventados). NO se cargan automaticamente.
-- NUNCA cargar en la instalacion de produccion del hospital.
--
-- Cargar (con Docker, desde la carpeta del proyecto):
--   docker compose exec db sh /crador/cargar_datos_prueba.sh
--
-- Usuarios de prueba (clave de todos: prueba123, hash MD5-crypt $1$ como SIHOS):
--   NIXON07    Administrador de prueba (rol admin por ADMIN_LOGIN)
--   MEDPRUEBA  Medico de prueba
--   ENFPRUEBA  Enfermera de prueba
--   INAPRUEBA  Usuario INACTIVO (Activo = 2): no debe poder ingresar
--
-- Se puede ejecutar varias veces: usa REPLACE y las admisiones quedan con
-- fechas relativas al dia en que se carga (CURDATE()).
-- Tablero esperado: Urgencias 3, Observacion 2, Consulta Externa 3 abiertas,
-- 6 admisiones del dia, 4 pendientes por cargar a SIHOS (1 con error, 1 cargada).
-- Pacientes 99000006, 99000007 y 99000008 no tienen admision: sirven para probar "Nueva admision".
-- =====================================================================

SET NAMES utf8;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- --- Usuarios ---------------------------------------------------------
REPLACE INTO Usuarios (CC, Login, Nombre, Nom1Usua, Ape1Usua, Password, UsuaAsis, CodiEspe, RegiProf, Activo,
                       TipoDocu, FotoFirm, huella, Foto, FechDigi, HoraDigi, UsuaDigi) VALUES
 ('900000001', 'NIXON07',   'ADMINISTRADOR DE PRUEBA', 'ADMINISTRADOR', 'PRUEBA', '$1$crAd0r01$cvGPKfSN3.AgqOIPW5n/G/', 2, NULL,  '',       1, 'CC', '', '', '', CURDATE(), CURTIME(), 'PRUEBA'),
 ('900000002', 'MEDPRUEBA', 'MEDICO GENERAL DE PRUEBA', 'MEDICO', 'PRUEBA', '$1$crAd0r02$WiPlu6XcYez4e.mCu6xtb0', 1, '001', 'RM-00001', 1, 'CC', '', '', '', CURDATE(), CURTIME(), 'PRUEBA'),
 ('900000003', 'ENFPRUEBA', 'ENFERMERA DE PRUEBA',      'ENFERMERA', 'PRUEBA', '$1$crAd0r03$NXVU3x1IlXUnwe48yDbwZ/', 1, '002', 'RE-00001', 1, 'CC', '', '', '', CURDATE(), CURTIME(), 'PRUEBA'),
 ('900000004', 'INAPRUEBA', 'USUARIO INACTIVO DE PRUEBA', 'INACTIVO', 'PRUEBA', '$1$crAd0r04$jRGEtrw.8IVCJmYyrIG.h.', 1, '001', 'RM-00002', 2, 'CC', '', '', '', CURDATE(), CURTIME(), 'PRUEBA');

-- --- Catalogos minimos --------------------------------------------------
REPLACE INTO CodiEspe (CodiEspe, NombEspe, GrupEspe) VALUES
 ('001', 'MEDICINA GENERAL', '01'),
 ('002', 'ENFERMERIA', '02');

REPLACE INTO CodiServ (CodiServ, NombServ, TipoAten, EsObserv, Activo) VALUES
 ('001', 'CONSULTA EXTERNA', 1, 0, 1),
 ('007', 'URGENCIAS', 3, 0, 1),
 ('008', 'OBSERVACION E INTERNACION', 3, 1, 1),
 ('013', 'CONSULTA EXTERNA ESPECIALIZADA', 1, 0, 1);

REPLACE INTO TipoDocu (CodiTipo, NombTipo, LongMini, LongMaxi) VALUES
 ('CC', 'CEDULA DE CIUDADANIA', 3, 10),
 ('TI', 'TARJETA DE IDENTIDAD', 10, 11),
 ('RC', 'REGISTRO CIVIL', 8, 11),
 ('CE', 'CEDULA DE EXTRANJERIA', 3, 7);

REPLACE INTO CodiSexo (CodiSexo, NombSexo) VALUES ('M', 'MASCULINO'), ('F', 'FEMENINO');

REPLACE INTO TipoUsua (CodiTipo, NombTipo) VALUES (1, 'CONTRIBUTIVO'), (2, 'SUBSIDIADO');

REPLACE INTO CodiAdmi (CodiAdmi, NombAdmi, TiDoTerc, NitAdmin, DepaAdmi, MuniAdmi, TipoAdmi, EstaAdmi, EsSoat, CodVeri) VALUES
 ('EPSP01', 'EPS DE PRUEBA SUBSIDIADO', 'NI', '800000001', '86', '865', 2, 1, 0, 0),
 ('EPSP02', 'EPS DE PRUEBA CONTRIBUTIVO', 'NI', '800000002', '86', '865', 1, 1, 0, 0);

REPLACE INTO Contrato (CodiInst, NumeCont, NumeCon1, CodiAdmi, DescCont, CodiManu, CodiMaSu, CodiPlan,
                       FechInCo, FechFiCo, EstaCont, TipoUsua, TipoCont, PlanBene, PiePagin) VALUES
 ('868650001001', 'PRU-001', 'PRU-001', 'EPSP01', 'CONTRATO DE PRUEBA SUBSIDIADO EVENTO', 'SOAT', 'SOAT', '01',
  CONCAT(YEAR(CURDATE()), '-01-01'), CONCAT(YEAR(CURDATE()), '-12-31'), 1, 2, 1, '01', ''),
 ('868650001001', 'PRU-002', 'PRU-002', 'EPSP02', 'CONTRATO DE PRUEBA CONTRIBUTIVO EVENTO', 'SOAT', 'SOAT', '01',
  CONCAT(YEAR(CURDATE()), '-01-01'), CONCAT(YEAR(CURDATE()), '-12-31'), 1, 1, 1, '01', '');

-- --- Catalogos para registrar atenciones (fase 2), con codigos reales de SIHOS --
REPLACE INTO TipoAfil (CodiTipo, NombTipo) VALUES ('A', 'ADICIONAL'), ('B', 'BENEFICIARIO'), ('C', 'COTIZANTE'), ('D', 'NO APLICA');
REPLACE INTO CodiEstr (CodiEstr, NombEstr) VALUES ('0', 'NIVEL CERO'), ('1', 'NIVEL UNO'), ('2', 'NIVEL DOS'), ('A', 'CATEGORIA A'), ('B', 'CATEGORIA B');
REPLACE INTO AdmiEstr (CodiInst, CodiAdmi, TiDoTerc, NuDoTerc, CodiEstr, TipoAten, TipoAfil, EstaEstr) VALUES
 ('868650001001', 'EPSP01', 'NI', '800000001', '1', 3, 'D', 1), ('868650001001', 'EPSP01', 'NI', '800000001', '2', 3, 'D', 1),
 ('868650001001', 'EPSP01', 'NI', '800000001', '1', 1, 'D', 1),
 ('868650001001', 'EPSP02', 'NI', '800000002', 'A', 3, 'C', 1), ('868650001001', 'EPSP02', 'NI', '800000002', 'B', 3, 'B', 1),
 ('868650001001', 'EPSP02', 'NI', '800000002', 'A', 1, 'C', 1), ('868650001001', 'EPSP02', 'NI', '800000002', 'A', 3, 'B', 1);
REPLACE INTO CodiDepa (CodiDepa, NombDepa) VALUES ('86', 'Putumayo'), ('52', 'Nariño');
REPLACE INTO CodiMuni (CodiDepa, CodiMuni, NombMuni) VALUES ('86', '001', 'MOCOA'), ('86', '568', 'PUERTO ASIS'),
 ('86', '757', 'SAN MIGUEL'), ('86', '865', 'VALLE DEL GUAMEZ HORMIGA'), ('52', '001', 'PASTO');
REPLACE INTO CodiZona (CodiZona, NombZona) VALUES ('U', 'Urbana'), ('R', 'Rural');
REPLACE INTO ViaIngre (CodiVia, NombVia, ValoDefe) VALUES (1, 'Urgencias', 0), (2, 'Consulta Externa o programada', 1), (3, 'Remitido', 0);
REPLACE INTO CausExte (CodiMoti, NombMoti, ValoDefe) VALUES ('01', 'Accidente de Trabajo', 0), ('02', 'Accidente de Transito', 0),
 ('05', 'Otro tipo de Accidente', 0), ('13', 'Enfermedad General.', 0), ('15', 'Pym - intevenciones individual', 0);
REPLACE INTO CondUsua (CodiCond, NombCond, ValoDefe) VALUES (1, 'Trimestre1', 0), (2, 'Trimestre2', 0), (3, 'Trimestre3', 0),
 (4, 'No Embarazada', 1), (5, 'No Aplica', 1);
REPLACE INTO GrupAten (CodiGrup, NombGrup, ValoDefe) VALUES ('O', 'Otros Grupos Poblacionales', 0), ('D', 'Desplazados', 0),
 ('G', 'Gestantes', 0), ('I', 'Indigena', 0), ('M', 'Migrantes', 0);
REPLACE INTO TipoAcom (CodiTipo, NombTipo, ValoDefe) VALUES (1, 'Solo', 1), (2, 'Familiar', 0), (3, 'Policia', 0), (4, 'Otro', 0);
REPLACE INTO Parentes (CodiPare, codigo, NombPare, activo) VALUES (1, '01', 'Padres', 1), (2, '02', 'Madre', 1), (5, '05', 'Hijo (a)', 1);
REPLACE INTO ClasTria (CodiTria, NombTria) VALUES (1, 'Triage I'), (2, 'Triage II'), (3, 'Triage III'), (4, 'Triage IV'), (5, 'Triage V');
REPLACE INTO CondTria (CodiTipo, NombTipo) VALUES (1, 'Urgencias'), (2, 'Prioritaria'), (3, 'Manejo en casa'), (4, 'Consulta Externa');
REPLACE INTO CodiCama (CodiInst, CodiCama, NombCama, CodiServ, Activa) VALUES
 ('868650001001', 'HOSP09', 'CAMA HOSPITALIZACION 9', '008', 1), ('868650001001', 'HOSP10', 'HOSPITALIZACION CAMA 10', '008', 1),
 ('868650001001', 'PED01', 'PEDIATRIA 1', '008', 1);
REPLACE INTO CausMorb (CodiDiag, NombCaus, Activo) VALUES ('R101', 'DOLOR ABDOMINAL LOCALIZADO EN PARTE SUPERIOR', 1),
 ('R103', 'DOLOR LOCALIZADO EN OTRAS PARTES INFERIORES DEL ABDOMEN', 1), ('K529', 'COLITIS Y GASTROENTERITIS NO INFECCIOSAS, NO ESPECIFICADAS', 1),
 ('R509', 'FIEBRE, NO ESPECIFICADA', 1), ('J00X', 'RINOFARINGITIS AGUDA (RESFRIADO COMUN)', 1), ('A09X', 'DIARREA Y GASTROENTERITIS DE PRESUNTO ORIGEN INFECCIOSO', 1),
 ('R51X', 'CEFALEA', 1), ('I10X', 'HIPERTENSION ESENCIAL (PRIMARIA)', 1), ('Z000', 'EXAMEN MEDICO GENERAL', 1);

-- --- Pacientes (inventados) ---------------------------------------------
REPLACE INTO Paciente (TipoDocu, NumeUsua, NombUsua, NombUsu1, Ape1Usua, Ape2Usua, CodiAdmi, TipoUsua, TipoAfil,
                       NumeCont, FechNaci, SexoUsua, ResiDepa, ResiMuni, ResiZona, DireResi, TeleCelu, FechDigi, UsuaDigi) VALUES
 ('CC', '99000001', 'JUAN',   'CARLOS', 'PRUEBA',  'UNO',    'EPSP01', 2, 'D', 'PRU-001', '1980-05-10', 'M', '86', '865', 'U', 'CALLE FALSA 1', '3000000001', CURDATE(), 'PRUEBA'),
 ('CC', '99000002', 'MARIA',  'LUISA',  'PRUEBA',  'DOS',    'EPSP02', 1, 'C', 'PRU-002', '1992-11-23', 'F', '86', '865', 'U', 'CALLE FALSA 2', '3000000002', CURDATE(), 'PRUEBA'),
 ('TI', '99000003', 'PEDRO',  '',       'PRUEBA',  'TRES',   'EPSP01', 2, 'D', 'PRU-001', '2012-02-01', 'M', '86', '865', 'R', 'VEREDA FALSA',  '3000000003', CURDATE(), 'PRUEBA'),
 ('CC', '99000004', 'ANA',    'SOFIA',  'PRUEBA',  'CUATRO', 'EPSP01', 2, 'D', 'PRU-001', '1975-08-30', 'F', '86', '865', 'U', 'CALLE FALSA 4', '3000000004', CURDATE(), 'PRUEBA'),
 ('RC', '99000005', 'LUCAS',  '',       'PRUEBA',  'CINCO',  'EPSP02', 1, 'B', 'PRU-002', '2023-03-15', 'M', '86', '865', 'U', 'CALLE FALSA 5', '3000000005', CURDATE(), 'PRUEBA'),
 -- Pacientes SIN admision: para probar "Nueva admision" en cualquier modulo
 ('CC', '99000006', 'ROSA',   'ELENA',  'PRUEBA',  'SEIS',   'EPSP01', 2, 'D', 'PRU-001', '1968-01-17', 'F', '86', '865', 'U', 'CALLE FALSA 6', '3000000006', CURDATE(), 'PRUEBA'),
 ('CC', '99000007', 'ANDRES', '',       'PRUEBA',  'SIETE',  'EPSP02', 1, 'C', 'PRU-002', '2001-06-05', 'M', '86', '865', 'U', 'CALLE FALSA 7', '3000000007', CURDATE(), 'PRUEBA'),
 ('TI', '99000008', 'VALERIA', '',      'PRUEBA',  'OCHO',   'EPSP01', 2, 'D', 'PRU-001', '2014-10-09', 'F', '86', '865', 'R', 'VEREDA FALSA 8', '3000000008', CURDATE(), 'PRUEBA');

-- --- Admisiones (ConsAdmi con prefijo PRUEBA para distinguirlas) ---------
DELETE FROM Admision WHERE ConsAdmi LIKE 'PRUEBA%';
INSERT INTO Admision (CodiInst, ConsAdmi, TipoDocu, NumeUsua, ValoEdad, UnidEdad, CodiAdmi, TipoUsua, TipoAfil, NumeCont,
                      NumePoli, FechIngr, HoraIngr, TipoAten, CodiServ, CentCost, ServEgre, CentEgre, MotiCons,
                      Cerrado, Anulado, FechDigi, HoraDigi, UsuaDigi) VALUES
 -- Urgencias (007): 3 abiertas (2 de hoy) y 1 cerrada de hoy
 ('868650001001', 'PRUEBA000001', 'CC', '99000001', 46, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE(), '07:15:00', 3, '007', '0701', '007', '0701', 'DOLOR ABDOMINAL', 2, 2, CURDATE(), '07:15:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000002', 'CC', '99000002', 33, 'A', 'EPSP02', 1, 'C', 'PRU-002', '', CURDATE(), '09:40:00', 3, '007', '0701', '007', '0701', 'FIEBRE',          2, 2, CURDATE(), '09:40:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000003', 'TI', '99000003', 14, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE() - INTERVAL 1 DAY, '22:05:00', 3, '007', '0701', '007', '0701', 'TRAUMA MANO', 2, 2, CURDATE() - INTERVAL 1 DAY, '22:05:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000004', 'CC', '99000004', 51, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE(), '06:00:00', 3, '007', '0701', '007', '0701', 'CEFALEA',         1, 2, CURDATE(), '06:00:00', 'MEDPRUEBA'),
 -- Observacion (008): 2 abiertas de dias anteriores
 ('868650001001', 'PRUEBA000005', 'CC', '99000004', 51, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE() - INTERVAL 1 DAY, '15:30:00', 3, '008', '0801', '008', '0801', 'DOLOR TORACICO', 2, 2, CURDATE() - INTERVAL 1 DAY, '15:30:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000006', 'CC', '99000001', 46, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE() - INTERVAL 2 DAY, '11:10:00', 3, '008', '0801', '008', '0801', 'DESHIDRATACION', 2, 2, CURDATE() - INTERVAL 2 DAY, '11:10:00', 'MEDPRUEBA'),
 -- Consulta externa (001 y 013): 3 abiertas de hoy y 1 anulada de hoy
 ('868650001001', 'PRUEBA000007', 'RC', '99000005', 3,  'A', 'EPSP02', 1, 'B', 'PRU-002', '', CURDATE(), '08:00:00', 1, '001', '0101', '001', '0101', 'CONTROL',         2, 2, CURDATE(), '08:00:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000008', 'CC', '99000002', 33, 'A', 'EPSP02', 1, 'C', 'PRU-002', '', CURDATE(), '08:20:00', 1, '001', '0101', '001', '0101', 'CONSULTA GENERAL', 2, 2, CURDATE(), '08:20:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000009', 'CC', '99000001', 46, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE(), '10:00:00', 1, '013', '0101', '013', '0101', 'CONTROL ESPECIALISTA', 2, 2, CURDATE(), '10:00:00', 'MEDPRUEBA'),
 ('868650001001', 'PRUEBA000010', 'CC', '99000004', 51, 'A', 'EPSP01', 2, 'D', 'PRU-001', '', CURDATE(), '10:30:00', 1, '001', '0101', '001', '0101', 'ERROR DE DIGITACION', 2, 1, CURDATE(), '10:30:00', 'MEDPRUEBA');

-- --- Control de cargas a SIHOS ------------------------------------------
DELETE FROM cont_carga_sihos WHERE ConsAdmiTemp LIKE 'PRUEBA%';
INSERT INTO cont_carga_sihos (CodiInst, ConsAdmiTemp, ConsAdmiSihos, estado, fecha_registro, usuario_registro, fecha_carga, usuario_carga, intentos, mensaje) VALUES
 ('868650001001', 'PRUEBA000001', NULL, 'pendiente', NOW(), 'MEDPRUEBA', NULL, NULL, 0, NULL),
 ('868650001001', 'PRUEBA000002', NULL, 'pendiente', NOW(), 'MEDPRUEBA', NULL, NULL, 0, NULL),
 ('868650001001', 'PRUEBA000003', NULL, 'pendiente', NOW(), 'MEDPRUEBA', NULL, NULL, 0, NULL),
 ('868650001001', 'PRUEBA000007', NULL, 'pendiente', NOW(), 'MEDPRUEBA', NULL, NULL, 0, NULL),
 ('868650001001', 'PRUEBA000005', NULL, 'error',     NOW(), 'MEDPRUEBA', NOW(), 'NIXON07', 1, 'Prueba: contrato no existe en SIHOS'),
 ('868650001001', 'PRUEBA000004', '209901010001', 'cargada', NOW(), 'MEDPRUEBA', NOW(), 'NIXON07', 1, 'Prueba: cargada');
