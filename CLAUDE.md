# Instrucciones para Claude en este repositorio

- Leer `README.md` y `docs/REGLAS.md` antes de programar.
- **Nunca renombrar tablas ni columnas** de `sql/`: deben ser idénticas a SIHOS para poder exportar.
- Stack: PHP 8 + MySQL 5.6 en Docker. Sin frameworks pesados; código simple que sistemas pueda mantener.
- Las sesiones en la nube **no tienen acceso** a la red del hospital (192.168.0.250). Probar con datos inventados;
  la conexión real a SIHOS se configura en `.env` en el equipo local.
- Nunca subir `.env`, claves, respaldos ni datos de pacientes.
- Idioma de la interfaz y de los comentarios: español.
