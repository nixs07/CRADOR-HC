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
4. Con la admisión cargada, el encabezado queda en modo lectura y se trabaja por pestañas: 1. Triage
   (solo Urgencias), 2. Consultas, 3. Signos vitales, 4. Prescripción … 9. Egreso (las que faltan: "Próximamente").
   Pie con **Volver a historias abiertas** y **Continuar**.

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
| `09_ficha_obs.png` | Admisión cargada · Observación (pestaña 3. Signos vitales) |
| `10_admision_nueva_ce.png` | Nueva admisión · Consulta Externa |
| `11_ficha_ce.png` | Admisión cargada · Consulta Externa |
| `12_triage.png` | Triage lleno (signos en fila compacta, IMC y TM calculados) |
| `13_signos.png` | Pestaña 3. Signos vitales: nueva toma arriba, tomas anteriores abajo |
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
