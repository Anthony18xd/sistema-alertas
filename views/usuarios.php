<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Usuarios y dispositivos';
$esAdmin = esAdmin();
$esRoot  = esRoot();

// ── Usuarios del panel ────────────────────────────────────
$usuarios = $pdo->query('SELECT id, username, rol, activo, created_at FROM usuarios ORDER BY id')->fetchAll();

// ── Dispositivos con API key ──────────────────────────────
$dispositivos = $pdo->query('SELECT id, nombre_dispositivo, api_key, activa, created_at, last_used_at FROM api_keys ORDER BY id')->fetchAll();

$tiempoLimite = time() - 120; // 2 minutos sin reportar = "sin conexión"

include __DIR__ . '/../includes/header.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fas fa-users"></i> Usuarios del panel</span>
                <?php if ($esAdmin): ?>
                    <button class="btn btn-sm btn-primary" id="btnNuevoUsuario">
                        <i class="fas fa-user-plus"></i> Agregar usuario
                    </button>
                <?php else: ?>
                    <a href="cuenta.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-key"></i> Cambiar mi contraseña</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Creado</th><?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="<?php echo $esAdmin ? 6 : 5; ?>" class="text-center text-muted py-4">No hay usuarios registrados</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u):
                                $esMismo = (int)$u['id'] === (int)$_SESSION['user_id'];
                            ?>
                                <tr>
                                    <td><?php echo (int)$u['id']; ?></td>
                                    <td>
                                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($u['username']); ?>
                                        <?php if ($esMismo): ?><span class="badge bg-info text-dark ms-1">tú</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $u['rol'] === 'root' ? 'bg-danger' : ($u['rol'] === 'admin' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <?php echo htmlspecialchars($u['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $u['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $u['activo'] ? 'activo' : 'inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                                    <?php if ($esAdmin && ($u['rol'] !== 'root' || $esRoot)): ?>
                                        <td>
                                            <?php if (!$esMismo): ?>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-outline-primary btn-editar" data-id="<?php echo (int)$u['id']; ?>" data-usuario="<?php echo htmlspecialchars($u['username']); ?>" data-rol="<?php echo htmlspecialchars($u['rol']); ?>" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary btn-reset" data-id="<?php echo (int)$u['id']; ?>" data-usuario="<?php echo htmlspecialchars($u['username']); ?>" title="Restablecer contraseña">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm <?php echo $u['activo'] ? 'btn-outline-warning' : 'btn-outline-success'; ?> btn-activo" data-id="<?php echo (int)$u['id']; ?>" data-usuario="<?php echo htmlspecialchars($u['username']); ?>" data-activo="<?php echo (int)$u['activo']; ?>" title="<?php echo $u['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="fas fa-<?php echo $u['activo'] ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="<?php echo (int)$u['id']; ?>" data-usuario="<?php echo htmlspecialchars($u['username']); ?>" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($esAdmin): ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-key"></i> Dispositivos (API Keys)</span>
                <button class="btn btn-sm btn-primary" id="btnNuevaKey"><i class="fas fa-plus"></i> Nueva key</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Dispositivo</th><th>Key</th><th>Conexión</th><th>Estado</th><th>Último uso</th>
                            <?php if ($esAdmin): ?><th>Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dispositivos)): ?>
                            <tr><td colspan="<?php echo $esAdmin ? 6 : 5; ?>" class="text-center text-muted py-4">No hay dispositivos registrados</td></tr>
                        <?php else: ?>
                            <?php foreach ($dispositivos as $d):
                                $online = $d['last_used_at'] && strtotime($d['last_used_at']) > $tiempoLimite;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['nombre_dispositivo']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <code title="<?php echo htmlspecialchars($d['api_key']); ?>"><?php echo htmlspecialchars(substr($d['api_key'], 0, 12)); ?>…</code>
                                            <button class="btn btn-sm btn-outline-secondary btn-copiar-key" data-key="<?php echo htmlspecialchars($d['api_key'], ENT_QUOTES); ?>" title="Copiar key completa">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $online ? 'bg-success' : 'bg-danger'; ?>">
                                            <i class="fas fa-<?php echo $online ? 'wifi' : 'exclamation-circle'; ?>"></i>
                                            <?php echo $online ? 'en línea' : 'sin conexión'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $d['activa'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $d['activa'] ? 'activa' : 'inactiva'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($d['last_used_at'] ?? 'nunca'); ?></td>
                                    <?php if ($esAdmin): ?>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm <?php echo $d['activa'] ? 'btn-outline-warning' : 'btn-outline-success'; ?> btn-key-activo" data-id="<?php echo (int)$d['id']; ?>" data-nombre="<?php echo htmlspecialchars($d['nombre_dispositivo']); ?>" data-activa="<?php echo (int)$d['activa']; ?>" title="<?php echo $d['activa'] ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo $d['activa'] ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-key-eliminar" data-id="<?php echo (int)$d['id']; ?>" data-nombre="<?php echo htmlspecialchars($d['nombre_dispositivo']); ?>" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($esAdmin): ?>
<!-- ── Modal Agregar / Editar usuario ─────────────────────── -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formUsuario">
                <input type="hidden" id="uId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsuarioTitulo">Agregar usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <div id="msgUsuario"></div>
                    <div class="mb-3">
                        <label for="uUsername" class="form-label">Nombre de usuario</label>
                        <input type="text" class="form-control" id="uUsername" name="username" required maxlength="50"
                               pattern="[a-zA-Z0-9_.]{3,50}" title="3-50 caracteres: letras, números, punto o guion bajo">
                    </div>
                    <div class="mb-3">
                        <label for="uRol" class="form-label">Rol</label>
                        <select class="form-select" id="uRol" name="rol">
                            <option value="operador">Operador (solo consulta)</option>
                            <option value="admin">Administrador (control total)</option>
                            <?php if ($esRoot): ?>
                            <option value="root">Superadministrador (root - gestiona alertas)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="campoPassword">
                        <label for="uPassword" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="uPassword" name="password" maxlength="128" autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres.</div>
                    </div>
                    <div class="mb-0" id="campoConfirmar">
                        <label for="uPassword2" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="uPassword2" maxlength="128" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarUsuario">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal Resetear contraseña ─────────────────────────── -->
<div class="modal fade" id="modalReset" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formReset">
                <div class="modal-header">
                    <h5 class="modal-title">Restablecer contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rId" value="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <div id="msgReset"></div>
                    <p class="text-muted">Nueva contraseña para <strong id="rUsername"></strong></p>
                    <div class="mb-3">
                        <label for="rPassword" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" id="rPassword" name="password" required minlength="8" maxlength="128" autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label for="rPassword2" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="rPassword2" required minlength="8" maxlength="128" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal Nueva API Key ───────────────────────────────── -->
<div class="modal fade" id="modalKey" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formKey">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <div id="msgKey"></div>
                    <div class="mb-3" id="campoNombreKey">
                        <label for="nKey" class="form-label">Nombre del dispositivo</label>
                        <input type="text" class="form-control" id="nKey" name="nombre" maxlength="150" placeholder="ej. Radiotaxi Centro">
                    </div>
                    <div id="resultadoKey" style="display:none;">
                        <label class="form-label">API Key (cópiala ahora, no se vuelve a mostrar)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="keyGenerada" readonly>
                            <button type="button" class="btn btn-outline-primary" id="btnCopiarGenerada"><i class="fas fa-copy"></i> Copiar</button>
                        </div>
                        <div class="form-text mt-2">Envíala en el encabezado <code>X-API-Key</code> desde la app.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btnGenerarKey">Generar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$js = '';

if ($esAdmin) {
$js .= <<<'HTML'

function copiarTexto(texto, okFn) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(okFn).catch(function () { fallbackCopiar(texto, okFn); });
    } else {
        fallbackCopiar(texto, okFn);
    }
}
function fallbackCopiar(texto, okFn) {
    var ta = document.createElement('textarea');
    ta.value = texto;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); okFn(); } catch (e) {}
    document.body.removeChild(ta);
}

var modalUsuarioEl = document.getElementById('modalUsuario');
var modalUsuario = bootstrap.Modal.getOrCreateInstance(modalUsuarioEl);
var modalResetEl = document.getElementById('modalReset');
var modalReset = bootstrap.Modal.getOrCreateInstance(modalResetEl);
var modalKeyEl = document.getElementById('modalKey');
var modalKey = bootstrap.Modal.getOrCreateInstance(modalKeyEl);

function msjUsuario(tipo, texto) {
    document.getElementById('msgUsuario').innerHTML = '<div class="alert alert-' + tipo + '">' + texto + '</div>';
}
function msjReset(tipo, texto) {
    document.getElementById('msgReset').innerHTML = '<div class="alert alert-' + tipo + '">' + texto + '</div>';
}
function msjKey(tipo, texto) {
    document.getElementById('msgKey').innerHTML = '<div class="alert alert-' + tipo + '">' + texto + '</div>';
}

function postApi(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
}
var csrfToken = document.querySelector('#formUsuario input[name=csrf_token]').value || document.querySelector('#formKey input[name=csrf_token]').value;

// ── Usuarios: agregar/editar ──────────────────────────────
document.getElementById('btnNuevoUsuario').addEventListener('click', function () {
    document.getElementById('modalUsuarioTitulo').textContent = 'Agregar usuario';
    document.getElementById('uId').value = '';
    document.getElementById('uUsername').value = '';
    document.getElementById('uRol').value = 'operador';
    document.getElementById('uPassword').value = '';
    document.getElementById('uPassword2').value = '';
    document.getElementById('campoPassword').style.display = '';
    document.getElementById('campoConfirmar').style.display = '';
    document.getElementById('uPassword').required = true;
    document.getElementById('uPassword2').required = true;
    document.getElementById('msgUsuario').innerHTML = '';
    modalUsuario.show();
});

document.querySelectorAll('.btn-editar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('modalUsuarioTitulo').textContent = 'Editar usuario';
        document.getElementById('uId').value = btn.dataset.id;
        document.getElementById('uUsername').value = btn.dataset.usuario;
        document.getElementById('uRol').value = btn.dataset.rol;
        document.getElementById('uPassword').value = '';
        document.getElementById('uPassword2').value = '';
        document.getElementById('campoPassword').style.display = 'none';
        document.getElementById('campoConfirmar').style.display = 'none';
        document.getElementById('uPassword').required = false;
        document.getElementById('uPassword2').required = false;
        document.getElementById('msgUsuario').innerHTML = '';
        modalUsuario.show();
    });
});

document.getElementById('formUsuario').addEventListener('submit', function (e) {
    e.preventDefault();
    var id = document.getElementById('uId').value;
    var p1 = document.getElementById('uPassword').value;
    var p2 = document.getElementById('uPassword2').value;

    if (!id && p1 !== p2) {
        msjUsuario('danger', 'Las contraseñas no coinciden.');
        return;
    }
    if (!id && p1.length < 8) {
        msjUsuario('danger', 'La contraseña debe tener mínimo 8 caracteres.');
        return;
    }

    var body = {
        csrf_token: e.target.csrf_token.value,
        username: document.getElementById('uUsername').value,
        rol: document.getElementById('uRol').value
    };
    body.accion = id ? 'editar' : 'crear';
    if (id) { body.id = parseInt(id, 10); }
    else { body.password = p1; }

    postApi('../api/gestionar_usuario.php', body).then(function (res) {
        if (res.success) { location.reload(); }
        else { msjUsuario('danger', res.error || 'Error al guardar.'); }
    }).catch(function () { msjUsuario('danger', 'Error de conexión.'); });
});

// ── Usuarios: reset / activo / eliminar ───────────────────
document.querySelectorAll('.btn-reset').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('rId').value = btn.dataset.id;
        document.getElementById('rUsername').textContent = btn.dataset.usuario;
        document.getElementById('rPassword').value = '';
        document.getElementById('rPassword2').value = '';
        document.getElementById('msgReset').innerHTML = '';
        modalReset.show();
    });
});

document.getElementById('formReset').addEventListener('submit', function (e) {
    e.preventDefault();
    var p1 = document.getElementById('rPassword').value;
    var p2 = document.getElementById('rPassword2').value;
    if (p1 !== p2) { msjReset('danger', 'Las contraseñas no coinciden.'); return; }
    if (p1.length < 8) { msjReset('danger', 'La contraseña debe tener mínimo 8 caracteres.'); return; }

    postApi('../api/gestionar_usuario.php', {
        csrf_token: e.target.csrf_token.value,
        accion: 'reset_password',
        id: parseInt(document.getElementById('rId').value, 10),
        password: p1
    }).then(function (res) {
        if (res.success) { modalReset.hide(); location.reload(); }
        else { msjReset('danger', res.error || 'Error al restablecer.'); }
    }).catch(function () { msjReset('danger', 'Error de conexión.'); });
});

document.querySelectorAll('.btn-activo').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var activo = btn.dataset.activo === '1';
        var accion = activo ? 'desactivar' : 'activar';
        if (!confirm('¿' + accion.charAt(0).toUpperCase() + accion.slice(1) + ' a "' + btn.dataset.usuario + '"?')) return;
        postApi('../api/gestionar_usuario.php', {
            csrf_token: csrfToken,
            accion: 'activo',
            id: parseInt(btn.dataset.id, 10),
            activo: activo ? 0 : 1
        }).then(function (res) {
            if (res.success) { location.reload(); }
            else { alert(res.error || 'Error al cambiar el estado.'); }
        }).catch(function () { alert('Error de conexión.'); });
    });
});

document.querySelectorAll('.btn-eliminar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (!confirm('¿Eliminar definitivamente a "' + btn.dataset.usuario + '"?')) return;
        postApi('../api/gestionar_usuario.php', {
            csrf_token: csrfToken,
            accion: 'eliminar',
            id: parseInt(btn.dataset.id, 10)
        }).then(function (res) {
            if (res.success) { location.reload(); }
            else { alert(res.error || 'Error al eliminar.'); }
        }).catch(function () { alert('Error de conexión.'); });
    });
});

// ── Copiar key completa ───────────────────────────────────
document.querySelectorAll('.btn-copiar-key').forEach(function (btn) {
    btn.addEventListener('click', function () {
        copiarTexto(btn.dataset.key, function () {
            var icon = btn.querySelector('i');
            icon.className = 'fas fa-check';
            setTimeout(function () { icon.className = 'fas fa-copy'; }, 1200);
        });
    });
});

// ── API Keys: nueva / activar / eliminar ──────────────────
document.getElementById('btnNuevaKey').addEventListener('click', function () {
    document.getElementById('formKey').reset();
    document.getElementById('campoNombreKey').style.display = '';
    document.getElementById('resultadoKey').style.display = 'none';
    document.getElementById('btnGenerarKey').style.display = '';
    document.getElementById('msgKey').innerHTML = '';
    modalKey.show();
});

document.getElementById('formKey').addEventListener('submit', function (e) {
    e.preventDefault();
    var nombre = document.getElementById('nKey').value;
    if (nombre.length < 2) { msjKey('danger', 'El nombre debe tener al menos 2 caracteres.'); return; }

    postApi('../api/gestionar_key.php', {
        csrf_token: e.target.csrf_token.value,
        accion: 'crear',
        nombre: nombre
    }).then(function (res) {
        if (res.success) {
            document.getElementById('keyGenerada').value = res.api_key;
            document.getElementById('campoNombreKey').style.display = 'none';
            document.getElementById('resultadoKey').style.display = '';
            document.getElementById('btnGenerarKey').style.display = 'none';
            msjKey('success', '<i class="fas fa-check-circle"></i> ' + (res.mensaje || 'Key generada.'));
        } else {
            msjKey('danger', res.error || 'Error al generar.');
        }
    }).catch(function () { msjKey('danger', 'Error de conexión.'); });
});

document.getElementById('btnCopiarGenerada').addEventListener('click', function () {
    var key = document.getElementById('keyGenerada').value;
    copiarTexto(key, function () {
        document.getElementById('btnCopiarGenerada').innerHTML = '<i class="fas fa-check"></i> Copiada';
        setTimeout(function () { document.getElementById('btnCopiarGenerada').innerHTML = '<i class="fas fa-copy"></i> Copiar'; }, 1500);
    });
});

document.querySelectorAll('.btn-key-activo').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var activa = btn.dataset.activa === '1';
        var accion = activa ? 'desactivar' : 'activar';
        if (!confirm('¿' + accion.charAt(0).toUpperCase() + accion.slice(1) + ' el dispositivo "' + btn.dataset.nombre + '"?')) return;
        postApi('../api/gestionar_key.php', {
            csrf_token: csrfToken,
            accion: 'activo',
            id: parseInt(btn.dataset.id, 10),
            activo: activa ? 0 : 1
        }).then(function (res) {
            if (res.success) { location.reload(); }
            else { alert(res.error || 'Error al cambiar el estado.'); }
        }).catch(function () { alert('Error de conexión.'); });
    });
});

document.querySelectorAll('.btn-key-eliminar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (!confirm('¿Eliminar el dispositivo "' + btn.dataset.nombre + '"? La app dejará de reportar.')) return;
        postApi('../api/gestionar_key.php', {
            csrf_token: csrfToken,
            accion: 'eliminar',
            id: parseInt(btn.dataset.id, 10)
        }).then(function (res) {
            if (res.success) { location.reload(); }
            else { alert(res.error || 'Error al eliminar.'); }
        }).catch(function () { alert('Error de conexión.'); });
    });
});
HTML;
}

$extra_js = '<script>' . $js . '</script>';
include __DIR__ . '/../includes/footer.php';