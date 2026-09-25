/*
 * CRADOR-HC - comportamiento general de la interfaz (sin librerias externas).
 *  1. Menu lateral en celular y tableta (cajon que se abre y se cierra).
 *  2. Filas de tabla que abren la historia: <tr data-href="admision.php?id=...">.
 *  3. Pestanas de la historia: <nav data-pestanas> con enlaces ?tab= y paneles data-panel.
 */
(function () {
    'use strict';

    // --- 1. Menu lateral -----------------------------------------------------
    var cuerpo = document.body;
    var menu = document.getElementById('menu-lateral');
    var abrir = document.querySelector('[data-menu-abrir]');
    var velo = document.querySelector('.velo');

    function cambiarMenu(abierto) {
        cuerpo.classList.toggle('menu-abierto', abierto);
        abrir.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        if (velo) { velo.hidden = !abierto; }
        if (abierto) {
            var primero = menu.querySelector('a, button');
            if (primero) { primero.focus(); }
        } else {
            abrir.focus();
        }
    }

    if (menu && abrir) {
        abrir.addEventListener('click', function () { cambiarMenu(true); });
        document.querySelectorAll('[data-menu-cerrar]').forEach(function (el) {
            el.addEventListener('click', function () { cambiarMenu(false); });
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && cuerpo.classList.contains('menu-abierto')) { cambiarMenu(false); }
        });
    }

    // --- 2. Filas que abren la historia --------------------------------------
    document.querySelectorAll('tr[data-href]').forEach(function (fila) {
        fila.addEventListener('click', function (ev) {
            // Si el clic fue en un enlace o boton de la fila, se respeta ese enlace
            if (ev.target.closest('a, button, input, select')) { return; }
            window.location.href = fila.dataset.href;
        });
    });

    // --- 3. Pestanas de la historia ------------------------------------------
    // Cada pestana es <a href="admision.php?id=..&tab=X" data-tab="X"> y su panel <section data-panel="X">.
    // Sin JavaScript el enlace recarga la pagina con ?tab=X; con JavaScript solo se cambia el panel
    // y se actualiza la direccion, para que al recargar se quede en la misma pestana.
    var barra = document.querySelector('[data-pestanas]');
    if (barra) {
        var enlaces = Array.prototype.slice.call(barra.querySelectorAll('a[data-tab]'));
        var panel = function (id) { return document.querySelector('[data-panel="' + id + '"]'); };

        var mostrar = function (id) {
            if (!panel(id)) { return false; }
            enlaces.forEach(function (a) {
                var activa = a.dataset.tab === id;
                a.classList.toggle('actual', activa);
                a.setAttribute('aria-selected', activa ? 'true' : 'false');
                var p = panel(a.dataset.tab);
                if (p) { p.hidden = !activa; }
            });
            return true;
        };

        barra.setAttribute('role', 'tablist');
        enlaces.forEach(function (a) {
            a.setAttribute('role', 'tab');
            a.addEventListener('click', function (ev) {
                if (!mostrar(a.dataset.tab)) { return; }
                ev.preventDefault();
                var url = new URL(window.location.href);
                url.searchParams.set('tab', a.dataset.tab);
                url.hash = '';
                history.replaceState(null, '', url.toString());
            });
        });
        // Enlaces viejos con #triage o #signos
        if (window.location.hash) { mostrar(window.location.hash.slice(1)); }
    }
})();
