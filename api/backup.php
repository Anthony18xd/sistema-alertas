<?php
/**
 * BACKUP WEB — Descarga respaldo desde el panel (Solo admin + CSRF)
 * Genera el backup, lo guarda en backups/ y lo envía como descarga.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/backup.php';
require_once __DIR__ . '/../includes/actividad.php';

if (!esAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acceso denegado. Solo el administrador.']);
    exit;
}

// ── CSRF (acepta POST con form-urlencoded o JSON) ─────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf = $body['csrf_token'] ?? '';
}
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

try {
    $backup = generarBackup($pdo);
    $ruta   = guardarBackupEnDisco($backup['contenido'], $backup['extension']);

    registrarActividad('backup_web', basename($ruta) . ' (' . round(strlen($backup['contenido']) / 1024) . ' KB)');

    // ── Descarga ─────────────────────────────────────────
    $nombre = basename($ruta);
    $mime = $backup['extension'] === 'db' ? 'application/octet-stream' : 'application/sql; charset=UTF-8';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($backup['contenido']));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo $backup['contenido'];
} catch (Throwable $e) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents($logDir . '/security.log', '[' . date('Y-m-d H:i:s') . '] backup error: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar el respaldo.']);
}