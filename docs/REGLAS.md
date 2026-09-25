# Reglas de negocio (verificadas contra SIHOS real, septiembre 2026)

## Módulos y servicios

| Módulo | CodiServ | TipoAten | CodiModu (SIHOS) |
| --- | --- | --- | --- |
| Urgencias | 007 | 3 | 6 |
| Observación e Internación | 008 | 3 | 8 |
| Consulta Externa | 001, 013 | 1 | 5 |

## Número de admisión (ConsAdmi)

- Formato `AAAAMMDD` + consecutivo de 4 dígitos del día de digitación (ej. `202609240001`). `varchar(12)`.
- En la contingencia la admisión lleva un número **temporal**: `C` + `AAMMDD` + consecutivo de 5 dígitos
  (ej. `C26092500001`, 12 caracteres). Nunca choca con SIHOS, que usa solo dígitos. Se reserva con
  `GET_LOCK('crador_consadmi')` para que dos equipos no saquen el mismo (probado con 12 procesos en paralelo).
- **Al cargar a SIHOS** se busca la última admisión de ese día en SIHOS y se asigna la siguiente. La fecha real
  de ingreso (`FechIngr`) se conserva.
- Todos los demás consecutivos (ConsOrde, ConsPres, ConsEvol, ConsSign, ConsHoEn...) son **por admisión**:
  no chocan con SIHOS.

## Orden de inserción (padre → hijo)

Paciente (si no existe) → Admision → EncaData → EncaOrde → EncaPres → HojaProc → resto de tablas
(DetaData, DetaOrde, DetaPres, DetaPrue, Triage, SignVita, RipsCons, EstaGene, Antecede, HojaEnfe, HojaMedi,
HojaMate, HojaLAdm, EvolInte, TrasCama, Remision, IncaPaci, SaliInte).
Cada admisión entra completa o no entra (transacción por admisión). La base de SIHOS no tiene triggers.

## Triage

- `Triage.ConsTria` **no es por admisión**: es el número de triage **del paciente** en toda su historia
  (1, 2, 3...). En la contingencia se calcula con los triages locales; **al cargar se recalcula** con
  `MAX(ConsTria)` del paciente en SIHOS + 1.
- La pantalla de triage de SIHOS guarda también la toma de signos No. 1 (`SignVita.ConsSign = 1`,
  `SintResp = SintPiel = 2`) y actualiza `Admision.ClasTria`. Las tomas posteriores llevan `SintResp = SintPiel = 0`.
- `Triage.CodiInst` tiene por defecto `734430295601` en la estructura: **siempre** se escribe `868650001001`.

## Signos vitales

- `MasaCorp` (IMC) = peso / (talla en metros)²; `TM` (presión arterial media) = (sistólica + 2 × diastólica) / 3.
- `CodiModu`: 6 Urgencias, 8 Observación, 5 Consulta Externa. `ValoEdad`/`UnidEdad` se copian de la admisión.

## Valores fijos de la admisión (lo que SIHOS guarda en la práctica, ago-sep 2026)

`EstaIngr = 1`, `EntoAten = 20`, `VienRefe = 0`, `CentCost = CentEgre = ''`, `NumePoli = ''`, `Reingres = 0`,
`ServEgre = CodiServ`, `CamaActu = CodiCama` (solo Observación lleva cama), `MotiCons = '.'` si no se escribe.
Por módulo: Urgencias vía de ingreso 1, Observación 1, Consulta Externa 2; causa externa habitual 13;
grupo poblacional O; condición de la usuaria 4 (mujer) o 5 (no aplica).

## Contratos y categorías

- Contrato "activo" = `Contrato.EstaCont = 1`. **No se usan las fechas**: los contratos más usados tienen
  `FechFiCo` vencida (2025-12-31) y SIHOS los sigue aceptando.
- La categoría (`CodiEstr`) se valida contra `AdmiEstr` por EPS + `TipoAten` + `TipoAfil` (`EstaEstr = 1`).

## Pacientes nuevos

Un paciente que no está en la copia local se crea en `Paciente` y queda marcado en `cont_paciente`
(`accion = 'nuevo'`). Al cargar: si ya existe en SIHOS (lo crearon mientras tanto) no se toca; si no, se inserta.

## Orden médica

`EncaData` + `DetaData` con `TipoObje = 7` = "Orden médica" en texto libre (`DetaData.Texto`).
`CodiItem` 131 en Urgencias (CodiModu 6), 130 en Observación (CodiModu 8).

## Qué catálogo valida cada campo

Todo campo con código se escoge de su catálogo (nunca texto libre), para que al cargar a SIHOS no entren códigos
que no existan (RIPS, reportes y facturación dependen de eso).

| Pantalla | Campo | Catálogo |
| --- | --- | --- |
| Paciente | TipoDocu, SexoUsua, EstaCivi, NiveEsco, PertEtni, TipoDisc | TipoDocu, CodiSexo, EstaCivi, CodiEsco, PertEtni, TipoDisc |
| Paciente | ResiDepa, ResiMuni, ResiComu, ResiZona, ResiBarr, CodiPais | CodiDepa, CodiMuni, CodiComu, CodiZona, CodiBarr, CodiPais |
| Admisión | CodiAdmi, NumeCont | CodiAdmi, Contrato (solo vigentes) |
| Admisión | TipoUsua, TipoAfil, CodiEstr | TipoUsua, TipoAfil, CodiEstr / AdmiEstr |
| Admisión | ViaIngre (**no** ViaAcces, que es vía de acceso quirúrgica) | ViaIngre |
| Admisión | CausExte, CondUsua, TipoAten, GrupoAte | CausExte, CondUsua, TipoAten, GrupAten |
| Admisión | CodiServ, CentCost, CodiCama, CodiCons | CodiServ, CeCoServ, CodiCama, CodiCons |
| Admisión | TipoAcom, Parentes | TipoAcom, Parentes |
| Triage | ClasTria, CondTria | ClasTria, CondTria |
| Diagnósticos | CodiDiag, CodiRel1..4 / DiagPrin... | CausMorb |
| Diagnósticos | TipoDiag | TipoDiag |
| Consulta | FinaCons | FinaCons |
| Órdenes y procedimientos | CodiProc, CodiFina | CodiProc, FinaProc |
| Prescripción y medicamentos | CodiSumi / CodiMedi, CodiVia, UnidMedi, TiemFrec | CodiSumi, ViaAdmi, UnidMedi, CodiTiem |
| Materiales | CodiMate, UnidMate | CodiSumi, CodiUnid |
| Notas de enfermería | TipoNota | TipoNota |
| Egreso | CausSali, DestSali, EstaSali, TipoEgre | CausSali, DestSali, EstaSali, TipoEgre |
| Remisión | MotiRemi, ModaSoli | MotiRemi, ModaSoli |
| Incapacidad | tipo | TipoInca |
| Profesional | UsuaDigi, CodiEspe | Usuarios, CodiEspe |

Los nombres exactos de las columnas de cada catálogo están en `sql/02_catalogos.sql` y `sql/03_catalogos_listas.sql`.

## Liquidación

No se liquida. Las columnas `NumeLiqu`, `ConsDeFa`, `CantFact` quedan en 0; facturación liquida en SIHOS.

## Usuarios y claves

- `Usuarios.Login` varchar(12), `Usuarios.Password` = MD5-crypt (`$1$...`, 34 caracteres).
- Validar con `password_verify($clave, $hash)` de PHP (compatible con crypt MD5).
- Solo usuarios con `Activo = 1`. Asistenciales: `UsuaAsis = 1`.
- `UsuaDigi` / `UsuaModi` de cada registro = login real del profesional.

## Fechas vacías

SIHOS usa `0000-00-00` y `00:00:00` como vacío en columnas `NOT NULL`. MySQL debe correr con
`sql_mode = 'NO_ENGINE_SUBSTITUTION'`.

## Uso real de las pestañas (muestra agosto 2026: 300 urgencias, 95 observación, 300 consulta externa)

| Tabla | Urgencias | Observación | Consulta ext. |
| --- | --- | --- | --- |
| SignVita | 99% | 100% | 68% |
| Triage | 99% | — | — |
| RipsCons | 83% | 100% | 68% |
| EstaGene | 83% | 100% | 68% |
| Antecede | 83% | 98% | 53% |
| EncaData/DetaData | 83% | 100% | — |
| EncaPres/DetaPres | 82% | 100% | 40% |
| SaliInte | 81% | 100% | — |
| HojaEnfe | 76% | 100% | 5% |
| EncaOrde/DetaOrde | 75% | 91% | 57% |
| HojaMedi | 73% | 100% | — |
| HojaMate | 69% | 88% | — |
| HojaProc | 55% | 72% | 32% |
| EvolInte | 45% | 97% | — |
| TrasCama | — | 100% | — |
| HojaLAdm (fase 2) | 63% | 97% | — |
| DetaPrue (fase 2) | 49% | 56% | 29% |

## Supuestos de la contingencia (pestañas de la historia, septiembre 2026)

Decisiones tomadas sin poder verificarlas contra registros reales de SIHOS. **Revisarlas antes de la primera
carga (fase 3)** comparando con una admisión real de cada módulo.

| Tema | Supuesto |
| --- | --- |
| Consecutivos | `ConsCons`, `ConsAnte`, `ConsEsGe`, `ConsPres`, `ConsOrde`, `ConsData`, `ConsHoPr`, `ConsHoEn`, `ConsHoMe`, `ConsEvol`: `MAX + 1` por admisión, dentro de la transacción (`SELECT … FOR UPDATE`). |
| `EncaPres.Consecut`, `EncaOrde.Consecut` | Son consecutivos **globales** de SIHOS. En la contingencia se guardan temporales (= `ConsPres` / `ConsOrde`); **al cargar se reasignan** con el siguiente de SIHOS. |
| Consulta (RipsCons) | Se guarda realizada y cerrada: `EstaReal = 1`, `FechCier/HoraCier/UsuaCier` = momento del guardado. `UsuaCons = UsuaAsis` = login. `CodiEspe` = especialidad del usuario. `TipoCons` (código CUPS de la consulta) es opcional. Se permiten diagnóstico principal y 2 relacionados; `CodiRel3/4` quedan vacíos. |
| Antecedentes (Antecede) | Se guarda una fila por consulta, ligada por `ConsCons`. Valores 1 = sí, 2 = no refiere; `AlerSiNo` igual. Se preguntan: patológicos, quirúrgicos, farmacológicos, tóxicos, traumáticos, familiares, ginecológicos, obstétricos y alérgicos. |
| Examen físico (EstaGene) | 1 = normal, 2 = anormal (con descripción obligatoria), `NULL` = sin examinar. Solo se guarda si hay estado general o algún sistema examinado. `ConsHoPr = 0`. |
| Prescripción | `PresSali`: se siguió la convención de SIHOS 1 = sí / 2 = no (1 = fórmula de salida). **Verificar**: la columna tiene 1 por defecto. `TipoPres = 1`, `FechEntr = Fecha`, `CodiFina` del detalle = `NULL`, `HoraApli = 0` (no hay catálogo `HoraApli` local), `HoraInic` = hora de la prescripción. `NumeDosi` = duración ÷ frecuencia (en horas, con `CodiTiem` 1 = horas, 2 = días, 3 = meses según el comentario de `DetaPres`) y `CantTota = CantSumi × NumeDosi`. Si no se escribe la indicación, `PresMedi` se arma con dosis, vía, frecuencia y duración. |
| Administración de medicamentos (HojaMedi) | Solo de lo prescrito en la admisión. `NumeOrde = ConsPres`, `Item = Item` de `DetaPres`; `EstaApli = 1`; plan = hora de aplicación. Suma la cantidad en `DetaPres.CantApli`. |
| Órdenes (EncaOrde/DetaOrde) | `EncaOrde.CodiFina` queda con su valor por defecto (`10`); la finalidad de cada ítem (catálogo `FinaProc`) va en `DetaOrde.CodiFina`. `CantReal = 0` (se realiza aparte). `CodiProf` = login. |
| Orden médica en texto | `EncaData/DetaData` con `TipoObje = 7`, `CodiItem` 131 Urgencias / 130 Observación. En Consulta Externa no se muestra. |
| Procedimientos (HojaProc) | `CantProc = 1`, `ProcReal = 1`, `NumePiez = CuadPiez = 0`, `NumeOrde = Item = 0` (no se liga a la orden). `UsuaDigi`, `UsuaModi`, `CodiProf` y `UsuaAsis` son `varchar(8)` en SIHOS: se guarda el login recortado a 8 caracteres. |
| Evolución (EvolInte) | Formato SOAP (`Subjetivo`, `Objetivo`, `Analisis`, `PlanMane`). No aplica en Consulta Externa (0 % de uso en la muestra). `ContSign/ContLiqu` = 1 marcado, 0 no. |
| Egreso (SaliInte) | Urgencias y Observación: se guarda `SaliInte` y se cierra la admisión (`Cerrado = 1`, `FechCier/HoraCier/UsuaCier`, `FechEgre/HoraEgre` = salida). `DiasEsta/HoraEsta` se calculan desde el ingreso. Si `EstaSali` es "muerto" (se reconoce por el nombre en el catálogo) se piden causa (`DiagMuer`, 4 caracteres) y fecha/hora de muerte. La cama queda libre porque la ocupación se calcula con las admisiones abiertas (no se toca `CodiCama`). |
| Consulta Externa: "Cerrar atención" | En la muestra CE no tiene `SaliInte`; el egreso solo marca la admisión cerrada (`Cerrado = 1` y fechas de cierre/egreso). |
| Confirmación | Egreso y cierre piden marcar una casilla de confirmación (se valida también en el servidor). |
| Traslado de cama (TrasCama) | Solo Observación, desde el encabezado. Cada fila es el tramo en la cama anterior: `CodiServ`/`CamaOrig` = servicio y cama de origen, `ServEgre`/`CamaDest` = destino, `FechIngr/HoraIngr` = inicio del tramo (ingreso o traslado anterior), `FechSali/HoraSali` = hora del traslado, `Dias` = días completos y `Horas` = horas restantes del tramo. Actualiza `Admision.CamaActu` y `ServEgre`; `CentEgre` no se toca (queda vacío, ver valores fijos). La cama destino debe estar activa y libre. |
| Materiales (HojaMate) | En la pestaña 7. `CodiMate` de `CodiSumi`, `UnidMate` de `CodiUnid`; `EsFact = 1`, `CantFact = 0`, `NumeOrde = Item = 0`, `CentCost = '0'`, `UsuaAsis` = login. |
| Remisión (Remision) | En la pestaña 9. `RemiMoti` = código del catálogo `MotiRemi`; `MotiRemi` (texto) = resumen clínico; `ModaSoli` del catálogo `ModaSoli`; `EspeRemi` de `CodiEspe` (opcional). **No hay catálogo local de instituciones receptoras**: `InstRemi` queda vacío y el nombre de la institución se escribe al inicio de `MotiRemi` ("INSTITUCION DESTINO: …"). `CodiRemi` = consecutivo por admisión; `FechSali/HoraSali` = fecha de la remisión; `FechAcep/HoraAcep` = la misma si se escribe quién acepta; `Cerrado = 0`; `TipoDiag` guarda el código de `TipoDiag` como texto. No cierra la admisión (el egreso se hace aparte). |
| Incapacidad (IncaPaci) | En la pestaña 9. `TipoInca` del catálogo; `OrigInca` 1 = común, 2 = laboral (comentario de la columna); días de 1 a 540; `ConsInca` = consecutivo por admisión. |
| Procedimiento de una orden | En la pestaña 6 se puede escoger un ítem de `DetaOrde` pendiente (`CantReal < CantSumi`): el procedimiento sale del ítem, `HojaProc.NumeOrde` = `EncaOrde.Consecut` (temporal, ver arriba) e `Item` = ítem, y `DetaOrde.CantReal` suma 1. |
