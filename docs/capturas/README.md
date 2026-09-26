# Capturas de pantalla (fase 2, bloque 1 · pantalla de trabajo por módulo)

Tomadas el 25/09/2026 con la app corriendo en Docker (`docker compose up -d`) y los **datos de prueba
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
   orden y la numeración de SIHOS en cada módulo (Urgencias 1-28, Observación 1-23, Consulta Externa 1-15; las
   que no tienen tablas: "No disponible en contingencia"). Cada pestaña tiene la barra de SIHOS (Nuevo,
   registros anteriores, fecha, hora; Imprimir y Cargos deshabilitados). Pie con **Volver a historias
   abiertas** y **Continuar**.

| Archivo | Pantalla |
| --- | --- |
| `01_login.png` | Ingreso |
| `23_seleccion_modulo.png` | Selección de módulo |
| `15_lista_urg.png` | Urgencias: ventana de historias abiertas |
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

## Pestañas de la historia (como SIHOS)

El recorrido llena y guarda cada pestaña de Urgencias, hace la remisión, la incapacidad y el egreso en
Observación, y en Consulta Externa guarda la consulta repartida en las pestañas 1 a 4 (primero sin enfermedad
actual: el error abre la pestaña 1), una nota médica y "Cerrar Historia".

| Archivo | Pantalla |
| --- | --- |
| `12_triage.png` | Urgencias 1. Triage (con Continuar en el consultorio) |
| `27_consulta_formulario.png` | Urgencias 2. Consultas: acordeones Anamnesis, Antecedentes, Revisión por Sistema y Exámen, Laboratorios y Diagnósticos, Plan de Manejo |
| `27_consulta.png` | 2. Consultas guardada |
| `28_prescripcion_formulario.png` | 4. Prescripción: Tipo de prescripción, DXP/DXR y dos suministros |
| `28_prescripcion.png` | 4. Prescripción guardada (dosis y cantidad total calculadas) |
| `38_ordenes_medicas.png` | 5. ORDENES MEDICAS (texto libre) |
| `29_ordenacion.png` | 7. Ordenación: autorización EPS, finalidad, ambulatoria, DXP/DXR1-4 e ítems |
| `30_procedimientos.png` | 6. Procedimientos: uno suelto y otro que atiende el ítem de la orden |
| `32_evolucion.png` | 8. Evolución con signos y diagnósticos Principal y Rela 1-4 |
| `31_notas_enfermeria.png` | 9. Notas Enfermería |
| `39_notas_medicas.png` | 10. Notas Médicas con "Revisada" |
| `40_medicamentos.png` | 11. Medicamentos (administración de lo prescrito) |
| `41_materiales.png` | 16. Materiales |
| `42_remisiones.png` | 15. Remisiones (con fecha y hora de aceptación) |
| `43_incapacidad.png` | 17. Incapacidad (fecha final calculada) |
| `36_traslado.png` | Observación: traslado de cama desde el encabezado (HOSP09 → HOSP10) con el historial |
| `44_obs_consultas.png` | Observación 1. Consultas |
| `37_egreso_remision.png` | Observación 22. Egreso con la remisión registrada |
| `33_egreso.png` | 22. Egreso con la confirmación marcada |
| `34_egreso_cerrado.png` | Admisión cerrada: egreso en modo consulta |
| `45_ce_anamnesis.png` | Consulta Externa 1. Anamnesis |
| `46_ce_revision.png` | Consulta Externa 2. Rev.Sistemas y Ex.Físico |
| `47_ce_cerrar_historia.png` | Consulta Externa: ventana "Cerrar Historia" del encabezado |
| `35_ce_cerrada.png` | Consulta Externa cerrada |
| `48_movil_consulta.png` | Celular · 2. Consultas |
