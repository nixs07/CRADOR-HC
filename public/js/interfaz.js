/*
 * CRADOR-HC - comportamiento general de la interfaz (sin librerias externas).
 *  1. Menu lateral en celular y tableta (cajon que se abre y se cierra).
 *  2. Filas de tabla que abren la historia: <tr data-href="admision.php?id=...">.
 *  3. Pestanas de la historia: <nav data-pestanas> con enlaces ?tab= y paneles data-panel.
 *  4. Menu lateral contraido en PC (se recuerda en este equipo).
 *  5. Ventana "Historias abiertas": [data-abrir-ventana="historias"] y [data-cerrar-ventana].
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

        var recordarPestana = function (id) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', id);
            url.hash = '';
            history.replaceState(null, '', url.toString());
        };
        var mostrar = function (id) {
            if (!panel(id)) { return false; }
            // "Continuar" lleva a la siguiente pestana disponible
            var c = document.querySelector('[data-continuar]');
            if (c) {
                var ids = enlaces.map(function (x) { return x.dataset.tab; });
                var sig = ids[ids.indexOf(id) + 1];
                c.hidden = !sig;
                if (sig) {
                    c.dataset.tab = sig;
                    var u = new URL(c.href, window.location.href);
                    u.searchParams.set('tab', sig);
                    c.href = u.toString();
                }
            }
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
                recordarPestana(a.dataset.tab);
            });
        });
        // Boton "Continuar" del pie: pasa a la siguiente pestana
        var continuar = document.querySelector('[data-continuar]');
        if (continuar) {
            continuar.addEventListener('click', function (ev) {
                if (mostrar(continuar.dataset.tab)) {
                    ev.preventDefault();
                    recordarPestana(continuar.dataset.tab);
                    barra.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            });
        }
        // La pestana activa queda a la vista dentro de la barra (se desplaza de lado si hace falta)
        var actual = barra.querySelector('.actual');
        if (actual) {
            var rb = barra.getBoundingClientRect(), ra = actual.getBoundingClientRect();
            barra.scrollLeft += (ra.left - rb.left) - (rb.width - ra.width) / 2;
        }
        // Enlaces viejos con #triage o #signos
        if (window.location.hash) { mostrar(window.location.hash.slice(1)); }
    }

    // --- 4. Menu lateral contraido en PC ------------------------------------
    var colapsar = document.querySelector('[data-menu-colapsar]');
    if (colapsar) {
        colapsar.setAttribute('aria-expanded', cuerpo.classList.contains('menu-colapsado') ? 'false' : 'true');
        colapsar.addEventListener('click', function () {
            var c = cuerpo.classList.toggle('menu-colapsado');
            colapsar.setAttribute('aria-expanded', c ? 'false' : 'true');
            try { localStorage.setItem('menuColapsado', c ? '1' : '0'); } catch (e) {}
        });
    }

    // --- 5. Ventana "Historias abiertas" -------------------------------------
    var ventana = document.getElementById('historias');
    if (ventana) {
        var origen = null;
        var abrirVentana = function () {
            origen = document.activeElement;
            ventana.hidden = false;
            cuerpo.classList.add('con-ventana');
            if (cuerpo.classList.contains('menu-abierto') && abrir) { cambiarMenu(false); }
            var f = ventana.querySelector('input[name="q"]');
            if (f) { f.focus(); }
        };
        var cerrarVentana = function () {
            ventana.hidden = true;
            cuerpo.classList.remove('con-ventana');
            var url = new URL(window.location.href);
            if (url.searchParams.has('historias')) {
                url.searchParams.delete('historias');
                if (!url.searchParams.has('id')) { url.searchParams.set('nueva', '1'); }
                history.replaceState(null, '', url.toString());
            }
            if (origen && origen.focus) { origen.focus(); }
        };
        if (!ventana.hidden) { cuerpo.classList.add('con-ventana'); }
        document.querySelectorAll('[data-abrir-ventana="historias"]').forEach(function (b) {
            b.addEventListener('click', function (ev) { ev.preventDefault(); abrirVentana(); });
        });
        ventana.querySelectorAll('[data-cerrar-ventana]').forEach(function (b) {
            b.addEventListener('click', function (ev) { ev.preventDefault(); cerrarVentana(); });
        });
        ventana.addEventListener('click', function (ev) { if (ev.target === ventana) { cerrarVentana(); } });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !ventana.hidden) { cerrarVentana(); }
        });
    }

    // --- 6. Otras ventanas (p. ej. "Cerrar Historia" de Consulta Externa) ---
    document.querySelectorAll('.ventana:not(#historias)').forEach(function (v) {
        var cerrar = function () {
            v.hidden = true;
            cuerpo.classList.remove('con-ventana');
            var url = new URL(window.location.href);
            if (url.searchParams.has('cerrar')) { url.searchParams.delete('cerrar'); history.replaceState(null, '', url.toString()); }
        };
        if (!v.hidden) { cuerpo.classList.add('con-ventana'); }
        document.querySelectorAll('[data-abrir-ventana="' + v.id + '"]').forEach(function (b) {
            b.addEventListener('click', function (ev) {
                ev.preventDefault();
                v.hidden = false;
                cuerpo.classList.add('con-ventana');
                var f = v.querySelector('input, select, textarea, button');
                if (f) { f.focus(); }
            });
        });
        v.querySelectorAll('[data-cerrar-ventana]').forEach(function (b) {
            b.addEventListener('click', function (ev) { ev.preventDefault(); cerrar(); });
        });
        v.addEventListener('click', function (ev) { if (ev.target === v) { cerrar(); } });
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !v.hidden) { cerrar(); } });
    });

    // --- 7. Barra de la pestana: ir a un registro anterior --------------------
    // <select data-ir-registro> con el id del registro: abre su pestana, lo despliega y lo muestra.
    document.querySelectorAll('select[data-ir-registro]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var el = sel.value && document.getElementById(sel.value);
            if (!el) { return; }
            var p = el.closest('[data-panel]');
            if (p && p.hidden) {
                var a = document.querySelector('[data-pestanas] a[data-tab="' + p.dataset.panel + '"]');
                if (a) { a.click(); }
            }
            if (el.tagName === 'DETAILS') { el.open = true; }
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            el.classList.add('resaltado');
            setTimeout(function () { el.classList.remove('resaltado'); }, 2000);
            sel.value = '';
        });
    });

    // --- 8. Incapacidad: fecha final = fecha inicial + dias - 1 ---------------
    document.querySelectorAll('[data-fecha-final]').forEach(function (dias) {
        var form = dias.form;
        var ini = form && form.querySelector('[name="' + dias.dataset.fechaFinal + '"]');
        var out = form && form.querySelector('[data-calc-final]');
        if (!ini || !out) { return; }
        var calcular = function () {
            var n = parseInt(dias.value, 10), f = ini.value ? new Date(ini.value + 'T00:00:00') : null;
            if (!f || !(n > 0)) { out.textContent = '—'; return; }
            f.setDate(f.getDate() + n - 1);
            out.textContent = ('0' + f.getDate()).slice(-2) + '/' + ('0' + (f.getMonth() + 1)).slice(-2) + '/' + f.getFullYear();
        };
        dias.addEventListener('input', calcular);
        ini.addEventListener('input', calcular);
        calcular();
    });
})();
