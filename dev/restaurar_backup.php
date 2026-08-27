<?php
/**
 * restaurar_backup.php — Restaura un respaldo (SOLO CLI con cuidado)
 *
 * Uso:
 *   php dev/restaurar_backup.php backups/alerta_backup_20260827_123456.db   (SQLite local)
 *   php dev/restaurar_backup.php backups/alerta_backup_20260827_123456.sql  (MySQL producción)
 *
 * - .db  → reemplaza data/alerta_local.db (agrega --force para sobrescribir)
 * - .sql → se ejecuta contra MySQL (borra y recrea tablas).
 *
 * ⚠️  Esta operación PISA los datos actuales. Haz un backup antes de restaurar.
 */

$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    http_response_code(403);
    echo 'Acceso denegado. Usa: php dev/restaurar_backup.php <archivo> [--force]';
    exit(1);
}

$ruta = $argv[1] ?? '';
$force = in_array('--force', $argv, true);

if ($ruta === '') {
    echo "Uso: php dev/restaurar_backup.php <archivo> [--force]\n";
    exit(1);
}

$ruta = realpath($ruta) ?: $ruta;

if (!file_exists($ruta)) {
    fwrite(STDERR, "El archivo no existe: $ruta\n");
    exit(1);
}

if (!$force) {
    echo "⚠️  Esto PISARÁ los datos actuales de la base de datos.\n";
    echo "  Archivo: $ruta\n";
    echo "  ¿Continuar? Escribe 'SI' para confirmar: ";
    $confirm = trim(fgets(STDIN));

    if (strtoupper($confirm) !== 'SI') {
        echo "Restauración cancelada.\n";
        exit(0);
    }
}

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/backup.php';

try {
    $resultado = restaurarBackup($ruta, $pdo, $force);
    echo "✓ $resultado\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}