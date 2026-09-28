# Reglas de negocio (verificadas contra SIHOS real, septiembre 2026)

> **Mandan sobre todo este archivo (y sobre `RECORRIDO_SIHOS.md` y `REVISION_SIHOS.md`)**:
> [`VALIDACIONES_SIHOS.md`](VALIDACIONES_SIHOS.md) (obligatorios, mensajes, búsquedas y antecedentes) y
> [`RESULTADO_CONSULTAS_SIHOS.md`](RESULTADO_CONSULTAS_SIHOS.md) (estructuras reales, catálogos y supuestos
> confirmados o corregidos), ambos verificados contra SIHOS producción el 26/09/2026. Solo se apartan de ellos las
> decisiones explícitas del usuario (sección "Decisiones del usuario"). Lo aplicado está en las secciones
> "Obligatorios y mensajes de SIHOS" y "Catálogos reales, antecedentes y supuestos confirmados" (al final).

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
- **Como SIHOS (`VALIDACIONES_SIHOS.md` §2)**: **obligatorios en Triage** (Peso, Talla, FC, FR, Temperatura,
  PA sistólica y diastólica, Saturación) **y en Evolución** (Peso, Talla, FC, FR, Temperatura, PA), con los mensajes
  de SIHOS ("Digite el peso", "Digite la talla", "Digite la frecuencia cardiaca"…). Oximetría, Glucometría y
  Fetocardia son opcionales. **Sin límites de valor** (SIHOS guarda temperaturas de 1 °C y PA 1/1) **salvo el peso
  máximo de 300 Kg** ("Por favor verifique el peso, este no puede sobrepasar 300 Kg."); los demás topes son solo el
  tamaño de la columna (p. ej. `Temperat` decimal(4,2) ≤ 99.99). En la pestaña Signos Vitales cada toma debe ser
  **posterior** a la última ("…no puede ser inferior o igual a los anteriormente digitados"). Campos y orden en Triage, Consultas, Evolución y
  Signos Vitales: Peso (Kg) · Talla (cm) · IMC (calculado) · FC (Min) · FR (Min) · Temp (°C) · PA (sist / diast) ·
  TM (calculada) · Fetocardia (Lat/min) · Saturación (%) · Oximetría · Glucometría.
- **"Dolor" no existe en SIHOS**: no se muestra y `SignVita.Dolor` se guarda en 0.
- Tabla histórica de Signos Vitales: Cons · Evolución (`ConsEvol`) · Sede (`CodiInst`; no hay columna de sede) ·
  Fecha · Hora · Peso · Talla · IMC · FC · FR · Temp · PA · Fetocardia · Saturación · Glucometría · Profesional.

## Valores fijos de la admisión (lo que SIHOS guarda en la práctica, ago-sep 2026)

`EstaIngr = 1` (catálogo `EstaIngr`: 1 Conciente), `EntoAten = 20`, `VienRefe = 0`, `CentCost = CentEgre = ''`,
`NumePoli` = SOAT (vacío salvo accidentes de tránsito; confirmado), `Reingres = 0`,
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
| Antecedentes (Antecede) | Se guarda una fila por consulta, ligada por `ConsCons`. Lo múltiple (familiares, alergias, factores, farmacológicos, preguntas) va en `comu_antecedentes_multiples` (ver al final). Lista Sí/No de SIHOS (`CodiSino`, confirmada): 1 Si, 2 No (por defecto), 3 No Sabe, 4 No Corresponde. En el orden de SIHOS: Planificación (`MetoPlan`), Familiares, Personales, Patológicos, Obstétricos, Ginecológicos, Quirúrgicos, Tóxicos, Alérgicos (`AlerSiNo`), Fisiológicos, Alimentarios, Traumáticos, Farmacológicos y Factor de riesgo (`FactRies`), cada uno con su columna `*Desc` (obligatoria si es Sí). Planificación y Factor de riesgo no tienen columna de descripción. |
| Examen físico (EstaGene) | Sistemas y etiquetas de SIHOS: Cabeza, Ojos, Oídos, Nariz, Boca, Cuello, Tórax (`CardPulm`), Abdomen, G/U (`GeniUrin`), Ano, Extremidades, Neurológico, Osteomuscular y Piel. Valores: ver "Verificado contra SIHOS". `ConsHoPr = 0`. |
| Prescripción | `TipoPres` 1 Regular / 2 Control / 3 Domiciliaria (supuesto: en SIHOS solo se usan 0 y 1), `FechEntr = Fecha`, `CodiFina` del detalle = `NULL`, `HoraApli = 0` (no hay catálogo `HoraApli` local), `HoraInic` = hora de la prescripción. `NumeDosi` = duración ÷ frecuencia (en horas, con `CodiTiem` 1 = horas, 2 = días, 3 = meses según el comentario de `DetaPres`) y `CantTota = CantSumi × NumeDosi`. La Nota (`PresMedi`) es obligatoria en la prescripción hospitalaria; en la Prescripción A, si no se escribe, se arma con dosis, vía, frecuencia y duración. `PresSali`: ver "Verificado contra SIHOS". |
| Administración de medicamentos (HojaMedi) | Pestaña Medicamentos. Solo de lo prescrito en la admisión; `Item = Item` de `DetaPres`; `EstaApli = 1`; plan = hora de aplicación. Suma la cantidad en `DetaPres.CantApli`. |
| Órdenes (EncaOrde/DetaOrde) | Pestaña Ordenación. La finalidad (obligatoria) es de la orden (`EncaOrde.CodiFina`, catálogo `FinaCons`) y `DetaOrde.CodiFina` queda `NULL`. `OrdeAmbu` (Ambulatoria): 1 marcado, 0 no. DXP y DXR1-DXR4 en `CodiDiag`, `CodiRel1-4`. `CantReal` suma al realizar el procedimiento del ítem. |
| Orden médica en texto | `EncaData/DetaData` con `TipoObje = 7`, `CodiItem` 131 Urgencias / 130 Observación. En Consulta Externa no se muestra. |
| Procedimientos (HojaProc) | `CantProc` (Cant, 1 por defecto) y `ProcReal` (Realizado?, marcado por defecto) se escriben en la pestaña; diagnósticos Principal, Rela 1, Rela 2, Rela 3 y Compl en `DiagPrin/TipoDiag`, `DiagRela/TipoDiaR`, `DiagRel1/TipoDia1`, `DiagRel2/TipoDia2`, `DiagComp/TipoDiaC`; `NumePiez = CuadPiez = 0`, `NumeOrde = Item = 0` si no atiende una orden. |
| Evolución (EvolInte) | Formato SOAP (`Subjetivo`, `Objetivo`, `Analisis`, `PlanMane`). No aplica en Consulta Externa (0 % de uso en la muestra). `ContSign/ContLiqu` = 1 marcado, 0 no. |
| Egreso (SaliInte) | Urgencias y Observación: se guarda `SaliInte` y se cierra la admisión (`Cerrado = 1`, `FechCier/HoraCier/UsuaCier`, `FechEgre/HoraEgre` = salida). `DiasEsta/HoraEsta` se calculan desde el ingreso. Si `EstaSali` es "muerto" (se reconoce por el nombre en el catálogo) se piden causa (`DiagMuer`, 4 caracteres) y fecha/hora de muerte. La cama queda libre porque la ocupación se calcula con las admisiones abiertas (no se toca `CodiCama`). |
| Consulta Externa: "Cerrar Historia" | El botón **Cerrar Historia** del encabezado (con confirmación) marca la admisión cerrada (`Cerrado = 1` y fechas de cierre/egreso). |
| Confirmación | Cerrar Historia pide confirmar en una ventana de la página (sin casilla); el egreso ya no cierra la historia. |
| Traslado de cama (TrasCama) | Pestaña 23. Cambio de Atención de Observación (antes en el encabezado). Cada fila es el tramo en la cama anterior: `CodiServ`/`CamaOrig` = servicio y cama de origen, `ServEgre`/`CamaDest` = destino, `FechIngr/HoraIngr` = inicio del tramo (ingreso o traslado anterior), `FechSali/HoraSali` = hora del traslado, `Dias` = días completos y `Horas` = horas restantes del tramo. Actualiza `Admision.CamaActu` y `ServEgre`; `CentEgre` no se toca (queda vacío, ver valores fijos). La cama destino debe estar activa y libre. |
| Materiales (HojaMate) | Pestaña Materiales (Urgencias 16, Observación 18). `CodiMate` de `CodiSumi`, `UnidMate` de `CodiUnid`; `EsFact = 1`, `CantFact = 0`, `NumeOrde = Item = 0`, `UsuaAsis` = login. `CentCost`: ver "Verificado contra SIHOS". |
| Remisión (Remision) — **verificada** (660 remisiones reales) | Pestaña Remisiones (Urgencias 15, Observación 24, Consulta Externa 18). Obligatorios: institución (`InstRemi` = `InstRemi.CodInsRe`), especialidad (`EspeRemi`), persona que acepta (`NombAcep`), autorización (`NumeAuto`), texto (`MotiRemi`), modalidad (`ModaSoli`) y motivo (`RemiMoti`). Cargo (`CargAcep`), Ambulancia (`Ambulanc`, `PlacAmbu`) y Otro motivo (`OtroMoti`) opcionales. **`FechSali/HoraSali` se guardan vacías** (como SIHOS): la fecha de la remisión es `FechDigi/HoraDigi` (= la fecha y hora de la barra). `Cerrado = 0`. `DiagRemi`/`TipoDiag` salen de la última consulta con diagnóstico (o el de ingreso). No cierra la admisión. |
| Incapacidad (IncaPaci) | Pestaña Incapacidad (Urgencias 17, Observación 21, Consulta Externa 12). La fecha final (inicial + días − 1) solo se muestra. `TipoInca` del catálogo; `OrigInca` 1 = común, 2 = laboral (comentario de la columna); días de 1 a 540; Nota (`ObseInca`) obligatoria; `ConsInca` = consecutivo por admisión. Licencia de maternidad (tipo cuyo nombre dice "MATERN"): fecha probable del parto, edad gestacional y nacidos vivos (> 0; no más de 1 si Embarazo múltiple = No) obligatorios. `EmbaMult` 1 Sí / 0 No (**supuesto**). |
| Procedimiento de una orden | En la pestaña Procedimientos se puede escoger un ítem de `DetaOrde` pendiente (`CantReal < CantSumi`): el procedimiento sale del ítem, `HojaProc.NumeOrde` = `EncaOrde.ConsOrde` (número de la orden **dentro de la admisión**, no el `Consecut` global; verificado contra SIHOS: 500 de 500) e `Item` = ítem, y `DetaOrde.CantReal` suma 1 (se busca por `ConsAdmi` + `ConsOrde` + `Item`). `CentCost = ''`. |
| Pestañas después de las visibles | Observación 27-31 (PyP, Imágenes, Laboratorios y Diagnósticos, SALUD PUBLICA, Atención del Menor) y Consulta Externa 16-21 (Medicamentos, No POS, Remisiones, Notas Enfermería, SALUD PUBLICA, Cambio de Atención) no tienen número visible en SIHOS: aquí se numeran a continuación. Las pestañas sin tablas en CRADOR-HC se muestran deshabilitadas "No disponible en contingencia" (Líquidos también: no hay captura de sus campos). |
| Consulta en Consulta Externa | Las pestañas 1. Anamnesis, 2. Rev.Sistemas y Ex.Físico, 3. Antecedentes, 4. Laboratorios y Diagnósticos y 7. Plan de Manejo son **un solo formulario** (una fila de `RipsCons`). Si hay un error se abre la pestaña donde está. |
| Barra de cada pestaña | Nuevo (formulario en blanco), No. (registros anteriores: lleva al registro), Fecha, Hora y campos propios (Tipo de prescripción, Tipo de incapacidad, Autorización, Profesional). Imprimir y Cargos están deshabilitados: no aplican en contingencia. |
| Notas (HojaEnfe) | Sin selector de tipo (lo da la pestaña, ver "Verificado contra SIHOS"). La casilla Revisada de Notas Médicas llena `Reviza = 1`, `UsuaRevi`, `FechRevi`, `HoraRevi`. |
| Signos en consulta y evolución | Consultas: la fila de signos es opcional al guardar cada acordeón (se guarda una toma de `SignVita` con `ConsCons`), pero **Cerrar Consulta** la exige ("Falta Diligenciar los Signos Vitales"). Evolución: **obligatorios** (ver "Signos vitales"); la toma lleva `ConsEvol`. `Oximetria` queda `NULL` si no se escribe. |
| Triage | "Continuar en el consultorio" no se muestra (decisión del usuario): `Triage.CodiCons` se guarda vacío. |
| Consulta (campos de SIHOS) | Sintomáticos (`SintResp`, `SintPiel`, `SintNerv`, `TubeMult`: 1 sí / 2 no), `PeriAbdo` (0-200) y `PeriTorx` (0-150), `LaboImag`, diagnósticos Principal y Rela 1-4 con tipo (`TipoDiag`, `TipoDia1-4`, 0 si no hay), Destino en Urgencias y Observación (`DestSali`, catálogo `DestSali`, se guarda como número; 4 si no se escoge). Prescripción: Tipo de prescripción (`TipoPres` 1 Regular / 2 Control / 3 Domiciliaria), DXP (obligatorio) y DXR 1-4. Evolución: Rela 1-4 con tipo (`TipoDiag1-4`). Egreso: Rela 1-3 y Complicación (`DiagComp`, su tipo en `TipoDia4`). |
| Plan de Manejo (Urgencias 20, Observación 25) | Es el mismo `RipsCons.ObseReco` del acordeón de Consultas. Sin selector de consulta (como SIHOS): edita el plan, el destino y el Código Dorado (`Especif`, `ObserCd`) de la consulta más reciente. Sin consulta: "Registre primero la consulta". |
| Campos de SIHOS sin guardar | No se piden porque no tienen columna o catálogo local: Lepra (`TipoLepr`), Tipo de discapacidad, Sivigila/Protocolo, Id Estudio, órdenes posfechadas, Alcance, Incapacidad retroactiva, Grupo de servicios y Modalidad de la incapacidad, Código Dorado: Acciones inmediatas y Continuidad del cuidado (formato por confirmar). Conducta, método de planificación, Estado del Código Dorado, Institución de la remisión, Revisado, índice cintura-cadera y Embarazo múltiple **ya se guardan** (ver al final). |

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
| `EstaGene` (sistemas) | Normal (1) por defecto en todos los sistemas; Anormal (2) exige descripción; **No se Explora = 3** (confirmado). |
| `CentCost` de `HojaMate`, `HojaProc` y `HojaMedi` | `''` (vacío; en SIHOS aparece `''` casi siempre o `'0'`). |
| `EncaOrde` | `CodiFina` obligatoria (sin valor por defecto; SIHOS pide la finalidad antes de agregar); `Autoriza = 0` y `OrdeSali = 0` **siempre** (no hay casilla de autorización). |
| `HojaMedi.NumeOrde` | = `EncaPres.ConsPres` de la prescripción aplicada. |
| `HojaProc.NumeOrde` | = `EncaOrde.ConsOrde` de la orden atendida (número dentro de la admisión), **no** `EncaOrde.Consecut`. |
| `HojaProc` usuarios | `UsuaDigi`, `UsuaModi`, `CodiProf` y `UsuaAsis` son `varchar(8)`: login recortado a 8 caracteres. |
| Consulta Externa sin `SaliInte` | Solo "Cerrar Historia" (marca la admisión cerrada). |
| `Triage.ConsTria` | Consecutivo por paciente (no por admisión). |
| Remisión | **Verificada** con 660 remisiones (ago–sep): `FechSali/HoraSali` vacías, fecha en `FechDigi/HoraDigi`, `Cerrado = 0`, `ModaSoli` 1 casi siempre, `InstRemi` = código de `InstRemi`. Consulta Externa no tiene remisiones en los datos (la pestaña se deja). |

## Encabezado y cierre (recorrido SIHOS 26/09/2026, `docs/RECORRIDO_SIHOS.md` §1)

- Barra: Admisión · Fecha · Hora · Autorización (en Observación: **Cama**) · SOAT · estado Abierta/Cerrada.
  Fila 1: Documento · Usuario · F. Nacimiento · Edad · Género · Grupo (`GrupoAte`). Fila 2: Servicio Origen
  (C.Costos) (`CodiServ` + `CentCost`) · Cama Origen (`CodiCama`) · Vía Ingreso · Servicio Actual (`ServEgre`) ·
  Cama Actual (`CamaActu`) · Entorno de Atención. Fila 3: Causa Externa · Estado Ingreso (`EstaIngr`) · Condición ·
  Discapacidad · Diagnóstico. EPS, Contrato, Tipo de usuario, Afiliación y Categoría **se quedan** (decisión del usuario).
- **SOAT** = `Admision.NumePoli` (**confirmado**): se escribe al crear la admisión y se muestra en la barra.
- **Estado Ingreso**: nombre del catálogo `EstaIngr` (1 Conciente, 2 Inconsciente, 3 Muerto; se guarda 1).
- **Paciente inactivo** (`Paciente.Activo = 0`): solo puede admitirse por Urgencias (como SIHOS).
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
  `Permisos`, `ModuObje` y `Objetos`, con sus **estructuras reales** en `sql/04_listas_permisos.sql`: objetos de
  `Permisos` (por `CodiGrup` y `CodiModu`) de los grupos del usuario, con `Objetos.Activo = 1`, sin los de
  `ModuObje.Orden >= 99` (reportes), orden por `MIN(ModuObje.Orden)` y empate por `CodiObje`. El objeto se liga a
  su panel por el nombre (sin tildes) de la lista fija.
- Si las tablas no existen, están vacías para el usuario o la consulta falla, se usa la **lista fija**
  `pestanas_lista()`: Urgencias según `docs/REVISION_SIHOS.md`; Observación y Consulta Externa según
  `docs/RECORRIDO_SIHOS.md` §4 y §5 (Observación: 5 No POS, 6 Ordenación; Consulta Externa: 12 Atención del Menor,
  13 Incapacidad). Las pestañas después de las visibles se numeran a continuación.
- Se copian desde SIHOS con "Actualizar catálogos" / `bin/actualizar_catalogos.php` (incluye todo
  `sql/04_listas_permisos.sql`).

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
  (`Consilia/ConsiDesc`) solo en Urgencias/Observación. Opciones Si | No | No Sabe | No Corresponde = `CodiSino`
  1 | 2 | 3 | 4 (**confirmado**).
- **Examen físico**: orden de sistemas de Urgencias (Cabeza, Cuello, Tórax, Abdomen, G/U, Extremidades, Neurológico,
  Nariz, Oídos, Boca, Ojos, Piel, Ano, Osteomuscular) y el de Consulta Externa (§5). Normal (1) por defecto, Anormal
  (2, con descripción) y No se Explora (**3, confirmado**).
- **Plan de Manejo (Urgencias 20, Observación 25)**: sin selector; edita el plan de la consulta más reciente:
  `ObseReco`, `DestSali` y del Código Dorado `Especif` y `ObserCd`.
- **Prescripción** en rejilla (6 filas + Agregar). Urgencias/Observación: Cantidad por dosis (`CantSumi`) · Unidad ·
  Vía · Cada (`CantFrec/TiemFrec`) · A partir de (`HoraInic`) · Número (Dosis) (`NumeDosi`) · Cantidad solicitada
  (`CantSoli`) · Nota (`PresMedi`) · Medi. Prin. (`MediPrin`) · Entregado (`CantEntr`, solo lectura). `CantTota` =
  dosis × número de dosis; la duración no está en esa pantalla: **supuesto** `CantPeDu` = número de dosis × cada
  (tope 127, `tinyint`) y `TiemPeDu` = `TiemFrec`. Prescripción A (Consulta Externa): Dosis · Vía · Frecuencia ·
  Periodo de duración (`CantPeDu/TiemPeDu`) · Total (Dosis) calculado · Cantidad solicitada · Nota. DXP y DXR 1-4 son
  listas con los diagnósticos de las consultas (`CodiDiag`, `CodiRel1-4`); Responsable de la entrega → `PersEntr`.
  `PresSali` = 1 en Urgencias/Observación y 2 en Consulta Externa (sin casilla). Tipo "Domiciliaria" = `TipoPres` 3
  (**supuesto**: SIHOS no tiene registros con Control ni Domiciliaria). Un solo buscador por código o nombre llena
  código, nombre, unidad (`CodiSumi.UnidMedi`) y vía (`CodiSumi.ViaAdmin`) de la fila.
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
- **Remisiones** (pestaña propia en los 3 módulos; verificada): Especialidad e **Institución** (catálogo real
  `InstRemi`, 01–07; se guarda `CodInsRe`) en listas; Autorización, Acepta, Cargo, Modalidad, Motivo, Otro motivo,
  Incluir Ambulancia y Placa (`PlacAmbu`), Fecha y Hora de aceptación y texto. Ver la fila "Remisión" de supuestos.
- **Incapacidad**: Maternidad → `IncaPaci.FePoPart`, `EdadGest`, `EmbaMult` (Sí/No, supuesto), `NaciVivo`. Fecha inicial = Fecha (`FechInca`).
- **Cambio de Atención (Observación 23)** es pestaña (ya no está en el encabezado): traslado de cama dentro de la
  institución (`TrasCama`, `CoinDest` vacío).

### Campos de SIHOS mostrados deshabilitados (sin columna o sin catálogo en CRADOR-HC)

| Pantalla | Campos |
| --- | --- |
| Consultas | Revisión: Tuberculosis Multidrogoresistente (7 opciones), Lepra (`TipoLepr` sin catálogo), Tipo de Discapacidad; Código Dorado: Acciones inmediatas y Continuidad del cuidado (`Accinme`, `ContCuid`: formato por confirmar) |
| Consulta Externa 4 | Laboratorios (resultados) |
| Prescripción | Susp, Unidad de la cantidad solicitada, Sugerido/Protocolo/Plantilla/Experiencia |
| Ordenación | (Solicitar Autorización para EPS), Salida, Susp, Tomar A (Cada), Plantillas/Sugerido/Protocolo |
| Procedimientos | Id Estudio |
| Evolución | Finalidad |
| Incapacidad | Alcance, Incapacidad retroactiva, Grupo de servicios, Modalidad de prestación |
| Egreso | Insumos pendientes por descargar (inventario) |

## Historias Abiertas y ventana automática (`docs/RECORRIDO_SIHOS.md` §2 y §4)

- Filtros de SIHOS: Seleccione Servicio · Mostrar N registros (10, 25, 50, 100). Se quitó el buscador libre (SIHOS no
  lo tiene).
- Columnas: Servicio · Cama · Admisión · Fecha · Duración · T · Autoriza. (`NumeAuto`) · Triage · Med · Ord · Paciente ·
  Edad · Estado · Profesional; filas coloreadas por triage. **"T" = tipo de contrato** (confirmado): `E` Evento
  (`Contrato.TipoCont = 1`), `C` Cápita (`TipoCont = 2`). **Med / Ord son íconos indicadores** (probable, no
  verificado en datos): jeringa si hay medicamentos prescritos pendientes por aplicar (`DetaPres.CantApli < CantTota`,
  sin suspender) o visto si ya se aplicaron; matraz si hay ítems de órdenes pendientes (`DetaOrde.CantReal < CantSumi`).
  Estado = "Abierta".
- Al abrir una historia de Urgencias u Observación (sin pestaña en la dirección) sale una ventana propia con los
  Antecedentes Tóxicos y Alérgicos del paciente (`Antecede.ToxiAler/ToxiDesc`, `AlerSiNo/AlerDesc`, de todas sus
  admisiones) y la Reconciliación Medicamentosa (tabla `RecoMedi` de sus admisiones). Se cierra con Esc,
  la X o Aceptar.

## Decisiones del usuario que se apartan de "igual que SIHOS"

- **Consultorios fuera de la vista del profesional, en todas las pantallas** (septiembre 2026): el Triage no pide
  "Continuar en el consultorio" (`Triage.CodiCons` se guarda `''`) y Historias Abiertas no tiene el filtro
  "Seleccione consultorio" ni la columna "Consultorio".
- EPS, Contrato, Tipo de usuario, Afiliación y Categoría se ven en el encabezado (en SIHOS están ocultos).
- **Marca HSCJ** (septiembre 2026): el nombre visible de la app es **HSCJ** y su único subtítulo "Excelencia y
  servicio a la comunidad"; la pantalla de ingreso es vertical y centrada (logo, HSCJ, lema, Usuario, Clave,
  Ingresar). Los identificadores técnicos siguen con "crador" (repositorio, contenedores `crador_db`/`crador_app`,
  base `crador_hc`, `.env`, tablas `cont_*`): cambiarlos rompería las instalaciones.

## Buscadores (autocompletar) y validación

- Todos los buscadores (diagnósticos CIE-10, procedimientos/CUPS, Tipo/Actividad, suministros y materiales) usan un
  **autocompletar propio** (`public/js/formularios.js`, sin librerías ni `<datalist>`): desde 2 caracteres y 250 ms de
  espera muestra "CÓDIGO · Nombre", busca por código o por nombre, se maneja con flechas/Enter/Esc o mouse/touch y al
  escoger deja el CÓDIGO en el campo y el nombre al lado (en las rejillas, en la columna Nombre). Un código exacto
  escrito a mano también se toma. Los campos conservan su `name`/`id` y el servidor vuelve a validar.
- **Una sola regla para buscar y validar** (`BUSCADORES` en `src/listas.php`): tabla, código, nombre y filtro de
  activos (CIE-10: `CausMorb.Activo = 1 o NULL`; CUPS: `CodiProc.Activo = 1`; suministros: `CodiSumi.SumiActi = 1`).
  El código se compara **limpio** (sin espacios, tabuladores ni saltos de línea). Causa del error "El diagnóstico no
  existe en el CIE-10 activo" con Z002: el buscador encontraba el código con `LIKE 'Z00%'` pero la validación usaba
  `CodiDiag = 'Z002'`, y un código copiado de SIHOS con un carácter de sobra al final (p. ej. `Z002` + salto de línea)
  pasa el `LIKE` pero no el `=`. Ahora las dos usan el código limpio (los datos de prueba incluyen ese caso).
- El Egreso muestra **Incapacidad (días)** en solo lectura: la **suma** de `IncaPaci.DiasInca` de la admisión (una
  incapacidad y sus prórrogas suman los días otorgados); "Sin incapacidad" si no hay. Al guardar, `SaliInte.DiasInca`
  se calcula en el servidor e ignora lo que venga en el formulario.
- Botón **Nuevo/Nueva** de las barras: verde de marca, alto 44 px, en todas las pestañas.

## Obligatorios y mensajes de SIHOS (`VALIDACIONES_SIHOS.md` §2)

Se usan los **mismos mensajes** de SIHOS donde el documento los trae.

| Pantalla | Obligatorio (mensaje) |
| --- | --- |
| Triage | Motivo, hallazgos, impresión diagnóstica, clasificación y signos (ver "Signos vitales"). |
| Evolución | Signos ("Digite el peso / la talla / la frecuencia cardiaca / la frecuencia respiratoria / la temperatura / la presion arterial sistolica / diastolica"), Tipo = consulta de la evolución ("Debe seleccionar la consulta"), diagnóstico principal ("Debe seleccionar el DX principal"), Subjetivo, Objetivo, Análisis y Plan ("Debe digitar alguna informacion"), tipo de cada Rela diligenciada. |
| Signos Vitales | Fecha y hora posteriores a la última toma. |
| Consultas / Anamnesis | "Por favor, digite el tipo de consulta / el motivo de la consulta / la enfermedad actual". Diagnóstico principal y su tipo; "Ningun diagnostico debe repetirse" (todas las pantallas con Rela). |
| Plan (acordeón y pestaña) | "Ingresar por favor el destino", "El campo Conducta es obligatorio" y el plan; pestaña: "Por favor complete todos los campos". |
| Cerrar Consulta y Egreso | La consulta (en el egreso, la última de la admisión) debe estar completa: "Falta Diligenciar uno de los Siguientes Campos: Motivo de la Consulta / Enfermedad Actual", "Falta Diligenciar el Diagnostico Principal", "…los Signos Vitales", "…los Antecedentes", "…el Plan de Manejo y Recomendaciones", "Falta Diligenciar Revision por Sistema". |
| Prescripción (hospitalaria) | "Debe formular al menos un medicamento", diagnóstico, cantidad > 0, vía, "Debe indicar el tiempo de Aplicacion", Nota, "No es posible prescribir para mas de 24 Horas" (número de dosis × frecuencia), sin suministros repetidos. |
| Prescripción A (Consulta Externa) | Cantidad > 0, tipo de prescripción, diagnóstico, sin suministros repetidos. |
| Ordenación | Finalidad, al menos un procedimiento, cantidad > 0, sin procedimientos repetidos. |
| Procedimientos | Procedimiento, descripción, diagnóstico principal y tipos, cantidad > 0. |
| Notas | Nota obligatoria; Actividad opcional (0 de 22.044 notas la llenan). |
| Medicamentos | Cantidad > 0 y no mayor a lo ordenado pendiente (`CantTota − CantApli`); fecha no anterior al ingreso. |
| Remisiones | Institución, especialidad, acepta, autorización, texto, modalidad y motivo. |
| Incapacidad | Tipo, días y nota; maternidad (ver supuestos). |
| Encabezado | Tipo de documento; paciente inactivo solo por Urgencias. |

## Catálogos reales, antecedentes y supuestos confirmados (`RESULTADO_CONSULTAS_SIHOS.md`)

- **Catálogos nuevos** (`sql/04_listas_permisos.sql`, se copian con "Actualizar catálogos"): `UsuaGrup`,
  `Permisos`, `ModuObje`, `Objetos` (estructuras reales), `priv_listas_tipos` y `priv_listas_elementos` (listas
  genéricas), `EstaIngr` e `InstRemi`. Reemplazan `sql/04_permisos_PROVISIONAL.sql` y
  `sql/05_instituciones_remision_PROVISIONAL.sql` (borrados).
- **Conducta** (Plan): lista 33 de `priv_listas_elementos`; `RipsCons.Conducta` guarda el **`id` del elemento**
  (121–127), no el código. **Estado del Código Dorado** (Consulta Externa): lista 47; `RipsCons.EstaCodo` guarda el
  **código como número** (01 → 1).
- **Antecedentes sin nada en gris**: `Antecede` con todas sus columnas reales (incluye `MetoDesc`, `Andropo/AndroDesc`,
  `Consilia/ConsiDesc`, `FechRegl`, `FechPart`) y **`comu_antecedentes_multiples`** (`sql/05_tablas_clinicas_nuevas.sql`),
  ligada a `Antecede.id`, con los `tipo_antecedente_id` reales de SIHOS: 34 Familiares (`parentesco_id` =
  `Parentes.CodiPare`, `diagnostico_id` = `CausMorb.id`), 35 Alérgicos (`tipo_alergia_id` lista 13,
  `tipo_medicamento_id`), 36 Factor de riesgo (`factor_riesgo_id` lista 14), 128 Farmacológicos (`farmacologico_id`),
  500 Patológicos y 501 Obstétricos (`preguntas_antecedentes_id` lista 45, `respuesta_id` 98 SI / 99 NO). Al guardar
  la consulta se reemplazan las filas de ese `Antecede.id`.
- **Reconciliación Medicamentosa**: tabla `RecoMedi` (estructura real), una fila por medicamento de la admisión
  (nombre, dosis, frecuencia en horas, vía, nota; todos obligatorios si se marca "Reconciliación").
- **Supuestos confirmados**: No Sabe = 3 y No Corresponde = 4 (`CodiSino`); SOAT = `Admision.NumePoli`; Estado
  Ingreso = `EstaIngr`; T de Historias Abiertas = tipo de contrato. **Corregido**: examen físico "No se Explora" = 3.

### Dónde quedan las admisiones creadas aquí (`VALIDACIONES_SIHOS.md` §5)

En la base local: tabla `Admision` con número temporal `C` + AAMMDD + 5 dígitos y una fila en `cont_carga_sihos`
con estado `pendiente`. Se ven en **Historias abiertas** de su módulo y en el **Tablero** del administrador
("Pendientes por cargar a SIHOS"). Pasan a SIHOS en la **fase 3** (carga por el administrador), donde reciben el
número definitivo.

### Pendiente por confirmar (antes de producción)

- **Tipos de columna** de `priv_listas_tipos`, `priv_listas_elementos` y `comu_antecedentes_multiples` (el documento
  trae las columnas, no los tipos): reemplazar por su `SHOW CREATE TABLE` de SIHOS.
- **A qué tabla apunta el id de medicamento** de `tipo_medicamento_id` / `farmacologico_id` (¿`CodUniPro.id`,
  `IdenUniMedi.id`?). Hoy se usa `CodiSumi.IdenUniMedi_id`.
- **Método de planificación** (`Antecede.MetoDesc`): no se sabe qué lista usa; se escribe el código a mano.
- **Orden y número de las preguntas** 502–510 de la lista 45 (Patológicos 502–508, Obstétricos 509–510 según el
  documento; los textos de prueba son supuestos) y los ids de las listas 46/47 en los datos de prueba (inventados).
- **Código Dorado**: formato de Acciones inmediatas (`Accinme`) y Continuidad del cuidado (`ContCuid`): siguen deshabilitados.
- **Embarazo múltiple** (`IncaPaci.EmbaMult`): 1 Sí / 0 No, supuesto. **Domiciliaria** = `TipoPres` 3, supuesto.
- **Med / Ord** de Historias Abiertas: significado probable, no verificado en datos.
- **Instalaciones existentes**: las tablas provisionales anteriores (`UsuaGrup`, `Permisos`, `ModuObje`, `Objetos`
  con columnas marcador e `InstRemision`) deben **borrarse** antes de correr `sql/04_listas_permisos.sql` y
  `sql/05_tablas_clinicas_nuevas.sql` (usan `CREATE TABLE IF NOT EXISTS` y no cambian una tabla que ya existe). Ver
  `docs/INSTALACION.md`, "Actualizar una instalación que ya existía".


## Ajustes de las pruebas en el hospital (28/09/2026)

- **Documento del encabezado con autocompletar**: desde 3 caracteres, el campo Documento muestra los pacientes que
  coinciden por documento o por nombre ("TIPO NÚMERO · NOMBRE", máximo 30, `api.php?que=pacientes`). Al escoger se
  carga el paciente (o su admisión abierta, si tiene). Si se pulsa Buscar con parte del documento o del nombre y hay
  un solo paciente, se carga ese.
- **Barra de pestañas sin repetidos**: con los permisos reales, Consulta Externa trae objetos distintos (otro
  `CodiObje`) con el mismo nombre (dos "Procedimientos", dos "Imagenes"). `pestanas_usuario()` deja solo el primero
  por orden, por nombre y por panel, como SIHOS (el mismo `CodiObje` por varios grupos ya sale una vez).
