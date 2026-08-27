<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Estado de dispositivos';

// Umbrales de actividad (en horas)
$UMBRAL_EN_LINEA  = 6;    // última alerta en las últimas 6 h
$UMBRAL_VERDE     = 24;   // hasta 24 h → actividad moderada
$UMBRAL_BAJA      = 7 * 24; // más de 7 días → sin actividad

$sql = 'SELECT dispositivo,
               COUNT(*) AS total,
               SUM(CASE WHEN status = "pendiente" THEN 1 ELSE 0 END) AS pendientes,
               MAX(fecha_hora) AS ultima_alerta,
               (SELECT bateria FROM alertas a2
                 WHERE a2.dispositivo = alertas.dispositivo
                 ORDER BY a2.id DESC LIMIT 1) AS ultima_bateria
        FROM alertas
        WHERE dispositivo <> ""
        GROUP BY dispositivo
        ORDER BY ultima_alerta DESC';

$stmt = $pdo->query($sql);
$dispositivos = $stmt->fetchAll();

$ahora = time();

// ── Últimas alertas (línea de tiempo resumida) ─────────────
$stmtUlt = $pdo->query('SELECT id, dispositivo, bateria, fecha_hora, status FROM alertas ORDER BY id DESC LIMIT 8');
$recientes = $stmtUlt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="card mb-4">
    <div class="card-header">
        <span><i class="fas fa-satellite-dish"></i> Resumen de dispositivos</span>
        <span class="text-muted float-end"><?php echo count($dispositivos); ?> dispositivos registrados</span>
    </div>
    <div class="card-body">
        <?php if (empty($dispositivos)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay alertas registradas todavía. Los dispositivos aparecerán aquí.</p>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($dispositivos as $d): ?>
                <?php
                    $ultTs = $d['ultima_alerta'] ? strtotime($d['ultima_alerta']) : null;
                    $horas = $ultTs ? ($ahora - $ultTs) / 3600 : PHP_INT_MAX;
                    $bateria = (int)$d['ultima_bateria'];

                    if ($horas <= $UMBRAL_EN_LINEA)       { $estado = 'en_linea';       $badge = 'bg-success'; }
                    elseif ($horas <= $UMBRAL_VERDE)      { $estado = 'actividad_moderada'; $badge = 'bg-primary'; }
                    elseif ($horas <= $UMBRAL_BAJA)       { $estado = 'baja_actividad'; $badge = 'bg-warning'; }
                    else                                  { $estado = 'sin_actividad';  $badge = 'bg-secondary'; }

                    $nombresEstado = [
                        'en_linea'          => 'En línea',
                        'actividad_moderada'=> 'Actividad moderada',
                        'baja_actividad'    => 'Baja actividad',
                        'sin_actividad'     => 'Sin actividad',
                    ];
                ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1 text-truncate" title="<?php echo htmlspecialchars($d['dispositivo']); ?>">
                                    <i class="fas fa-mobile-alt text-primary"></i> <?php echo htmlspecialchars($d['dispositivo']); ?>
                                </h6>
                                <span class="badge <?php echo $badge; ?>"><?php echo $nombresEstado[$estado]; ?></span>
                            </div>
                            <div class="text-muted small">Última alerta:
                                <?php echo $ultTs
                                    ? htmlspecialchars(date('Y-m-d H:i', $ultTs)) . ' (' . round($horas) . ' h)'
                                    : '—'; ?>
                            </div>
                            <div class="mt-2 d-flex justify-content-between small">
                                <span><i class="fas fa-battery-half"></i> <?php echo $bateria; ?>%</span>
                                <span><?php echo (int)$d['total']; ?> alertas</span>
                                <span class="text-warning"><?php echo (int)$d['pendientes']; ?> pend.</span>
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar <?php echo $bateria < 30 ? 'bg-danger' : ($bateria < 60 ? 'bg-warning' : 'bg-success'); ?>"
                                     style="width: <?php echo max(0, min(100, $bateria)); ?>%;"></div>
                            </div>
                            <a href="alertas.php?dispositivo=<?php echo urlencode($d['dispositivo']); ?>"
                               class="btn btn-sm btn-outline-primary w-100 mt-2">Ver alertas</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span><i class="fas fa-list"></i> Últimas alertas</span>
        <a href="alertas.php" class="btn btn-sm btn-outline-primary float-end">Ver todas</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Dispositivo</th>
                    <th>Batería</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recientes)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Sin alertas recientes</td></tr>
                <?php else: ?>
                    <?php foreach ($recientes as $a): ?>
                        <tr>
                            <td><?php echo (int)$a['id']; ?></td>
                            <td><i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($a['dispositivo']); ?></td>
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

<div class="form-text mt-3">
    <i class="fas fa-info-circle"></i>
    Estado según la última alerta recibida: <span class="badge bg-success">En línea</span> &lt; 6 h ·
    <span class="badge bg-primary">Moderada</span> &lt; 24 h ·
    <span class="badge bg-warning">Baja</span> hasta 7 días ·
    <span class="badge bg-secondary">Inactivo</span> más de 7 días.
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>