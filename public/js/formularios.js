/*
 * CRADOR-HC - comportamiento de formularios (sin librerias externas).
 *
 * Marcado que entiende este script:
 *  <select data-depende="api.php?que=contratos" data-de="CodiAdmi" data-param="admi">
 *     recarga sus opciones cuando cambia el campo name="CodiAdmi".
 *     Varios campos: data-de="CodiAdmi,TipoAfil" data-param="admi,afil"; valores fijos en la URL.
 *  <input data-diagnostico>  busca diagnosticos CIE-10 mientras se escribe y
 *     muestra el nombre en el elemento #<id>-nombre.
 *  <input data-buscar="procedimientos|suministros">  igual, con CodiProc o CodiSumi.
 *  <div data-filas> con <template>: filas que se agregan y quitan (prescripcion, ordenes).
 *  <div data-mostrar-si="Campo=valor">: bloque visible solo si el campo tiene ese valor.
 *  <form data-signos>  calcula IMC y presion arterial media al escribir.
 *  <form data-una-vez>  deshabilita el boton al enviar (evita doble registro).
 */
(function () {
    'use strict';

    function campo(form, nombre) {
        return form.querySelector('[name="' + nombre + '"]');
    }

    // --- Listas dependientes -------------------------------------------------
    document.querySelectorAll('select[data-depende]').forEach(function (sel) {
        var form = sel.form;
        var de = sel.dataset.de.split(',');
        var params = sel.dataset.param.split(',');

        function recargar() {
            var url = sel.dataset.depende;
            var completo = true;
            de.forEach(function (n, i) {
                var v = campo(form, n) ? campo(form, n).value : '';
                if (!v) { completo = false; }
                url += '&' + params[i] + '=' + encodeURIComponent(v);
            });
            var anterior = sel.value;
            sel.innerHTML = '<option value="">— Seleccione —</option>';
            if (!completo) { sel.dispatchEvent(new Event('change')); return; }
            sel.disabled = true;
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (datos) {
                    if (datos.error) { alertaCampo(sel, datos.error); return; }
                    datos.forEach(function (o) {
                        var op = document.createElement('option');
                        op.value = o.c;
                        op.textContent = o.n;
                        if (o.c === anterior) { op.selected = true; }
                        sel.appendChild(op);
                    });
                    // Si solo hay una opcion, se escoge sola
                    if (datos.length === 1) { sel.value = datos[0].c; }
                })
                .catch(function () { alertaCampo(sel, 'No se pudo cargar la lista.'); })
                .finally(function () { sel.disabled = false; sel.dispatchEvent(new Event('change')); });
        }

        de.forEach(function (n) {
            var c = campo(form, n);
            if (c) { c.addEventListener('change', recargar); }
        });
    });

    function alertaCampo(el, texto) {
        var div = document.createElement('div');
        div.className = 'error-campo';
        div.textContent = texto;
        el.insertAdjacentElement('afterend', div);
    }

    // --- Buscadores con catalogo (CIE-10, procedimientos, suministros): autocompletar propio ----------
    // <input data-diagnostico> o <input data-buscar="procedimientos|suministros|diagnosticos">.
    // Al escribir (desde 2 caracteres, espera de 250 ms) se despliega una lista bajo el campo con "CODIGO · Nombre";
    // busca por codigo o por nombre. Flechas/Enter/Esc o mouse/touch. Al escoger, el campo queda con el CODIGO y el
    // nombre se muestra en #<id>-nombre, en la nota del campo o, en una rejilla, en la celda .nota-campo de la fila.
    // Si se escribe un codigo exacto valido tambien lo toma. El servidor vuelve a validar con la misma regla.
    var contador = 0;
    var abierta = null;   // lista abierta (solo una a la vez)
    function activarBuscador(inp) {
        if (inp.dataset.activo) { return; }
        inp.dataset.activo = '1';
        inp.removeAttribute('list');
        var que = inp.dataset.buscar || 'diagnosticos';
        var id = 'ac-' + (++contador);
        var caja = document.createElement('ul');
        caja.className = 'ac-lista';
        caja.id = id;
        caja.setAttribute('role', 'listbox');
        caja.hidden = true;
        document.body.appendChild(caja);
        inp.setAttribute('role', 'combobox');
        inp.setAttribute('aria-autocomplete', 'list');
        inp.setAttribute('aria-expanded', 'false');
        inp.setAttribute('aria-controls', id);
        inp.setAttribute('autocomplete', 'off');
        var nombre = (inp.id && document.getElementById(inp.id + '-nombre')) || inp.parentNode.querySelector('.nota-campo')
                  || (inp.closest('tr') && inp.closest('tr').querySelector('.nota-campo'));
        var espera = null, datos = [], activo = -1, pedido = 0;

        function ponerNombre(t) { if (nombre) { nombre.textContent = t || ''; } }
        function colocar() {
            var r = inp.getBoundingClientRect();
            caja.style.left = (r.left + window.scrollX) + 'px';
            caja.style.top = (r.bottom + window.scrollY + 2) + 'px';
            caja.style.minWidth = Math.max(r.width, Math.min(420, window.innerWidth - 24)) + 'px';
            var sobra = window.innerWidth - (r.left + caja.offsetWidth) - 12;
            if (sobra < 0) { caja.style.left = Math.max(12, r.left + window.scrollX + sobra) + 'px'; }
        }
        function cerrar() {
            caja.hidden = true; activo = -1;
            inp.setAttribute('aria-expanded', 'false'); inp.removeAttribute('aria-activedescendant');
            if (abierta === cerrar) { abierta = null; }
        }
        function marcar(i) {
            var items = caja.children;
            if (!items.length) { return; }
            activo = (i + items.length) % items.length;
            Array.prototype.forEach.call(items, function (li, k) { li.classList.toggle('activo', k === activo); li.setAttribute('aria-selected', k === activo ? 'true' : 'false'); });
            inp.setAttribute('aria-activedescendant', items[activo].id);
            items[activo].scrollIntoView({ block: 'nearest' });
        }
        function escoger(i) {
            var d = datos[i];
            if (!d) { return; }
            inp.value = d.c;
            ponerNombre(d.n);
            cerrar();
            inp.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function pintar(q) {
            caja.innerHTML = '';
            activo = -1;
            if (!datos.length) {
                var li = document.createElement('li');
                li.className = 'ac-vacio'; li.textContent = 'Sin resultados para "' + q + '"';
                caja.appendChild(li);
            }
            datos.forEach(function (d, i) {
                var li = document.createElement('li');
                li.id = id + '-' + i;
                li.setAttribute('role', 'option');
                var c = document.createElement('strong'); c.textContent = d.c;
                var n = document.createElement('span'); n.textContent = d.n;
                li.appendChild(c); li.appendChild(document.createTextNode(' · ')); li.appendChild(n);
                // mousedown/touchstart: se escoge antes de que el campo pierda el foco
                li.addEventListener('mousedown', function (ev) { ev.preventDefault(); escoger(i); });
                li.addEventListener('touchstart', function (ev) { ev.preventDefault(); escoger(i); }, { passive: false });
                caja.appendChild(li);
            });
            if (abierta && abierta !== cerrar) { abierta(); }
            abierta = cerrar;
            caja.hidden = false;
            inp.setAttribute('aria-expanded', 'true');
            colocar();
        }
        function buscar() {
            var q = inp.value.trim();
            if (q.length < 2) { datos = []; cerrar(); return; }
            var n = ++pedido;
            fetch('api.php?que=' + que + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (n !== pedido || document.activeElement !== inp) { return; }
                    datos = Array.isArray(res) ? res : [];
                    // Codigo exacto valido: se toma aunque no se escoja de la lista
                    var exacto = datos.filter(function (d) { return d.c === q.toUpperCase(); })[0];
                    ponerNombre(exacto ? exacto.n : '');
                    pintar(q);
                    if (exacto) { marcar(datos.indexOf(exacto)); }
                })
                .catch(function () { cerrar(); });
        }
        inp.addEventListener('input', function () {
            if (que === 'diagnosticos') {
                var p = inp.selectionStart; inp.value = inp.value.toUpperCase(); try { inp.setSelectionRange(p, p); } catch (e) {}
            }
            ponerNombre('');
            clearTimeout(espera);
            espera = setTimeout(buscar, 250);
        });
        inp.addEventListener('keydown', function (ev) {
            if (caja.hidden) {
                if (ev.key === 'ArrowDown' && inp.value.trim().length >= 2) { ev.preventDefault(); buscar(); }
                return;
            }
            if (ev.key === 'ArrowDown') { ev.preventDefault(); marcar(activo + 1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); marcar(activo - 1); }
            else if (ev.key === 'Enter') { ev.preventDefault(); escoger(activo >= 0 ? activo : 0); }
            else if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); cerrar(); }
            else if (ev.key === 'Tab') { if (activo >= 0) { escoger(activo); } else { cerrar(); } }
        });
        inp.addEventListener('blur', function () {
            setTimeout(function () {
                cerrar();
                // Si quedo escrito un codigo que esta en la lista, se toma con su nombre
                var v = inp.value.trim().toUpperCase();
                var d = datos.filter(function (x) { return x.c === v; })[0];
                if (d) { inp.value = d.c; ponerNombre(d.n); }
            }, 150);
        });
        window.addEventListener('resize', function () { if (!caja.hidden) { colocar(); } });
        window.addEventListener('scroll', function () { if (!caja.hidden) { colocar(); } }, true);
    }
    document.querySelectorAll('input[data-diagnostico], input[data-buscar]').forEach(activarBuscador);

    // --- Filas que se agregan y quitan (medicamentos, procedimientos ordenados) --
    // <div data-filas> con un <template> de la fila; boton [data-agregar-fila] y [data-quitar-fila] en cada fila.
    document.querySelectorAll('[data-filas]').forEach(function (caja) {
        var plantilla = caja.querySelector('template');
        var cuerpo = caja.querySelector('[data-filas-cuerpo]');
        function numerar() {
            cuerpo.querySelectorAll('[data-fila-numero]').forEach(function (n, i) { n.textContent = i + 1; });
        }
        caja.querySelector('[data-agregar-fila]').addEventListener('click', function () {
            var fila = plantilla.content.firstElementChild.cloneNode(true);
            cuerpo.appendChild(fila);
            fila.querySelectorAll('input[data-buscar]').forEach(activarBuscador);
            numerar();
            var primero = fila.querySelector('input, select');
            if (primero) { primero.focus(); }
        });
        cuerpo.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-quitar-fila]');
            if (!b) { return; }
            if (cuerpo.children.length > 1) { b.closest('[data-fila]').remove(); numerar(); }
        });
    });

    // --- Campos que se muestran segun una casilla o lista ---------------------
    // <div data-mostrar-si="EstaSali=2"> se ve solo si el campo EstaSali vale 2.
    document.querySelectorAll('[data-mostrar-si]').forEach(function (bloque) {
        var partes = bloque.dataset.mostrarSi.split('=');
        var form = bloque.closest('form');
        var c = form && form.querySelector('[name="' + partes[0] + '"]');
        if (!c) { return; }
        function revisar() { bloque.hidden = String(c.value) !== partes[1]; }
        c.addEventListener('change', revisar);
        revisar();
    });

    // --- IMC y presion arterial media ----------------------------------------
    document.querySelectorAll('form[data-signos]').forEach(function (form) {
        function num(n) {
            var c = campo(form, n);
            return c ? parseFloat(String(c.value).replace(',', '.')) : NaN;
        }
        function calcular() {
            // Resultado dentro del mismo formulario (puede haber dos formularios de signos en la historia)
            var imc = form.querySelector('[data-calc="imc"]') || document.getElementById('calc-imc');
            var tm = form.querySelector('[data-calc="tm"]') || document.getElementById('calc-tm');
            var peso = num('Peso'), talla = num('Talla'), pas = num('PANume'), pad = num('PADeno');
            if (imc) { imc.textContent = (peso > 0 && talla > 0) ? (peso / Math.pow(talla / 100, 2)).toFixed(2) : '—'; }
            if (tm) { tm.textContent = (pas > 0 && pad > 0) ? Math.round((pas + 2 * pad) / 3) : '—'; }
        }
        form.addEventListener('input', calcular);
        calcular();
    });

    // --- Evitar doble envio --------------------------------------------------
    document.querySelectorAll('form[data-una-vez]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            // El boton pulsado puede llevar un valor (p. ej. name="boton" value="cerrar"): un boton deshabilitado
            // no se envia, asi que su valor se copia en un campo oculto antes de deshabilitarlo
            var b0 = ev.submitter;
            if (b0 && b0.name) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = b0.name; h.value = b0.value;
                form.appendChild(h);
            }
            form.querySelectorAll('button[type="submit"]').forEach(function (b) {
                b.disabled = true;
                b.textContent = 'Guardando…';
            });
        });
    });

    // Mensajes de SIHOS en la validacion del navegador: data-mensaje (campo vacio) y data-mensaje-max (tope).
    document.addEventListener('invalid', function (ev) {
        var c = ev.target;
        if (!c.dataset) return;
        if (c.validity.valueMissing && c.dataset.mensaje) c.setCustomValidity(c.dataset.mensaje);
        else if (c.validity.rangeOverflow && c.dataset.mensajeMax) c.setCustomValidity(c.dataset.mensajeMax);
    }, true);
    document.addEventListener('input', function (ev) {
        if (ev.target.setCustomValidity) ev.target.setCustomValidity('');
    }, true);
})();
