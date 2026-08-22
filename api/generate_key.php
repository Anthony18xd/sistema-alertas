<?php
/**
 * Generador de API Keys
 * Requiere sesión de administrador activa
 * Uso: php api/generate_key.php "Nombre del dispositivo"
 */

session_start();
require_once __DIR__ . '/../config/security.php';

// ── Solo CLI o admin autenticado ──────────────────────────
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acceso denegado. Inicia sesión como administrador.']);
    exit;
}

// ── CSRF para peticiones web ──────────────────────────────
if (!$isCLI) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }
}

// ── Obtener nombre del dispositivo ────────────────────────
$nombre = '';
if ($isCLI) {
    $nombre = $argv[1] ?? '';
} else {
    $nombre = $_POST['nombre'] ?? '';
}

if (empty($nombre)) {
    if ($isCLI) {
        echo "Uso: php generate_key.php \"Nombre del dispositivo\"\n";
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'El campo nombre es requerido']);
    }
    exit(1);
}

// ── Sanitizar nombre ──────────────────────────────────────
$nombre = htmlspecialchars(trim($nombre), ENT_QUOTES, 'UTF-8');
$nombre = mb_substr($nombre, 0, 150);

if (strlen($nombre) < 2) {
    if ($isCLI) {
        echo "Error: El nombre debe tener al menos 2 caracteres\n";
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'El nombre debe tener al menos 2 caracteres']);
    }
    exit(1);
}

// ── Generar key ───────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';

$apiKey = bin2hex(random_bytes(32));

try {
    $stmt = $pdo->prepare('INSERT INTO api_keys (api_key, nombre_dispositivo, activa) VALUES (:key, :nombre, 1)');
    $stmt->execute([':key' => $apiKey, ':nombre' => $nombre]);

    if ($isCLI) {
        echo "═══════════════════════════════════════════════\n";
        echo "  API Key generada correctamente\n";
        echo "═══════════════════════════════════════════════\n";
        echo "  Dispositivo: $nombre\n";
        echo "  API Key:     $apiKey\n";
        echo "═══════════════════════════════════════════════\n";
        echo "\n Envía esta key en el header:\n";
        echo "  X-API-Key: $apiKey\n\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'mensaje' => 'API Key generada correctamente',
            'api_key' => $apiKey,
            'dispositivo' => $nombre
        ]);
    }
} catch (PDOException $e) {
    if ($isCLI) {
        echo "Error al crear la API Key\n";
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error al crear la API Key']);
    }
    exit(1);
}
