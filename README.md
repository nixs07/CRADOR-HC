# CRADOR-HC — Contingencia SIHOS

App web local para registrar la atención clínica mientras SIHOS está caído, y cargarla a SIHOS cuando vuelva.
E.S.E. Hospital Sagrado Corazón de Jesús.

## Qué hace

- **Módulos:** Urgencias, Observación e Internación, Consulta Externa.
- **Pestañas:** admisión (encabezado completo con EPS y contrato), triage, signos vitales, anamnesis y diagnóstico,
  examen físico, antecedentes, orden médica, órdenes, procedimientos, prescripción y materiales (solo códigos),
  notas de enfermería, administración de medicamentos, evolución, traslado de cama y egreso.
- **No hace:** liquidación, inventario, envío RDA, Promoción y Mantenimiento.
- **Usuarios:** los profesionales entran con su mismo login y clave de SIHOS.
- **Carga a SIHOS:** solo el administrador (NIXON07), directo a la base de datos.

## Regla principal

Las tablas tienen **exactamente los mismos nombres y columnas que SIHOS** (ver `sql/`). Así la carga es un
`INSERT` directo. No renombrar tablas ni columnas.

## Estructura

| Carpeta | Contenido |
| --- | --- |
| `sql/00_control.sql` | Tablas propias de la app, prefijo `cont_` (catálogos, cargas a SIHOS, accesos). Nunca se exportan. |
| `sql/01_tablas_clinicas.sql` | 23 tablas clínicas de la admisión (Admision, Triage, SignVita, RipsCons...) |
| `sql/02_catalogos.sql` | 24 catálogos principales que se copian de SIHOS (Paciente, Contrato, CodiAdmi, CausMorb, CodiProc...) |
| `sql/03_catalogos_listas.sql` | 37 catálogos de listas desplegables (TipoDocu, ViaIngre, ViaAdmi, UnidMedi, CausSali, DestSali...) |
| `sql/99_datos_prueba.sql` | Datos inventados para pruebas. **No** se cargan solos; nunca en producción. |
| `public/` | Páginas web (lo único que publica Apache): login, tablero, catálogos, CSS |
| `src/` | Código PHP común: configuración, conexión PDO, sesión/CSRF, catálogos, tablero |
| `bin/` | Tareas por línea de comandos (`actualizar_catalogos.php` y su `.bat` para el Programador de tareas) |
| `docker/` | Dockerfile de la app y scripts de la base (datos de prueba, respaldo, restauración) |
| `docs/` | Reglas de negocio (`REGLAS.md`) e instalación (`INSTALACION.md`) |

## Instalación rápida

```powershell
Copy-Item .env.example .env      # y editar claves / datos de SIHOS
docker compose up -d
docker compose exec -T app php bin/actualizar_catalogos.php   # primera carga de catálogos y usuarios
```

Abrir <http://localhost:8080>. Guía completa: [`docs/INSTALACION.md`](docs/INSTALACION.md).

## Estado

- **Fase 1 (hecha):** Docker, login con usuarios de SIHOS, tablero, actualización de catálogos, tablas de control.
- **Fase 2:** módulos clínicos (admisión, triage, signos vitales, órdenes, prescripción, notas, evolución, egreso...).
- **Fase 3:** carga a SIHOS por el administrador (`cont_carga_sihos`).

## Stack

PHP 8.2 (Apache, PDO, sin frameworks) + MySQL 5.6 en Docker (igual que SIHOS). Configuración en `.env`
(nunca se sube a GitHub; plantilla en `.env.example`). Sin CDN: funciona sin internet.
Por ahora corre en el computador de sistemas; está pensado para pasarse a un servidor pequeño
distinto del servidor de SIHOS.
