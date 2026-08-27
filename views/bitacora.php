<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

requireAdmin();

$titulo = 'Bitácora (auditoría)';

// ── Filtros ───────────────────────────────────────────────
$porUsuario = trim($_GET['usuario'] ?? '');
$porAccion  = trim($_GET['accion'] ?? '');
$desde      = $_GET['desde'] ?? '';
$hasta      = $_GET['hasta'] ?? '';

if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';

// ── Acciones distintas disponibles ────────────────────────
$acciones = $pdo->query('SELECT DISTINCT accion FROM logs_actividad ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);

// ── Consulta paginada ─────────────────────────────────────
$sql = 'SELECT * FROM logs_actividad';
$params = [];
$cond = [];

if ($porUsuario !== '') {
    $cond[] = 'username = :usuario';
    $params[':usuario'] = mb_substr($porUsuario, 0, 50);
}
if ($porAccion !== '') {
    $cond[] = 'accion = :accion';
    $params[':accion'] = mb_substr($porAccion, 0, 100);
}
if ($desde !== '') {
    $cond[] = 'created_at >= :desde';
    $params[':desde'] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $cond[] = 'created_at <= :hasta';
    $params[':hasta'] = $hasta . ' 23:59:59';
}
if ($cond) {
    $sql .= ' WHERE ' . implode(' AND ', $cond);
}

$totalStmt = $pdo->prepare(str_replace('SELECT *', 'SELECT COUNT(*) AS c', $sql));
$totalStmt->execute($params);
$total = (int)$totalStmt->fetch()['c'];

$porPagina = 50;
$pagina = max(1, (int)($_GET['pag'] ?? 1));
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT ' . (int)$porPagina . ' OFFSET ' . (int)$offset);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// ── Iconos por acción ─────────────────────────────────────
$iconos = [
    'login'            => 'fa-sign-in-alt',
    'login_fallido'    => 'fa-times-circle',
    'logout'           => 'fa-sign-out-alt',
    'usuario_creado'   => 'fa-user-plus',
    'usuario_editado'  => 'fa-user-edit',
    'usuario_eliminado'=> 'fa-user-minus',
    'usuario_activo'   => 'fa-user-check',
    'usuario_inactivo' => 'fa-user-slash',
    'reset_password'   => 'fa-key',
    'password_cambio'  => 'fa-lock',
    'preferencias'     => 'fa-sliders-h',
    'config_sistema'   => 'fa-cog',
    'key_creada'       => 'fa-key',
    'key_activada'     => 'fa-check-circle',
    'key_desactivada'  => 'fa-ban',
    'key_eliminada'    => 'fa-trash-alt',
    'alerta_completada'=> 'fa-check-double',
    'nota_alerta'      => 'fa-sticky-note',
    'exportar_csv'     => 'fa-file-csv',
    'backup_web'       => 'fa-download',
    'backup_cli'       => 'fa-server',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header"><i class="fas fa-history"></i> Registro de acciones (<?php echo $total; ?>)</div>
    <div class="card-body pb-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-auto">
                <label class="form-label mb-1">Usuario</label>
                <input type="text" name="usuario" class="form-control form-control-sm" value="<?php echo htmlspecialchars($porUsuario); ?>" maxlength="50" placeholder="ej. admin">
            </div>
            <div class="col-md-auto">
                <label class="form-label mb-1">Acción</label>
                <select name="accion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $a === $porAccion ? 'selected' : ''; ?>><?php echo htmlspecialchars($a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <label class="form-label mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($desde); ?>">
            </div>
            <div class="col-md-auto">
                <label class="form-label mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hasta); ?>">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="bitacora.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php if (empty($registros)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Sin registros con esos filtros</td></tr>
                <?php else: ?>
                    <?php foreach ($registros as $r): ?>
                        <tr>
                            <td class="text-nowrap"><?php echo htmlspecialchars($r['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($r['username'] ?: '—'); ?></td>
                            <td>
                                <i class="fas <?php echo $iconos[$r['accion']] ?? 'fa-circle'; ?>"></i>
                                <?php echo htmlspecialchars($r['accion']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['detalle'] ?: '—'); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($r['ip']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPaginas > 1): ?>
        <div class="card-body pt-2">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php
                                $q = http_build_query(array_filter([
                                    'usuario' => $porUsuario, 'accion' => $porAccion,
                                    'desde' => $desde, 'hasta' => $hasta, 'pag' => $p,
                                ], fn ($v) => $v !== ''));
                                echo htmlspecialchars($q);
                            ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>