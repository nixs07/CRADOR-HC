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
