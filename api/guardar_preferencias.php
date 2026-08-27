<?php
/**
 * GUARDAR PREFERENCIAS DEL USUARIO — sesión autenticada + CSRF
 * Body JSON: { csrf_token, sonido: bool, intervalo: int }
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

$sonido    = isset($input['sonido']) ? (bool)$input['sonido'] : true;
$intervalo = isset($input['intervalo']) ? (int)$input['intervalo'] : 30;
$intervalo = max(15, min(300, $intervalo));

try {
    // Preservar preferencias ya existentes
    $stmt = $pdo->prepare('SELECT preferencias FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $actual = $stmt->fetch()['preferencias'] ?? '';
    $pref = $actual ? (json_decode($actual, true) ?: []) : [];

    $pref['sonido']    = $sonido;
    $pref['intervalo'] = $intervalo;

    $json = json_encode($pref);
    $up = $pdo->prepare('UPDATE usuarios SET preferencias = :pref WHERE id = :id');
    $up->execute([':pref' => $json, ':id' => $_SESSION['user_id']]);

    // Actualizar la sesión para que surta efecto de inmediato
    $_SESSION['pref'] = [
        'sonido'    => $sonido,
        'intervalo' => $intervalo,
    ];

    registrarActividad('preferencias', 'Sonido: ' . ($sonido ? 'sí' : 'no') . ', intervalo: ' . $intervalo . 's');

    echo json_encode(['success' => true, 'mensaje' => 'Preferencias guardadas correctamente.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}