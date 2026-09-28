# Recorrido SIHOS real vs CRADOR-HC — Urgencias, Observación y Consulta Externa (26/09/2026)

Fuente: SIHOS producción (:82), módulo Urgencias, historia abierta real, usuario NIXON07. Se leyó la
estructura de cada pantalla (marcos `admisiones/admision.php` y el de cada pestaña) sin guardar nada.
Regla del usuario: **cada pantalla debe tener exactamente lo mismo que SIHOS — ni más ni menos**.
Además: **"Cerrar Historia" siempre visible** y **signos vitales sin límites** (solo calculan IMC y TM).

## 0. Hallazgos generales

1. **La numeración de pestañas depende del usuario** (permisos) y SIHOS ni siquiera la ordena siempre igual
   (en dos cargas seguidas cambió el orden de empates: Prescripción/ORDENES MEDICAS, Ordenación/Procedimientos,
   Evolución/Notas Enfermería). Solución: copiar `UsuaGrup`, `Permisos`, `ModuObje` y `Objetos` como
   catálogos y **armar la barra por usuario** como SIHOS: objetos permitidos del módulo, orden por
   `ModuObje.Orden` (mínimo por objeto), empate por `CodiObje`, numerados 1..N. Las que no aplican en
   contingencia se muestran deshabilitadas con su número.
2. **Signos vitales en todas partes** (Triage, Consultas, Evolución, Signos Vitales): SIN mínimos, máximos ni
   obligatorios (SIHOS guardó PA 1/1 y TM 0). Mismos campos y orden:
   Peso (Kg) · Talla (cm) · IMC (Kg/m², calculado) · FC (Min) · FR (Min) · Temp (°C) · PA (sist / diast) ·
   TM (calculada) · Fetocardia (Lat/min) · Saturación (%) · Oximetría · Glucometría.
   **Quitar "Dolor (0-10)"**: no existe en SIHOS.
3. **Botonera de cada pestaña como SIHOS**: Nuevo/Nueva, No. (anteriores), Fecha, Hora, y abajo
   Guardar · Modificar · Consultar · Imprimir · Cancelar/Limpiar según la pestaña (Imprimir puede quedar
   deshabilitado). CRADOR hoy usa "Guardar/Limpiar".

## 1. Encabezado de la admisión

SIHOS (en este orden):
- Barra: **Admisión · Fecha · Hora · Autorización · SOAT** · [carné] · estado **Abierta/Cerrada**.
- Fila 1: **Documento (Entidad)** tipo + número + [...] · **Usuario** · **F. Nacimiento** · **Edad** · **Género** · **Grupo**.
- Fila 2: **Servicio Origen (C.Costos)** · **Cama Origen** · **Vía Ingreso** · **Servicio Actual** · **Cama Actual** · **Entorno de Atención**.
- Fila 3: **Causa Externa** · **Estado Ingreso** · **Condición** · **Discapacidad** · **Diagnóstico** (código + nombre).
- Íconos izquierda (familia, carné, alertas, alérgicos) y botones: **Modificar · Eliminar · Buscar · Imprimir · Limpiar · Anular · Cerrar Historia**.

CRADOR — corregir:
- EPS, Contrato, Tipo de usuario, Afiliación y Categoría **se quedan** (en SIHOS existen pero están ocultos;
  decisión del usuario: mantenerlos visibles en CRADOR).
- **Falta**: SOAT, Grupo (como campo propio), Servicio Origen, Cama Origen, Servicio Actual, Cama Actual,
  Estado Ingreso, Discapacidad (`Admision` no tiene columna: mostrar "Sin discapacidad"/desde paciente),
  Género separado de Edad, estado Abierta/Cerrada.
- **Botón "Cerrar Historia" en TODOS los módulos** (hoy solo Consulta Externa). En Urgencias/Observación
  cierra igual que el Egreso de SIHOS (pide egreso si no existe).

## 2. Historias Abiertas (ventana)

SIHOS: filtros **Seleccione Servicio · Seleccione consultorio**, "Mostrar N registros", columnas
**Servicio · Cama · Admisión · Fecha · Duración · T · Autoriza. · Triage · Med · Ord · Paciente · Edad · Estado ·
Consultorio · Profesional**; filas coloreadas por triage.
CRADOR: buscador libre y columnas Paciente/Triage/Servicio/Admisión/Fecha/Duración/Edad/Estado/Profesional.
Igualar columnas, orden y filtros.

## 3. Pestañas

### 1. Triage
SIHOS: Fecha · Hora · Profesional · **Motivo** · Signos (fila §0.2) · **Hallazgos Clínicos** · **Impresión
Diagnóstica** (código ... nombre) · **Clasificación** · **Conducta** · (texto sin etiqueta) · **Continuar en el
consultorio** · botones Guardar · Modificar · Imprimir · Consultar.
CRADOR: quitar Dolor, quitar límites/obligatorios de signos, quitar la etiqueta "Observaciones de la conducta"
(en SIHOS es un texto sin título), botones como SIHOS.

### 2. Consultas
SIHOS: barra Nuevo · No. · Fecha · Hora · Cargos · Consultar · Imprimir. **Cinco acordeones, cada uno con su
propio Guardar**, y al final **"Cerrar Consulta"** + "Duración".
- **Anamnesis**: Fecha · Hora · Profesional · Tipo (código ... nombre) · Finalidad · Motivo · Enfermedad Actual.
- **Antecedentes** (opciones **Si | No | No Sabe | No Corresponde**), en este orden: Planificación (+ método) ·
  Familiares (+ parentesco + diagnóstico CIE-10) · Personales · Patológicos (+ ventana: Hipertensión crónica,
  Diabetes, LES/Enf. autoinmune, Síndrome metabólico, ERC, Trombofilia/TVP, Anemia de células falciformes:
  SI/NO) · Obstétricos (+ ventana: Preeclampsia en gestación previa, Sepsis en gestaciones previas) ·
  Ginecológicos · Quirúrgicos · Tóxicos · Alérgicos (+ Tipo de Alergia: Alimento/Medicamento/Otra + Alergia a
  Medicamentos código) · Fisiológicos · Alimentarios · Traumáticos · Farmacológicos (+ Tipo Medicamento) ·
  **Andrológicos** · **Conciliación medicamentosa** · Factor riesgo (+ Tipo: Biológicos/Biomecánicos/Físicos...) ·
  **Reconciliación Medicamentosa** (casilla + Medicamento · Dosis · Frecuencia · Vía adminis. · Nota → tabla `RecoMedi`).
- **Revisión por Sistema y Exámen**: Revisión por Sistemas · Sintomático Respiratorio (Si/No) · Tuberculosis
  Multidrogoresistente (7 opciones) · Sintomático de Piel · Lepra (No/Pausibacilar/Multibacilar) · Sintomático
  Nervioso Periférico · Tipo de Discapacidad (selección múltiple) · Signos (§0.2) · Hallazgos Estado General ·
  Perímetro Abdominal (0-200) · Perímetro Tórax (0-150) · sistemas **en este orden**: Cabeza, Cuello, Tórax,
  Abdomen, G/U, Extremidades, Neurológico, Nariz, Oídos, Boca, Ojos, Piel, Ano, Osteomuscular, cada uno
  **Normal | Anormal | No se Explora** + texto (por defecto Normal).
- **Laboratorios y Diagnósticos**: Análisis de Laboratorio e Imágenes Diagnósticas · Diagnósticos Principal y
  **Rela 1–4**, cada uno con Tipo (Impresión Diagnóstica / Confirmado Nuevo / Confirmado Repetido).
- **Plan de Manejo y Recomendaciones**: Destino · Conducta · Plan de Manejo y Recomendaciones.
CRADOR: revisar que el orden y las opciones sean estos (hoy "Sin examinar" y otro orden de sistemas;
faltan Andrológicos, Conciliación, Reconciliación, Tipo de alergia, Lepra/Discapacidad; Guardar por sección y
"Cerrar Consulta").

### 4. Prescripción
SIHOS: Nuevo · No. · **Tipo de Prescripción (Regular | Control | Domiciliaria)** · Fecha · Hora · Sugerido ·
Protocolo · Plantilla · DXP (lista con los diagnósticos de la consulta) · **DXR 1–4** · rejilla de 6 filas (+ agregar):
**Código · Nombre · Susp · Cantidad por dosis · Unidad · Vía · Cada · A partir de · Número (Dosis) · Cantidad
solicitada · Unidad · Nota · Medi. Prin. · Entregado** · Observaciones · Responsable de la entrega ·
Guardar · Imprimir · Consultar · Limpiar · Eliminar · Cancelar.
CRADOR: pasar a rejilla; agregar "A partir de", "Número (Dosis)", "Cantidad solicitada", Domiciliaria,
DXR 3–4; **quitar la casilla "Fórmula de salida"** (SIHOS no la muestra aquí; `PresSali` se decide por
módulo/egreso — revisar).

### 5. ORDENES MEDICAS
SIHOS: Nuevo · Modificar · No. · Fecha · Hora · Profesional · "**1. Orden medica:**" (texto) · Guardar ·
Consultar · Imprimir · Cancelar. CRADOR: etiqueta exacta "1. Orden medica:", sin texto de ayuda.

### Ordenación
SIHOS: Nuevo · No. · Fecha · Hora · Plantillas · sugerido · Protocolo · (Solicitar Autorización para EPS) ·
**Finalidad** · **Salida** · **Ambulatoria** · DXP (lista) · **DXR1–4** · rejilla: **Código · Nombre · Cant · Susp ·
Nota · Tomar A (Cada)** · Observaciones. CRADOR: agregar Tomar A, Salida, DXR1–4.

### Procedimientos
SIHOS: Nuevo · Fecha · Hora · Actividad (código ... nombre) · Finalidad · Cant · Id Estudio · Realizado? ·
Descripción · Diagnósticos Principal, Rela 1–3, Compl (con Tipo) · Guardar · Cancelar · Imprimir · Consultar ·
Revisado · lista de procedimientos. CRADOR: el selector "Atiende la orden" no está en SIHOS (dejar oculto o
como SIHOS); agregar Id Estudio y Revisado (deshabilitados si no hay columna).

### Evolución
SIHOS: Nueva · No. · Fecha · Hora · **Tipo (código ... nombre) · Finalidad** · Subjetivo · Objetivo · Signos ·
Diagnósticos Principal + **Rela 1–4** · Análisis · Plan · Controles Especiales (Signos, Líquidos) · Revisado ·
Guardar · Consultar · Imprimir · Cancelar. CRADOR: "Código de la atención (CUPS, opcional)" → **Tipo** +
**Finalidad**; etiqueta "Plan".

### Notas Enfermería / Notas Médicas
SIHOS (iguales): Nueva · No. · Fecha · Hora · **Revisada** · Profesional · **Actividad** · Nota · Guardar ·
Consultar · Imprimir · Cancelar. CRADOR: agregar **Actividad** (`HojaEnfe.Procedim`) y Revisada en ambas.

### Medicamentos (aplicación)
SIHOS: No. (prescripción) · Fecha · Hora · rejilla con cada medicamento prescrito: Fecha/Hora aplicación ·
Fecha/Hora planeado · Código · Nombre · Vía · Cantidad · Unidad · Observaciones · Profesional · Módulo ·
Suspensión. CRADOR: hoy es un formulario de un medicamento; pasar a rejilla de la prescripción.

### Remisiones
SIHOS: Nuevo · No. · Fecha · Hora · Autorización · **Especialidad** (lista) · **Institución** (lista:
catálogo "Instituciones de Remisión") · Acepta (Nombre) · Cargo · Autorización · Modalidad · Motivo (+ otro) ·
**Incluir Ambulancia** · Fecha y Hora aceptación · texto. CRADOR: **copiar el catálogo de instituciones** y
usar lista (hoy va como texto dentro del motivo); agregar Cargo, Ambulancia.

### Materiales
SIHOS: Nuevo · Fecha · Hora · Plantilla · rejilla de 5 filas: **Fecha · Hora · Código · Nombre · Cant ·
Unidad · Indicaciones** (+ Orden/Item/Factura informativos). CRADOR: pasar a rejilla.

### Incapacidad
SIHOS: Nueva · **Tipo** (Enfermedad General/Profesional/Accidente de Trabajo) · No. · Fecha · Hora · Alcance ·
Origen (Común/Laboral) · Fecha inicial · Días · Fecha final · **Incapacidad retroactiva** · **Grupo de servicios** ·
**Modalidad de prestación** · **Maternidad** (Fecha probable del parto, Edad gestacional, Embarazo múltiple,
Nacidos vivos) · Nota. CRADOR: agregar los que falten (si no hay columna en `IncaPaci`, mostrarlos
deshabilitados para que la pantalla sea igual).

### Signos Vitales
SIHOS: Nuevo · Fecha · Hora · fila de signos (§0.2) · Guardar · Cancelar · Imprimir · tabla histórica:
Cons · Evolución · Sede · Fecha · Hora · Peso · Talla · IMC · FC · FR · Temp · PA · Fetocardia · Saturación ·
Glucometría · Profesional. CRADOR: sin límites, sin Dolor, tabla con esas columnas.

### Plan de Manejo
SIHOS: Fecha · Hora · **Destino** · Recomendaciones y Plan de Manejo · bloque **Código Dorado** (Acciones
inmediatas; Continuidad del cuidado; Estado: Riesgo Alto/Moderado/Bajo; Especifique; Observaciones →
`RipsCons.Accinme, ContCuid, EstaCodo, Especif, ObserCd`) · Guardar · Imprimir · Consultar.
CRADOR: agregar Destino y Código Dorado; quitar el selector "No. (consulta)".

### Egreso
SIHOS: Fecha · Hora · Estadía (días, horas) · Profesional · **Estado · Causa · Destino** · Incapacidad (días) ·
Diagnósticos Egreso + Rela 1–3 + Complic. (con Tipo) · Muerte: Diagnóstico · Fecha · Hora · Plan de Manejo
Ambulatorio y Observaciones · **Guardar · Modificar · Cerrar Historia · Limpiar · Imprimir** · listas de
pendientes (insumos por descargar, ayudas diagnósticas por interpretar, consultas por cerrar).
CRADOR: quitar "Tipo de egreso" y la casilla de confirmación (no están en SIHOS; usar confirmación del
navegador), agregar Dx de egreso con tipos, las listas de pendientes y Guardar/Modificar separados de Cerrar Historia.

## 4. Observación e Internación (revisado)

- Las pestañas usan las **mismas páginas compartidas** que Urgencias (`comun/...`): lo corregido en §3 aplica igual.
- Barra de pestañas (usuario NIXON07): 1 Consultas · 2 Evolución · 3 Prescripción · 4 ORDENES MEDICAS · 5 No POS ·
  6 Ordenación · 7 Notas Enfermería · 8 Notas Médicas · 9 Procedimientos · 10 Medicamentos · 11 Consentimiento ·
  12 Cirugía · 13 Anestesia · 14 Signos Vitales · 15 Neurológico · 16 Oxígeno · 17 Líquidos · 18 Materiales ·
  19 Devoluciones · 20 Recién Nacidos · 21 Incapacidad · 22 Egreso · 23 Cambio de Atención · 24 Remisiones ·
  25 Plan de Manejo · 26 GLUCOMETRIA · 27 PyP · 28 Imágenes · 29 Laboratorios y Diagnósticos · 30 SALUD PUBLICA · …
  (para un médico cambia según permisos: ver §0.1).
- **Encabezado**: en la barra superior va **Cama** (buscador de cama) en lugar de "Autorización"; el resto igual
  que Urgencias (Servicio/Cama Origen y Actual con las camas del servicio). Botones iguales, incluido Cerrar Historia.
- **Cambio de Atención (23) es una PESTAÑA**, no un botón del encabezado: Nuevo · No. · Fecha · Hora ·
  **Atención Origen** (Servicio, Centro de Costos, Cama, Estadística Días/Horas) · **Atención Destino**
  (Institución, Servicio, Entorno de atención, Centro de costos, Cama) · Guardar · Cancelar · Imprimir · tabla:
  No. · Fecha · Hora · Servicio Origen · Cama Origen · Días · Horas · Módulo · Institución · Servicio Destino · Cama Destino.
- **Remisiones (24)** es pestaña propia (ya estaba así en CRADOR).
- Al abrir una historia SIHOS muestra una **ventana automática** con Antecedentes Tóxicos, Antecedentes
  Alérgicos y Reconciliación Medicamentosa del paciente (también aplica a Urgencias). CRADOR no la tiene.

## 5. Consulta Externa (revisado)

- Barra (NIXON07): 1 Anamnesis · 2 Rev.Sistemas y Ex.Físico · 3 Antecedentes · 4 Laboratorios y Diagnósticos ·
  5 Prescripción A · 6 Ordenación · 7 Plan de Manejo · 8 Control · 9 Tamizaje Riesgo Cardiovascular ·
  10 Consentimiento · 11 Procedimientos · 12 Atención del Menor · 13 Incapacidad · 14 Notas Médicas ·
  15 Imágenes · 16 Medicamentos · 17 No POS · 18 Remisiones · 19 Notas Enfermería · 20 SALUD PUBLICA ·
  21 Cambio de Atención · … (reportes administrativos que no aplican).
- Encabezado igual al de Urgencias (Autorización, SOAT, estado Abierta/**Cerrada**, botón Cerrar Historia).
- **En CE las secciones de la consulta son PESTAÑAS SEPARADAS** (no acordeones):
  - **1 Anamnesis**: Fecha · Hora · Profesional · **Actividad** (código ... nombre) · Finalidad · Motivo de Consulta · Enfermedad Actual.
  - **2 Rev.Sistemas y Ex.Físico**: Revisión Por Sistema · Sintomático Respiratorio · Sintomático Nervioso Periférico ·
    Sintomático de Piel · Tuberculosis Multidrogoresistente · Lepra · Tipo de Discapacidad (múltiple) · Signos (§0.2) ·
    Estado General · Perímetro Abdominal (0-200) · Perímetro Tórax (0-150) · **Índice Cintura-Cadera** (Perímetro
    Cintura, Perímetro Cadera, ICC → `SignVita.PeriCint, PeriCade, ResCXC`) · sistemas **en este orden (distinto a
    Urgencias)**: Cabeza, Ojos, Oídos, Nariz, Boca, Cuello, Tórax, Abdomen, G/U, Ano, Extremidades, Neurológico,
    Osteomuscular, Piel — Normal | Anormal | No se Explora.
  - **3 Antecedentes**: como Urgencias pero **sin Andrológicos ni Conciliación medicamentosa**, y en Obstétricos
    agrega **FUR** y **Fecha Probable del Parto**; incluye Reconciliación Medicamentosa.
  - **4 Laboratorios y Diagnósticos**: Fecha · Hora · Laboratorios · Últimos Diagnósticos · Análisis de Laboratorio e
    Imágenes Diagnósticas · Diagnósticos Principal + Rela 1–4 con Tipo. (**El Plan de Manejo NO va aquí**.)
  - **7 Plan de Manejo**: igual a Urgencias (Destino, Recomendaciones y Plan de Manejo, Código Dorado).
- **5 Prescripción A** (ambulatoria) tiene rejilla distinta a la hospitalaria: Código · Nombre · Susp · **Dosis**
  (cantidad + unidad) · Vía · **Frecuencia** (cantidad + Horas/Día/Mes/Minutos) · **Periodo de duración** (cantidad +
  Horas/Día/Mes/Minutos) · Cada · **Total (Dosis)** · Cantidad solicitada · Unidad · Nota. Tipo: Regular | Control.
  Botones: Plantilla · Experiencia · Protocolo.
- 8 Control (formulario de crónicos), 9 Tamizaje, 12 Atención del Menor: no aplican en contingencia (deshabilitadas).

## 6. Pendiente

- Nada del recorrido. Falta aplicar las correcciones y repetir el recorrido en CRADOR.
