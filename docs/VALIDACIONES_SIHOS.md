# Validaciones, obligatorios, búsquedas y antecedentes — SIHOS real (26/09/2026)

Leído de los scripts de cada pantalla de SIHOS producción (mensajes de validación) y confirmado con datos reales
(septiembre 2026). **Manda sobre REGLAS.md.** Regla del usuario: igual a SIHOS — obligatorio donde SIHOS obliga,
búsqueda y autocompletado donde SIHOS los tiene, sin límites donde SIHOS no los pone.

## 1. Antecedentes: NADA debe quedar en gris

Todo tiene dónde guardarse:

- **`Antecede`** (una fila por consulta) tiene, además de lo que ya usa CRADOR: `MetoDesc` (método de planificación),
  `Andropo` + `AndroDesc` (Andrológicos), `Consilia` + `ConsiDesc` (Conciliación medicamentosa), `FechRegl` (FUR),
  `FechPart` (Fecha Probable del Parto). Verificar que `sql/01_tablas_clinicas.sql` tenga TODAS las columnas reales:
  `id, CodiInst, ConsAdmi, ConsAnte, TipoDocu, NumeUsua, CodiModu, ConsCons, Familiar, FamiDesc, Personal, PersDesc,
  Patologi, PatoDesc, Obstetri, ObstDesc, Quirurgi, QuirDesc, ToxiAler, ToxiDesc, AlerSiNo, AlerDesc, Fisiolog, FisiDesc,
  Alimenta, AlimDesc, Traumati, TrauDesc, Farmacol, FactRies, FarmDesc, Oculares, OculDesc, MetoPlan, MetoDesc, Ginecolo,
  GineDesc, Icterico, IcteDesc, Discrasi, DiscDesc, TBC, TBCDesc, LUES, LUESDesc, Asma, AsmaDesc, IMAO, IMAODesc,
  Esteroid, EsteDesc, Hipotens, HipoDesc, Insulina, InsuDesc, BBloquea, BBloDesc, Otros, OtroDesc, FechRegl, FechPart,
  FechDigi, HoraDigi, UsuaDigi, FechModi, HoraModi, UsuaModi, Andropo, Consilia, AndroDesc, ConsiDesc`.
- **`comu_antecedentes_multiples`** (175.921 filas) guarda lo "múltiple" de cada antecedente, ligado a `Antecede.id`:
  `id, antecedente_id, tipo_antecedente_id, parentesco_id, diagnostico_id, tipo_alergia_id, tipo_medicamento_id,
  factor_riesgo_id, farmacologico_id, preguntas_antecedentes_id, respuesta_id, descripcion, activo, created_at,
  updated_at, deleted_at`. Valores REALES que usa SIHOS en `tipo_antecedente_id` (copiar tal cual, aunque no coincidan
  con los nombres de la lista 15):

  | Antecedente | `tipo_antecedente_id` real | Columnas que llena |
  | --- | --- | --- |
  | Familiares (parentesco + diagnóstico) | **34** | `parentesco_id` (= `Parentes.CodiPare`, 1–8), `diagnostico_id` (= `CausMorb.id`), `descripcion` |
  | Alérgicos (tipo de alergia + medicamento) | **35** | `tipo_alergia_id` (lista 13: 21–26), `tipo_medicamento_id` (id del medicamento, 88–13083), `descripcion` |
  | Factor de riesgo (tipo) | **36** | `factor_riesgo_id` (lista 14: 27–32), `descripcion` |
  | Farmacológicos (tipo medicamento) | **128** | `farmacologico_id` (id del medicamento, 99–13306), `descripcion` |
  | Patológicos (ventana: Hipertensión crónica… Anemia falciforme) | **500** | `preguntas_antecedentes_id` (lista 45: 502–508), `respuesta_id` (98 SI / 99 NO) |
  | Obstétricos (ventana: Preeclampsia, Sepsis) | **501** | `preguntas_antecedentes_id` (502–510), `respuesta_id` (98/99) |

  Pendiente de confirmar a qué tabla apunta el id de medicamento (`tipo_medicamento_id` / `farmacologico_id`):
  probablemente `CodUniPro.id` o `IdenUniMedi.id`.
- **`RecoMedi`** (reconciliación medicamentosa): estructura en `RESULTADO_CONSULTAS_SIHOS.md`.
- **`AlergMed`** (TipoDocu, NumeUsua, CodiPrinc…): hoy vacía en SIHOS; no usar.
- Mensajes de validación de SIHOS en Antecedentes: "Seleccione un parentesco", "Seleccione un diagnóstico",
  "Ingrese una descripción", "Seleccione un tipo de alergia", "Seleccione un medicamento", "Ingrese una descripción de la
  alergia", "Debe ingresar un tipo de planificacion familiar", "Debe ingresar por lo menos un registro de Reconciliacion
  Medicamentosa. Si no tiene registro, escribalo en la Nota", "Debe seleccionar un tipo de riesgo". En Reconciliación:
  medicamento, dosis, frecuencia, vía y nota obligatorios.

## 2. Obligatorios (confirmados con datos reales)

- **Triage** (1.523 triages de septiembre, sin excepción): Motivo, Hallazgos Clínicos, Impresión Diagnóstica,
  Clasificación, **FC, FR, Temperatura, PA sistólica y diastólica, Saturación**, y casi siempre **Peso y Talla**
  (2 y 1 vacíos). **Opcionales**: Oximetría (siempre vacía), Glucometría (62 % vacía), Fetocardia.
  → Los signos **SÍ son obligatorios** en triage (se quitaron por error). Lo que no hay son **límites de valor**:
  hay temperaturas guardadas de 1 °C. Único límite visto: **peso máximo 300 kg** ("Por favor verifique el peso, este no
  puede sobrepasar 300 Kg.", en Revisión por Sistemas).
- **Evolución**: "Digite el peso / la talla / la frecuencia cardiaca / la frecuencia respiratoria / la temperatura / la
  presion arterial sistolica / diastolica", "Debe seleccionar la consulta", "Debe seleccionar el DX principal",
  "Debe digitar alguna informacion", tipo de diagnóstico de cada Rela diligenciada. (Datos: 0 evoluciones sin esos signos.)
- **Signos Vitales**: fecha y hora **posteriores** a la última toma ("no puede ser inferior o igual a los anteriormente
  digitados").
- **Consultas (Urg/Obs)** y **Anamnesis (CE)**: tipo de consulta, motivo y enfermedad actual ("Por favor, digite el tipo
  de consulta / el motivo de la consulta / la enfermedad actual").
- **Laboratorios y Diagnósticos**: diagnóstico principal y su tipo; tipo de cada Rela diligenciada; "Ningun diagnostico
  debe repetirse".
- **Plan de Manejo (acordeón y pestaña)**: **Destino y Conducta obligatorios** ("Ingresar por favor el destino",
  "El campo Conducta es obligatorio…"), y el texto del plan. La pestaña Plan de Manejo: "Por favor complete todos los campos".
- **Cerrar Consulta / Egreso**: SIHOS revisa que la consulta esté completa: "Falta Diligenciar uno de los Siguientes Campos:
  Motivo de la Consulta / Enfermedad Actual", "Falta Diligenciar el Diagnostico Principal", "…los Signos Vitales",
  "…los Antecedentes", "…el Plan de Manejo y Recomendaciones", "…Revision por Sistema". Egreso además: diagnóstico
  válido y tipos.
- **Prescripción (hospitalaria)**: "Debe formular al menos un medicamento", diagnóstico obligatorio (normativa 2275),
  cantidad > 0, vía obligatoria, "Debe indicar el tiempo de Aplicacion", **Nota obligatoria**, "No es posible prescribir
  para mas de 24 Horas", no repetir el mismo suministro en la prescripción.
- **Prescripción A (CE)**: cantidad > 0, tipo de prescripción, diagnóstico obligatorio, no repetir suministro.
- **Ordenación**: finalidad antes de agregar procedimientos, al menos un procedimiento, cantidad > 0, no repetir.
- **Procedimientos**: procedimiento, descripción, diagnóstico principal y tipos, cantidad > 0, fecha/hora coherente.
- **Notas (Enfermería y Médicas)**: nota obligatoria. El mensaje "Debe Digitar la Actividad" existe, pero en los datos
  la Actividad **nunca** se llena (0 de 22.044): dejarla opcional.
- **Medicamentos (aplicación)**: cantidad > 0 y no mayor a la ordenada; fecha de aplicación no menor al ingreso.
- **Remisiones**: institución, especialidad, persona que acepta, **número de autorización**, descripción, modalidad y
  motivo, todos obligatorios.
- **Incapacidad**: tipo, días y nota obligatorios; maternidad: fecha probable de parto, edad gestacional, nacidos vivos
  (> 0; no > 1 si embarazo múltiple = No).
- **Encabezado**: tipo de documento obligatorio; paciente inactivo solo puede atenderse por Urgencias.

## 3. Búsquedas y autocompletado

- **Medicamentos/suministros** (Prescripción, Prescripción A, Materiales, Reconciliación): los campos **Código** y
  **Nombre** son de búsqueda (`type=search`): se escribe parte del código **o** del nombre y aparece la lista; al elegir,
  se llenan código, nombre, unidad y vía. Pedido del usuario: **un solo buscador** que acepte nombre o código y al
  guardar reparta código y nombre en sus columnas.
- **Diagnósticos** (todas las pestañas): código + "…" que abre la búsqueda; al escribir el código se llena el nombre.
- **Procedimientos / Actividad / Tipo de consulta**: código + "…" con búsqueda por código o nombre.
- **Alergia a medicamentos y Tipo medicamento (Farmacológicos)**: búsqueda por nombre de medicamento (ventana "Medicamentos").
- **Familiares**: parentesco (lista) + diagnóstico CIE-10 (búsqueda).

## 4. Listas que faltan en CRADOR (por eso hay campos sin opciones)

Copiar como catálogos: `priv_listas_tipos`, `priv_listas_elementos` (Conducta = tipo 33, Código Dorado = 46/47/50,
tipos de alergia = 13, factores de riesgo = 14, tipo antecedente = 15, preguntas de antecedentes = 45), `EstaIngr`,
`InstRemi`, `UsuaGrup`, `Permisos`, `ModuObje`, `Objetos`. Ver `RESULTADO_CONSULTAS_SIHOS.md`.
**Conducta guarda el `id` del elemento (121–127), no el código.**

## 5. ¿Dónde quedan las admisiones creadas en CRADOR?

En la base local de CRADOR: tabla `Admision` con número temporal `C` + AAMMDD + 5 dígitos, y una fila en
`cont_carga_sihos` con estado `pendiente`. Se ven en **Historias abiertas** de su módulo y en el **Tablero** del
administrador ("Pendientes por cargar a SIHOS"). Pasan a SIHOS en la **fase 3** (carga por el administrador), donde
reciben el número definitivo.
