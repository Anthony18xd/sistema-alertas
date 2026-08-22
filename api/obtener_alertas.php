<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/security.php';

// ── API Key obligatoria ───────────────────────────────────
$keyData = validateApiKey();

// ── Obtener alertas pendientes ────────────────────────────
try {
    require_once __DIR__ . '/../config/database.php';

    $stmt = $pdo->prepare("
        SELECT id, dispositivo, numero, bateria, fecha_hora, latitud, longitud
        FROM alertas
        WHERE status = 'pendiente'
        ORDER BY fecha_hora DESC
        LIMIT 100
    ");
    $stmt->execute();
    $alertas = $stmt->fetchAll();

    foreach ($alertas as &$a) {
        $a['latitud']   = (float)$a['latitud'];
        $a['longitud']  = (float)$a['longitud'];
        $a['bateria']   = (int)$a['bateria'];
        $a['id']        = (int)$a['id'];
    }

    echo json_encode([
        'success' => true,
        'total'   => count($alertas),
        'data'    => $alertas
    ]);
} catch (PDOException $e) {
    logSuspiciousActivity('DB error al obtener alertas');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al obtener alertas']);
}
