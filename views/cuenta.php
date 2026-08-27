<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Mi cuenta';

// ── Datos de la cuenta ─────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, username, rol, activo, created_at FROM usuarios WHERE id = :id');
$stmt->execute([':id' => $_SESSION['user_id']]);
$cuenta = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-card"></i> Datos de la cuenta</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <th class="w-25">Usuario</th>
                            <td><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($cuenta['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Rol</th>
                            <td>
                                <span class="badge <?php echo $cuenta['rol'] === 'admin' ? 'bg-warning' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($cuenta['rol']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Cuenta desde</th>
                            <td><?php echo htmlspecialchars($cuenta['created_at']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-key"></i> Cambiar contraseña</div>
            <div class="card-body">
                <div id="msgCambio"></div>
                <form id="formCambio" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                    <div class="mb-3">
                        <label for="password_actual" class="form-label">Contraseña actual</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password_actual" name="password_actual" required maxlength="128" autocomplete="current-password">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password_nueva" class="form-label">Nueva contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock-open"></i></span>
                            <input type="password" class="form-control" id="password_nueva" name="password_nueva" required minlength="8" maxlength="128" autocomplete="new-password">
                        </div>
                        <div class="form-text">Mínimo 8 caracteres.</div>
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmar" class="form-label">Confirmar nueva contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-check"></i></span>
                            <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" required minlength="8" maxlength="128" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<HTML
<script>
(function () {
    var form = document.getElementById('formCambio');
    var msg = document.getElementById('msgCambio');

    function mostrar(tipo, texto) {
        msg.innerHTML = '<div class="alert alert-' + tipo + '">' + texto + '</div>';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button[type=submit]');
        var data = {
            csrf_token: form.csrf_token.value,
            password_actual: form.password_actual.value,
            password_nueva: form.password_nueva.value,
            password_confirmar: form.password_confirmar.value
        };
        btn.disabled = true;
        fetch('../api/cambiar_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                mostrar('success', '<i class="fas fa-check-circle"></i> ' + (res.mensaje || 'Contraseña actualizada.'));
                form.reset();
            } else {
                mostrar('danger', '<i class="fas fa-exclamation-triangle"></i> ' + (res.error || 'Error al actualizar la contraseña.'));
            }
        })
        .catch(function () {
            mostrar('danger', 'Error de conexión. Intenta de nuevo.');
        })
        .finally(function () { btn.disabled = false; });
    });
})();
</script>
HTML;
include __DIR__ . '/../includes/footer.php';