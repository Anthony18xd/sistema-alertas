<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Dashboard';

// ── Estadísticas estáticas (tarjetas) ──────────────────────
$total       = (int)$pdo->query('SELECT COUNT(*) AS c FROM alertas')->fetch()['c'];
$pendientes  = (int)$pdo->query("SELECT COUNT(*) AS c FROM alertas WHERE status = 'pendiente'")->fetch()['c'];
$completadas = (int)$pdo->query("SELECT COUNT(*) AS c FROM alertas WHERE status = 'completado'")->fetch()['c'];
$stmtHoy = $pdo->prepare('SELECT COUNT(*) AS c FROM alertas WHERE fecha_hora >= :inicio');
$stmtHoy->execute([':inicio' => date('Y-m-d 00:00:00')]);
$hoy = (int)$stmtHoy->fetch()['c'];

$total_dispositivos = (int)$pdo->query('SELECT COUNT(DISTINCT dispositivo) AS c FROM alertas WHERE dispositivo <> ""')->fetch()['c'];

// ── Últimas alertas ────────────────────────────────────────
$stmt = $pdo->query('SELECT id, dispositivo, numero, bateria, fecha_hora, status FROM alertas ORDER BY id DESC LIMIT 10');
$recientes = $stmt->fetchAll();

$extra_js = '<script src="/assets/lib/chartjs/chart.umd.min.js"></script>';

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
            <div class="stat-icon bg-info-subtle rounded-circle"><i class="fas fa-satellite-dish"></i></div>
            <div class="stat-info">
                <h5>Hoy / Dispositivos</h5>
                <h3><?php echo $hoy; ?> / <?php echo $total_dispositivos; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-line"></i> Alertas — últimos 7 días</div>
            <div class="card-body">
                <canvas id="grafica7d" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-pie"></i> Alertas por dispositivo</div>
            <div class="card-body">
                <canvas id="graficaDev" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<?php if ($total_dispositivos > 0): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-satellite-dish"></i> Estado de dispositivos</span>
        <a href="estado_dispositivos.php" class="btn btn-sm btn-outline-primary">Ver detalle</a>
    </div>
    <div class="card-body">
        <?php
        $stmtDev = $pdo->query(
            'SELECT dispositivo,
                    MAX(fecha_hora) AS ultima,
                    (SELECT bateria FROM alertas a2 WHERE a2.dispositivo = alertas.dispositivo ORDER BY a2.id DESC LIMIT 1) AS bateria
             FROM alertas WHERE dispositivo <> "" GROUP BY dispositivo ORDER BY ultima DESC LIMIT 8'
        );
        $dispositivos = $stmtDev->fetchAll();
        ?>
        <div class="row g-2">
            <?php foreach ($dispositivos as $d): ?>
                <?php
                    $dt = $d['ultima'] ? strtotime($d['ultima']) : null;
                    $horas = $dt ? (time() - $dt) / 3600 : PHP_INT_MAX;
                    $bat = (int)$d['bateria'];
                    if ($horas <= 6)       { $badge = 'bg-success'; $txt = 'En línea'; }
                    elseif ($horas <= 24)  { $badge = 'bg-primary'; $txt = 'Activo'; }
                    elseif ($horas <= 168) { $badge = 'bg-warning'; $txt = 'Baja actividad'; }
                    else                   { $badge = 'bg-secondary'; $txt = 'Inactivo'; }
                    $bar = $bat < 30 ? 'bg-danger' : ($bat < 60 ? 'bg-warning' : 'bg-success');
                ?>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-2 d-flex align-items-center gap-2">
                        <i class="fas fa-mobile-alt text-primary"></i>
                        <div class="flex-grow-1 text-truncate">
                            <div class="text-truncate small fw-semibold" title="<?php echo htmlspecialchars($d['dispositivo']); ?>"><?php echo htmlspecialchars($d['dispositivo']); ?></div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?php echo $badge; ?>"><?php echo $txt; ?></span>
                                <span class="small text-muted"><?php echo $bat; ?>%</span>
                            </div>
                            <div class="progress mt-1" style="height: 5px;">
                                <div class="progress-bar <?php echo $bar; ?>" style="width: <?php echo max(0, min(100, $bat)); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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

<script>
(function () {
    var canvas7 = document.getElementById('grafica7d');
    var canvasDev = document.getElementById('graficaDev');
    if (!canvas7 || !window.Chart) return;

    fetch('../api/estadisticas.php')
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) return;

            // ── Líneas 7 días ──
            var dias = res.ultimos7dias;
            var fechas = dias.map(function (d) {
                var partes = d.fecha.split('-');
                return partes[2] + '/' + partes[1];
            });
            new Chart(canvas7, {
                type: 'line',
                data: {
                    labels: fechas,
                    datasets: [
                        {
                            label: 'Pendientes',
                            data: dias.map(function (d) { return d.pendientes; }),
                            borderColor: '#f0ad4e43',
                            backgroundColor: 'rgba(240, 173, 78, 0.15)',
                            tension: 0.3, fill: true
                        },
                        {
                            label: 'Completadas',
                            data: dias.map(function (d) { return d.completados; }),
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.10)',
                            tension: 0.3, fill: true
                        },
                        {
                            label: 'Total',
                            data: dias.map(function (d) { return d.total; }),
                            borderColor: '#0d6efd',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { boxWidth: 12 } } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            // ── Dona por dispositivo ──
            var devs = res.por_dispositivo;
            if (!devs.length) {
                canvasDev.parentElement.innerHTML = '<p class="text-muted text-center py-4">Sin datos aún</p>';
                return;
            }
            new Chart(canvasDev, {
                type: 'doughnut',
                data: {
                    labels: devs.map(function (d) { return d.dispositivo; }),
                    datasets: [{
                        data: devs.map(function (d) { return d.total; })
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                    }
                }
            });
        })
        .catch(function () {});
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>