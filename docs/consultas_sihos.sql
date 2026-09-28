-- =====================================================================================================
-- Consultas para correr en SIHOS (producción, SOLO LECTURA) y mandar el resultado al equipo de CRADOR-HC.
-- Ninguna consulta trae datos de pacientes: solo estructuras y catálogos.
-- Correr con un usuario de solo lectura, por ejemplo:
--   mysql -h 192.168.0.250 -u USUARIO -p BASE < docs/consultas_sihos.sql > resultado_sihos.txt
-- =====================================================================================================

-- -----------------------------------------------------------------------------------------------------
-- 1. Permisos: barra de pestañas por usuario (RECORRIDO_SIHOS.md §0.1).
--    Reemplazan las definiciones PROVISIONALES de sql/04_permisos_PROVISIONAL.sql.
-- -----------------------------------------------------------------------------------------------------
SHOW CREATE TABLE UsuaGrup;
SHOW CREATE TABLE Permisos;
SHOW CREATE TABLE ModuObje;
SHOW CREATE TABLE Objetos;

-- Filas de ejemplo (catálogos, sin pacientes)
SELECT * FROM Objetos LIMIT 200;
SELECT * FROM ModuObje WHERE CodiModu IN (5, 6, 8) LIMIT 500;   -- si la columna del módulo tiene otro nombre, quitar el WHERE
SELECT * FROM UsuaGrup LIMIT 50;
SELECT * FROM Permisos LIMIT 200;

-- -----------------------------------------------------------------------------------------------------
-- 2. Reconciliación medicamentosa (pestaña Consultas > Antecedentes y ventana automática al abrir la historia)
--    Solo la estructura: la tabla tiene datos de pacientes, NO hacer SELECT.
-- -----------------------------------------------------------------------------------------------------
SHOW CREATE TABLE RecoMedi;

-- -----------------------------------------------------------------------------------------------------
-- 3. Catálogo "Instituciones de Remisión" (pestaña Remisiones, campo Institución -> Remision.InstRemi)
--    Primero encontrar el nombre de la tabla; luego su estructura y sus filas (es un catálogo).
-- -----------------------------------------------------------------------------------------------------
SHOW TABLES LIKE '%Remi%';
SHOW TABLES LIKE '%Inst%';
-- Cuando se sepa el nombre (reemplazar NOMBRE_TABLA):
-- SHOW CREATE TABLE NOMBRE_TABLA;
-- SELECT * FROM NOMBRE_TABLA;

-- -----------------------------------------------------------------------------------------------------
-- 4. Listas sin catálogo local (encabezado y pestañas). Tablas de listas genéricas ("lista tipos N").
-- -----------------------------------------------------------------------------------------------------
SHOW TABLES LIKE '%Lista%';
SHOW TABLES LIKE '%Tipo%';
-- Estado Ingreso (Admision.EstaIngr), Conducta de la consulta (lista tipo 33), Tipo de alergia, Tuberculosis
-- multidrogoresistente (7 opciones), Lepra, Tipo de discapacidad, Alcance / Incapacidad retroactiva / Grupo de
-- servicios / Modalidad de prestación de la incapacidad, Código Dorado (Estado), Tipo de Prescripción
-- (Regular / Control / Domiciliaria):
-- SHOW CREATE TABLE NOMBRE_TABLA_LISTAS;
-- SELECT * FROM NOMBRE_TABLA_LISTAS WHERE Tipo IN (33, ...);

-- -----------------------------------------------------------------------------------------------------
-- 5. Valores reales para confirmar supuestos (conteos, sin datos personales)
-- -----------------------------------------------------------------------------------------------------
-- Tipo de prescripción usado (¿Domiciliaria = 3?)
SELECT TipoPres, CodiModu, COUNT(*) AS n FROM EncaPres GROUP BY TipoPres, CodiModu;
-- ¿SOAT se guarda en Admision.NumePoli?
SELECT ServEgre, COUNT(*) AS n, SUM(NumePoli <> '') AS con_poliza
  FROM Admision WHERE FechIngr >= '2026-08-01' GROUP BY ServEgre;
-- Estado Ingreso
SELECT EstaIngr, COUNT(*) AS n FROM Admision WHERE FechIngr >= '2026-08-01' GROUP BY EstaIngr;
-- Remisiones recientes (la remisión quedó "sin verificar")
SELECT COUNT(*) AS n, MAX(FechSali) AS ultima FROM Remision;
-- Actividad de las notas (HojaEnfe.Procedim) y Revisada en notas de enfermería
SELECT TipoNota, SUM(Procedim <> '') AS con_actividad, SUM(Reviza = 1) AS revisadas, COUNT(*) AS n
  FROM HojaEnfe WHERE FechNota >= '2026-08-01' GROUP BY TipoNota;
-- Valores reales de las opciones de Antecedentes (Si | No | No Sabe | No Corresponde): ¿3 y 4?
SELECT Patologi, COUNT(*) AS n FROM Antecede WHERE FechDigi >= '2026-08-01' GROUP BY Patologi;
SELECT AlerSiNo, COUNT(*) AS n FROM Antecede WHERE FechDigi >= '2026-08-01' GROUP BY AlerSiNo;
-- Examen físico "No se Explora": ¿NULL, 0 o 3?
SELECT Cabeza, COUNT(*) AS n FROM EstaGene WHERE FechDigi >= '2026-08-01' GROUP BY Cabeza;
-- Código Dorado (lista tipo 47) y Conducta (lista tipo 33) usados
SELECT EstaCodo, COUNT(*) AS n FROM RipsCons WHERE FechCons >= '2026-08-01' GROUP BY EstaCodo;
SELECT Conducta, COUNT(*) AS n FROM RipsCons WHERE FechCons >= '2026-08-01' GROUP BY Conducta;

-- =====================================================================================================
-- 28/09/2026 (puntos 4 y 7 de REVISION_CLAUDE_LOCAL.md): método de planificación y catálogo HoraApli
-- =====================================================================================================
-- Método de planificación (Antecede.MetoDesc): ¿de qué tabla o lista sale? Hoy CRADOR usa códigos PROVISIONALES 1..14
SELECT MetoPlan, MetoDesc, COUNT(*) AS n FROM Antecede WHERE FechDigi >= '2026-01-01' GROUP BY MetoPlan, MetoDesc ORDER BY n DESC;
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = 'sihos' AND (TABLE_NAME LIKE '%Plan%' OR TABLE_NAME LIKE '%Meto%' OR TABLE_NAME LIKE '%Anti%conc%');
SELECT t.id AS tipo, t.nombre AS lista, e.id, e.codigo, e.nombre FROM priv_listas_tipos t JOIN priv_listas_elementos e ON e.prv_lista_tipo_id = t.id
 WHERE t.nombre LIKE '%planifica%' OR t.nombre LIKE '%anticon%' OR e.nombre LIKE '%Implante%' OR e.nombre LIKE '%Anillo%';
-- "Cada" de la prescripción hospitalaria: estructura y filas reales de HoraApli
SHOW CREATE TABLE HoraApli;
SELECT * FROM HoraApli;
-- Qué guarda SIHOS en una prescripción hospitalaria (PresSali 1) y en una ambulatoria (PresSali 2): frecuencia, duración,
-- número de dosis, total, contenido y cantidad solicitada (sin datos de pacientes)
SELECT e.PresSali, d.HoraApli, d.CantFrec, d.TiemFrec, d.CantPeDu, d.TiemPeDu, d.NumeDosi, d.CantSumi, d.CantTota,
       d.Contenid, s.Contenid AS ContenidSumi, d.CantSoli
  FROM DetaPres d JOIN EncaPres e ON e.CodiInst = d.CodiInst AND e.ConsAdmi = d.ConsAdmi AND e.ConsPres = d.ConsPres
  LEFT JOIN CodiSumi s ON s.CodiSumi = d.CodiSumi
 WHERE e.Fecha >= '2026-09-20' ORDER BY e.PresSali, d.HoraApli LIMIT 60;
-- Medicamentos sin Contenid parametrizado (¿cómo calcula SIHOS la cantidad solicitada en ese caso?)
SELECT COUNT(*) AS sin_contenido FROM CodiSumi WHERE SumiActi = 1 AND (Contenid = '' OR Contenid = '0');
