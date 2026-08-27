<?php
/**
 * migracion_usuario_roles.php — Agrega las columnas rol y activo a la tabla usuarios.
 * Funciona tanto con MySQL (producción) como con SQLite (local), sin perder datos.
 *
 * Uso:
 *   php dev/migracion_usuario_roles.php
 *
 * Nota: Las bases de datos creadas después de este cambio ya traen las columnas.
 */

$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    http_response_code(403);
    echo 'Acceso denegado. Usa: php dev/migracion_usuario_roles.php';
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$modificado = false;

try {
    // ── Verificar columnas existentes ─────────────────────
    if ($driver === 'mysql') {
        $cols = array_map(function ($r) {
            return $r['Field'];
        }, $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll());
    } else {
        $cols = array_map(function ($r) {
            return $r['name'];
        }, $pdo->query('PRAGMA table_info(usuarios)')->fetchAll());
    }

    if (!in_array('rol', $cols, true)) {
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'operador'");
        } else {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) DEFAULT 'operador' NOT NULL");
        }
        $modificado = true;
        echo "  + Columna agregada: rol\n";
    }

    if (!in_array('activo', $cols, true)) {
        $tipo = $driver === 'mysql' ? 'TINYINT(1)' : 'INTEGER';
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN activo $tipo NOT NULL DEFAULT 1");
        $modificado = true;
        echo "  + Columna agregada: activo\n";
    }

    // ── Asegurar que exista al menos un administrador ─────
    $total = (int)$pdo->query('SELECT COUNT(*) AS c FROM usuarios')->fetch()['c'];

    if ($total === 1) {
        $id = (int)$pdo->query('SELECT id FROM usuarios LIMIT 1')->fetch()['id'];
        $pdo->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id')
            ->execute([':rol' => 'admin', ':id' => $id]);
        echo "  + Usuario existente promovido a admin (única cuenta)\n";
    } else {
        $pdo->prepare("UPDATE usuarios SET rol = 'admin' WHERE username = 'admin' AND rol <> 'admin'")
            ->execute();
        echo "  + El usuario 'admin' tiene rol de administrador\n";
    }

    // ── Resumen ───────────────────────────────────────────
    $usuarios = $pdo->query('SELECT id, username, rol, activo FROM usuarios ORDER BY id')->fetchAll();
    echo "═══════════════════════════════════════════════\n";
    if ($modificado) {
        echo "  Migración completada\n";
    } else {
        echo "  Sin cambios (columnas ya existentes)\n";
    }
    echo "═══════════════════════════════════════════════\n";
    foreach ($usuarios as $u) {
        printf(
            "  #%d  %-20s rol=%-9s activo=%s\n",
            $u['id'],
            $u['username'],
            $u['rol'],
            $u['activo'] ? 'sí' : 'no'
        );
    }
    echo "═══════════════════════════════════════════════\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}