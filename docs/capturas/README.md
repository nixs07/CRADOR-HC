# Capturas de pantalla (fase 2, bloque 1 · pantalla de trabajo por módulo)

Regeneradas el 26/09/2026 (marca HSCJ, obligatorios y mensajes de SIHOS) con la app corriendo en Docker (`docker compose up -d`) y los **datos de prueba
inventados** (`docker compose exec db sh /crador/cargar_datos_prueba.sh`). Ningún dato es de pacientes reales.
Usuario: `MEDPRUEBA` / `prueba123` (la captura 19 es con `NIXON07`, administrador).

Se generan con `recorrido.js` (Playwright), que además comprueba el flujo completo: ingreso, elegir módulo,
el médico no puede abrir el tablero, buscar documento en el encabezado, crear paciente, 3 admisiones (una por
módulo) creadas en el encabezado, triage, toma de signos No. 2, salir e ingreso del administrador:

```sh
OUT=docs/capturas NODE_PATH=$(npm root -g) node docs/capturas/recorrido.js
```

## Flujo (como en SIHOS)

1. Después del ingreso el profesional **elige el módulo** (23). El tablero es solo del administrador (19).
2. Cada módulo tiene **una sola pantalla de trabajo** (`atencion.php`): encabezado de la admisión arriba y
   pestañas numeradas debajo. Al entrar se abre la ventana **Historias abiertas** (15-17); clic en la fila
   carga la admisión.
3. **Nueva admisión** en el mismo encabezado: se escribe el documento y se pulsa Buscar. Si el paciente no
   existe se crea (4, 5) y se vuelve con el documento cargado; si existe, el encabezado queda editable (6, 8, 10).
4. Con la admisión cargada, el encabezado queda en modo lectura y se trabaja por pestañas con los nombres, el
   orden y la numeración de SIHOS en cada módulo (Urgencias 1-28, Observación 1-31, Consulta Externa 1-21; las
   que no tienen tablas: "No disponible en contingencia"). Cada pestaña tiene la barra de SIHOS (Nuevo,
   registros anteriores, fecha, hora; Imprimir y Cargos deshabilitados). Pie con **Volver a historias
   abiertas** y **Continuar**.

| Archivo | Pantalla |
| --- | --- |
| `01_login.png` | Ingreso HSCJ: vertical y centrado (logo, HSCJ, lema, Usuario, Clave, Ingresar) |
| `02_login_movil.png` | Ingreso en celular |
| `23_seleccion_modulo.png` | Selección de módulo |
| `15_lista_urg.png` | Urgencias: ventana de historias abiertas |
| `56_documento_autocompletar.png` | Documento del encabezado con autocompletar (por número o nombre, desde 3 caracteres) |
| `03_pacientes_busqueda.png` | Búsqueda de pacientes por nombre (botón "…") |
| `04_paciente_nuevo.png` | Paciente nuevo |
| `05_paciente_creado.png` | De vuelta en el módulo con el documento cargado (encabezado en modo nueva admisión) |
| `06_admision_nueva_urg.png` | Encabezado en modo nueva admisión · Urgencias |
| `07_ficha_urg.png` | Admisión cargada · pestaña 1. Triage lista para llenar |
| `08_admision_nueva_obs.png` | Nueva admisión · Observación e Internación (con cama) |
| `09_ficha_obs.png` | Admisión cargada · Observación (pestaña 1. Consultas) |
| `10_admision_nueva_ce.png` | Nueva admisión · Consulta Externa |
| `11_ficha_ce.png` | Admisión cargada · Consulta Externa |
| `12_triage.png` | Triage lleno (signos en fila compacta, IMC y TM calculados) |
| `13_signos.png` | Urgencias 18. Signos Vitales: nueva toma arriba, tomas anteriores abajo |
| `14_ficha_urg_con_triage_y_signos.png` | Después de guardar la toma No. 2 |
| `16_lista_obs.png` | Observación: historias abiertas |
| `17_lista_ce.png` | Consulta Externa: historias abiertas |
| `19_tablero_administrador.png` | Tablero (solo administrador) |
| `20_movil_seleccion_modulo.png` | Celular · selección de módulo |
| `21_movil_lista_urg.png` | Celular · historias abiertas (filas como tarjetas) |
| `22_movil_ficha.png` | Celular · admisión cargada, pestaña Triage |
| `25_movil_signos.png` | Celular · pestaña Signos vitales |
| `26_movil_nueva_admision.png` | Celular · encabezado en modo nueva admisión |
| `24_movil_menu.png` | Celular · menú lateral |

## Pestañas de la historia (como SIHOS, `docs/RECORRIDO_SIHOS.md`)

El recorrido llena y guarda cada pestaña de Urgencias, hace la remisión, la incapacidad y el egreso en
Observación, y en Consulta Externa guarda la consulta repartida en las pestañas 1 a 4 y 7 (primero sin enfermedad
actual: el error abre la pestaña 1), una Prescripción A (fórmula de salida), una nota médica y "Cerrar Historia".

| Archivo | Pantalla |
| --- | --- |
| `12_triage.png` | Urgencias 1. Triage (sin consultorio; diagnóstico escogido con el autocompletar) |
| `27_consulta_formulario.png` | Urgencias 2. Consultas: cinco acordeones con su Guardar, Cerrar Consulta y Duración |
| `27_consulta.png` | 2. Consultas guardada |
| `49_plan_manejo.png` | Urgencias 20. Plan de Manejo (ObseReco de la consulta) |
| `28_prescripcion_formulario.png` | 4. Prescripción en rejilla (Cantidad por dosis, Cada, A partir de, Número de dosis, Cantidad solicitada, Medi. Prin.) |
| `28_prescripcion.png` | 4. Prescripción guardada (dosis y cantidad total calculadas) |
| `38_ordenes_medicas.png` | 5. ORDENES MEDICAS (texto libre) |
| `29_ordenacion.png` | 7. Ordenación en rejilla (Autorización EPS y Salida deshabilitados, DXP/DXR1-4 en listas) |
| `30_procedimientos.png` | 6. Procedimientos: uno suelto y otro que atiende el ítem de la orden |
| `32_evolucion.png` | 8. Evolución con signos y diagnósticos Principal y Rela 1-4 |
| `31_notas_enfermeria.png` | 9. Notas Enfermería |
| `39_notas_medicas.png` | 10. Notas Médicas con "Revisada" |
| `40_medicamentos.png` | 11. Medicamentos: rejilla de la prescripción escogida |
| `41_materiales.png` | 16. Materiales en rejilla de 5 filas |
| `42_remisiones.png` | 15. Remisiones con Especialidad e Institución de lista |
| `43_incapacidad.png` | 17. Incapacidad con Alcance, Clasificación y Maternidad |
| `36_cambio_atencion.png` | Observación 23. Cambio de Atención (traslado de cama HOSP09 → HOSP10, TrasCama) |
| `44_obs_consultas.png` | Observación 1. Consultas guardada |
| `37_obs_remisiones.png` | Observación 24. Remisiones |
| `33_egreso.png` | 22. Egreso: Guardar/Modificar separados de Cerrar Historia y listas de pendientes |
| `34_egreso_cerrado.png` | Admisión cerrada: egreso en modo consulta |
| `45_ce_anamnesis.png` | Consulta Externa 1. Anamnesis |
| `46_ce_revision.png` | Consulta Externa 2. Rev.Sistemas y Ex.Físico (sistemas en Normal por defecto) |
| `50_ce_plan.png` | Consulta Externa 7. Plan de Manejo (parte del formulario de la consulta) |
| `47_ce_cerrar_historia.png` | Consulta Externa: ventana "Cerrar Historia" del encabezado |
| `51_cerrar_historia.png` | Observación: confirmación de Cerrar Historia en la página (sin casilla) |
| `52_ventana_antecedentes.png` | Ventana automática al abrir la historia: antecedentes tóxicos, alérgicos y reconciliación |
| `53_prescripcion_autocompletar.png` | Prescripción: autocompletar propio desplegado (búsqueda por nombre en la columna Código) |
| `54_ordenacion_autocompletar.png` | Ordenación: autocompletar propio desplegado |
| `35_ce_cerrada.png` | Consulta Externa cerrada |
| `48_movil_consulta.png` | Celular · 2. Consultas |

## Obligatorios y mensajes de SIHOS (`docs/VALIDACIONES_SIHOS.md`)

| Archivo | Pantalla |
| --- | --- |
| `55_triage_obligatorios.png` | Triage guardado sin signos y con peso 350: mensajes de SIHOS ("Digite la talla"…, "Por favor verifique el peso, este no puede sobrepasar 300 Kg.") |
| `57_guardar_se_queda.png` | Consultas: al guardar Antecedentes la página se queda en la misma sección y el aviso sale abajo, junto a Continuar |
| `58_antecedentes_si_no.png` | Antecedentes: con Si se habilitan descripción y campos adicionales; con No la descripción queda de solo lectura (sin borrar el texto); método de planificación en lista |
| `59_prescripcion_a_calculo.png` | Prescripción A: 1000 mg c/6 h por 3 días → Total (Dosis) 12 → 12000 / 500 = 24 |
| `60_admision_cerrada_por_numero.png` | Número de admisión + Enter abre una historia cerrada en solo lectura |
| `61_documento_admisiones_cerradas.png` | Documento sin admisión abierta: lista de sus admisiones (cerradas) para abrirlas |

El recorrido también comprueba: signos obligatorios en Evolución, toma de Signos Vitales posterior a la anterior,
Cerrar Consulta completa (signos y Revisión por Sistema), Nota y máximo 24 horas en la Prescripción, buscador de
medicamentos que llena unidad, vía y contenido, remisión con placa de ambulancia y examen físico "No se Explora" = 3.
Desde el 28/09/2026 también: DXP como lista (vacía sin consulta: "Debe seleccionar diagnóstico, es obligatorio según la
normativa 2275"), cálculo de la prescripción con los ejemplos de SIHOS (75 mg c/12 h → 2 dosis → 2 unidades; c/12 h
5 días → 10; c/8 h 3 días → 9; c/6 h 3 días → 12; 12000 / 500 = 24), antecedente que pasa a No sin borrar su texto,
aviso abajo y quedarse en la sección al guardar, y búsqueda por número de admisión de una historia cerrada. Con
`PROYECTO=<proyecto de docker compose>` el recorrido consulta la base para verificar (líneas `-- BD`).
