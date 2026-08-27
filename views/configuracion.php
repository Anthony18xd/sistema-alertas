<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$titulo = 'Configuración';

// ── Backups existentes ────────────────────────────────────
$backupDir = __DIR__ . '/../backups';
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/alerta_backup_*');
    foreach ($files as $f) {
        $backups[] = [
            'nombre' => basename($f),
            'tamano' => round(filesize($f) / 1024, 1),
            'fecha'  => date('Y-m-d H:i:s', filemtime($f)),
            'path'   => $f,
        ];
    }
    usort($backups, fn ($a, $b) => strcmp($b['nombre'], $a['nombre']));
    $backups = array_slice($backups, 0, 15);
}

include __DIR__ . '/../includes/header.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);
$nombreSistema = htmlspecialchars($APLICACION['nombre_sistema'] ?? 'ALERTA');
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-cog"></i> Sistema</div>
            <div class="card-body">
                <div id="msgConfig"></div>
                <form id="formConfig">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <div class="mb-3">
                        <label for="nombre_sistema" class="form-label">Nombre del sistema</label>
                        <input type="text" class="form-control" id="nombre_sistema" name="nombre_sistema"
                               value="<?php echo $nombreSistema; ?>" required maxlength="40">
                        <div class="form-text">Se muestra en la barra lateral y en el título.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><i class="fas fa-database"></i> Respaldo de la base de datos</div>
            <div class="card-body">
                <p class="text-muted">
                    Genera una copia completa de la base de datos (usuarios, alertas, dispositivos, bitácora).
                </p>
                <form method="post" action="../api/backup.php" id="formBackup">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download"></i> Descargar respaldo ahora
                    </button>
                </form>
                <hr>
                <p class="mb-1"><strong>Respaldos automáticos (servidor)</strong></p>
                <p class="text-muted small mb-2">
                    En la computadora del servidor puedes programar un respaldo diario:<br>
                    <code>php dev/backup.php</code><br>
                    y copiar <code>backups/</code> a un sitio seguro. También puedes agregar una tarea
                    programada (cron) con la opción <code>--silent</code>.
                </p>
                <p class="text-muted small mb-0">
                    Restaurar: <code>php dev/restaurar_backup.php backups/archivo.db</code> (ver el script para detalles).
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-archive"></i> Últimos respaldos en el servidor</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No hay respaldos aún</td></tr>
                        <?php else: ?>
                            <?php foreach ($backups as $b): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($b['nombre']); ?></code></td>
                                    <td><?php echo $b['tamano']; ?> KB</td>
                                    <td><?php echo htmlspecialchars($b['fecha']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
(function () {
    var formConfig = document.getElementById('formConfig');
    var msg = document.getElementById('msgConfig');

    function mostrar(tipo, texto) {
        msg.innerHTML = '<div class="alert alert-' + tipo + '">' + texto + '</div>';
    }

    formConfig.addEventListener('submit', function (e) {
        e.preventDefault();
        fetch('../api/guardar_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: formConfig.csrf_token.value,
                nombre_sistema: formConfig.nombre_sistema.value
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            mostrar(res.success ? 'success' : 'danger',
                (res.success ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-exclamation-triangle"></i> ')
                + (res.mensaje || res.error || 'Error.'));
            if (res.success) {
                setTimeout(function () { location.reload(); }, 600);
            }
        })
        .catch(function () { mostrar('danger', 'Error de conexión.'); });
    });
})();
</script>
HTML;
include __DIR__ . '/../includes/footer.php';