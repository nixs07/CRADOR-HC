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

## Interfaz

- Después del ingreso el profesional **elige el módulo** (Urgencias, Observación e Internación o Consulta
  Externa), como en SIHOS. Cada módulo tiene **una sola pantalla de trabajo** (`public/atencion.php`): encabezado
  de la admisión arriba (buscar documento, nueva admisión, historias abiertas) y pestañas numeradas en el orden de
  SIHOS debajo; el triage y los signos se registran dentro de su pestaña. El tablero es solo del administrador.
- Funciona en PC, tableta y celular (menú lateral en cajón, tablas como tarjetas). Todo es local, sin CDN:
  fuente Plus Jakarta Sans (`public/fonts`, licencia OFL), íconos Lucide en `public/img/iconos.svg` (licencia
  ISC), CSS propio en `public/css/estilo.css` y JS propio en `public/js/`.
- Capturas y recorrido de prueba: [`docs/capturas/`](docs/capturas/README.md).

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
| `public/` | Páginas web (lo único que publica Apache): login, tablero, pacientes, admisiones, triage, signos, CSS/JS |
| `src/` | Código PHP común: configuración, conexión PDO, sesión/CSRF, catálogos, listas, atención clínica |
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
- **Fase 2 — bloque 1 (hecho):** buscar y crear pacientes, nueva admisión con encabezado completo (EPS, contrato
  activo, categoría, vía de ingreso, causa externa, cama en Observación, acompañante), lista de pacientes abiertos
  por módulo, ficha de la admisión, triage (Urgencias) y signos vitales.
- **Fase 2 — bloque 2 (hecho):** pestañas de la historia: 2. Consultas (RipsCons, Antecede, EstaGene),
  4. Prescripción (EncaPres/DetaPres), 5. Órdenes médicas (texto libre EncaData/DetaData y órdenes EncaOrde/DetaOrde),
  6. Procedimientos (HojaProc), 7. Notas de enfermería (HojaEnfe) y administración de medicamentos (HojaMedi),
  8. Evolución (EvolInte) y 9. Egreso (SaliInte y cierre). Supuestos en `docs/REGLAS.md`.
- **Fase 2 — siguientes bloques:** traslado de cama, materiales, remisión e incapacidad.
  procedimientos, fórmula y medicamentos, materiales, notas de enfermería, evolución, traslado de cama y egreso.
- **Fase 3:** carga a SIHOS por el administrador (`cont_carga_sihos`).

## Stack

PHP 8.2 (Apache, PDO, sin frameworks) + MySQL 5.6 en Docker (igual que SIHOS). Configuración en `.env`
(nunca se sube a GitHub; plantilla en `.env.example`). Sin CDN: funciona sin internet.
Por ahora corre en el computador de sistemas; está pensado para pasarse a un servidor pequeño
distinto del servidor de SIHOS.
