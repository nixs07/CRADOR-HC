/*
 * CRADOR-HC - menu lateral en celular y tableta (cajon que se abre y se cierra).
 * Sin librerias externas. En pantallas anchas el menu siempre esta visible y este
 * script no hace nada.
 */
(function () {
    'use strict';

    var cuerpo = document.body;
    var menu = document.getElementById('menu-lateral');
    var abrir = document.querySelector('[data-menu-abrir]');
    var velo = document.querySelector('.velo');
    if (!menu || !abrir) { return; }

    function cambiar(abierto) {
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

    abrir.addEventListener('click', function () { cambiar(true); });
    document.querySelectorAll('[data-menu-cerrar]').forEach(function (el) {
        el.addEventListener('click', function () { cambiar(false); });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && cuerpo.classList.contains('menu-abierto')) { cambiar(false); }
    });
})();
