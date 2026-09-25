/*
 * CRADOR-HC - comportamiento general de la interfaz (sin librerias externas).
 *  1. Menu lateral en celular y tableta (cajon que se abre y se cierra).
 *  2. Filas de tabla que abren la historia: <tr data-href="admision.php?id=...">.
 *  3. Pestanas de la historia: <nav data-pestanas> con enlaces a #seccion.
 *     Sin JavaScript todas las secciones se ven una debajo de otra.
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
    var barra = document.querySelector('[data-pestanas]');
    if (barra) {
        var enlaces = Array.prototype.slice.call(barra.querySelectorAll('a[href^="#"]'));
        var secciones = enlaces.map(function (a) { return document.getElementById(a.getAttribute('href').slice(1)); });

        var mostrar = function (id, enfocar) {
            var existe = secciones.some(function (s) { return s && s.id === id; });
            if (!existe) { id = barra.dataset.inicial || (secciones[0] && secciones[0].id); }
            enlaces.forEach(function (a, i) {
                var activa = secciones[i] && secciones[i].id === id;
                a.classList.toggle('actual', activa);
                a.setAttribute('aria-selected', activa ? 'true' : 'false');
                if (secciones[i]) { secciones[i].hidden = !activa; }
            });
            if (enfocar) { barra.scrollIntoView({ block: 'nearest' }); }
        };

        barra.setAttribute('role', 'tablist');
        enlaces.forEach(function (a) {
            a.setAttribute('role', 'tab');
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                var id = a.getAttribute('href').slice(1);
                history.replaceState(null, '', '#' + id);
                mostrar(id, false);
            });
        });
        mostrar(window.location.hash.slice(1), true);
        window.addEventListener('hashchange', function () { mostrar(window.location.hash.slice(1), true); });
    }
})();
