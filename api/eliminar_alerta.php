<?php
/**
 * ELIMINAR ALERTA — Solo superadministrador (root)
 * Método: POST, body en JSON (incluye csrf_token)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

if (!esRoot()) {
    logSuspiciousActivity('Intento de eliminar alerta sin rol root: ' . ($_SESSION['username'] ?? 'sin_sesion'));
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Solo el superadministrador puede eliminar alertas.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $input['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $stmtInfo = $pdo->prepare('SELECT id, dispositivo, numero, fecha_hora FROM alertas WHERE id = :id');
    $stmtInfo->execute([':id' => $id]);
    $alerta = $stmtInfo->fetch();

    if (!$alerta) {
        http_response_code(404);
        echo json_encode(['error' => 'Alerta no encontrada']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM alertas WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        registrarActividad('alerta_eliminada', 'Alerta #' . $id . ' (' . ($alerta['dispositivo'] ?? '?') . ')');
        echo json_encode(['success' => true, 'mensaje' => 'Alerta #' . $id . ' eliminada correctamente.']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Alerta no encontrada']);
    }
} catch (PDOException $e) {
    logSuspiciousActivity('DB error al eliminar alerta');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al eliminar la alerta']);
}
