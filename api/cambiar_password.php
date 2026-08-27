<?php
/**
 * CAMBIO DE CONTRASEÑA PROPIA
 * Cualquier usuario autenticado. Requiere la contraseña actual.
 * Método: POST, body en JSON (incluye csrf_token)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

// ── Validar CSRF ──────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $input['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$actual = $input['password_actual'] ?? '';
$nueva  = $input['password_nueva'] ?? '';
$confirmar = $input['password_confirmar'] ?? '';

if ($actual === '' || $nueva === '' || $confirmar === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Completa todos los campos.']);
    exit;
}

if ($nueva !== $confirmar) {
    http_response_code(400);
    echo json_encode(['error' => 'La nueva contraseña y su confirmación no coinciden.']);
    exit;
}

if (mb_strlen($nueva) < 8 || mb_strlen($nueva) > 128) {
    http_response_code(400);
    echo json_encode(['error' => 'La nueva contraseña debe tener entre 8 y 128 caracteres.']);
    exit;
}

try {
    // ── Verificar contraseña actual ──────────────────────
    $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado.']);
        exit;
    }

    if (!password_verify($actual, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'La contraseña actual es incorrecta.']);
        exit;
    }

    // ── Actualizar ───────────────────────────────────────
    $hashed = password_hash($nueva, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :p WHERE id = :id');
    $stmt->execute([':p' => $hashed, ':id' => $_SESSION['user_id']]);

    session_regenerate_id(true);

    registrarActividad('password_cambio', 'Contraseña propia actualizada');
    echo json_encode(['success' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
} catch (PDOException $e) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents(
        $logDir . '/security.log',
        '[' . date('Y-m-d H:i:s') . '] DB error en cambiar_password: ' . $e->getMessage() . "\n",
        FILE_APPEND | LOCK_EX
    );
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al actualizar la contraseña.']);
}