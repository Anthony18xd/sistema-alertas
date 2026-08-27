<?php
/**
 * GUARDAR NOTA DE ALERTA — sesión autenticada + CSRF
 * Método: POST, body en JSON { csrf_token, id, nota }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $input['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$id   = (int)($input['id'] ?? 0);
$nota = trim($input['nota'] ?? '');
$nota = mb_substr($nota, 0, 500);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE alertas SET nota = :nota WHERE id = :id');
    $stmt->execute([':nota' => $nota === '' ? null : $nota, ':id' => $id]);

    if ($stmt->rowCount() > 0) {
        registrarActividad('nota_alerta', "Alerta #{$id}");
        echo json_encode(['success' => true, 'mensaje' => 'Nota guardada correctamente.']);
    } else {
        // El valor pudo no cambiar (rowCount 0); verificamos existencia
        $existe = $pdo->prepare('SELECT COUNT(*) c FROM alertas WHERE id = :id');
        $existe->execute([':id' => $id]);
        if ((int)$existe->fetch()['c'] > 0) {
            echo json_encode(['success' => true, 'mensaje' => 'Nota guardada correctamente.']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Alerta no encontrada.']);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}