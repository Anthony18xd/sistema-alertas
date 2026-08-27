<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/security.php';

// ── Método permitido ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logSuspiciousActivity('Method not allowed: ' . ($_SERVER['REQUEST_METHOD'] ?? ''));
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// ── Autenticación por sesión (panel web) ──────────────────
session_start();
if (!isset($_SESSION['user_id'])) {
    logSuspiciousActivity('Unauthorized access attempt to cambiar_estado');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado. Inicia sesión.']);
    exit;
}

// ── Validar JSON ──────────────────────────────────────────
$input = validateJsonInput();

if (!isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Campos id y status son requeridos']);
    exit;
}

$id = (int)$input['id'];
$status = trim($input['status']);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

if (!in_array($status, ['pendiente', 'completado'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Estado inválido. Usa: pendiente o completado']);
    exit;
}

// ── Actualizar ────────────────────────────────────────────
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/actividad.php';

    $stmt = $pdo->prepare("UPDATE alertas SET status = :status, completado_por = :completado_por WHERE id = :id");
    $completado_por = $status === 'completado' ? ($_SESSION['username'] ?? '') : null;
    $stmt->execute([
        ':status' => $status,
        ':completado_por' => $completado_por,
        ':id' => $id
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Alerta no encontrada']);
        exit;
    }

    if ($status === 'completado') {
        registrarActividad('alerta_completada', "Alerta #{$id}");
    } else {
        registrarActividad('alerta_reabierta', "Alerta #{$id}");
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Estado actualizado correctamente'
    ]);
} catch (PDOException $e) {
    logSuspiciousActivity('DB error al actualizar estado');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al actualizar el estado']);
}
