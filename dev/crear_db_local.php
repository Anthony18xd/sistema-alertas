<?php
/**
 * crear_db_local.php — Crea la base de datos SQLite para desarrollo local
 * SOLO PARA DESARROLLO. Protegido por .htaccess en producción.
 *
 * Uso:
 *   php dev/crear_db_local.php            -> crea la DB (o la recrea con --force)
 *   php dev/crear_db_local.php --force    -> borra y recrea desde cero
 */

$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    http_response_code(403);
    echo 'Acceso denegado. Usa: php dev/crear_db_local.php [--force]';
    exit(1);
}

$force = in_array('--force', $argv, true);

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

$dbPath = $dataDir . '/alerta_local.db';

if ($force && file_exists($dbPath)) {
    unlink($dbPath);
    echo "  Base de datos anterior eliminada\n";
}

if (file_exists($dbPath)) {
    echo "  La base de datos ya existe: $dbPath\n";
    echo "  Usa --force para recrearla desde cero\n";
    exit(0);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    // ── Esquema (equivalente a database/schema.sql en MySQL) ──
    $pdo->exec("
        CREATE TABLE usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE alertas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dispositivo VARCHAR(150) NOT NULL,
            numero VARCHAR(20) DEFAULT '' NOT NULL,
            bateria INT DEFAULT 0 NOT NULL,
            fecha_hora DATETIME NOT NULL,
            latitud DECIMAL(10, 7) NOT NULL,
            longitud DECIMAL(10, 7) NOT NULL,
            status VARCHAR(20) DEFAULT 'pendiente' NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("CREATE INDEX idx_status ON alertas (status)");
    $pdo->exec("CREATE INDEX idx_fecha ON alertas (fecha_hora)");
    $pdo->exec("CREATE INDEX idx_dispositivo ON alertas (dispositivo)");

    $pdo->exec("
        CREATE TABLE api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            nombre_dispositivo VARCHAR(150) NOT NULL,
            activa INTEGER DEFAULT 1 NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL DEFAULT NULL
        )
    ");

    // ── Datos iniciales ──────────────────────────────────────
    // Usuario admin por defecto (contraseña: password) — CAMBIAR EN PRODUCCIÓN
    $pdo->prepare('INSERT INTO usuarios (username, password_hash) VALUES (?, ?)')
        ->execute(['admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi']);

    // API Key inicial para el proyecto
    $pdo->prepare('INSERT INTO api_keys (api_key, nombre_dispositivo, activa) VALUES (?, ?, 1)')
        ->execute(['alerta_muni_2026_xK9mP2vL8nQ4wR7jT3yH6bC5fD1gE0sA', 'App Android ALERTA']);

    echo "═══════════════════════════════════════════════\n";
    echo "  Base de datos local creada correctamente\n";
    echo "═══════════════════════════════════════════════\n";
    echo "  Archivo:   $dbPath\n";
    echo "  Tablas:    usuarios, alertas, api_keys\n";
    echo "  Usuario:   admin / password (cámbiala)\n";
    echo "\n  Siguiente paso:\n";
    echo "    php dev/simular_alerta.php 5\n\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
