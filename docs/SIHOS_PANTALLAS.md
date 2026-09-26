# Pantallas de SIHOS por módulo (referencia para CRADOR-HC)

Levantado el 26/09/2026 a partir de 40 capturas de SIHOS (Urgencias, Consulta Externa, Procedimientos y
Observación e Internación). **Solo estructura**: aquí no hay nombres, documentos, diagnósticos ni textos de
pacientes. Las capturas no se guardan en el repositorio.

Convenciones de la columna "Tabla.columna": la tabla y columna de `sql/01_tablas_clinicas.sql` donde CRADOR-HC
guarda el campo. "Sin columna identificada" = el campo no tiene una columna clara en las tablas que copiamos.
"(no aplica en contingencia)" = función excluida (liquidación, cargos, imágenes, RDA, PyM).

## Encabezado de la admisión (igual en todos los módulos)

Barra: **Admisión** (Admision.ConsAdmi), **Cama** (solo Observación, selector de cama: Admision.CamaActu; en
CRADOR-HC el cambio de cama es "Traslado de cama" → TrasCama), **Fecha** (FechIngr), **Hora** (HoraIngr),
**Autorización** (NumeAuto), **SOAT** (sin columna identificada), botón de carné, estado **Abierta/Cerrada**
(Cerrado).

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Documento (Entidad) | lista tipo + número + botón "…" | Admision.TipoDocu, NumeUsua |
| Usuario | texto (nombre del paciente) | Paciente.NombUsua… |
| F. Nacimiento | fecha | Paciente.FechNaci |
| Edad | texto | Admision.ValoEdad / UnidEdad |
| Género | lista | Paciente.SexoUsua |
| Grupo | lista | Admision.GrupoAte |
| Servicio Origen (C.Costos) | lista | Admision.CodiServ |
| Cama Origen | lista | Admision.CodiCama |
| Vía Ingreso | lista | Admision.ViaIngre |
| Servicio Actual | lista | Admision.ServEgre |
| Cama Actual | lista | Admision.CamaActu |
| Entorno De Atención | lista | Admision.EntoAten |
| Causa Externa | lista | Admision.CausExte |
| Estado Ingreso | lista | Admision.EstaIngr |
| Condición | lista | Admision.CondUsua |
| Discapacidad | lista | sin columna identificada en Admision |
| Diagnóstico | código + nombre | Admision.DiagIngr |

Botones: Buscar, Imprimir, Limpiar, **Cerrar Historia**. Íconos a la izquierda (familia, carné, alertas).
Debajo, la barra de pestañas con `<<` y `>>` (se desplaza) y el pie **Volver / Continuar**.

## Pestañas por módulo

Las pestañas con número entre paréntesis no se ven en ninguna captura (quedaron detrás de `>>`); se anotan como
"no visible".

### Urgencias

1.Triage · 2.Consultas · 3.Atención del Menor · 4.Prescripción · 5.ORDENES MEDICAS · 6.Procedimientos ·
7.Ordenación · 8.Evolución · 9.Notas Enfermería · 10.Notas Médicas · (11 no visible) · 12.Consentimiento ·
13.Líquidos · 14.Oxígeno · 15.Remisiones · 16.Materiales · 17.Incapacidad · 18.Signos Vitales · 19.No POS ·
(20, 21, 22 no visibles) · 23.Esquema de Quemaduras · 24.GLUCOMETRIA · 25.Devoluciones · 26.Egreso ·
27.Cambio de Atención · 28.Imágenes

### Observación e Internación

1.Consultas · 2.Evolución · 3.Prescripción · 4.ORDENES MEDICAS · 5.Ordenación · 6.No POS · 7.Notas Enfermería ·
8.Notas Médicas · 9.Procedimientos · 10.Medicamentos · 11.Consentimiento · 12.Cirugía · 13.Anestesia ·
14.Signos Vitales · 15.Neurológico · (16 no visible) · 17.Líquidos · 18.Materiales · 19.Devoluciones ·
20.Recién Nacidos · 21.Incapacidad · 22.Egreso · 23.Cambio de Atención

### Consulta Externa

1.Anamnesis · 2.Rev.Sistemas y Ex.Físico · 3.Antecedentes · 4.Laboratorios y Diagnósticos · 5.Prescripción A… ·
6.Ordenación · (7 no visible) · 8.Control · 9.Tamizaje Riesgo Cardiovascular · 10.Consentimiento ·
11.Procedimientos · 12.Incapacidad · 13.Atención del Menor · 14.Notas Médicas · 15.Imágenes

En Consulta Externa la consulta (RipsCons) está repartida en las pestañas 1 a 4 en vez de acordeones.

### Procedimientos (módulo aparte de SIHOS, no está en CRADOR-HC)

1.Notas Médicas · 2.Consentimiento · 3.Notas Enfermería · 4.Procedimientos · 5.Prescripción · 6.Ordenación ·
7.Prescripción A…

## Detalle de cada pestaña

### Triage (Urgencias 1)

Barra: Fecha, Hora, "Profesional: …".

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Fecha / Hora | fecha / hora | Triage.FechTria / HoraTria |
| Motivo | texto largo | Triage.MotiCons |
| Signos Vitales: Peso (kg), Talla (cm), IMC (kg/m²), FC (min), FR (min), Temp (°C), PA (sist / diast), TM, Fetocardia (lat/min), Saturación (%), Oximetría, Glucometría | números en una fila | SignVita.Peso, Talla, MasaCorp, Pulso, Respirac, Temperat, PANume, PADeno, TM, FetoCard, Saturaci, Oximetria, GlucMetr (toma No. 1) |
| Hallazgos Clínicos | texto largo | Triage.HallClin |
| Impresión Diagnóstica | código "…" + nombre | Triage.CodiDiag |
| Clasificación | lista | Triage.ClasTria |
| Conducta | lista | Triage.CondTria |
| (texto bajo Conducta) | texto largo | Triage.Conducta |
| Continuar en el consultorio | lista | Triage.CodiCons |

Botones: Guardar, Modificar, Imprimir, Consultar.

### Consultas (Urgencias 2, Observación 1; Consulta Externa 1 a 4)

Barra: **Nuevo**, selector de consultas anteriores, **Fecha**, **Hora**, **Cargos**, **Consultar**, **Imprimir**.
Acordeones (en Consulta Externa, pestañas):

**Anamnesis** (CE: 1.Anamnesis)

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Fecha / Hora | fecha / hora | RipsCons.FechCons / HoraCons |
| Tipo (CE: Actividad) | código "…" + nombre | RipsCons.TipoCons (CodiProc) |
| Finalidad | lista | RipsCons.FinaCons |
| Motivo (CE: Motivo de Consulta) | texto largo | RipsCons.MotiCons |
| Enfermedad Actual | texto largo | RipsCons.EnfeActu |

**Antecedentes** (CE: 3.Antecedentes). Cada antecedente: lista Sí/No + texto.

| Etiqueta SIHOS | Tabla.columna |
| --- | --- |
| Planificación (Sí/No + método) | Antecede.MetoPlan (método: MetoDesc, sin catálogo local) |
| Familiares (Sí/No + parentesco + diagnóstico) | Antecede.Familiar / FamiDesc |
| Personales | Antecede.Personal / PersDesc |
| Patológicos | Antecede.Patologi / PatoDesc |
| Obstétricos | Antecede.Obstetri / ObstDesc |
| Ginecológicos | Antecede.Ginecolo / GineDesc |
| Quirúrgicos | Antecede.Quirurgi / QuirDesc |
| Tóxicos | Antecede.ToxiAler / ToxiDesc |
| Alérgicos (+ tipo de alergia, alergia a medicamentos) | Antecede.AlerSiNo / AlerDesc |
| Fisiológicos | Antecede.Fisiolog / FisiDesc |
| Alimentarios | Antecede.Alimenta / AlimDesc |
| Traumáticos | Antecede.Traumati / TrauDesc |
| Farmacológicos (+ tipo medicamento) | Antecede.Farmacol / FarmDesc |
| Factor de Riesgo (+ tipo) | Antecede.FactRies (sin columna de descripción) |
| Reconciliación medicamentosa | sin columna identificada |

**Revisión por Sistema y Exámen** (CE: 2.Rev.Sistemas y Ex.Físico)

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Revisión por Sistemas | texto largo | RipsCons.ReviSist |
| Sintomático Respiratorio | lista | RipsCons.SintResp |
| Sintomático de Piel | lista | RipsCons.SintPiel |
| Sintomático Nervioso Periférico | lista | RipsCons.SintNerv |
| Tuberculosis Multidrogoresistente | lista | RipsCons.TubeMult |
| Lepra | lista | RipsCons.TipoLepr (códigos sin catálogo local) |
| Tipo de Discapacidad | lista | sin columna identificada en RipsCons |
| Signos Vitales (misma fila del triage) | números | SignVita (toma ligada con ConsCons) |
| Hallazgos Estado General | texto | EstaGene.EstaGene |
| Perímetro Abdominal (0-200) | número | RipsCons.PeriAbdo |
| Perímetro Tórax (0-150) | número | RipsCons.PeriTorx |
| Índice Cintura-Cadera: Perímetro Cintura, Perímetro Cadera, ICC (solo CE) | números | SignVita.PeriCint, PeriCade, ResCXC |
| Cabeza, Ojos, Oídos, Nariz, Boca, Cuello, Tórax, Abdomen, G/U, Ano, Extremidades, Neurológico, Osteomuscular, Piel | lista Normal/Anormal + texto | EstaGene.Cabeza…Piel (+ *Desc); Tórax = CardPulm, G/U = GeniUrin |

**Laboratorios y Diagnósticos** (CE: 4.Laboratorios y Diagnósticos)

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Análisis de Laboratorio e Imágenes Diagnósticas | texto largo | RipsCons.LaboImag |
| Diagnósticos: Principal, Rela 1, Rela 2, Rela 3, Rela 4 (código "…", nombre, Tipo, Sivigila, Protocolo) | código + lista | RipsCons.CodiDiag, CodiRel1-4, TipoDiag, TipoDia1-4 |

**Plan de Manejo y Recomendaciones**

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Destino | lista | RipsCons.DestSali |
| Conducta | lista | RipsCons.Conducta (lista tipo 33, sin catálogo local) |
| Plan de Manejo y Recomendaciones | texto largo | RipsCons.ObseReco |
| Duración | texto calculado | — |

### Prescripción (Urgencias 4, Observación 3, CE 5)

Barra: Nuevo, No. (prescripciones anteriores), **Tipo de Prescripción** (Regular…), Fecha, Hora. Aviso
"No hay diagnósticos". **DXP** (lista de diagnósticos de la atención), **DXR 1**, **DXR 2** (CE también DXR3).

| Columna SIHOS | Tabla.columna |
| --- | --- |
| Suministro: Código "…", Nombre, Susp | DetaPres.CodiSumi (Susp = FechSusp) |
| Posología: Cantidad por dosis (cantidad + unidad), Vía | DetaPres.CantSumi, UnidMedi, CodiVia |
| Uso: Cada (CE: Frecuencia Cada + Tiempo), duración | DetaPres.CantFrec, TiemFrec, CantPeDu, TiemPeDu |
| Observaciones | EncaPres.ObseOrde |
| Tipo de prescripción | EncaPres.TipoPres (1 regular, 2 control) |
| DXP / DXR 1 / DXR 2 | EncaPres.CodiDiag / CodiRel1 / CodiRel2 |
| CE: Órdenes posfechadas (Cantidad, Periodicidad), Responsable de la entrega | sin columna identificada / EncaPres.PersEntr |

### ORDENES MEDICAS (Urgencias 5, Observación 4)

Orden médica en texto libre: EncaData / DetaData con TipoObje 7 (CodiItem 131 Urgencias, 130 Observación).
No existe en Consulta Externa.

### Ordenación (Urgencias 7, Observación 5, CE 6)

Barra: Nuevo, órdenes anteriores, Fecha, Hora.

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| (Solicitar Autorización Para EPS) | casilla | EncaOrde.Autoriza |
| Finalidad | lista ("No Aplica") | EncaOrde.CodiFina (catálogo FinaCons) |
| Ambulatoria | casilla | EncaOrde.OrdeAmbu |
| DXP, DXR1, DXR2, DXR3, DXR4 | diagnósticos | EncaOrde.CodiDiag, CodiRel1-4 |
| Código "…", Nombre, Cant, Susp, Prof., Nota | fila repetida | DetaOrde.CodiProc, CantSumi, FechSusp, CodiProf, ObseProc |
| Observaciones | texto largo | EncaOrde.ObseOrde |

### Procedimientos (Urgencias 6, Observación 9, CE 11)

Barra: Nuevo, Fecha, Hora; botones Adjuntar Imagen, Esquema de Vacunación, Laboratorios, Cargos (no aplican en
contingencia).

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Actividad | código "…" + nombre | HojaProc.CodiProc |
| Finalidad | lista | HojaProc.CodiFina |
| Cant | número | HojaProc.CantProc |
| Id Estudio | texto | sin columna identificada |
| Realizado? | casilla | HojaProc.ProcReal |
| Descripción | texto largo (editor) | HojaProc.IndiAdic |
| Diagnósticos: Principal, Rela 1, Rela 2, Rela 3, Compl (Tipo) | código + lista | HojaProc.DiagPrin/TipoDiag, DiagRela/TipoDiaR, DiagRel1/TipoDia1, DiagRel2/TipoDia2, DiagComp/TipoDiaC |
| Revisado | casilla | HojaProc.ContRevi |

Debajo, la lista de procedimientos (No., Fecha, Hora, Sede, Nombre, Fina., Cant, DXP, Orden, Item, Liqu, Cons.,
Profesional).

### Evolución (Urgencias 8, Observación 2)

Barra: Nueva, No. (evoluciones anteriores), Fecha, Hora, Profesional.

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Subjetivo | texto largo | EvolInte.Subjetivo |
| Objetivo | texto largo | EvolInte.Objetivo |
| Signos Vitales (fila) | números | SignVita (toma ligada con ConsEvol) |
| Diagnósticos: Principal, Rela 1-4 (Tipo) | código + lista | EvolInte.CodiDiag, CodiRel1-4, TipoDiag, TipoDiag1-4 |
| Análisis | texto largo (editor) | EvolInte.Analisis |
| Plan de Manejo | texto largo | EvolInte.PlanMane |
| Controles Especiales: Signos Vitales, Líquidos | casillas | EvolInte.ContSign, ContLiqu |
| Revisado | casilla | EvolInte.ContRevi |

### Notas Enfermería (Urgencias 9, Observación 7) y Notas Médicas (Urgencias 10, Observación 8, CE 14)

Barra: Nueva, No., Fecha, Hora, "Módulo / Profesional". Texto de la nota (editor, dictado por voz).
Notas Médicas tiene además la casilla **Revisada**.

| Etiqueta SIHOS | Tabla.columna |
| --- | --- |
| Fecha / Hora | HojaEnfe.FechNota / HoraNota |
| Texto | HojaEnfe.NotaEnfe |
| Revisada (solo Notas Médicas) | HojaEnfe.Reviza (UsuaRevi, FechRevi, HoraRevi) |
| (tipo, implícito por la pestaña) | HojaEnfe.TipoNota |

### Medicamentos (Observación 10)

Tabla de aplicación: Con, Fecha aplicación (fecha, hora), Fecha planeado (fecha, hora), Medicamento (código,
nombre), Vía, Cantidad Aplicar, Unidad, Aplicar, Observaciones, Profesional, Módulo, Fecha/Hora Susp.,
Disponible para aplicar, Entregado en farmacia, Devuelto en farmacia → HojaMedi (FechMedi, HoraMedi, FechPlan,
HoraPlan, CodiMedi, ViaAdmi, CantMedi, UnidMedi, EstaApli, IndiAdic, UsuaAsis, CodiModu).

### Remisiones (Urgencias 15)

Barra: Nuevo, No., Fecha, Hora, Autorización "…" (Remision.NumeAuto).

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Especialidad | lista | Remision.EspeRemi |
| Institución | lista | Remision.InstRemi (sin catálogo local de instituciones) |
| Acepta (Nombre) | texto | Remision.NombAcep |
| Cargo | texto | Remision.CargAcep |
| Autorización | texto | Remision.NumeAuto |
| Motivo | lista | Remision.RemiMoti (catálogo MotiRemi) |
| Incluir Ambulancia | casilla | Remision.Ambulanc |
| Fecha y Hora Aceptación | fecha + hora | Remision.FechAcep / HoraAcep |
| (texto) | texto largo | Remision.MotiRemi |

### Materiales (Urgencias 16, Observación 18)

HojaMate (sin captura del detalle): material (CodiMate), unidad (UnidMate), cantidad (CantMate), observación.

### Incapacidad (Urgencias 17, Observación 21, CE 12)

Barra: Nueva, **Tipo** (TipoInca), No., Fecha, Hora, Profesional.

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Datos de la incapacidad: Alcance | lista | IncaPaci.TipoAlca (sin catálogo local) |
| Origen (Común…) | lista | IncaPaci.OrigInca |
| Fecha inicial | fecha | IncaPaci.FechInca |
| Días | número | IncaPaci.DiasInca |
| Fecha final | fecha calculada | — |
| Clasificación: Incapacidad retroactiva | lista | IncaPaci.CodIncRetro (sin catálogo local) |
| Grupo de servicios | lista | IncaPaci.CodGrupServ (sin catálogo local) |
| Modalidad de prestación de servicios | lista | IncaPaci.CodMosPresServ (sin catálogo local) |
| Nota | texto largo | IncaPaci.ObseInca |

### Signos Vitales (Urgencias 18, Observación 14)

Tomas de signos (SignVita), con la misma fila de campos del triage.

### Egreso (Urgencias 26, Observación 22)

| Etiqueta SIHOS | Tipo | Tabla.columna |
| --- | --- | --- |
| Fecha / Hora | fecha / hora | SaliInte.FechSali / HoraSali |
| Estadía: Día(s), Hora(s) | calculado | SaliInte.DiasEsta / HoraEsta |
| Estado (VIVO…) | lista | SaliInte.EstaSali |
| Causa | lista | SaliInte.CausSali |
| Destino | lista | SaliInte.DestSali |
| Incapacidad: Día(s) | número | SaliInte.DiasInca |
| Diagnósticos: Egreso, Rela 1, Rela 2, Rela 3, Complic. (Tipo) | código + lista | SaliInte.DiagEgre, DiagRel1-3, DiagComp, TipoDiag, TipoDia1-4 |
| Plan de Manejo Ambulatorio y Observaciones | texto largo | SaliInte.ObseSali |

Botones: Cerrar Historia, Limpiar, Imprimir. Debajo: "Insumos y Medicamentos Pendientes por Descargar"
(inventario, no aplica en contingencia).

### Pestañas sin tablas en CRADOR-HC (se muestran deshabilitadas "No disponible en contingencia")

Atención del Menor, Consentimiento, Líquidos, Oxígeno, No POS, Esquema de Quemaduras, GLUCOMETRIA, Devoluciones,
Cambio de Atención, Imágenes, Cirugía, Anestesia, Neurológico, Recién Nacidos, Control, Tamizaje Riesgo
Cardiovascular.
