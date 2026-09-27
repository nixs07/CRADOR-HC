# Resultado de docs/consultas_sihos.sql (SIHOS producción, 26/09/2026, solo lectura)

Corrido desde la red del hospital. Solo estructuras, catálogos y conteos (sin datos de pacientes).
**Manda sobre los supuestos anotados en REGLAS.md.**

## 1. Permisos (reemplazar sql/04_permisos_PROVISIONAL.sql)

Estructuras reales (base `sihos`):

```sql
CREATE TABLE `UsuaGrup` (
  `CC` varchar(15), `Login` varchar(12) NOT NULL, `CodiGrup` int(3) NOT NULL,
  `FechDigi` date, `HoraDigi` time, `UsuaDigi` varchar(12), `FechModi` date, `HoraModi` time, `UsuaModi` varchar(12),
  PRIMARY KEY (`Login`,`CodiGrup`)
);
CREATE TABLE `Permisos` (
  `CodiGrup` int(3) NOT NULL, `CodiModu` int(3) NOT NULL, `CodiObje` int(3) NOT NULL, `Valor` char(10),
  `FechDigi` date, `HoraDigi` time, `UsuaDigi` varchar(12), `FechModi` date, `HoraModi` time, `UsuaModi` varchar(12),
  PRIMARY KEY (`CodiGrup`,`CodiModu`,`CodiObje`)
);
CREATE TABLE `ModuObje` (
  `CodiModu` int(2) NOT NULL, `CodiObje` int(3) NOT NULL, `Orden` int(3) NOT NULL, `OrdeImpr` int(3), `EsEpicri` int(1),
  `ImpFiHCE` int(1), `ReCaOrde` int(1), `ReCaFact` int(1), `ReCaLibr` int(1),
  `FechDigi` date, `HoraDigi` time, `UsuaDigi` varchar(12), `FechModi` date, `HoraModi` time, `UsuaModi` varchar(12),
  PRIMARY KEY (`CodiModu`,`CodiObje`,`Orden`)          -- OJO: un objeto puede tener VARIAS filas (varios Orden)
);
CREATE TABLE `Objetos` (
  `CodiObje` int(3) NOT NULL, `NombObje` varchar(50), `DescPagi` varchar(100), `UrlPagin` varchar(50),
  `UrlImpri` varchar(50), `Iso` varchar(30), `Version` varchar(30), `Fecha` date, `TipoObje` int(2), `ClasObje` int(2),
  `CodiModu` int(3), `Activo` int(1), `ObjeHCED` int(1),
  `FechDigi` date, `HoraDigi` time, `UsuaDigi` varchar(12), `FechModi` date, `HoraModi` time, `UsuaModi` varchar(12),
  PRIMARY KEY (`CodiObje`)
);
```
(Tipos de columna tomados de information_schema; charset latin1 como el resto.)
Barra de un usuario: objetos de `Permisos` de sus grupos (`UsuaGrup`) en el módulo, con `Objetos.Activo = 1`,
orden por `MIN(ModuObje.Orden)` y empate por `CodiObje`; excluir los de `Orden >= 99` (reportes).
Se copian con "Actualizar catálogos" (son catálogos: no tienen datos de pacientes).

Reconciliación medicamentosa:
```sql
CREATE TABLE `RecoMedi` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `CodiInst` varchar(12), `ConsAdmi` varchar(12), `NombSumi` varchar(200),
  `CantSumi` decimal(20,2), `ViaAdmin` int(1), `FrecApli` int(2), `NotaSumi` varchar(300),
  `FechDigi` date, `HoraDigi` time, `UsuaDigi` varchar(12), `FechModi` date, `HoraModi` time, `UsuaModi` varchar(12),
  PRIMARY KEY (`id`)
);
```
→ Agregarla a las tablas clínicas (se carga a SIHOS por admisión; `id` se deja al AUTO_INCREMENT de SIHOS).
Así la Reconciliación deja de estar en gris.

## 2. Instituciones de remisión (reemplazar sql/05_...)

Tabla real **`InstRemi`** (7 filas), PK (`CodiInst`,`CodInsRe`):
`CodiInst varchar(12), CodInsRe varchar(10), CoinReal varchar(12), NombInst varchar(60), TipoIden char(2),
NitInsti varchar(20), CodiMuni char(3), CodiDepa char(2), DireInst varchar(40), TeleInst varchar(10),
MailInst varchar(30), TipoPers int(2), ActiEcon varchar(5), CodiNive int(2), CodiProc varchar(15), KiloMetr int(10),
GastGaso decimal(6,2)` + campos de auditoría. Filas: 01 HOSPITAL LOCAL PUERTO ASIS · 02 OTRAS INSTITUCIONES ·
03 ESE HOSPITAL JOSE MARIA HERNANDEZ DE MOCOA · 04 ESE HOSPITAL UNIVERSITARIO DEPTAL DE NARIÑO ·
05 ESE HOSPITAL UNIVERSITARIO DPTAL DE NEIVA · 06 ESE HOSPITAL SAGRADO CORAZON DE JESUS ·
07 HOSPITAL DE ALTA COMPLEJIDAD DEL PUTUMAYO SAS ZOMAC.
`Remision.InstRemi` guarda **`CodInsRe`** (ago–sep: 02 = 637, 03 = 17, 06 = 3, 07 = 3). Lista = `NombInst`.

## 3. Listas

- **Estado Ingreso** = tabla **`EstaIngr`** (`CodiEsta int(1)` PK, `NombEsta varchar(30)`, `ValoDefe int(1)` + auditoría):
  1 Conciente (defecto) · 2 Inconsciente · 3 Muerto. En uso: 100 % = 1.
- **Sí/No de antecedentes** = tabla **`CodiSino`** (ya está en los catálogos): **1 Si · 2 No · 3 No Sabe · 4 No Corresponde ·
  5 Sin Dato · 21 Riesgo No Evaluado**. En datos reales aparecen 1, 2, 3 y 5. → "No Sabe" = 3 y "No Corresponde" = 4 **confirmados**.
- Listas genéricas: tablas **`priv_listas_tipos`** (id, nombre, descripcion, prv_lista_tipo_id + timestamps) y
  **`priv_listas_elementos`** (id, codigo, nombre, descripcion, prv_lista_tipo_id, prv_lista_elemento_id, favorito,
  metadato, activo + timestamps). Copiarlas como catálogo. Tipos usados en la historia:
  - 12 Entornos de atención: 01 Hogar · 02 Comunitario · 03 Escolar · 04 Laboral · 05 Institucional.
  - 13 Tipos de alergias: 01 Medicamento · 02 Alimento · 03 Sustancia del ambiente · 04 Sustancia que entran en contacto · 05 Picadura de insectos · 06 Otra.
  - 14 Factores de riesgo: 01 Químicos · 02 Físicos · 03 Biomecánicos · 04 Psicosociales · 05 Biológicos · 06 Otro.
  - **33 Tipo de conducta** (RipsCons.Conducta): 1 OBSERVACIÓN EN URGENCIAS · 2 ATENCIÓN EN EL AMBIENTE DE TRANSICIÓN ·
    3 ATENCIÓN EN SALA DE PARTOS · 4 ATENCIÓN EN SALA DE CIRUGÍA · 5 HOSPITALIZACIÓN · 6 MANEJO AMBULATORIO ·
    7 SALIDA VOLUNTARIA O ABANDONO. **OJO: `RipsCons.Conducta` guarda el `id` del elemento (121–127), NO el código**
    (datos: 122 = 2.051, 126 = 830, 121 = 307, 125 = 95, 127 = 34, 123 = 12; NULL si no se llena).
  - 46 Código Dorado (acciones/continuidad): 01 Psicología · 02 Psiquiatría · 03 Trabajo Social · 04 Hospitalización ·
    05 Tele orientación · 06 Control por Psicología · 07 Control por Psiquiatría · 08 Seguimiento telefónico ·
    09 Educación a familiar o red de apoyo · 10 Otro.
  - **47 Estados de riesgo Código Dorado** (RipsCons.EstaCodo): 01 Riesgo Alto · 02 Riesgo Moderado · 03 Riesgo Bajo ·
    04 Seguimiento · 05 Cerrado. `EstaCodo` guarda el **código como número** (datos: 4 = 978, 5 = 75, 3 = 16, 2 = 1;
    0 o NULL si no aplica).
  - 50 (segunda lista de Código Dorado): 1 Contacto telefónico · 2 Visita Domiciliaria · 3 Visita de auditor concurrente · 4 Otro.
  - 49 Métodos de concepción: 1 Espontáneo · 2 Fertilización in vitro · 3 Inductor de la ovulación.
  - 35 Tipo de prescripción (lista nueva, MIPRES): 11/12/21/22/30 — **no es** `EncaPres.TipoPres`.

## 4. Supuestos confirmados o corregidos

| Supuesto | Resultado |
| --- | --- |
| "No Sabe" = 3, "No Corresponde" = 4 | **Confirmado** (`CodiSino`). |
| Examen físico "No se Explora" = vacío | **CORREGIR: "No se Explora" = 3** (Cabeza: 1 = 14.225, 2 = 39, **3 = 1**; Ojos: 3 = 2). 1 Normal, 2 Anormal, 3 No se Explora. |
| Domiciliaria en `EncaPres.TipoPres` | En la práctica solo se usan **0 y 1** (Regular) desde junio. No hay registros con Control ni Domiciliaria: dejar Regular = 1, Control = 2, Domiciliaria = 3 como supuesto (no afecta datos reales). |
| SOAT en `Admision.NumePoli` | **Confirmado**: casi siempre vacío; se llena en accidentes de tránsito (causa externa 02: 15 de 171). |
| Estado Ingreso | Catálogo `EstaIngr`; 100 % = 1 Conciente. |
| Columna **T** de Historias Abiertas | **Tipo de contrato**: `E` = Evento (`Contrato.TipoCont = 1`), `C` = Cápita (`TipoCont = 2`). Verificado con 4 admisiones. |
| Columnas **Med** / **Ord** | (Probable, no verificado en datos) Íconos: medicamentos pendientes por aplicar (jeringa) / aplicados (visto) y órdenes (ícono de laboratorio). Indicadores, no datos: mostrar si la admisión tiene prescripciones / órdenes pendientes. |
| Notas: Actividad (`HojaEnfe.Procedim`) | Existe en pantalla pero **nunca se llena** (0 de 22.044 notas en septiembre). Dejar el campo, opcional. |
| Notas: Revisada | Casi no se usa (6 notas médicas de 7.992). |
| Remisiones "sin verificar" | 660 remisiones ago–sep. **`FechSali` / `HoraSali` SIEMPRE vacías (0000-00-00)**: la fecha de la remisión es `FechDigi/HoraDigi`. `Cerrado = 0` (100 %). `ModaSoli` 1 = 658, 2 = 2. `InstRemi` = código de `InstRemi`. Solo Urgencias (6), Observación (8) y 9; **Consulta Externa no tiene remisiones**. La tabla tiene columnas para Cargo (`CargAcep`), Ambulancia (`Ambulanc`, `PlacAmbu`), Autorización (`NumeAuto`) y Otro motivo (`OtroMoti`): **no van en gris**. |
