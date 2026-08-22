<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Dashboard';

// ── Estadísticas ──────────────────────────────────────────
$total      = (int)$pdo->query('SELECT COUNT(*) AS c FROM alertas')->fetch()['c'];
$pendientes = (int)$pdo->query("SELECT COUNT(*) AS c FROM alertas WHERE status = 'pendiente'")->fetch()['c'];
$completadas= (int)$pdo->query("SELECT COUNT(*) AS c FROM alertas WHERE status = 'completado'")->fetch()['c'];
$stmtHoy = $pdo->prepare('SELECT COUNT(*) AS c FROM alertas WHERE fecha_hora >= :inicio');
$stmtHoy->execute([':inicio' => date('Y-m-d 00:00:00')]);
$hoy = (int)$stmtHoy->fetch()['c'];

// ── Últimas 10 alertas ────────────────────────────────────
$stmt = $pdo->query('SELECT id, dispositivo, numero, bateria, fecha_hora, latitud, longitud, status FROM alertas ORDER BY id DESC LIMIT 10');
$recientes = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-subtle rounded-circle"><i class="fas fa-bell"></i></div>
            <div class="stat-info">
                <h5>Total alertas</h5>
                <h3><?php echo $total; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="stat-icon bg-warning-subtle rounded-circle"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <h5>Pendientes</h5>
                <h3><?php echo $pendientes; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-subtle rounded-circle"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h5>Completadas</h5>
                <h3><?php echo $completadas; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-subtle rounded-circle"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <h5>Hoy</h5>
                <h3><?php echo $hoy; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Últimas alertas</span>
        <a href="alertas.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
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
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recientes)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay alertas registradas</td></tr>
                <?php else: ?>
                    <?php foreach ($recientes as $a): ?>
                        <tr>
                            <td><?php echo (int)$a['id']; ?></td>
                            <td><i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($a['dispositivo']); ?></td>
                            <td><?php echo htmlspecialchars($a['numero']); ?></td>
                            <td><?php echo (int)$a['bateria']; ?>%</td>
                            <td><?php echo htmlspecialchars($a['fecha_hora']); ?></td>
                            <td>
                                <span class="badge <?php echo $a['status'] === 'pendiente' ? 'bg-warning' : 'bg-success'; ?>">
                                    <?php echo htmlspecialchars($a['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
