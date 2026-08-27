<?php
/**
 * LIBRERÍA DE BACKUPS
 * generaBackup(): devuelve una copia consistente según el motor (SQLite/MySQL).
 * guardarBackupEnDisco(): guarda el backup en backups/ con fecha y hora.
 * restaurarBackup(): restaura un backup .db (SQLite local) o .sql (MySQL).
 */

function directorioBackups(): string {
    $dir = __DIR__ . '/../backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

/**
 * Genera el backup en memoria.
 * @return array{contenido:string, extension:string, descripcion:string}
 */
function generarBackup(PDO $pdo): array {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $tmp = tempnam(sys_get_temp_dir(), 'alerta_bk_');
        $tmpDb = $tmp . '.db';
        // VACUUM INTO crea una copia consistente del .db (soporta escrituras concurrentes WAL).
        // Se abre una conexión aparte porque PDO mantiene statements activos que bloquean VACUUM.
        $stmtInfo = $pdo->query('PRAGMA database_list');
        $filaInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        $stmtInfo = null;
        $rutaDb = $filaInfo['file'] ?? null;
        if (!$rutaDb || !is_file($rutaDb)) {
            throw new RuntimeException('No se pudo localizar el archivo SQLite.');
        }
        $bkPDO = new PDO('sqlite:' . $rutaDb);
        $bkPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $bkPDO->exec('VACUUM INTO ' . $bkPDO->quote($tmpDb));
        $bkPDO = null;
        $contenido = file_get_contents($tmpDb);
        unlink($tmpDb);
        @unlink($tmp);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el backup generado.');
        }
        return ['contenido' => $contenido, 'extension' => 'db', 'descripcion' => 'SQLite (copia del archivo)'];
    }

    // ── MySQL: volcado SQL portátil ─────────────────────
    $tablas = ['usuarios', 'alertas', 'api_keys', 'logs_actividad', 'configuracion'];
    $sql = "-- ALERTA - Volcado de base de datos\n";
    $sql .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Motor: MySQL\n\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    $existentes = array_map(fn ($t) => array_values($t)[0], $pdo->query('SHOW TABLES')->fetchAll());

    foreach ($tablas as $tabla) {
        if (!in_array($tabla, $existentes, true)) {
            continue;
        }

        $create = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch();
        $sql .= "-- --------------------------------------------\n";
        $sql .= "-- Estructura de $tabla\n";
        $sql .= "-- --------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        $sql .= "-- --------------------------------------------\n";
        $sql .= "-- Datos de $tabla\n";
        $sql .= "-- --------------------------------------------\n";

        $filas = $pdo->query("SELECT * FROM `$tabla`")->fetchAll();
        if (empty($filas)) {
            $sql .= "-- (sin registros)\n\n";
            continue;
        }
        foreach ($filas as $fila) {
            $campos  = [];
            $valores = [];
            foreach ($fila as $col => $val) {
                if (is_int($col)) {
                    continue;
                }
                $campos[] = "`$col`";
                $valores[] = $val === null ? 'NULL' : $pdo->quote((string)$val);
            }
            $sql .= 'INSERT INTO `' . $tabla . '` (' . implode(', ', $campos) . ') VALUES (' . implode(', ', $valores) . ");\n";
        }
        $sql .= "\n";
    }

    return ['contenido' => $sql, 'extension' => 'sql', 'descripcion' => 'MySQL (volcado SQL)'];
}

/**
 * Guarda el contenido del backup en backups/ y devuelve la ruta.
 */
function guardarBackupEnDisco(string $contenido, string $extension): string {
    $dir = directorioBackups();
    $nombre = 'alerta_backup_' . date('Ymd_His') . '.' . $extension;
    $ruta = $dir . '/' . $nombre;
    file_put_contents($ruta, $contenido, LOCK_EX);
    return $ruta;
}

/**
 * Restaura un backup.
 * - .db  → reemplaza la BD SQLite local (solo con --force si ya existe)
 * - .sql → se ejecuta contra MySQL
 * @return string mensaje del resultado
 */
function restaurarBackup(string $ruta, PDO $pdo, bool $force = false): string {
    if (!file_exists($ruta)) {
        throw new RuntimeException('El archivo de backup no existe: ' . $ruta);
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

    if ($ext === 'db') {
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Los backups .db solo aplican a la BD local SQLite.');
        }
        $destino = __DIR__ . '/../data/alerta_local.db';
        if (file_exists($destino) && !$force) {
            throw new RuntimeException('La BD local ya existe. Usa --force para sobrescribirla.');
        }
        if (!copy($ruta, $destino)) {
            throw new RuntimeException('No se pudo copiar el backup .db');
        }
        return 'BD local restaurada desde ' . basename($ruta);
    }

    if ($ext === 'sql') {
        if ($driver !== 'mysql') {
            throw new RuntimeException('Los backups .sql son para MySQL; en local restaura un .db.');
        }
        $contenido = file_get_contents($ruta);
        if ($pdo->exec($contenido) === false) {
            throw new RuntimeException('No se pudo ejecutar el backup .sql');
        }
        return 'MySQL restaurado desde ' . basename($ruta);
    }

    throw new RuntimeException('Extensión de backup no soportada: .' . $ext);
}