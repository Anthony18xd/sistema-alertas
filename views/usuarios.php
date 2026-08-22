<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Usuarios y dispositivos';

// ── Usuarios del panel ────────────────────────────────────
$usuarios = $pdo->query('SELECT id, username, created_at FROM usuarios ORDER BY id')->fetchAll();

// ── Dispositivos con API key ──────────────────────────────
$dispositivos = $pdo->query('SELECT id, nombre_dispositivo, api_key, activa, created_at, last_used_at FROM api_keys ORDER BY id')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-users"></i> Usuarios del panel</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>ID</th><th>Usuario</th><th>Creado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?php echo (int)$u['id']; ?></td>
                                <td><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-key"></i> Dispositivos (API Keys)</span>
                <form method="post" action="../api/generate_key.php" class="d-flex gap-2" id="formKey">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre del dispositivo" required maxlength="150">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Dispositivo</th><th>Key</th><th>Estado</th><th>Último uso</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dispositivos)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No hay dispositivos registrados</td></tr>
                        <?php else: ?>
                            <?php foreach ($dispositivos as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['nombre_dispositivo']); ?></td>
                                    <td><code title="<?php echo htmlspecialchars($d['api_key']); ?>"><?php echo htmlspecialchars(substr($d['api_key'], 0, 12)); ?>…</code></td>
                                    <td>
                                        <span class="badge <?php echo $d['activa'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $d['activa'] ? 'activa' : 'inactiva'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($d['last_used_at'] ?? 'nunca'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
