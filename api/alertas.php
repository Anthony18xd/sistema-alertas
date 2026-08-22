<?php
/**
 * GET /api/alertas.php — Endpoint unificado de lectura
 * Soporta: ?since=<id> ?status=<pendiente|completado|todos> ?limit=<n> ?latest=<n>
 * Usa el mismo middleware de seguridad que los demás endpoints
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/security.php';

// ── API Key obligatoria ───────────────────────────────────
$keyData = validateApiKey();

// ── Parámetros seguros ────────────────────────────────────
$since  = filter_var($_GET['since'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
$limit  = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['default' => 50, 'min_range' => 1, 'max_range' => 200]]);
$status = $_GET['status'] ?? 'pendiente';
$latest = filter_var($_GET['latest'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);

// Validar status
$statusesValidos = ['pendiente', 'completado', 'todos'];
if (!in_array($status, $statusesValidos)) {
    $status = 'pendiente';
}

// ── Consulta =================================================
try {
    require_once __DIR__ . '/../config/database.php';

    // Modo latest: solo las N más recientes (para notificaciones)
    if ($latest > 0) {
        $stmt = $pdo->prepare("
            SELECT id, dispositivo, bateria, fecha_hora, status
            FROM alertas
            ORDER BY id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', min($latest, 20), PDO::PARAM_INT);
        $stmt->execute();
        $alertas = $stmt->fetchAll();

        foreach ($alertas as &$a) {
            $a['id']         = (int)$a['id'];
            $a['bateria']    = (int)$a['bateria'];
            $a['fecha_hora'] = date('Y-m-d H:i:s', strtotime($a['fecha_hora']));
        }

        // Obtener el ID más alto
        $stmtMax = $pdo->query('SELECT MAX(id) AS max_id FROM alertas');
        $rowMax = $stmtMax->fetch();
        $latestId = (int)($rowMax['max_id'] ?? 0);

        echo json_encode([
            'success'    => true,
            'alerts'     => $alertas,
            'latest_id'  => $latestId
        ]);
        exit;
    }

    // Modo normal: con filtros
    $sql = "SELECT id, dispositivo, numero, bateria,
                   fecha_hora, latitud, longitud, status
            FROM alertas
            WHERE id > :since";

    if ($status !== 'todos') {
        $sql .= " AND status = :status";
    }

    $sql .= " ORDER BY id DESC LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since', $since, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    if ($status !== 'todos') {
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    }
    $stmt->execute();
    $alertas = $stmt->fetchAll();

    foreach ($alertas as &$a) {
        $a['id']         = (int)$a['id'];
        $a['bateria']    = (int)$a['bateria'];
        $a['latitud']    = (float)$a['latitud'];
        $a['longitud']   = (float)$a['longitud'];
        $a['fecha_hora'] = date('Y-m-d H:i:s', strtotime($a['fecha_hora']));
    }

    echo json_encode([
        'success' => true,
        'total'   => count($alertas),
        'alertas' => $alertas
    ]);
} catch (PDOException $e) {
    logSuspiciousActivity('DB error en alertas.php GET');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
