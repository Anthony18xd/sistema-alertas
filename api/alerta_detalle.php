<?php
/**
 * DETALLE DE ALERTA — Lectura (sesión autenticada)
 * GET ?id=123  →  JSON con los datos completos de la alerta
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, dispositivo, numero, bateria, fecha_hora, latitud, longitud, status, nota, completado_por
         FROM alertas WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $alerta = $stmt->fetch();

    if (!$alerta) {
        http_response_code(404);
        echo json_encode(['error' => 'Alerta no encontrada.']);
        exit;
    }

    echo json_encode(['success' => true, 'alerta' => $alerta]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}