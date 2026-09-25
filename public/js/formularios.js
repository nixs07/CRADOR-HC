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

    // --- Buscadores con catalogo (CIE-10, procedimientos, suministros) ------
    // <input data-diagnostico> o <input data-buscar="procedimientos|suministros|diagnosticos">.
    // El nombre del codigo se muestra en #<id>-nombre o en el .nota-campo de la misma celda.
    var contador = 0;
    function activarBuscador(inp) {
        if (inp.dataset.activo) { return; }
        inp.dataset.activo = '1';
        var que = inp.dataset.buscar || 'diagnosticos';
        var lista = document.createElement('datalist');
        lista.id = 'lista-buscar-' + (++contador);
        inp.setAttribute('list', lista.id);
        inp.insertAdjacentElement('afterend', lista);
        var nombre = (inp.id && document.getElementById(inp.id + '-nombre')) || inp.parentNode.querySelector('.nota-campo');
        var espera = null;
        var ultimos = {};

        inp.addEventListener('input', function () {
            if (que === 'diagnosticos') { inp.value = inp.value.toUpperCase(); }
            var q = inp.value.split(' ')[0];
            if (nombre) { nombre.textContent = ultimos[q] || ''; }
            clearTimeout(espera);
            if (inp.value.length < 2) { return; }
            espera = setTimeout(function () {
                fetch('api.php?que=' + que + '&q=' + encodeURIComponent(inp.value), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (datos) {
                        lista.innerHTML = '';
                        (Array.isArray(datos) ? datos : []).forEach(function (d) {
                            ultimos[d.c] = d.n;
                            var op = document.createElement('option');
                            op.value = d.c;
                            op.label = d.n;
                            op.textContent = d.c + ' · ' + d.n;
                            lista.appendChild(op);
                        });
                        if (nombre) { nombre.textContent = ultimos[inp.value] || ''; }
                    });
            }, 250);
        });
        inp.addEventListener('change', function () {
            if (nombre) { nombre.textContent = ultimos[inp.value] || nombre.textContent; }
        });
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
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (b) {
                b.disabled = true;
                b.textContent = 'Guardando…';
            });
        });
    });
})();
