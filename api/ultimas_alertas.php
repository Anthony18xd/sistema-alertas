<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/security.php';

// ── API Key obligatoria ───────────────────────────────────
$keyData = validateApiKey();

// ── Parámetro seguro ──────────────────────────────────────
$ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;
if ($ultimo_id < 0) $ultimo_id = 0;

try {
    require_once __DIR__ . '/../config/database.php';

    // Obtener el ID más alto
    $stmt = $pdo->query('SELECT MAX(id) AS max_id FROM alertas');
    $row = $stmt->fetch();
    $latest_id = (int)($row['max_id'] ?? 0);

    // Obtener las últimas 3 alertas
    $stmt = $pdo->query('SELECT id, dispositivo, bateria, fecha_hora, status FROM alertas ORDER BY id DESC LIMIT 3');
    $alertas = $stmt->fetchAll();

    foreach ($alertas as &$a) {
        $a['id']      = (int)$a['id'];
        $a['bateria'] = (int)$a['bateria'];
    }

    $hay_nuevas = $ultimo_id > 0 && $latest_id > $ultimo_id;

    echo json_encode([
        'success'    => true,
        'alerts'     => $alertas,
        'latest_id'  => $latest_id,
        'hay_nuevas' => $hay_nuevas
    ]);
} catch (PDOException $e) {
    logSuspiciousActivity('DB error al obtener últimas alertas');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al obtener alertas']);
}
