# Capturas de pantalla (fase 2, bloque 1 · nuevo diseño)

Tomadas el 25/09/2026 con la app corriendo en Docker (`docker compose up -d`) y los **datos de prueba
inventados** (`docker compose exec db sh /crador/cargar_datos_prueba.sh`). Ningún dato es de pacientes reales.
Usuario: `MEDPRUEBA` / `prueba123` (la captura 19 es con `NIXON07`, administrador).

Se generan con `recorrido.js` (Playwright), que además comprueba el flujo completo: ingreso, elegir módulo,
crear paciente, 3 admisiones (una por módulo), triage, toma de signos No. 2, salir e ingreso del administrador:

```sh
OUT=docs/capturas NODE_PATH=$(npm root -g) node docs/capturas/recorrido.js
```

Flujo: después del ingreso el profesional **elige el módulo** (23). Dentro del módulo la pantalla principal es
**Historias abiertas** (15-17) y cada historia tiene el encabezado de la admisión y pestañas numeradas como en
SIHOS (7, 9, 11, 14). El tablero (2, 18, 19) queda para el administrador y como opción "Tablero general".

| # | Pantalla | Archivo |
| --- | --- | --- |
| 1 | Ingreso (login) | `01_login.png` |
| 2 | Tablero general | `02_tablero.png` |
| 3 | Nueva admisión: búsqueda de pacientes | `03_pacientes_busqueda.png` |
| 4 | Nuevo paciente | `04_paciente_nuevo.png` |
| 5 | Paciente creado (búsqueda con botones para abrir admisión) | `05_paciente_creado.png` |
| 6 | Nueva admisión · Urgencias | `06_admision_nueva_urg.png` |
| 7 | Historia de Urgencias (sin triage) | `07_ficha_urg.png` |
| 8 | Nueva admisión · Observación e Internación (con cama) | `08_admision_nueva_obs.png` |
| 9 | Historia de Observación | `09_ficha_obs.png` |
| 10 | Nueva admisión · Consulta Externa | `10_admision_nueva_ce.png` |
| 11 | Historia de Consulta Externa | `11_ficha_ce.png` |
| 12 | Triage (orden de SIHOS, signos en fila compacta, IMC y TM calculados) | `12_triage.png` |
| 13 | Toma de signos vitales | `13_signos.png` |
| 14 | Historia de Urgencias con triage y dos tomas de signos | `14_ficha_urg_con_triage_y_signos.png` |
| 15 | Historias abiertas · Urgencias | `15_lista_urg.png` |
| 16 | Historias abiertas · Observación e Internación | `16_lista_obs.png` |
| 17 | Historias abiertas · Consulta Externa | `17_lista_ce.png` |
| 18 | Tablero después de registrar | `18_tablero_final.png` |
| 19 | Tablero del administrador | `19_tablero_administrador.png` |
| 20 | Celular · Tablero | `20_movil_tablero.png` |
| 21 | Celular · Historias abiertas de Urgencias (filas como tarjetas) | `21_movil_lista_urg.png` |
| 22 | Celular · Historia | `22_movil_ficha.png` |
| 23 | Selección de módulo (después del ingreso) | `23_seleccion_modulo.png` |
| 24 | Celular · Menú lateral abierto | `24_movil_menu.png` |
