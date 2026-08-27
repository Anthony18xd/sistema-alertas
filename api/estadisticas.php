<?php
/**
 * ESTADÍSTICAS PARA EL DASHBOARD — sesión autenticada
 * Devuelve: últimos 7 días + top dispositivos
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

try {
    // ── Últimos 7 días ───────────────────────────────────
    $dias = [];
    $hoy = new DateTime();
    for ($i = 6; $i >= 0; $i--) {
        $d = (clone $hoy)->modify("-{$i} days")->format('Y-m-d');
        $dias[$d] = ['fecha' => $d, 'total' => 0, 'pendientes' => 0, 'completados' => 0];
    }

    $region = [
        'fecha' => date('Y-m-d', strtotime('-6 days')),
    ];

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->prepare(
            'SELECT substr(fecha_hora, 1, 10) AS dia, status, COUNT(*) AS c
             FROM alertas WHERE fecha_hora >= :fecha
             GROUP BY substr(fecha_hora, 1, 10), status'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT DATE(fecha_hora) AS dia, status, COUNT(*) AS c
             FROM alertas WHERE fecha_hora >= :fecha
             GROUP BY DATE(fecha_hora), status'
        );
    }
    $stmt->execute([':fecha' => $region['fecha']]);
    foreach ($stmt as $fila) {
        $dia = $fila['dia'];
        if (!isset($dias[$dia])) {
            continue;
        }
        $dias[$dia]['total'] += (int)$fila['c'];
        if ($fila['status'] === 'pendiente') {
            $dias[$dia]['pendientes'] += (int)$fila['c'];
        } else {
            $dias[$dia]['completados'] += (int)$fila['c'];
        }
    }

    // ── Por dispositivo (top 8 + "otros") ────────────────
    $stmtD = $pdo->query('SELECT dispositivo, COUNT(*) AS c FROM alertas GROUP BY dispositivo ORDER BY c DESC LIMIT 50');
    $porDispositivo = [];
    foreach ($stmtD as $fila) {
        $porDispositivo[] = [
            'dispositivo' => $fila['dispositivo'] !== '' ? $fila['dispositivo'] : 'Sin nombre',
            'total' => (int)$fila['c']
        ];
    }

    echo json_encode([
        'success' => true,
        'ultimos7dias' => array_values($dias),
        'por_dispositivo' => $porDispositivo,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}