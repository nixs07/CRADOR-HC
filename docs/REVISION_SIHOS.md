# Revisión contra SIHOS real (26/09/2026)

Resultado de comparar la app con la base real de SIHOS. **Manda sobre los supuestos de [`REGLAS.md`](REGLAS.md)**:
si algo de REGLAS.md contradice este resumen, vale lo que dice aquí.

Fuente: tablas `ModuObje`, `Objetos` y `Permisos` de SIHOS, registros reales de las tablas clínicas y capturas de
pantalla de SIHOS.

## Pestañas definitivas

| Módulo | Pestañas verificadas |
| --- | --- |
| Urgencias | 11 Medicamentos, 20 Plan de Manejo, 21 Neurológico, 22 PROCEDIMIENTO TERAPIAS |
| Observación | 16 Oxígeno, 24 Remisiones (pestaña propia), 25 Plan de Manejo, 26 GLUCOMETRIA |
| Consulta Externa | 7 Plan de Manejo; Remisiones va después de Imágenes |

Lista completa por módulo en [`SIHOS_PANTALLAS.md`](SIHOS_PANTALLAS.md).

## Reglas de datos

| Tema | Regla |
| --- | --- |
| Plan de Manejo | Es `RipsCons.ObseReco`. |
| `EncaPres.PresSali` | 1 = hospitalaria, 2 = fórmula de salida. Consulta Externa siempre 2. |
| `EncaPres.Consecut` / `EncaOrde.Consecut` | Un solo contador global **compartido** por las dos tablas y **no único**. En fase 3 el siguiente = `GREATEST(MAX(EncaPres.Consecut), MAX(EncaOrde.Consecut)) + 1`. |
| `HojaEnfe.TipoNota` | 1 = Enfermería, 2 = Médico, 5 = Consentimiento. |
| Cierre de `RipsCons` | Urgencias y Observación: se cierra al guardar. Consulta Externa: `EstaReal = 1` sin cierre. |
| `RipsCons.TipoCons` | Obligatorio: Urgencias 890701, Observación 89060102, Consulta Externa 890201. |
| `EstaGene` | Por defecto 1 = Normal. |
| `CentCost` | `''` (vacío) en `HojaMate`, `HojaProc` y `HojaMedi`. En SIHOS aparece `''` (mayoría) o `'0'`. |
| `HojaProc.NumeOrde` | = `EncaOrde.ConsOrde` (número de la orden dentro de la admisión), **no** `EncaOrde.Consecut`: 500 de 500 procedimientos reales con `NumeOrde > 0` coinciden con `ConsOrde` y 0 con `Consecut`. |
| `HojaMedi.NumeOrde` | = `EncaPres.ConsPres`. |

## Confirmados

- `HojaProc`: usuarios de máximo 8 caracteres (`UsuaDigi`, `UsuaModi`, `CodiProf`, `UsuaAsis`).
- Consulta Externa sin `SaliInte` (solo "Cerrar Historia").
- `EncaOrde`: `CodiFina = '10'`, `Autoriza = 0`, `OrdeSali = 0`.
- `RipsCons.DestSali = 4` en Consulta Externa.
- `Triage.ConsTria` es consecutivo por paciente.

## Pendiente

- `Remision`: SIHOS no tiene registros recientes para verificarla; sigue como supuesto (ver REGLAS.md).
