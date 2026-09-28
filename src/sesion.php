<?php
/**
 * Sesion, ingreso (login), roles y proteccion CSRF.
 *
 * Los usuarios son los mismos de SIHOS: tabla Usuarios LOCAL (copiada de SIHOS
 * con "Actualizar catalogos"). La clave es MD5-crypt ($1$...), que PHP valida
 * con password_verify().
 */

const INTENTOS_MAXIMOS = 5;      // intentos fallidos permitidos por login...
const INTENTOS_MINUTOS = 15;     // ...en esta ventana de minutos

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('CRADORHC');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    // Cierre por inactividad
    $limite = (int) config('SESION_MINUTOS', '480') * 60;
    if (isset($_SESSION['usuario'], $_SESSION['ultimo_uso']) && (time() - $_SESSION['ultimo_uso']) > $limite) {
        cerrar_sesion();
        session_start();
        flash('aviso', 'La sesión se cerró por inactividad. Ingrese de nuevo.');
    }
    $_SESSION['ultimo_uso'] = time();
}

/** Usuario en sesion: ['Login','Nombre','CodiEspe','admin'] o null. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function es_admin(): bool
{
    return !empty($_SESSION['usuario']['admin']);
}

/** Exige sesion iniciada; si no, envia al login. */
function requiere_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        redirigir('login.php');
    }
    return $u;
}

/** Exige rol administrador. */
function requiere_admin(): array
{
    $u = requiere_login();
    if (!es_admin()) {
        http_response_code(403);
        flash('error', 'Solo el administrador puede entrar a esa opción.');
        redirigir(pagina_inicio());
    }
    return $u;
}

/**
 * Valida login y clave contra la tabla Usuarios local.
 * Devuelve null si todo esta bien, o el mensaje de error para mostrar.
 */
function intentar_ingreso(string $login, string $clave): ?string
{
    $login = strtoupper(trim($login));
    if ($login === '' || $clave === '') {
        return 'Escriba usuario y clave.';
    }
    if (strlen($login) > 12) {
        return 'Usuario o clave incorrectos.';
    }

    $pdo = db();

    // Bloqueo temporal por intentos fallidos
    $st = $pdo->prepare('SELECT COUNT(*) FROM cont_acceso
                          WHERE login = ? AND exito = 0 AND fecha > (NOW() - INTERVAL ' . INTENTOS_MINUTOS . ' MINUTE)');
    $st->execute([$login]);
    if ((int) $st->fetchColumn() >= INTENTOS_MAXIMOS) {
        registrar_acceso($login, false, 'bloqueado por intentos');
        return 'Demasiados intentos fallidos. Espere ' . INTENTOS_MINUTOS . ' minutos e intente de nuevo.';
    }

    $st = $pdo->prepare('SELECT Login, Nombre, CodiEspe, Password FROM Usuarios WHERE Login = ? AND Activo = 1 LIMIT 1');
    $st->execute([$login]);
    $fila = $st->fetch();

    // password_verify es compatible con los hash MD5-crypt ($1$) de SIHOS
    if (!$fila || !is_string($fila['Password']) || $fila['Password'] === ''
        || !password_verify($clave, $fila['Password'])) {
        registrar_acceso($login, false, $fila ? 'clave incorrecta' : 'usuario inexistente o inactivo');
        return 'Usuario o clave incorrectos.';
    }

    // Ingreso correcto: nuevo id de sesion (evita fijacion de sesion)
    session_regenerate_id(true);
    unset($_SESSION['csrf'], $_SESSION['sihos_estado'], $_SESSION['modulo']);
    $_SESSION['usuario'] = [
        'Login'    => $fila['Login'],
        'Nombre'   => $fila['Nombre'],
        'CodiEspe' => $fila['CodiEspe'],
        'admin'    => strcasecmp($fila['Login'], admin_login()) === 0,
    ];
    $_SESSION['ultimo_uso'] = time();
    registrar_acceso($fila['Login'], true, 'ingreso');
    return null;
}

function registrar_acceso(string $login, bool $exito, string $detalle): void
{
    try {
        $st = db()->prepare('INSERT INTO cont_acceso (fecha, login, ip, exito, detalle) VALUES (NOW(), ?, ?, ?, ?)');
        $st->execute([substr($login, 0, 12), ip_cliente(), $exito ? 1 : 0, substr($detalle, 0, 100)]);
    } catch (Throwable $e) {
        error_log('CRADOR: no se pudo registrar acceso: ' . $e->getMessage());
    }
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
// CSRF: todo formulario POST lleva csrf_campo() y se valida con csrf_verificar()
// ---------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Termina la peticion si el token no coincide. */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        http_response_code(400);
        exit('Solicitud no válida (token de seguridad vencido). Vuelva atrás, recargue la página e intente de nuevo.');
    }
}

// ---------------------------------------------------------------------
// Modulo de trabajo (como en SIHOS: el profesional entra a un modulo)
// ---------------------------------------------------------------------

/** Modulo elegido en esta sesion: 'urg', 'obs', 'ce' o null. */
function modulo_actual(): ?string
{
    $m = $_SESSION['modulo'] ?? null;
    return isset(MODULOS_DETALLE[$m]) ? $m : null;
}

/** Guarda el modulo de trabajo en la sesion. */
function modulo_elegir(?string $clave): void
{
    if (isset(MODULOS_DETALLE[$clave])) {
        $_SESSION['modulo'] = $clave;
    }
}

/**
 * Pagina de inicio segun el usuario: el administrador va al tablero; el profesional
 * va a la pantalla de trabajo de su modulo, o a elegir modulo si aun no tiene.
 */
function pagina_inicio(): string
{
    if (es_admin()) {
        return 'index.php';
    }
    $m = modulo_actual();
    return $m ? 'atencion.php?modulo=' . $m : 'modulo.php';
}
