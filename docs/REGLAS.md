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
| Antecedentes (Antecede) | Se guarda una fila por consulta, ligada por `ConsCons`. Lista Sí/No de SIHOS: 1 = sí, 2 = no (por defecto). En el orden de SIHOS: Planificación (`MetoPlan`), Familiares, Personales, Patológicos, Obstétricos, Ginecológicos, Quirúrgicos, Tóxicos, Alérgicos (`AlerSiNo`), Fisiológicos, Alimentarios, Traumáticos, Farmacológicos y Factor de riesgo (`FactRies`), cada uno con su columna `*Desc` (obligatoria si es Sí). Planificación y Factor de riesgo no tienen columna de descripción. |
| Examen físico (EstaGene) | Lista por sistema con **Sin examinar** (por defecto, `NULL`), Normal (1) y Anormal (2, con descripción obligatoria). Sistemas y etiquetas de SIHOS: Cabeza, Ojos, Oídos, Nariz, Boca, Cuello, Tórax (`CardPulm`), Abdomen, G/U (`GeniUrin`), Ano, Extremidades, Neurológico, Osteomuscular y Piel. Solo se guarda si hay estado general o algún sistema examinado. `ConsHoPr = 0`. |
| Prescripción | `PresSali`: se siguió la convención de SIHOS 1 = sí / 2 = no (1 = fórmula de salida). **Verificar**: la columna tiene 1 por defecto. `TipoPres = 1`, `FechEntr = Fecha`, `CodiFina` del detalle = `NULL`, `HoraApli = 0` (no hay catálogo `HoraApli` local), `HoraInic` = hora de la prescripción. `NumeDosi` = duración ÷ frecuencia (en horas, con `CodiTiem` 1 = horas, 2 = días, 3 = meses según el comentario de `DetaPres`) y `CantTota = CantSumi × NumeDosi`. Si no se escribe la indicación, `PresMedi` se arma con dosis, vía, frecuencia y duración. |
| Administración de medicamentos (HojaMedi) | Solo de lo prescrito en la admisión. `NumeOrde = ConsPres`, `Item = Item` de `DetaPres`; `EstaApli = 1`; plan = hora de aplicación. Suma la cantidad en `DetaPres.CantApli`. |
| Órdenes (EncaOrde/DetaOrde) | Pestaña Ordenación. Como en SIHOS la finalidad es de la orden: `EncaOrde.CodiFina` del catálogo `FinaCons` ("No aplica" = `10` por defecto) y `DetaOrde.CodiFina` queda `NULL`. `Autoriza` (Solicitar autorización para EPS) y `OrdeAmbu` (Ambulatoria): 1 marcado, 0 no. DXP y DXR1-DXR4 en `CodiDiag`, `CodiRel1-4`. `CantReal` suma al realizar el procedimiento del ítem. |
| Orden médica en texto | `EncaData/DetaData` con `TipoObje = 7`, `CodiItem` 131 Urgencias / 130 Observación. En Consulta Externa no se muestra. |
| Procedimientos (HojaProc) | `CantProc` (Cant, 1 por defecto) y `ProcReal` (Realizado?, marcado por defecto) se escriben en la pestaña; diagnósticos Principal, Rela 1, Rela 2, Rela 3 y Compl en `DiagPrin/TipoDiag`, `DiagRela/TipoDiaR`, `DiagRel1/TipoDia1`, `DiagRel2/TipoDia2`, `DiagComp/TipoDiaC`; `NumePiez = CuadPiez = 0`, `NumeOrde = Item = 0` si no atiende una orden. `UsuaDigi`, `UsuaModi`, `CodiProf` y `UsuaAsis` son `varchar(8)` en SIHOS: se guarda el login recortado a 8 caracteres. |
| Evolución (EvolInte) | Formato SOAP (`Subjetivo`, `Objetivo`, `Analisis`, `PlanMane`). No aplica en Consulta Externa (0 % de uso en la muestra). `ContSign/ContLiqu` = 1 marcado, 0 no. |
| Egreso (SaliInte) | Urgencias y Observación: se guarda `SaliInte` y se cierra la admisión (`Cerrado = 1`, `FechCier/HoraCier/UsuaCier`, `FechEgre/HoraEgre` = salida). `DiasEsta/HoraEsta` se calculan desde el ingreso. Si `EstaSali` es "muerto" (se reconoce por el nombre en el catálogo) se piden causa (`DiagMuer`, 4 caracteres) y fecha/hora de muerte. La cama queda libre porque la ocupación se calcula con las admisiones abiertas (no se toca `CodiCama`). |
| Consulta Externa: "Cerrar Historia" | Como en SIHOS, Consulta Externa no tiene pestaña de egreso: el botón **Cerrar Historia** del encabezado (con confirmación) solo marca la admisión cerrada (`Cerrado = 1` y fechas de cierre/egreso); en la muestra CE no tiene `SaliInte`. |
| Confirmación | Egreso y cierre piden marcar una casilla de confirmación (se valida también en el servidor). |
| Traslado de cama (TrasCama) | Solo Observación, desde el encabezado. Cada fila es el tramo en la cama anterior: `CodiServ`/`CamaOrig` = servicio y cama de origen, `ServEgre`/`CamaDest` = destino, `FechIngr/HoraIngr` = inicio del tramo (ingreso o traslado anterior), `FechSali/HoraSali` = hora del traslado, `Dias` = días completos y `Horas` = horas restantes del tramo. Actualiza `Admision.CamaActu` y `ServEgre`; `CentEgre` no se toca (queda vacío, ver valores fijos). La cama destino debe estar activa y libre. |
| Materiales (HojaMate) | Pestaña Materiales (Urgencias 16, Observación 18). `CodiMate` de `CodiSumi`, `UnidMate` de `CodiUnid`; `EsFact = 1`, `CantFact = 0`, `NumeOrde = Item = 0`, `CentCost = '0'`, `UsuaAsis` = login. |
| Remisión (Remision) | Pestaña Remisiones (Urgencias 15, Consulta Externa 7); en Observación va dentro del Egreso. `RemiMoti` = código del catálogo `MotiRemi`; `MotiRemi` (texto) = resumen clínico; `ModaSoli` del catálogo `ModaSoli`; `EspeRemi` de `CodiEspe` (opcional). **No hay catálogo local de instituciones receptoras**: `InstRemi` queda vacío y el nombre de la institución se escribe al inicio de `MotiRemi` ("INSTITUCION DESTINO: …"). `CodiRemi` = consecutivo por admisión; `FechSali/HoraSali` = fecha de la remisión; `FechAcep/HoraAcep` = Fecha y Hora Aceptación de SIHOS; si se dejan vacías y se escribe quién acepta, la de la remisión; `Cerrado = 0`; `TipoDiag` guarda el código de `TipoDiag` como texto. No cierra la admisión (el egreso se hace aparte). |
| Incapacidad (IncaPaci) | Pestaña Incapacidad (Urgencias 17, Observación 21, Consulta Externa 12). La fecha final (inicial + días − 1) solo se muestra. `TipoInca` del catálogo; `OrigInca` 1 = común, 2 = laboral (comentario de la columna); días de 1 a 540; `ConsInca` = consecutivo por admisión. |
| Procedimiento de una orden | En la pestaña Procedimientos se puede escoger un ítem de `DetaOrde` pendiente (`CantReal < CantSumi`): el procedimiento sale del ítem, `HojaProc.NumeOrde` = `EncaOrde.Consecut` (temporal, ver arriba) e `Item` = ítem, y `DetaOrde.CantReal` suma 1. |
| Pestañas por módulo | Nombres, orden y numeración de las capturas de SIHOS (`docs/SIHOS_PANTALLAS.md`); los números que no se ven en las capturas se saltan. **Supuestos**: Urgencias 11 = "Medicamentos" (administración, `HojaMedi`); Consulta Externa 7 = "Remisiones"; Consulta Externa 5 se llama "Prescripción Ambulatoria" (en la captura se lee "Prescripción A…"). En Observación la remisión va dentro de 22. Egreso (la 16 no se ve). Las pestañas sin tablas en CRADOR-HC (Atención del Menor, Consentimiento, Líquidos, Oxígeno, No POS, Quemaduras, Glucometría, Devoluciones, Cambio de Atención, Imágenes, Cirugía, Anestesia, Neurológico, Recién Nacidos, Control, Tamizaje) se muestran deshabilitadas "No disponible en contingencia". Líquidos (`HojaLAdm`) queda deshabilitada porque no hay captura de sus campos. |
| Consulta en Consulta Externa | Las pestañas 1. Anamnesis, 2. Rev.Sistemas y Ex.Físico, 3. Antecedentes y 4. Laboratorios y Diagnósticos son **un solo formulario** (una fila de `RipsCons`); el Plan de Manejo y Recomendaciones va en la 4. Si hay un error se abre la pestaña donde está. |
| Barra de cada pestaña | Nuevo (formulario en blanco), No. (registros anteriores: lleva al registro), Fecha, Hora y campos propios (Tipo de prescripción, Tipo de incapacidad, Autorización, Profesional). Imprimir y Cargos están deshabilitados: no aplican en contingencia. |
| Notas (HojaEnfe) | Como en SIHOS no hay selector de tipo: Notas Enfermería guarda el `TipoNota` cuyo nombre contiene "ENFERMER" y Notas Médicas el que contiene "MEDIC" (**verificar** los códigos reales del catálogo). La casilla Revisada de Notas Médicas llena `Reviza = 1`, `UsuaRevi`, `FechRevi`, `HoraRevi`. |
| Signos en consulta y evolución | La fila de signos de Consultas (Revisión por sistema) y de Evolución es opcional: si se escribe alguno se guarda una toma de `SignVita` con `ConsCons` o `ConsEvol` (y se piden los obligatorios). `Oximetria` queda `NULL` si no se escribe. |
| Triage | "Continuar en el consultorio" = `Triage.CodiCons` del catálogo `CodiCons` (activos), opcional. |
| Consulta (campos de SIHOS) | Sintomáticos (`SintResp`, `SintPiel`, `SintNerv`, `TubeMult`: 1 sí / 2 no), `PeriAbdo` (0-200) y `PeriTorx` (0-150), `LaboImag`, diagnósticos Principal y Rela 1-4 con tipo (`TipoDiag`, `TipoDia1-4`, 0 si no hay), Destino (`DestSali`, catálogo `DestSali`, se guarda como número; 4 si no se escoge). Prescripción: Tipo de prescripción (`TipoPres` 1 regular / 2 control), DXP, DXR 1 y DXR 2. Evolución: Rela 1-4 con tipo (`TipoDiag1-4`). Egreso: Rela 1-3 y Complicación (`DiagComp`, su tipo en `TipoDia4`). |
| Campos de SIHOS sin guardar | No se piden porque no tienen columna o catálogo local: Lepra (`TipoLepr`), Tipo de discapacidad, Conducta de la consulta (lista 33), método de planificación (`MetoDesc`), Sivigila/Protocolo, Id Estudio, Revisado del procedimiento y de la evolución, índice cintura-cadera, órdenes posfechadas, Alcance, Incapacidad retroactiva, Grupo de servicios y Modalidad de la incapacidad, Institución receptora de la remisión (va como texto en `MotiRemi`). |
