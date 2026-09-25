/*
 * CRADOR-HC - comportamiento de formularios (sin librerias externas).
 *
 * Marcado que entiende este script:
 *  <select data-depende="api.php?que=contratos" data-de="CodiAdmi" data-param="admi">
 *     recarga sus opciones cuando cambia el campo name="CodiAdmi".
 *     Varios campos: data-de="CodiAdmi,TipoAfil" data-param="admi,afil"; valores fijos en la URL.
 *  <input data-diagnostico>  busca diagnosticos CIE-10 mientras se escribe y
 *     muestra el nombre en el elemento #<id>-nombre.
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

    // --- Busqueda de diagnosticos -------------------------------------------
    document.querySelectorAll('input[data-diagnostico]').forEach(function (inp) {
        var lista = document.createElement('datalist');
        lista.id = inp.id + '-lista';
        inp.setAttribute('list', lista.id);
        inp.insertAdjacentElement('afterend', lista);
        var nombre = document.getElementById(inp.id + '-nombre');
        var espera = null;
        var ultimos = {};

        inp.addEventListener('input', function () {
            inp.value = inp.value.toUpperCase();
            var q = inp.value.split(' ')[0];
            if (nombre) { nombre.textContent = ultimos[q] || ''; }
            clearTimeout(espera);
            if (inp.value.length < 2) { return; }
            espera = setTimeout(function () {
                fetch('api.php?que=diagnosticos&q=' + encodeURIComponent(inp.value), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (datos) {
                        lista.innerHTML = '';
                        (datos || []).forEach(function (d) {
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
            if (nombre) { nombre.textContent = ultimos[inp.value] || ''; }
        });
    });

    // --- IMC y presion arterial media ----------------------------------------
    document.querySelectorAll('form[data-signos]').forEach(function (form) {
        function num(n) {
            var c = campo(form, n);
            return c ? parseFloat(String(c.value).replace(',', '.')) : NaN;
        }
        function calcular() {
            var imc = document.getElementById('calc-imc');
            var tm = document.getElementById('calc-tm');
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
