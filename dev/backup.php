<?php
/**
 * backup.php — Genera un respaldo de la base de datos (CLI)
 *
 * Uso:
 *   php dev/backup.php            → crea archivo en backups/
 *   php dev/backup.php --silent   → solo imprime la ruta (útil para cron)
 *
 * En MySQL (producción) genera un volcado .sql; en SQLite (local) una copia .db.
 * IMPORTANTE: guarda este directorio (backups/) fuera del servidor de forma regular.
 */

$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    http_response_code(403);
    echo 'Acceso denegado. Usa: php dev/backup.php';
    exit(1);
}

$silent = in_array('--silent', $argv, true);

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/backup.php';
require_once __DIR__ . '/../includes/actividad.php';

try {
    $backup = generarBackup($pdo);
    $ruta   = guardarBackupEnDisco($backup['contenido'], $backup['extension']);

    registrarActividad('backup_cli', basename($ruta) . ' (' . round(strlen($backup['contenido']) / 1024) . ' KB)');

    if ($silent) {
        echo $ruta . "\n";
    } else {
        echo "═══════════════════════════════════════════════\n";
        echo "  Respaldo creado correctamente\n";
        echo "═══════════════════════════════════════════════\n";
        echo "  Archivo:  $ruta\n";
        echo "  Tamaño:   " . round(filesize($ruta) / 1024, 1) . " KB\n";
        echo "  Motor:    " . $backup['descripcion'] . "\n";
        echo "═══════════════════════════════════════════════\n";
        echo "\n  Copia este archivo a un sitio seguro (pendrive,\n";
        echo "  correo interno, otro servidor). También puedes\n";
        echo "  descargarlo desde el panel: Configuración → Backup.\n\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}