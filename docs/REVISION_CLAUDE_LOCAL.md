# Revisión del Claude local (PC del hospital, con acceso a SIHOS real)

Canal de trabajo: la nube programa en `fase-2` y deja `docs/ENTREGA.md`; el Claude local prueba en
`C:\CRADOR-HC` con datos reales, compara con SIHOS y escribe aquí. **Lo de este archivo manda.**
Lo más reciente va arriba.

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
