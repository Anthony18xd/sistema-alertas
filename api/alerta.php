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

// ── API Key obligatoria ───────────────────────────────────
$keyData = validateApiKey();

// ── Validar JSON ──────────────────────────────────────────
$input = validateJsonInput();

// ── Campos requeridos ─────────────────────────────────────
$campos = ['dispositivo', 'numero', 'bateria', 'fecha_hora', 'latitud', 'longitud'];
foreach ($campos as $campo) {
    if (!isset($input[$campo]) || (is_string($input[$campo]) && trim($input[$campo]) === '')) {
        http_response_code(400);
        echo json_encode(['error' => "Campo '$campo' es requerido"]);
        exit;
    }
}

// ── Sanitización y validación ─────────────────────────────
$dispositivo = sanitizeString($input['dispositivo']);
$numero = sanitizeString($input['numero'], 20);
$fecha_hora = sanitizeString($input['fecha_hora'], 30);

if (empty($dispositivo) || empty($numero)) {
    http_response_code(400);
    echo json_encode(['error' => 'dispositivo y numero no pueden estar vacíos']);
    exit;
}

// Latitud
$latitud = filter_var($input['latitud'], FILTER_VALIDATE_FLOAT);
if ($latitud === false || $latitud < -90 || $latitud > 90) {
    http_response_code(400);
    echo json_encode(['error' => 'Latitud inválida (debe ser entre -90 y 90)']);
    exit;
}

// Longitud
$longitud = filter_var($input['longitud'], FILTER_VALIDATE_FLOAT);
if ($longitud === false || $longitud < -180 || $longitud > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Longitud inválida (debe ser entre -180 y 180)']);
    exit;
}

// Batería
$bateria = filter_var($input['bateria'], FILTER_VALIDATE_INT);
if ($bateria === false || $bateria < 0 || $bateria > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Batería debe ser un entero entre 0 y 100']);
    exit;
}

// Fecha
if (!strtotime($fecha_hora)) {
    http_response_code(400);
    echo json_encode(['error' => 'fecha_hora no es una fecha válida (usa formato: YYYY-MM-DD HH:MM:SS)']);
    exit;
}

// ── Insertar en BD ────────────────────────────────────────
try {
    require_once __DIR__ . '/../config/database.php';

    $stmt = $pdo->prepare("
        INSERT INTO alertas (dispositivo, numero, bateria, fecha_hora, latitud, longitud, status)
        VALUES (:dispositivo, :numero, :bateria, :fecha_hora, :latitud, :longitud, 'pendiente')
    ");
    $stmt->execute([
        ':dispositivo' => $dispositivo,
        ':numero'      => $numero,
        ':bateria'     => $bateria,
        ':fecha_hora'  => $fecha_hora,
        ':latitud'     => $latitud,
        ':longitud'    => $longitud,
    ]);

    $id = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'mensaje' => 'Alerta recibida correctamente',
        'id'      => (int)$id
    ]);
} catch (PDOException $e) {
    logSuspiciousActivity('DB error al insertar alerta');
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al guardar la alerta']);
}
