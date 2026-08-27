<?php
/**
 * migracion_v2.php — Amplía el esquema con:
 *   usuarios.preferencias        (JSON con sonido + intervalo)
 *   alertas.nota / completado_por
 *   tabla logs_actividad         (bitácora / auditoría)
 *   tabla configuracion          (ajustes globales)
 *
 * Funciona en MySQL (producción) y SQLite (local) sin perder datos.
 * Uso: php dev/migracion_v2.php
 */

$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    http_response_code(403);
    echo 'Acceso denegado. Usa: php dev/migracion_v2.php';
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// ── Lista de columnas de una tabla ────────────────────────
function columnasTabla(PDO $pdo, string $driver, string $tabla): array {
    if ($driver === 'mysql') {
        return array_map(fn ($r) => $r['Field'], $pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll());
    }
    return array_map(fn ($r) => $r['name'], $pdo->query("PRAGMA table_info($tabla)")->fetchAll());
}

// ── Agregar columna si no existe ──────────────────────────
function agregarColumna(PDO $pdo, string $driver, string $tabla, string $col, string $def, string $tipoSqlite, string $tipoMysql): bool {
    $cols = columnasTabla($pdo, $driver, $tabla);
    if (in_array($col, $cols, true)) {
        return false;
    }
    $tipo = $driver === 'mysql' ? $tipoMysql : $tipoSqlite;
    $pdo->exec("ALTER TABLE $tabla ADD COLUMN $col $tipo $def");
    return true;
}

try {
    $cambios = 0;

    // ── usuarios.preferencias ────────────────────────────
    if (agregarColumna($pdo, $driver, 'usuarios', 'preferencias', '', 'TEXT', 'TEXT NULL')) {
        echo "  + Columnas agregadas: usuarios.preferencias\n";
        $cambios++;
    }

    // ── alertas.nota / completado_por ────────────────────
    if (agregarColumna($pdo, $driver, 'alertas', 'nota', '', 'TEXT', 'TEXT NULL')) {
        echo "  + Columna agregada: alertas.nota\n";
        $cambios++;
    }
    if (agregarColumna($pdo, $driver, 'alertas', 'completado_por', '', 'VARCHAR(50)', 'VARCHAR(50) NULL')) {
        echo "  + Columna agregada: alertas.completado_por\n";
        $cambios++;
    }

    // ── Tabla logs_actividad ─────────────────────────────
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs_actividad (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL DEFAULT NULL,
            username VARCHAR(50) NOT NULL DEFAULT '',
            accion VARCHAR(100) NOT NULL,
            detalle VARCHAR(255) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_accion (accion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs_actividad (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL DEFAULT NULL,
            username VARCHAR(50) NOT NULL DEFAULT '',
            accion VARCHAR(100) NOT NULL,
            detalle VARCHAR(255) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    if ($cambios > 0) {
        echo "  + Tablas garantizadas: logs_actividad, configuracion\n";
    }

    // ── Tabla configuracion ──────────────────────────────
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion (
            clave VARCHAR(50) PRIMARY KEY,
            valor TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO configuracion (clave, valor) VALUES ('nombre_sistema', 'ALERTA')");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion (
            clave VARCHAR(50) PRIMARY KEY,
            valor TEXT NOT NULL
        )");
        $pdo->exec("INSERT OR IGNORE INTO configuracion (clave, valor) VALUES ('nombre_sistema', 'ALERTA')");
    }

    echo "═══════════════════════════════════════════════\n";
    echo "  Migración v2 " . ($cambios > 0 ? "completada ($cambios cambio/s)" : "sin cambios") . "\n";
    echo "═══════════════════════════════════════════════\n";
    echo "  Tablas: usuarios, alertas, api_keys, logs_actividad, configuracion\n";
    echo "  Motor:  $driver\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}