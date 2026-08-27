<?php
// ── Refuerzo de sesión (si Apache no lo trae forzado) ─────
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 1800);

session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// ── Headers de seguridad para las páginas del panel ──────
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── Verificar que la cuenta siga existiendo y activa ─────
try {
    $stmt = $pdo->prepare('SELECT id, username, rol, activo FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $usuario = null;
}

if (!$usuario || !(int)$usuario['activo']) {
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit;
}

// ── Sincronizar datos de sesión ──────────────────────────
$_SESSION['user_id']    = $usuario['id'];
$_SESSION['username']   = $usuario['username'];
$_SESSION['rol']        = $usuario['rol'];
$_SESSION['user_admin'] = ($usuario['rol'] === 'admin' || $usuario['rol'] === 'root');
$_SESSION['user_root']  = ($usuario['rol'] === 'root');

// ── CSRF token (por si la sesión es anterior a esta función) ─
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Preferencias del usuario (sonido, intervalo) ─────────
$pref = [];
try {
    $stmtP = $pdo->prepare('SELECT preferencias FROM usuarios WHERE id = :id');
    $stmtP->execute([':id' => $_SESSION['user_id']]);
    $rowP = $stmtP->fetch();
    if ($rowP && $rowP['preferencias']) {
        $pref = json_decode($rowP['preferencias'], true) ?: [];
    }
} catch (PDOException $e) {
    // Columna aún no existente en instalaciones antiguas
}
$_SESSION['pref'] = [
    'sonido'    => isset($pref['sonido']) ? (bool)$pref['sonido'] : true,
    'intervalo' => isset($pref['intervalo']) ? max(15, min(300, (int)$pref['intervalo'])) : 30,
];

// ── Configuración global del sistema ─────────────────────
global $APLICACION;
$APLICACION = ['nombre_sistema' => 'ALERTA'];
try {
    $configStmt = $pdo->query('SELECT clave, valor FROM configuracion');
    foreach ($configStmt as $filaC) {
        $APLICACION[$filaC['clave']] = $filaC['valor'];
    }
} catch (PDOException $e) {
    // Tabla aún no creada en instalaciones antiguas
}

// ── Helpers de rol ───────────────────────────────────────
/**
 * ¿El usuario actual es administrador (admin o superadministrador root)?
 */
function esAdmin(): bool {
    return ($_SESSION['user_admin'] ?? false) === true;
}

/**
 * ¿El usuario actual es el superadministrador (root)?
 * Es el rol con máximo poder: puede eliminar alertas.
 */
function esRoot(): bool {
    return ($_SESSION['user_root'] ?? false) === true;
}

/**
 * Detiene la ejecución si el usuario no es administrador.
 */
function requireAdmin(): void {
    if (!esAdmin()) {
        header('Location: /views/dashboard.php');
        exit;
    }
}

/**
 * Detiene la ejecución si el usuario no es el superadministrador (root).
 */
function requireRoot(): void {
    if (!esRoot()) {
        header('Location: /views/dashboard.php');
        exit;
    }
}