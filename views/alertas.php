<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Alertas';

// ── Filtro de estado ──────────────────────────────────────
$filtro = $_GET['status'] ?? 'todos';
if (!in_array($filtro, ['pendiente', 'completado', 'todos'])) {
    $filtro = 'todos';
}

// ── Consulta ──────────────────────────────────────────────
$sql = 'SELECT id, dispositivo, numero, bateria, fecha_hora, latitud, longitud, status FROM alertas';
$params = [];
if ($filtro !== 'todos') {
    $sql .= ' WHERE status = :status';
    $params[':status'] = $filtro;
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alertas = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-list"></i> Alertas (<?php echo count($alertas); ?>)</span>
        <div class="btn-group btn-group-sm">
            <a href="?status=todos" class="btn btn-outline-secondary <?php echo $filtro === 'todos' ? 'active' : ''; ?>">Todas</a>
            <a href="?status=pendiente" class="btn btn-outline-secondary <?php echo $filtro === 'pendiente' ? 'active' : ''; ?>">Pendientes</a>
            <a href="?status=completado" class="btn btn-outline-secondary <?php echo $filtro === 'completado' ? 'active' : ''; ?>">Completadas</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Dispositivo</th>
                    <th>Número</th>
                    <th>Batería</th>
                    <th>Fecha</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alertas)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay alertas</td></tr>
                <?php else: ?>
                    <?php foreach ($alertas as $a): ?>
                        <tr>
                            <td><?php echo (int)$a['id']; ?></td>
                            <td><i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($a['dispositivo']); ?></td>
                            <td><?php echo htmlspecialchars($a['numero']); ?></td>
                            <td><?php echo (int)$a['bateria']; ?>%</td>
                            <td><?php echo htmlspecialchars($a['fecha_hora']); ?></td>
                            <td>
                                <a href="https://www.google.com/maps?q=<?php echo $a['latitud']; ?>,<?php echo $a['longitud']; ?>"
                                   target="_blank" rel="noopener" class="text-decoration-none">
                                    <i class="fas fa-map-marker-alt"></i> Ver
                                </a>
                            </td>
                            <td>
                                <span class="badge <?php echo $a['status'] === 'pendiente' ? 'bg-warning' : 'bg-success'; ?>">
                                    <?php echo htmlspecialchars($a['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['status'] === 'pendiente'): ?>
                                    <button class="btn btn-sm btn-success btn-completar" data-id="<?php echo (int)$a['id']; ?>">
                                        <i class="fas fa-check"></i> Completar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extra_js = <<<HTML
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-completar');
    if (!btn) return;
    var id = btn.dataset.id;
    if (!confirm('¿Marcar la alerta #' + id + ' como completada?')) return;
    fetch('../api/cambiar_estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id, 10), status: 'completado' })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error al actualizar');
        }
    })
    .catch(function () { alert('Error de conexión'); });
});
</script>
HTML;
include __DIR__ . '/../includes/footer.php'; ?>
