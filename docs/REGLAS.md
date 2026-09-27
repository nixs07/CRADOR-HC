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
- **Como SIHOS (recorrido 26/09/2026, `docs/RECORRIDO_SIHOS.md` §0.2): sin mínimos, máximos ni obligatorios**
  (SIHOS guarda PA 1/1 y TM 0). Solo se valida que sea un número ≥ 0 y que quepa en la columna (p. ej. `Peso`
  decimal(5,2) ≤ 999.99, `Temperat` decimal(4,2) ≤ 99.99). Campos y orden en Triage, Consultas, Evolución y
  Signos Vitales: Peso (Kg) · Talla (cm) · IMC (calculado) · FC (Min) · FR (Min) · Temp (°C) · PA (sist / diast) ·
  TM (calculada) · Fetocardia (Lat/min) · Saturación (%) · Oximetría · Glucometría.
- **"Dolor" no existe en SIHOS**: no se muestra y `SignVita.Dolor` se guarda en 0.
- Tabla histórica de Signos Vitales: Cons · Evolución (`ConsEvol`) · Sede (`CodiInst`; no hay columna de sede) ·
  Fecha · Hora · Peso · Talla · IMC · FC · FR · Temp · PA · Fetocardia · Saturación · Glucometría · Profesional.

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

> **`docs/RECORRIDO_SIHOS.md` (recorrido contra SIHOS producción, 26/09/2026) manda sobre todo lo de este
> archivo.** Las secciones del final ("Encabezado y cierre", "Barra de pestañas por usuario", "Pestañas con los campos
> y botones de SIHOS", "Historias Abiertas") recogen lo aplicado de ese recorrido.

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
| Confirmación | Cerrar Historia pide confirmar en una ventana de la página (sin casilla); el egreso ya no cierra la historia. |
| Traslado de cama (TrasCama) | Pestaña 23. Cambio de Atención de Observación (antes en el encabezado). Cada fila es el tramo en la cama anterior: `CodiServ`/`CamaOrig` = servicio y cama de origen, `ServEgre`/`CamaDest` = destino, `FechIngr/HoraIngr` = inicio del tramo (ingreso o traslado anterior), `FechSali/HoraSali` = hora del traslado, `Dias` = días completos y `Horas` = horas restantes del tramo. Actualiza `Admision.CamaActu` y `ServEgre`; `CentEgre` no se toca (queda vacío, ver valores fijos). La cama destino debe estar activa y libre. |
| Materiales (HojaMate) | Pestaña Materiales (Urgencias 16, Observación 18). `CodiMate` de `CodiSumi`, `UnidMate` de `CodiUnid`; `EsFact = 1`, `CantFact = 0`, `NumeOrde = Item = 0`, `UsuaAsis` = login. `CentCost`: ver "Verificado contra SIHOS". |
| Remisión (Remision) — **sin verificar** | SIHOS no tiene remisiones recientes para compararlas: todo lo de esta fila es supuesto. Pestaña Remisiones (Urgencias 15, Observación 24, Consulta Externa 18). `RemiMoti` = código del catálogo `MotiRemi`; `MotiRemi` (texto) = resumen clínico; `ModaSoli` del catálogo `ModaSoli`; `EspeRemi` de `CodiEspe` (opcional). **No hay catálogo local de instituciones receptoras**: `InstRemi` queda vacío y el nombre de la institución se escribe al inicio de `MotiRemi` ("INSTITUCION DESTINO: …"). `CodiRemi` = consecutivo por admisión; `FechSali/HoraSali` = fecha de la remisión; `FechAcep/HoraAcep` = Fecha y Hora Aceptación de SIHOS; si se dejan vacías y se escribe quién acepta, la de la remisión; `Cerrado = 0`; `TipoDiag` guarda el código de `TipoDiag` como texto. No cierra la admisión (el egreso se hace aparte). |
| Incapacidad (IncaPaci) | Pestaña Incapacidad (Urgencias 17, Observación 21, Consulta Externa 12). La fecha final (inicial + días − 1) solo se muestra. `TipoInca` del catálogo; `OrigInca` 1 = común, 2 = laboral (comentario de la columna); días de 1 a 540; `ConsInca` = consecutivo por admisión. |
| Procedimiento de una orden | En la pestaña Procedimientos se puede escoger un ítem de `DetaOrde` pendiente (`CantReal < CantSumi`): el procedimiento sale del ítem, `HojaProc.NumeOrde` = `EncaOrde.ConsOrde` (número de la orden **dentro de la admisión**, no el `Consecut` global; verificado contra SIHOS: 500 de 500) e `Item` = ítem, y `DetaOrde.CantReal` suma 1 (se busca por `ConsAdmi` + `ConsOrde` + `Item`). `CentCost = ''`. |
| Pestañas después de las visibles | Observación 27-31 (PyP, Imágenes, Laboratorios y Diagnósticos, SALUD PUBLICA, Atención del Menor) y Consulta Externa 16-21 (Medicamentos, No POS, Remisiones, Notas Enfermería, SALUD PUBLICA, Cambio de Atención) no tienen número visible en SIHOS: aquí se numeran a continuación. Las pestañas sin tablas en CRADOR-HC se muestran deshabilitadas "No disponible en contingencia" (Líquidos también: no hay captura de sus campos). |
| Consulta en Consulta Externa | Las pestañas 1. Anamnesis, 2. Rev.Sistemas y Ex.Físico, 3. Antecedentes, 4. Laboratorios y Diagnósticos y 7. Plan de Manejo son **un solo formulario** (una fila de `RipsCons`). Si hay un error se abre la pestaña donde está. |
| Barra de cada pestaña | Nuevo (formulario en blanco), No. (registros anteriores: lleva al registro), Fecha, Hora y campos propios (Tipo de prescripción, Tipo de incapacidad, Autorización, Profesional). Imprimir y Cargos están deshabilitados: no aplican en contingencia. |
| Notas (HojaEnfe) | Sin selector de tipo (lo da la pestaña, ver "Verificado contra SIHOS"). La casilla Revisada de Notas Médicas llena `Reviza = 1`, `UsuaRevi`, `FechRevi`, `HoraRevi`. |
| Signos en consulta y evolución | La fila de signos de Consultas (Revisión por sistema) y de Evolución es opcional: si se escribe alguno se guarda una toma de `SignVita` con `ConsCons` o `ConsEvol` (sin obligatorios). `Oximetria` queda `NULL` si no se escribe. |
| Triage | "Continuar en el consultorio" = `Triage.CodiCons` del catálogo `CodiCons` (activos), opcional. |
| Consulta (campos de SIHOS) | Sintomáticos (`SintResp`, `SintPiel`, `SintNerv`, `TubeMult`: 1 sí / 2 no), `PeriAbdo` (0-200) y `PeriTorx` (0-150), `LaboImag`, diagnósticos Principal y Rela 1-4 con tipo (`TipoDiag`, `TipoDia1-4`, 0 si no hay), Destino en Urgencias y Observación (`DestSali`, catálogo `DestSali`, se guarda como número; 4 si no se escoge). Prescripción: Tipo de prescripción (`TipoPres` 1 regular / 2 control), DXP, DXR 1 y DXR 2. Evolución: Rela 1-4 con tipo (`TipoDiag1-4`). Egreso: Rela 1-3 y Complicación (`DiagComp`, su tipo en `TipoDia4`). |
| Plan de Manejo (Urgencias 20, Observación 25) | Es el mismo `RipsCons.ObseReco` del acordeón de Consultas. Sin selector de consulta (como SIHOS): edita el plan, el destino y el Código Dorado (`Especif`, `ObserCd`) de la consulta más reciente. Sin consulta: "Registre primero la consulta". |
| Campos de SIHOS sin guardar | No se piden porque no tienen columna o catálogo local: Lepra (`TipoLepr`), Tipo de discapacidad, Conducta de la consulta (lista 33), método de planificación (`MetoDesc`), Sivigila/Protocolo, Id Estudio, Revisado del procedimiento y de la evolución, índice cintura-cadera, órdenes posfechadas, Alcance, Incapacidad retroactiva, Grupo de servicios y Modalidad de la incapacidad, Institución receptora de la remisión (va como texto en `MotiRemi`). |

## Verificado contra SIHOS (septiembre 2026)

Revisado por el usuario contra registros reales de SIHOS. Tiene prioridad sobre los supuestos de arriba. Resumen de la
revisión del 26/09/2026 en [`REVISION_SIHOS.md`](REVISION_SIHOS.md), que manda sobre todo este documento.

| Tema | Regla verificada |
| --- | --- |
| Pestañas por módulo | Nombres, orden y numeración exactos. Urgencias: 1 Triage … 20 Plan de Manejo, 21 Neurológico, 22 PROCEDIMIENTO TERAPIAS … 28 Imágenes. Observación: 1 Consultas … 16 Oxígeno … 24 Remisiones, 25 Plan de Manejo, 26 GLUCOMETRIA. Consulta Externa: 1 Anamnesis … 5 Prescripción A, 6 Ordenación, 7 Plan de Manejo … 15 Imágenes (lista completa en `docs/SIHOS_PANTALLAS.md`). |
| Plan de Manejo (`RipsCons.ObseReco`) | Texto libre "Plan de Manejo y Recomendaciones". En Urgencias y Observación está lleno en el 100 % de las consultas: **obligatorio** en la consulta. |
| `EncaPres.PresSali` | 1 = prescripción hospitalaria, **2 = fórmula de salida**. Urgencias y Observación: siempre 1; Prescripción A de Consulta Externa: siempre 2. Sin casilla "Fórmula de salida" (SIHOS no la muestra en la pestaña). |
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

## Encabezado y cierre (recorrido SIHOS 26/09/2026, `docs/RECORRIDO_SIHOS.md` §1)

- Barra: Admisión · Fecha · Hora · Autorización (en Observación: **Cama**) · SOAT · estado Abierta/Cerrada.
  Fila 1: Documento · Usuario · F. Nacimiento · Edad · Género · Grupo (`GrupoAte`). Fila 2: Servicio Origen
  (C.Costos) (`CodiServ` + `CentCost`) · Cama Origen (`CodiCama`) · Vía Ingreso · Servicio Actual (`ServEgre`) ·
  Cama Actual (`CamaActu`) · Entorno de Atención. Fila 3: Causa Externa · Estado Ingreso (`EstaIngr`) · Condición ·
  Discapacidad · Diagnóstico. EPS, Contrato, Tipo de usuario, Afiliación y Categoría **se quedan** (decisión del usuario).
- **SOAT**: no hay columna identificada en `Admision` (¿`NumePoli`? **pendiente confirmar**): se muestra deshabilitado.
- **Estado Ingreso**: se muestra el código de `EstaIngr` (no hay catálogo local; CRADOR guarda 1). **Pendiente**: nombre de la lista.
- **Discapacidad**: `Admision` no tiene columna; se muestra `Paciente.TipoDisc` con el catálogo `TipoDisc`, o
  "Sin discapacidad" si es 0.
- Botones Modificar · Eliminar · Imprimir · Anular: visibles y **deshabilitados** (no aplican en contingencia).
  Buscar, Limpiar y **Cerrar Historia** funcionan en los 3 módulos.
- **Cerrar Historia**: confirmación en la página (ventana propia, sin casilla). Urgencias y Observación exigen el
  egreso (`SaliInte`); si no existe, lleva a la pestaña Egreso. Cierra: `Cerrado = 1`, `FechCier/HoraCier/UsuaCier`,
  `FechEgre/HoraEgre` = salida del egreso (Consulta Externa: el momento del cierre).
- **Egreso**: Guardar crea `SaliInte` y Modificar lo actualiza (`FechModi/HoraModi/UsuaModi`); **ninguno cierra la
  historia**. Sin "Tipo de egreso" (`SaliInte.TipoEgre` queda con su valor por defecto 0) y sin casilla de
  confirmación. Debajo, las listas de pendientes: insumos por descargar (no aplica), ayudas diagnósticas por
  interpretar (ítems de `DetaOrde` con `CantReal < CantSumi`) y consultas por cerrar (`RipsCons.FechCier` vacía).

## Barra de pestañas por usuario (permisos de SIHOS, `docs/RECORRIDO_SIHOS.md` §0.1)

- En SIHOS la numeración depende del usuario: objetos permitidos del módulo, orden por el **mínimo de
  `ModuObje.Orden`** de cada objeto, empate por **`CodiObje`**, numerados 1..N. Las pestañas que CRADOR-HC no
  implementa se muestran deshabilitadas con su número.
- `pestanas_usuario($login, $modulo)` (src/formulario.php) arma la barra desde los catálogos `UsuaGrup`,
  `Permisos`, `ModuObje` y `Objetos`. **Sus estructuras no están en sql/ y sus columnas no están confirmadas**:
  `sql/04_permisos_PROVISIONAL.sql` las crea con lo mínimo (confirmados: `ModuObje.Orden` y `CodiObje`; el resto
  son marcadores). El objeto se liga a su panel por el nombre (sin tildes) de la lista fija.
- Si las tablas no existen, están vacías para el usuario o la consulta falla, se usa la **lista fija**
  `pestanas_lista()`: Urgencias según `docs/REVISION_SIHOS.md`; Observación y Consulta Externa según
  `docs/RECORRIDO_SIHOS.md` §4 y §5 (Observación: 5 No POS, 6 Ordenación; Consulta Externa: 12 Atención del Menor,
  13 Incapacidad). Las pestañas después de las visibles se numeran a continuación.
- Pendiente del usuario: correr `docs/consultas_sihos.sql` en SIHOS (sección 1), reemplazar las definiciones
  provisionales, ajustar la consulta de `pestanas_usuario()` y copiar los catálogos con
  `bin/actualizar_catalogos.php --tablas=UsuaGrup,Permisos,ModuObje,Objetos` (ya los incluye).

## Pestañas con los campos y botones de SIHOS (`docs/RECORRIDO_SIHOS.md` §3 a §5)

- **Botonera**: barra Nuevo/Nueva · No. · Fecha · Hora (y los campos propios de cada pestaña) y abajo los botones de
  SIHOS en su orden. Guardar (y Modificar donde aplica) funcionan; Imprimir, Consultar, Cargos, Eliminar, Sugerido,
  Protocolo, Plantilla y Experiencia se ven **deshabilitados** (no aplican en contingencia).
- **Consultas (Urgencias/Observación)**: cinco acordeones, cada uno con su Guardar sobre la misma consulta
  (`RipsCons` + `Antecede` + `EstaGene` + la toma de `SignVita` con `ConsCons`, que se actualizan si ya existen), y
  **Cerrar Consulta**, que llena `FechCier/HoraCier/UsuaCier`, con la Duración. El formulario sigue en la consulta
  abierta hasta cerrarla; "Nuevo" empieza otra. Tipo y Finalidad, Motivo y Enfermedad Actual se piden siempre; el
  diagnóstico principal al guardar Laboratorios y Diagnósticos, el Plan o al cerrar; el Plan al guardarlo o al cerrar.
- **Consulta Externa**: pestañas separadas (1, 2, 3, 4 y 7) de un mismo formulario, cada una con su Guardar; la
  consulta queda realizada **sin cierre** (verificado). Índice Cintura-Cadera → `SignVita.PeriCint`, `PeriCade`,
  `ResCXC` (= cintura / cadera). FUR y Fecha Probable del Parto → `Antecede.FechRegl` y `FechPart`. Pestaña 7: Destino
  (siempre 4), Recomendaciones y Plan de Manejo y Código Dorado.
- **Antecedentes**: orden de SIHOS con Andrológicos (`Andropo/AndroDesc`) y Conciliación medicamentosa
  (`Consilia/ConsiDesc`) solo en Urgencias/Observación. Opciones Si | No | No Sabe | No Corresponde:
  **pendiente confirmar** que No Sabe = 3 y No Corresponde = 4 (1 y 2 sí son sí/no).
- **Examen físico**: orden de sistemas de Urgencias (Cabeza, Cuello, Tórax, Abdomen, G/U, Extremidades, Neurológico,
  Nariz, Oídos, Boca, Ojos, Piel, Ano, Osteomuscular) y el de Consulta Externa (§5). Normal (1) por defecto, Anormal
  (2, con descripción) y No se Explora (**`NULL`, pendiente confirmar**).
- **Plan de Manejo (Urgencias 20, Observación 25)**: sin selector; edita el plan de la consulta más reciente:
  `ObseReco`, `DestSali` y del Código Dorado `Especif` y `ObserCd`.
- **Prescripción** en rejilla (6 filas + Agregar). Urgencias/Observación: Cantidad por dosis (`CantSumi`) · Unidad ·
  Vía · Cada (`CantFrec/TiemFrec`) · A partir de (`HoraInic`) · Número (Dosis) (`NumeDosi`) · Cantidad solicitada
  (`CantSoli`) · Nota (`PresMedi`) · Medi. Prin. (`MediPrin`) · Entregado (`CantEntr`, solo lectura). `CantTota` =
  dosis × número de dosis; la duración no está en esa pantalla: **supuesto** `CantPeDu` = número de dosis × cada
  (tope 127, `tinyint`) y `TiemPeDu` = `TiemFrec`. Prescripción A (Consulta Externa): Dosis · Vía · Frecuencia ·
  Periodo de duración (`CantPeDu/TiemPeDu`) · Total (Dosis) calculado · Cantidad solicitada · Nota. DXP y DXR 1-4 son
  listas con los diagnósticos de las consultas (`CodiDiag`, `CodiRel1-4`); Responsable de la entrega → `PersEntr`.
  `PresSali` = 1 en Urgencias/Observación y 2 en Consulta Externa (sin casilla). **Tipo "Domiciliaria": pendiente
  confirmar** su código en `TipoPres` (se ve deshabilitada; se guardan 1 Regular y 2 Control).
- **ORDENES MEDICAS**: etiqueta "1. Orden medica:"; Modificar deshabilitado.
- **Ordenación** en rejilla: Código · Nombre · Cant · Susp · Nota · Tomar A (Cada, sin columna en `DetaOrde`).
  (Solicitar Autorización para EPS) y Salida visibles y deshabilitados (`Autoriza = OrdeSali = 0`, verificado).
- **Procedimientos**: Id Estudio deshabilitado (sin columna); Revisado → `HojaProc.ContRevi`, `MediRevi`, `FechRevi`,
  `HoraRevi`. El selector "Atiende la orden" no está en SIHOS: **quedó oculto** (el servidor aún acepta `OrdenItem`).
- **Evolución**: Tipo → `EvolInte.CodiProc`; Finalidad deshabilitada (sin columna); Revisado → `ContRevi`, `MediRevi`,
  `FechRevi`, `HoraRevi`.
- **Notas Enfermería / Notas Médicas** (iguales): Revisada y Actividad (`HojaEnfe.Procedim`, código CUPS) en las dos.
- **Medicamentos** en rejilla: se escoge la prescripción (No.) y cada medicamento es una fila con fecha/hora de
  aplicación y planeada, cantidad y observaciones; se guardan las filas con cantidad (una fila de `HojaMedi` cada una).
- **Materiales** en rejilla de 5 filas: Fecha · Hora · Código · Nombre · Cant · Unidad · Indicaciones (Orden, Item y
  Factura informativos, vacíos).
- **Remisiones** (pestaña propia en los 3 módulos; **sin verificar**): Especialidad y **Institución** en listas.
  El catálogo "Instituciones de Remisión" no está en sql/: `sql/05_instituciones_remision_PROVISIONAL.sql` crea
  `InstRemision (CodiInre, NombInre)` como **marcador** (nombre y columnas sin confirmar) → `Remision.InstRemi`. La
  pantalla no pide diagnóstico ni placa: `DiagRemi`/`TipoDiag` salen de la última consulta con diagnóstico (o el de
  ingreso) y `PlacAmbu` queda vacía.
- **Incapacidad**: Maternidad → `IncaPaci.FePoPart`, `EdadGest`, `NaciVivo`. Fecha inicial = Fecha (`FechInca`).
- **Cambio de Atención (Observación 23)** es pestaña (ya no está en el encabezado): traslado de cama dentro de la
  institución (`TrasCama`, `CoinDest` vacío).

### Campos de SIHOS mostrados deshabilitados (sin columna o sin catálogo en CRADOR-HC)

| Pantalla | Campos |
| --- | --- |
| Encabezado | SOAT (¿`NumePoli`?); Estado Ingreso muestra el código |
| Consultas | Antecedentes: método de planificación (`MetoDesc` sin catálogo), parentesco y diagnóstico de familiares, ventanas de Patológicos y Obstétricos, Tipo de Alergia y Alergia a Medicamentos, Tipo Medicamento, Tipo de factor de riesgo, **Reconciliación Medicamentosa** (tabla `RecoMedi`); Revisión: Tuberculosis Multidrogoresistente (7 opciones), Lepra (`TipoLepr` sin catálogo), Tipo de Discapacidad; Plan: Conducta (lista 33); Código Dorado: Acciones inmediatas, Continuidad del cuidado, Estado (lista 47) |
| Consulta Externa 4 | Laboratorios (resultados) |
| Prescripción | Susp, Unidad de la cantidad solicitada, tipo Domiciliaria, Sugerido/Protocolo/Plantilla/Experiencia |
| Ordenación | (Solicitar Autorización para EPS), Salida, Susp, Tomar A (Cada), Plantillas/Sugerido/Protocolo |
| Procedimientos | Id Estudio |
| Evolución | Finalidad |
| Incapacidad | Alcance, Incapacidad retroactiva, Grupo de servicios, Modalidad de prestación, Embarazo múltiple |
| Egreso | Insumos pendientes por descargar (inventario) |

## Historias Abiertas y ventana automática (`docs/RECORRIDO_SIHOS.md` §2 y §4)

- Filtros de SIHOS: Seleccione Servicio · Seleccione consultorio (`Triage.CodiCons`) · Mostrar N registros (10, 25, 50,
  100). Se quitó el buscador libre (SIHOS no lo tiene).
- Columnas: Servicio · Cama · Admisión · Fecha · Duración · T · Autoriza. (`NumeAuto`) · Triage · Med · Ord · Paciente ·
  Edad · Estado · Consultorio · Profesional; filas coloreadas por triage. **Supuestos**: "T" = color del triage;
  "Med" = medicamentos prescritos pendientes por aplicar (`DetaPres.CantApli < CantTota`, sin suspender); "Ord" =
  ítems de órdenes pendientes (`DetaOrde.CantReal < CantSumi`); Estado = "Abierta".
- Al abrir una historia de Urgencias u Observación (sin pestaña en la dirección) sale una ventana propia con los
  Antecedentes Tóxicos y Alérgicos del paciente (`Antecede.ToxiAler/ToxiDesc`, `AlerSiNo/AlerDesc`, de todas sus
  admisiones) y la Reconciliación Medicamentosa ("no disponible": falta la tabla `RecoMedi`). Se cierra con Esc,
  la X o Aceptar.
