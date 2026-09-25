# Demo navegable (sin servidor)

Genera un solo `index.html` con las pantallas reales de la app (datos de prueba inventados) para mostrar
cómo se ve y se navega sin instalar nada. **No guarda datos**: los formularios muestran un aviso.

1. Con la app corriendo en Docker y los datos de prueba cargados, ejecutar `docs/capturas/recorrido.js`
   (deja admisiones con todas las pestañas llenas).
2. `cd docs/demo && NODE_PATH=$(npm root -g) node recoger.js` → crea `recogido.json`.
3. `python3 armar.py` → crea `index.html` (no se sube al repositorio).

En la demo: usuario `MEDPRUEBA` (cualquier clave) o `NIXON07` para ver el tablero del administrador.
