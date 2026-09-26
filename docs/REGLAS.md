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
| Consulta (RipsCons) | `UsuaCons = UsuaAsis` = login. `CodiEspe` = especialidad del usuario. Cierre y `TipoCons`: ver "Verificado contra SIHOS". |
| Antecedentes (Antecede) | Se guarda una fila por consulta, ligada por `ConsCons`. Lista Sí/No de SIHOS: 1 = sí, 2 = no (por defecto). En el orden de SIHOS: Planificación (`MetoPlan`), Familiares, Personales, Patológicos, Obstétricos, Ginecológicos, Quirúrgicos, Tóxicos, Alérgicos (`AlerSiNo`), Fisiológicos, Alimentarios, Traumáticos, Farmacológicos y Factor de riesgo (`FactRies`), cada uno con su columna `*Desc` (obligatoria si es Sí). Planificación y Factor de riesgo no tienen columna de descripción. |
| Examen físico (EstaGene) | Sistemas y etiquetas de SIHOS: Cabeza, Ojos, Oídos, Nariz, Boca, Cuello, Tórax (`CardPulm`), Abdomen, G/U (`GeniUrin`), Ano, Extremidades, Neurológico, Osteomuscular y Piel. Valores: ver "Verificado contra SIHOS". `ConsHoPr = 0`. |
| Prescripción | `TipoPres` 1 regular / 2 control (Tipo de prescripción), `FechEntr = Fecha`, `CodiFina` del detalle = `NULL`, `HoraApli = 0` (no hay catálogo `HoraApli` local), `HoraInic` = hora de la prescripción. `NumeDosi` = duración ÷ frecuencia (en horas, con `CodiTiem` 1 = horas, 2 = días, 3 = meses según el comentario de `DetaPres`) y `CantTota = CantSumi × NumeDosi`. Si no se escribe la indicación, `PresMedi` se arma con dosis, vía, frecuencia y duración. `PresSali`: ver "Verificado contra SIHOS". |
| Administración de medicamentos (HojaMedi) | Pestaña Medicamentos. Solo de lo prescrito en la admisión; `Item = Item` de `DetaPres`; `EstaApli = 1`; plan = hora de aplicación. Suma la cantidad en `DetaPres.CantApli`. |
| Órdenes (EncaOrde/DetaOrde) | Pestaña Ordenación. La finalidad es de la orden (`EncaOrde.CodiFina`, catálogo `FinaCons`) y `DetaOrde.CodiFina` queda `NULL`. `OrdeAmbu` (Ambulatoria): 1 marcado, 0 no. DXP y DXR1-DXR4 en `CodiDiag`, `CodiRel1-4`. `CantReal` suma al realizar el procedimiento del ítem. |
| Orden médica en texto | `EncaData/DetaData` con `TipoObje = 7`, `CodiItem` 131 Urgencias / 130 Observación. En Consulta Externa no se muestra. |
| Procedimientos (HojaProc) | `CantProc` (Cant, 1 por defecto) y `ProcReal` (Realizado?, marcado por defecto) se escriben en la pestaña; diagnósticos Principal, Rela 1, Rela 2, Rela 3 y Compl en `DiagPrin/TipoDiag`, `DiagRela/TipoDiaR`, `DiagRel1/TipoDia1`, `DiagRel2/TipoDia2`, `DiagComp/TipoDiaC`; `NumePiez = CuadPiez = 0`, `NumeOrde = Item = 0` si no atiende una orden. |
| Evolución (EvolInte) | Formato SOAP (`Subjetivo`, `Objetivo`, `Analisis`, `PlanMane`). No aplica en Consulta Externa (0 % de uso en la muestra). `ContSign/ContLiqu` = 1 marcado, 0 no. |
| Egreso (SaliInte) | Urgencias y Observación: se guarda `SaliInte` y se cierra la admisión (`Cerrado = 1`, `FechCier/HoraCier/UsuaCier`, `FechEgre/HoraEgre` = salida). `DiasEsta/HoraEsta` se calculan desde el ingreso. Si `EstaSali` es "muerto" (se reconoce por el nombre en el catálogo) se piden causa (`DiagMuer`, 4 caracteres) y fecha/hora de muerte. La cama queda libre porque la ocupación se calcula con las admisiones abiertas (no se toca `CodiCama`). |
| Consulta Externa: "Cerrar Historia" | El botón **Cerrar Historia** del encabezado (con confirmación) marca la admisión cerrada (`Cerrado = 1` y fechas de cierre/egreso). |
| Confirmación | Egreso y cierre piden marcar una casilla de confirmación (se valida también en el servidor). |
| Traslado de cama (TrasCama) | Solo Observación, desde el encabezado. Cada fila es el tramo en la cama anterior: `CodiServ`/`CamaOrig` = servicio y cama de origen, `ServEgre`/`CamaDest` = destino, `FechIngr/HoraIngr` = inicio del tramo (ingreso o traslado anterior), `FechSali/HoraSali` = hora del traslado, `Dias` = días completos y `Horas` = horas restantes del tramo. Actualiza `Admision.CamaActu` y `ServEgre`; `CentEgre` no se toca (queda vacío, ver valores fijos). La cama destino debe estar activa y libre. |
| Materiales (HojaMate) | Pestaña Materiales (Urgencias 16, Observación 18). `CodiMate` de `CodiSumi`, `UnidMate` de `CodiUnid`; `EsFact = 1`, `CantFact = 0`, `NumeOrde = Item = 0`, `UsuaAsis` = login. `CentCost`: ver "Verificado contra SIHOS". |
| Remisión (Remision) — **sin verificar** | SIHOS no tiene remisiones recientes para compararlas: todo lo de esta fila es supuesto. Pestaña Remisiones (Urgencias 15, Observación 24, Consulta Externa 18). `RemiMoti` = código del catálogo `MotiRemi`; `MotiRemi` (texto) = resumen clínico; `ModaSoli` del catálogo `ModaSoli`; `EspeRemi` de `CodiEspe` (opcional). **No hay catálogo local de instituciones receptoras**: `InstRemi` queda vacío y el nombre de la institución se escribe al inicio de `MotiRemi` ("INSTITUCION DESTINO: …"). `CodiRemi` = consecutivo por admisión; `FechSali/HoraSali` = fecha de la remisión; `FechAcep/HoraAcep` = Fecha y Hora Aceptación de SIHOS; si se dejan vacías y se escribe quién acepta, la de la remisión; `Cerrado = 0`; `TipoDiag` guarda el código de `TipoDiag` como texto. No cierra la admisión (el egreso se hace aparte). |
| Incapacidad (IncaPaci) | Pestaña Incapacidad (Urgencias 17, Observación 21, Consulta Externa 12). La fecha final (inicial + días − 1) solo se muestra. `TipoInca` del catálogo; `OrigInca` 1 = común, 2 = laboral (comentario de la columna); días de 1 a 540; `ConsInca` = consecutivo por admisión. |
| Procedimiento de una orden | En la pestaña Procedimientos se puede escoger un ítem de `DetaOrde` pendiente (`CantReal < CantSumi`): el procedimiento sale del ítem, `HojaProc.NumeOrde` = `EncaOrde.ConsOrde` (número de la orden **dentro de la admisión**, no el `Consecut` global; verificado contra SIHOS: 500 de 500) e `Item` = ítem, y `DetaOrde.CantReal` suma 1 (se busca por `ConsAdmi` + `ConsOrde` + `Item`). `CentCost = ''`. |
| Pestañas después de las visibles | Observación 27-31 (PyP, Imágenes, Laboratorios y Diagnósticos, SALUD PUBLICA, Atención del Menor) y Consulta Externa 16-21 (Medicamentos, No POS, Remisiones, Notas Enfermería, SALUD PUBLICA, Cambio de Atención) no tienen número visible en SIHOS: aquí se numeran a continuación. Las pestañas sin tablas en CRADOR-HC se muestran deshabilitadas "No disponible en contingencia" (Líquidos también: no hay captura de sus campos). |
| Consulta en Consulta Externa | Las pestañas 1. Anamnesis, 2. Rev.Sistemas y Ex.Físico, 3. Antecedentes, 4. Laboratorios y Diagnósticos y 7. Plan de Manejo son **un solo formulario** (una fila de `RipsCons`). Si hay un error se abre la pestaña donde está. |
| Barra de cada pestaña | Nuevo (formulario en blanco), No. (registros anteriores: lleva al registro), Fecha, Hora y campos propios (Tipo de prescripción, Tipo de incapacidad, Autorización, Profesional). Imprimir y Cargos están deshabilitados: no aplican en contingencia. |
| Notas (HojaEnfe) | Sin selector de tipo (lo da la pestaña, ver "Verificado contra SIHOS"). La casilla Revisada de Notas Médicas llena `Reviza = 1`, `UsuaRevi`, `FechRevi`, `HoraRevi`. |
| Signos en consulta y evolución | La fila de signos de Consultas (Revisión por sistema) y de Evolución es opcional: si se escribe alguno se guarda una toma de `SignVita` con `ConsCons` o `ConsEvol` (y se piden los obligatorios). `Oximetria` queda `NULL` si no se escribe. |
| Triage | "Continuar en el consultorio" = `Triage.CodiCons` del catálogo `CodiCons` (activos), opcional. |
| Consulta (campos de SIHOS) | Sintomáticos (`SintResp`, `SintPiel`, `SintNerv`, `TubeMult`: 1 sí / 2 no), `PeriAbdo` (0-200) y `PeriTorx` (0-150), `LaboImag`, diagnósticos Principal y Rela 1-4 con tipo (`TipoDiag`, `TipoDia1-4`, 0 si no hay), Destino en Urgencias y Observación (`DestSali`, catálogo `DestSali`, se guarda como número; 4 si no se escoge). Prescripción: Tipo de prescripción (`TipoPres` 1 regular / 2 control), DXP, DXR 1 y DXR 2. Evolución: Rela 1-4 con tipo (`TipoDiag1-4`). Egreso: Rela 1-3 y Complicación (`DiagComp`, su tipo en `TipoDia4`). |
| Plan de Manejo (Urgencias 20, Observación 25) | Es el mismo `RipsCons.ObseReco` del acordeón de Consultas: la pestaña muestra y edita el plan de la consulta escogida en "No." (por defecto la última; `UPDATE` con `FechModi/HoraModi/UsuaModi`). Sin consulta: "Registre primero la consulta". |
| Campos de SIHOS sin guardar | No se piden porque no tienen columna o catálogo local: Lepra (`TipoLepr`), Tipo de discapacidad, Conducta de la consulta (lista 33), método de planificación (`MetoDesc`), Sivigila/Protocolo, Id Estudio, Revisado del procedimiento y de la evolución, índice cintura-cadera, órdenes posfechadas, Alcance, Incapacidad retroactiva, Grupo de servicios y Modalidad de la incapacidad, Institución receptora de la remisión (va como texto en `MotiRemi`). |

## Verificado contra SIHOS (septiembre 2026)

Revisado por el usuario contra registros reales de SIHOS. Tiene prioridad sobre los supuestos de arriba. Resumen de la
revisión del 26/09/2026 en [`REVISION_SIHOS.md`](REVISION_SIHOS.md), que manda sobre todo este documento.

| Tema | Regla verificada |
| --- | --- |
| Pestañas por módulo | Nombres, orden y numeración exactos. Urgencias: 1 Triage … 20 Plan de Manejo, 21 Neurológico, 22 PROCEDIMIENTO TERAPIAS … 28 Imágenes. Observación: 1 Consultas … 16 Oxígeno … 24 Remisiones, 25 Plan de Manejo, 26 GLUCOMETRIA. Consulta Externa: 1 Anamnesis … 5 Prescripción A, 6 Ordenación, 7 Plan de Manejo … 15 Imágenes (lista completa en `docs/SIHOS_PANTALLAS.md`). |
| Plan de Manejo (`RipsCons.ObseReco`) | Texto libre "Plan de Manejo y Recomendaciones". En Urgencias y Observación está lleno en el 100 % de las consultas: **obligatorio** en la consulta. |
| `EncaPres.PresSali` | 1 = prescripción hospitalaria, **2 = fórmula de salida**. Urgencias y Observación: 1 por defecto, con la casilla "Fórmula de salida" (= 2). Prescripción A de Consulta Externa: siempre 2. |
| `EncaPres.Consecut` / `EncaOrde.Consecut` | **Un solo contador global compartido** por las dos tablas (≈ 1.020.023 en septiembre 2026). En la contingencia se guarda temporal (= `ConsPres` / `ConsOrde`). No es único. **Fase 3**: al cargar, el siguiente = `GREATEST(MAX(EncaPres.Consecut), MAX(EncaOrde.Consecut)) + 1`. `HojaProc.NumeOrde` **no** cambia (usa `ConsOrde`). |
| `HojaEnfe.TipoNota` | Códigos fijos: **1 = nota de enfermería, 2 = nota médica**, 5 = consentimiento. No se busca por nombre. |
| Cierre de `RipsCons` | Urgencias y Observación: se cierra al guardar (`FechCier/HoraCier/UsuaCier` llenos). Consulta Externa: `EstaReal = 1` **sin cierre** (`FechCier = '0000-00-00'`, `HoraCier = '00:00:00'`, `UsuaCier = ''`). |
| `RipsCons.TipoCons` | Obligatorio y siempre lleno. Por defecto 890701 en Urgencias, 89060102 en Observación y 890201 en Consulta Externa (otros comunes: Observación 890601; Consulta Externa 890301, 890208). |
| `RipsCons.DestSali` | 4 en Consulta Externa (no se pide). |
| `EstaGene` (sistemas) | Normal (1) por defecto en todos los sistemas; Anormal (2) exige descripción. |
| `CentCost` de `HojaMate`, `HojaProc` y `HojaMedi` | `''` (vacío; en SIHOS aparece `''` casi siempre o `'0'`). |
| `EncaOrde` | `CodiFina = '10'` por defecto; `Autoriza = 0` y `OrdeSali = 0` **siempre** (no hay casilla de autorización). |
| `HojaMedi.NumeOrde` | = `EncaPres.ConsPres` de la prescripción aplicada. |
| `HojaProc.NumeOrde` | = `EncaOrde.ConsOrde` de la orden atendida (número dentro de la admisión), **no** `EncaOrde.Consecut`. |
| `HojaProc` usuarios | `UsuaDigi`, `UsuaModi`, `CodiProf` y `UsuaAsis` son `varchar(8)`: login recortado a 8 caracteres. |
| Consulta Externa sin `SaliInte` | Solo "Cerrar Historia" (marca la admisión cerrada). |
| `Triage.ConsTria` | Consecutivo por paciente (no por admisión). |
| Remisión | **Sin verificar**: no hay remisiones recientes en SIHOS; se deja como está (ver la fila de supuestos). |
