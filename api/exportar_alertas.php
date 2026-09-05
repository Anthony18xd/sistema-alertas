<?php
/**
 * EXPORTAR ALERTAS A CSV — sesión autenticada + CSRF (form POST)
 * Usa los mismos filtros que el listado de alertas.
 * Protocolo seguro: BOM UTF-8 + sanitización contra CSV Injection.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';
require_once __DIR__ . '/../includes/reporte.php';

// Las descargas son binarias: nunca volcar warnings/deprecados al cuerpo
@ini_set('display_errors', '0');

// ── CSRF ─────────────────────────────────────────────────
$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

// ── Formato de salida ────────────────────────────────────
$formato = $_POST['formato'] ?? 'csv';
if (!in_array($formato, ['csv', 'xlsx', 'pdf'], true)) {
    $formato = 'csv';
}

// ── Filtros (idénticos a views/alertas.php) ──────────────
$estado     = $_POST['estado'] ?? 'todos';
if (!in_array($estado, ['pendiente', 'completado', 'todos'], true)) {
    $estado = 'todos';
}

$filtroDispositivo = trim($_POST['dispositivo'] ?? '');
$filtroFechaDesde  = $_POST['fecha_desde'] ?? '';
$filtroFechaHasta  = $_POST['fecha_hasta'] ?? '';
$busqueda          = trim($_POST['q'] ?? '');

// Validar formato de fechas
if ($filtroFechaDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaDesde)) {
    $filtroFechaDesde = '';
}
if ($filtroFechaHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaHasta)) {
    $filtroFechaHasta = '';
}

// ── Consulta ─────────────────────────────────────────────
$sql = 'SELECT id, dispositivo, numero, bateria, fecha_hora, latitud, longitud, status, nota, completado_por FROM alertas';
$params = [];
$cond = [];

if ($estado !== 'todos') {
    $cond[] = 'status = :estado';
    $params[':estado'] = $estado;
}
if ($filtroDispositivo !== '') {
    $cond[] = 'dispositivo = :disp';
    $params[':disp'] = $filtroDispositivo;
}
if ($busqueda !== '') {
    $cond[] = '(dispositivo LIKE :busq OR numero LIKE :busq)';
    $params[':busq'] = '%' . $busqueda . '%';
}
if ($filtroFechaDesde !== '') {
    $cond[] = 'fecha_hora >= :desde';
    $params[':desde'] = $filtroFechaDesde . ' 00:00:00';
}
if ($filtroFechaHasta !== '') {
    $cond[] = 'fecha_hora <= :hasta';
    $params[':hasta'] = $filtroFechaHasta . ' 23:59:59';
}
if ($cond) {
    $sql .= ' WHERE ' . implode(' AND ', $cond);
}
$sql .= ' ORDER BY id DESC LIMIT 50000';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alertas = $stmt->fetchAll();

registrarActividad('exportar_' . $formato, count($alertas) . ' alertas');

// ── Reportes Excel y PDF (con gráfico de uso) ─────────────
if ($formato === 'xlsx' || $formato === 'pdf') {
    $uso = reporte_uso_diario($pdo, $cond, $params);

    $total    = count($alertas);
    $pend     = 0;
    foreach ($alertas as $a) {
        if ($a['status'] === 'pendiente') $pend++;
    }
    $comp       = $total - $pend;
    $dispositivos = count(array_unique(array_column($alertas, 'dispositivo')));

    $resumen = [
        'total'        => $total,
        'pendientes'   => $pend,
        'completados'  => $comp,
        'dispositivos' => $dispositivos,
        'usuario'      => $_SESSION['username'] ?? '',
        'desde'        => $uso['desde'],
        'hasta'        => $uso['hasta'],
    ];

    $encabezados = [
        'ID', 'Dispositivo', 'Número', 'Batería (%)', 'Fecha y hora',
        'Latitud', 'Longitud', 'Estado', 'Nota', 'Completado por',
    ];
    $filas = [];
    foreach ($alertas as $a) {
        $filas[] = [
            $a['id'],
            $a['dispositivo'],
            $a['numero'],
            $a['bateria'],
            $a['fecha_hora'],
            $a['latitud'],
            $a['longitud'],
            $a['status'],
            $a['nota'] ?? '',
            $a['completado_por'] ?? '',
        ];
    }

    $nombreBase = 'reporte_alertas_' . date('Ymd_His');

    if ($formato === 'xlsx') {
        $bin = reporte_xlsx('REPORTE DE ALERTAS — ALERTA', $encabezados, $filas, $uso['dias'], $resumen);
        if ($bin === '') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No se pudo generar el archivo Excel']);
            exit;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreBase . '.xlsx"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $bin;
        exit;
    }

    // PDF
    $bin = reporte_pdf('REPORTE DE ALERTAS — ALERTA', $encabezados, $filas, $uso['dias'], $resumen);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.pdf"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $bin;
    exit;
}

// ── Cabeceras de descarga (CSV) ───────────────────────────
$fecha = date('Ymd_His');
$nombre = 'alertas_' . $fecha . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

// BOM UTF-8 para Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'ID', 'Dispositivo', 'Número', 'Batería (%)', 'Fecha y hora',
    'Latitud', 'Longitud', 'Estado', 'Nota', 'Completado por'
]);

$celdasSeguras = function (string $valor): string {
    // Evita CSV Injection (fórmulas que se ejecutan en Excel)
    if ($valor !== '' && in_array($valor[0], ['=', '+', '-', '@'], true)) {
        return "'" . $valor;
    }
    return $valor;
};

foreach ($alertas as $a) {
    $fila = [
        $a['id'],
        $celdasSeguras($a['dispositivo']),
        $celdasSeguras($a['numero']),
        $a['bateria'],
        $a['fecha_hora'],
        $a['latitud'],
        $a['longitud'],
        $a['status'],
        $celdasSeguras($a['nota'] ?? ''),
        $a['completado_por'] ?? ''
    ];
    fputcsv($out, $fila);
}

fclose($out);
exit;