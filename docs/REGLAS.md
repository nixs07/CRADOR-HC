# Reglas de negocio (verificadas contra SIHOS real, septiembre 2026)

## Módulos y servicios

| Módulo | CodiServ | TipoAten | CodiModu (SIHOS) |
| --- | --- | --- | --- |
| Urgencias | 007 | 3 | 6 |
| Observación e Internación | 008 | 3 | 8 |
| Consulta Externa | 001, 013 | 1 | 5 |

## Número de admisión (ConsAdmi)

- Formato `AAAAMMDD` + consecutivo de 4 dígitos del día de digitación (ej. `202609240001`). `varchar(12)`.
- En la contingencia la admisión lleva un número temporal. **Al cargar a SIHOS** se busca la última admisión
  de ese día en SIHOS y se asigna la siguiente. La fecha real de ingreso (`FechIngr`) se conserva.
- Todos los demás consecutivos (ConsOrde, ConsPres, ConsEvol, ConsSign, ConsHoEn...) son **por admisión**:
  no chocan con SIHOS.

## Orden de inserción (padre → hijo)

Paciente (si no existe) → Admision → EncaData → EncaOrde → EncaPres → HojaProc → resto de tablas
(DetaData, DetaOrde, DetaPres, DetaPrue, Triage, SignVita, RipsCons, EstaGene, Antecede, HojaEnfe, HojaMedi,
HojaMate, HojaLAdm, EvolInte, TrasCama, Remision, IncaPaci, SaliInte).
Cada admisión entra completa o no entra (transacción por admisión). La base de SIHOS no tiene triggers.

## Orden médica

`EncaData` + `DetaData` con `TipoObje = 7` = "Orden médica" en texto libre (`DetaData.Texto`).
`CodiItem` 131 en Urgencias (CodiModu 6), 130 en Observación (CodiModu 8).

## Qué catálogo valida cada campo

Todo campo con código se escoge de su catálogo (nunca texto libre), para que al cargar a SIHOS no entren códigos
que no existan (RIPS, reportes y facturación dependen de eso).

| Pantalla | Campo | Catálogo |
| --- | --- | --- |
| Paciente | TipoDocu, SexoUsua, EstaCivi, NiveEsco, PertEtni, TipoDisc | TipoDocu, CodiSexo, EstaCivi, CodiEsco, PertEtni, TipoDisc |
| Paciente | ResiDepa, ResiMuni, ResiComu, ResiZona, ResiBarr, CodiPais | CodiDepa, CodiMuni, CodiComu, CodiZona, CodiBarr, CodiPais |
| Admisión | CodiAdmi, NumeCont | CodiAdmi, Contrato (solo vigentes) |
| Admisión | TipoUsua, TipoAfil, CodiEstr | TipoUsua, TipoAfil, CodiEstr / AdmiEstr |
| Admisión | ViaIngre (**no** ViaAcces, que es vía de acceso quirúrgica) | ViaIngre |
| Admisión | CausExte, CondUsua, TipoAten, GrupoAte | CausExte, CondUsua, TipoAten, GrupAten |
| Admisión | CodiServ, CentCost, CodiCama, CodiCons | CodiServ, CeCoServ, CodiCama, CodiCons |
| Admisión | TipoAcom, Parentes | TipoAcom, Parentes |
| Triage | ClasTria, CondTria | ClasTria, CondTria |
| Diagnósticos | CodiDiag, CodiRel1..4 / DiagPrin... | CausMorb |
| Diagnósticos | TipoDiag | TipoDiag |
| Consulta | FinaCons | FinaCons |
| Órdenes y procedimientos | CodiProc, CodiFina | CodiProc, FinaProc |
| Prescripción y medicamentos | CodiSumi / CodiMedi, CodiVia, UnidMedi, TiemFrec | CodiSumi, ViaAdmi, UnidMedi, CodiTiem |
| Materiales | CodiMate, UnidMate | CodiSumi, CodiUnid |
| Notas de enfermería | TipoNota | TipoNota |
| Egreso | CausSali, DestSali, EstaSali, TipoEgre | CausSali, DestSali, EstaSali, TipoEgre |
| Remisión | MotiRemi, ModaSoli | MotiRemi, ModaSoli |
| Incapacidad | tipo | TipoInca |
| Profesional | UsuaDigi, CodiEspe | Usuarios, CodiEspe |

Los nombres exactos de las columnas de cada catálogo están en `sql/02_catalogos.sql` y `sql/03_catalogos_listas.sql`.

## Liquidación

No se liquida. Las columnas `NumeLiqu`, `ConsDeFa`, `CantFact` quedan en 0; facturación liquida en SIHOS.

## Usuarios y claves

- `Usuarios.Login` varchar(12), `Usuarios.Password` = MD5-crypt (`$1$...`, 34 caracteres).
- Validar con `password_verify($clave, $hash)` de PHP (compatible con crypt MD5).
- Solo usuarios con `Activo = 1`. Asistenciales: `UsuaAsis = 1`.
- `UsuaDigi` / `UsuaModi` de cada registro = login real del profesional.

## Fechas vacías

SIHOS usa `0000-00-00` y `00:00:00` como vacío en columnas `NOT NULL`. MySQL debe correr con
`sql_mode = 'NO_ENGINE_SUBSTITUTION'`.

## Uso real de las pestañas (muestra agosto 2026: 300 urgencias, 95 observación, 300 consulta externa)

| Tabla | Urgencias | Observación | Consulta ext. |
| --- | --- | --- | --- |
| SignVita | 99% | 100% | 68% |
| Triage | 99% | — | — |
| RipsCons | 83% | 100% | 68% |
| EstaGene | 83% | 100% | 68% |
| Antecede | 83% | 98% | 53% |
| EncaData/DetaData | 83% | 100% | — |
| EncaPres/DetaPres | 82% | 100% | 40% |
| SaliInte | 81% | 100% | — |
| HojaEnfe | 76% | 100% | 5% |
| EncaOrde/DetaOrde | 75% | 91% | 57% |
| HojaMedi | 73% | 100% | — |
| HojaMate | 69% | 88% | — |
| HojaProc | 55% | 72% | 32% |
| EvolInte | 45% | 97% | — |
| TrasCama | — | 100% | — |
| HojaLAdm (fase 2) | 63% | 97% | — |
| DetaPrue (fase 2) | 49% | 56% | 29% |
