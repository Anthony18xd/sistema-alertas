<?php
/**
 * GESTIÓN DE API KEYS (dispositivos) — Solo administradores
 * Acciones: crear | activo | eliminar
 * Método: POST, body en JSON (incluye csrf_token)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

if (!esAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Solo el administrador puede gestionar dispositivos.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $input['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$accion = $input['accion'] ?? '';
if (!in_array($accion, ['crear', 'activo', 'eliminar'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción inválida']);
    exit;
}

try {
    switch ($accion) {

        case 'crear':
            $nombre = trim($input['nombre'] ?? '');
            $nombre = mb_substr(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'), 0, 150);

            if (mb_strlen($nombre) < 2) {
                http_response_code(400);
                echo json_encode(['error' => 'El nombre del dispositivo debe tener al menos 2 caracteres.']);
                exit;
            }

            $apiKey = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO api_keys (api_key, nombre_dispositivo, activa) VALUES (:k, :n, 1)');
            $stmt->execute([':k' => $apiKey, ':n' => $nombre]);

            registrarActividad('key_creada', "Dispositivo: {$nombre}");

            echo json_encode([
                'success' => true,
                'mensaje' => 'API Key generada correctamente.',
                'api_key' => $apiKey,
                'dispositivo' => $nombre
            ]);
            break;

        case 'activo':
            $id     = (int)($input['id'] ?? 0);
            $activo = (int)($input['activo'] ?? -1);

            if ($id <= 0 || !in_array($activo, [0, 1], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Parámetros inválidos.']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE api_keys SET activa = :a WHERE id = :id');
            $stmt->execute([':a' => $activo, ':id' => $id]);

            if ($stmt->rowCount() > 0) {
                registrarActividad($activo ? 'key_activada' : 'key_desactivada', "ID: {$id}");
                echo json_encode(['success' => true, 'mensaje' => $activo ? 'Dispositivo activado.' : 'Dispositivo desactivado.']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Dispositivo no encontrado.']);
            }
            break;

        case 'eliminar':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID inválido.']);
                exit;
            }

            $stmtNombre = $pdo->prepare('SELECT nombre_dispositivo FROM api_keys WHERE id = :id');
            $stmtNombre->execute([':id' => $id]);
            $nombre = $stmtNombre->fetch()['nombre_dispositivo'] ?? '';

            $stmt = $pdo->prepare('DELETE FROM api_keys WHERE id = :id');
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                registrarActividad('key_eliminada', "Dispositivo: " . ($nombre ?: "ID: {$id}"));
                echo json_encode(['success' => true, 'mensaje' => 'Dispositivo eliminado correctamente.']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Dispositivo no encontrado.']);
            }
            break;
    }
} catch (PDOException $e) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents($logDir . '/security.log', '[' . date('Y-m-d H:i:s') . '] DB error en gestionar_key: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al procesar la solicitud.']);
}