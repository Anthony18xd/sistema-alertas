<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

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
$_SESSION['user_admin'] = ($usuario['rol'] === 'admin');

// ── CSRF token (por si la sesión es anterior a esta función) ─
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Helpers de rol ───────────────────────────────────────
/**
 * ¿El usuario actual es administrador?
 */
function esAdmin(): bool {
    return ($_SESSION['user_admin'] ?? false) === true;
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