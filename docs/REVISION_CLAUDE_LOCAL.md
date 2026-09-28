# Revisión del Claude local (PC del hospital, con acceso a SIHOS real)

Canal de trabajo: la nube programa en `fase-2` y deja `docs/ENTREGA.md`; el Claude local prueba en
`C:\CRADOR-HC` con datos reales, compara con SIHOS y escribe aquí. **Lo de este archivo manda.**
Lo más reciente va arriba.

---

## 28/09/2026 (2) — Respuestas a las 7 dudas de docs/ENTREGA.md (verificado en SIHOS producción)

1. **Método de planificación** = catálogo **`CodiMePl`** (`CodiMePl int`, `NombMePl` + auditoría). Códigos reales:
   0 Otro Metodo · 1 DIU COBRE · 2 Hormonales · 3 Condon · 4 Pomeroy · 5 Sin Metodo · 6 Natural · 7 Lactancia ·
   8 DIU post parto cesarea · 9 Complicacion o cambio · 10 Vasectom · 11 Ovulos · 12 Dispositivo Intrauterino y Barrera ·
   13 Implante Subdermico · 14 Implante Subdermico y Barrera · 15 Oral · 16 Oral y Barrera · 17 Inyectable Mensual ·
   18 Inyectable Mensual y Barrera · 19 Inyectable Trimestral · 20 Inyectable Trimestral y Barrera · 21 Emergencia ·
   22 Emergencia y Barrera · 23 Esterilizacion · 24 Esterilizacion y Barrera · 25 Barrera · 26 Abstinencia Periodica ·
   27 Anillo Vaginal · 28 Coito Interrumpido · 29 Diafragma (con espermicida) · 30 Parche transdermico ·
   31 D. Intrauterino levonorgestrel. `Antecede.MetoDesc` guarda este código (0 = sin dato/otro; los más usados: 5, 4, 13, 3, 21).
   **Reemplazar la lista provisional 1–14 y copiar `CodiMePl` como catálogo.**
2. **`HoraApli`** real: `CodiHora int(2)` PK, `NombHora varchar(10)`, + FechDigi/HoraDigi/UsuaDigi/FechModi/HoraModi/UsuaModi.
   Filas: 0 "AHORA", 1 "1 HORA", 2…24 "N HORAS". Copiarla como catálogo (reemplaza la provisional).
3. **Hospitalaria vs. salida** (Urg/Obs, septiembre, 15.359 ítems):
   - `PresSali = 1` (hospitalaria): usa **Cada** (`HoraApli`) y **nunca** frecuencia/duración (`CantFrec = TiemFrec = CantPeDu = TiemPeDu = 0`).
   - `PresSali = 2` (fórmula de salida): usa **frecuencia y duración** (como la Prescripción A) en el 100 % y `HoraApli = 0`.
   → La fórmula de salida de Urgencias/Observación debe mostrar la rejilla ambulatoria (frecuencia/duración), no la de "Cada".
4. **Redondeo cuando no divide exacto: redondeo normal (0,5 hacia arriba), no hacia arriba siempre.**
   Hospitalaria (24 / horas): 5 h → 5 (4,8) · 7 h → 3 (3,43) · 9 h → 3 (2,67) · 10 h → 2 (2,4).
   Ambulatoria (duración / frecuencia): 48 h × 5 d → 3 (2,5) · 48 h × 7 d → 4 (3,5) · 5 h × 7 d → 34 (33,6) ·
   27 h × 7 d → 6 (6,2) · 25 h × 90 d → 86 (86,4). `NumeDosi = ROUND(...)`, editable. (Las exactas: 1 h → 24, 4 → 6, 6 → 4, 8 → 3, 12 → 2, 24 → 1.)
5. **Medicamento sin contenido:** no ocurre en SIHOS (0 de 15.359 ítems con `Contenid` = 0). Si llegara a pasar, dejar
   Cantidad solicitada = número de dosis y editable. `CantSoli = CEIL(CantTota / Contenid)` se cumple en el 99,9 % de
   la hospitalaria; en la ambulatoria/salida ~75 % (el médico la ajusta: frascos, blísteres) → siempre editable.
6. **Mensajes exactos:** los de `VALIDACIONES_SIHOS.md` §2 son textuales de SIHOS. Prescripción hospitalaria además:
   "Debe digitar la cantidad", "Debe suministrar una cantidad de aplicación valida", "Debe suministrar un numero de dosis
   valido", "La via de los Suministros no puede estar vacia", "Debe indicar el tiempo de Aplicacion del Suministro",
   "Debe Diligenciar El Campo Nota", "Debe formular al menos un medicamento", "El Suministro ya esta Cargado en la
   Prescripcion Actual", "No es posible prescribir para mas de 24 Horas".
7. **Diagnóstico de ingreso (`Admision.DiagIngr`):**
   - **Consulta Externa: sí**, se llena con el diagnóstico principal de la primera consulta (787 de 796 admisiones = 99 %).
   - **Urgencias:** coincide con el de la primera consulta en el 79 % y con el del triage en el 62 %. Regla: al guardar el
     **triage** llenar `DiagIngr` con su diagnóstico si está vacío, y al guardar la **primera consulta** reemplazarlo por el
     diagnóstico principal de la consulta.

---

## 28/09/2026 — Verificación de los 8 puntos que el usuario le pasó a la nube

**Punto 3 — Diagnóstico según la consulta o el procedimiento.** Confirmado en SIHOS: en **Prescripción, Prescripción A
y Ordenación** el **DXP es una lista (select)** con los diagnósticos ya registrados en la(s) consulta(s) de la admisión
(principal y relacionados); no se digita. DXR 1–4 se digitan con búsqueda. Si no hay consulta, la lista queda vacía
("Seleccione un diagnóstico") y no deja guardar ("Debe seleccionar diagnóstico, es obligatorio según la normativa 2275").
En **Procedimientos** el diagnóstico principal se busca (código + nombre), por defecto el de la consulta.

**Punto 4 — Antecedentes: habilitar el texto solo con "Si".** El usuario lo ve así en SIHOS: con **Si** se habilita la
descripción (y los campos adicionales: parentesco, tipo de alergia, etc.); con **No / No Sabe / No Corresponde** el texto
queda deshabilitado. Aplicarlo en TODOS los antecedentes.
Ojo (datos reales, septiembre): con "No" hay texto en ~33 % de Patológicos y Quirúrgicos ("NIEGA", "NIEGA LA MADRE",
"NO REFIERE"); probablemente vienen copiados de atenciones anteriores. Con "No" **no borrar** un texto que ya exista;
solo no pedirlo. Con "Si" la descripción es obligatoria.

**Punto 5 — Mensaje de guardado.** Pedido del usuario: el aviso "guardado / no se guardó" debe verse **abajo, junto al
botón Continuar/Siguiente**, sin subir la página.

**Punto 6 — Quedarse en la misma sección al guardar.** Confirmado: en SIHOS cada sección tiene su Guardar y la pantalla
no se mueve. Al guardar Antecedentes, Revisión, Diagnósticos o Plan, quedarse en esa misma sección (no volver a Anamnesis).

**Punto 7 — Cálculo de medicamentos igual a SIHOS** (verificado con prescripciones reales del 27/09):
- Parametrización del medicamento en `CodiSumi`: `Contenid` (contenido de la unidad mayor en unidades menores),
  `UnidMedi` (unidad de aplicación), `ViaAdmin` (vía por defecto), `ConcMedi`, `Posologi`. Al escoger el medicamento se
  llenan unidad y vía; `DetaPres.Contenid` = el de `CodiSumi`.
- **Hospitalaria (Urg/Obs):** "Cada" = `HoraApli` (tabla `HoraApli`: 0 = AHORA, 1…24 = horas). Máximo 24 horas
  ("No es posible prescribir para mas de 24 Horas"). `NumeDosi` = 24 / horas (AHORA = 1), editable.
  `CantTota` = `CantSumi` (dosis) × `NumeDosi`. `CantSoli` (a farmacia, unidades mayores) = redondeo hacia arriba de
  `CantTota` / `Contenid`. Ej.: 75 mg cada 12 h → 2 dosis → 150 mg → 2 unidades.
- **Ambulatoria (Prescripción A / fórmula):** `CantFrec` + `TiemFrec` (1 h, 2 días, 3 meses) y `CantPeDu` + `TiemPeDu`.
  `NumeDosi` = duración en horas / frecuencia en horas. Ejemplos reales: cada 12 h por 5 días → 10; cada 8 h por 3 días → 9;
  cada 6 h por 3 días → 12. `CantTota` = dosis × NumeDosi (1000 mg × 12 = 12000). `CantSoli` = redondeo arriba de
  CantTota / Contenid (12000 / 500 = 24). Todo editable (en jarabes el médico pide 1 frasco).
- Pedido del usuario: el botón/campo de **dosis más grande**.

**Punto 8 — Buscar historias por admisión o paciente.** En SIHOS, en el encabezado se escribe el **número de admisión**
(Enter) o el **documento** + **Buscar**, y abre cualquier historia, **abierta o cerrada**; "Historias abiertas" solo
lista las abiertas. Hacer lo mismo en CRADOR (buscar por admisión o por documento/nombre, incluidas las cerradas).
Los registros se guardan **solo en la base** `crador_hc` (no hay archivos aparte).

**Puntos 1 y 2:** pendiente que el usuario aclare qué no se autorellena y qué sobra en el encabezado.

**Nota de instalación:** el PC del hospital ya corre la app fija en `C:\CRADOR-HC` (rama `fase-2`, base `crador_hc`,
catálogos reales). Después de cada entrega la actualizo y reviso.
