<?php
/**
 * AUDITORÍA — registro de acciones en la bitácora (logs_actividad)
 * Uso: registrarActividad('usuario_creado', 'Creado el usuario juan');
 *
 * Se reutiliza en el panel y en las APIs. Nunca rompe el flujo principal.
 */

function registrarActividad(string $accion, string $detalle = '', ?int $userId = null, string $username = ''): void {
    global $pdo;

    if (!$pdo) {
        return;
    }

    $isCLI = php_sapi_name() === 'cli';

    if ($isCLI) {
        $userId  = 0;
        $username = 'cli';
        $ip      = 'cli';
    } else {
        if ($userId === null) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }
        if ($username === '') {
            $username = (string)($_SESSION['username'] ?? '');
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = '0.0.0.0';
        }
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logs_actividad (user_id, username, accion, detalle, ip) VALUES (:u, :n, :a, :d, :ip)'
        );
        $stmt->execute([
            ':u'  => $userId ?: null,
            ':n'  => mb_substr($username, 0, 50),
            ':a'  => mb_substr($accion, 0, 100),
            ':d'  => mb_substr($detalle, 0, 255),
            ':ip' => mb_substr($ip, 0, 45),
        ]);
    } catch (PDOException $e) {
        // La auditoría nunca debe impedir la operación principal.
    }
}