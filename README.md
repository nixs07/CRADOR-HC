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
| `sql/01_tablas_clinicas.sql` | 23 tablas clínicas de la admisión (Admision, Triage, SignVita, RipsCons...) |
| `sql/02_catalogos.sql` | 24 catálogos principales que se copian de SIHOS (Paciente, Contrato, CodiAdmi, CausMorb, CodiProc...) |
| `sql/03_catalogos_listas.sql` | 37 catálogos de listas desplegables (TipoDocu, ViaIngre, ViaAdmi, UnidMedi, CausSali, DestSali...) |
| `docs/` | Plan, decisiones y reglas de negocio |

## Stack

PHP + MySQL 5.6 en Docker (igual que SIHOS). Configuración en `.env` (nunca se sube a GitHub).
Por ahora corre en el computador de sistemas; está pensado para pasarse a un servidor pequeño
distinto del servidor de SIHOS.
