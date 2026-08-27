<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$titulo = 'Alertas';
$esRoot = esRoot();

// ── Filtros ────────────────────────────────────────────────
$filtro       = $_GET['status'] ?? 'todos';
if (!in_array($filtro, ['pendiente', 'completado', 'todos'], true)) {
    $filtro = 'todos';
}

$busqueda          = trim($_GET['q'] ?? '');
$filtroDispositivo = trim($_GET['dispositivo'] ?? '');
$filtroFechaDesde  = $_GET['fecha_desde'] ?? '';
$filtroFechaHasta  = $_GET['fecha_hasta'] ?? '';

if ($filtroFechaDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaDesde)) $filtroFechaDesde = '';
if ($filtroFechaHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaHasta)) $filtroFechaHasta = '';

// ── Query string extra para conservar filtros en las pestañas ─
$extraQs = http_build_query(array_filter([
    'q' => $busqueda, 'dispositivo' => $filtroDispositivo,
    'fecha_desde' => $filtroFechaDesde, 'fecha_hasta' => $filtroFechaHasta,
], fn ($v) => $v !== ''));
$extraQs = $extraQs === '' ? '' : '&' . $extraQs;

// ── Dispositivos disponibles (para el filtro) ─────────────
$dispositivos = $pdo->query('SELECT DISTINCT dispositivo FROM alertas WHERE dispositivo <> "" ORDER BY dispositivo')->fetchAll(PDO::FETCH_COLUMN);

// ── Consulta ───────────────────────────────────────────────
$sql = 'SELECT id, dispositivo, numero, bateria, fecha_hora, latitud, longitud, status FROM alertas';
$params = [];
$cond = [];

if ($filtro !== 'todos') {
    $cond[] = 'status = :status';
    $params[':status'] = $filtro;
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
$sql .= ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alertas = $stmt->fetchAll();

$extra_css = '<link rel="stylesheet" href="/assets/lib/leaflet/leaflet.css">';
$extra_js  = '<script src="/assets/lib/leaflet/leaflet.js"></script>';

include __DIR__ . '/../includes/header.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-list"></i> Alertas (<?php echo count($alertas); ?>)</span>
        <div class="btn-group btn-group-sm">
            <a href="?status=todos<?php echo $extraQs; ?>" class="btn btn-outline-secondary <?php echo $filtro === 'todos' ? 'active' : ''; ?>">Todas</a>
            <a href="?status=pendiente<?php echo $extraQs; ?>" class="btn btn-outline-secondary <?php echo $filtro === 'pendiente' ? 'active' : ''; ?>">Pendientes</a>
            <a href="?status=completado<?php echo $extraQs; ?>" class="btn btn-outline-secondary <?php echo $filtro === 'completado' ? 'active' : ''; ?>">Completadas</a>
        </div>
    </div>

    <div class="card-body pb-2 pt-3">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filtro); ?>">
            <div class="col-md-2">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Dispositivo o número" maxlength="100">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Dispositivo</label>
                <select name="dispositivo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($dispositivos as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $d === $filtroDispositivo ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtroFechaDesde); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtroFechaHasta); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="alertas.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                <button type="button" class="btn btn-sm btn-outline-success" id="btnExportar">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
        </form>
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
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay alertas con esos criterios</td></tr>
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
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary btn-ver" data-id="<?php echo (int)$a['id']; ?>">
                                        <i class="fas fa-eye"></i> Detalle
                                    </button>
                                    <?php if ($a['status'] === 'pendiente'): ?>
                                        <button class="btn btn-sm btn-success btn-completar" data-id="<?php echo (int)$a['id']; ?>">
                                            <i class="fas fa-check"></i> Completar
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($esRoot): ?>
                                        <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="<?php echo (int)$a['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal Detalle de alerta ────────────────────────────── -->
<div class="modal fade modal-xxl" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alerta <span id="detId">#0</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="detCsrf" value="<?php echo $csrf; ?>">
                <div id="msgDetalle"></div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <table class="table table-sm table-borderless mb-3">
                            <tbody>
                                <tr><th class="w-40">Dispositivo</th><td id="detDispositivo">—</td></tr>
                                <tr><th>Número</th><td id="detNumero">—</td></tr>
                                <tr><th>Batería</th><td id="detBateria">—</td></tr>
                                <tr><th>Fecha y hora</th><td id="detFecha">—</td></tr>
                                <tr><th>Ubicación</th><td id="detUbicacion">—</td></tr>
                                <tr><th>Estado</th><td id="detEstado">—</td></tr>
                                <tr><th>Completado por</th><td id="detCompletadoPor">—</td></tr>
                            </tbody>
                        </table>
                        <div id="mapDetalle" style="height: 260px; width: 100%;"></div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nota del operador</label>
                        <textarea class="form-control" id="detNota" rows="6" maxlength="500"></textarea>
                        <button class="btn btn-sm btn-outline-primary mt-2" id="btnGuardarNota">
                            <i class="fas fa-save"></i> Guardar nota
                        </button>
                        <div class="form-text mt-2">Describe lo que se atendió o el seguimiento.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="detEnlaceGmaps" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i> Abrir en Google Maps
                </a>
                <button type="button" class="btn btn-success" id="btnCompletarModal" style="display:none;">
                    <i class="fas fa-check"></i> Marcar como completada
                </button>
                <?php if ($esRoot): ?>
                <button type="button" class="btn btn-outline-danger" id="btnEliminarModal" style="display:none;">
                    <i class="fas fa-trash"></i> Eliminar alerta
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js .= <<<'HTML'
<script>
(function () {
    var modalEl = document.getElementById('modalDetalle');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var mapa = null;
    var marcador = null;
    var alertaActual = null;

    function escapeHtml(texto) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(texto == null ? '' : String(texto)));
        return div.innerHTML;
    }

    function iniciarMapa() {
        if (mapa) return;
        mapa = L.map('mapDetalle').setView([0, 0], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(mapa);
        marcador = L.marker([0, 0]).addTo(mapa);
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        if (mapa) mapa.invalidateSize();
    });

    function verAlerta(id) {
        document.getElementById('msgDetalle').innerHTML = '';
        fetch('../api/alerta_detalle.php?id=' + id)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    document.getElementById('msgDetalle').innerHTML = '<div class="alert alert-danger">' + escapeHtml(res.error || 'Error') + '</div>';
                    return;
                }
                var a = res.alerta;
                alertaActual = a;
                document.getElementById('detId').textContent = '#' + a.id;
                document.getElementById('detDispositivo').textContent = a.dispositivo;
                document.getElementById('detNumero').textContent = a.numero || '—';
                document.getElementById('detBateria').textContent = a.bateria + '%';
                document.getElementById('detFecha').textContent = a.fecha_hora;
                document.getElementById('detUbicacion').textContent = a.latitud + ', ' + a.longitud;
                document.getElementById('detEstado').innerHTML = a.status === 'pendiente'
                    ? '<span class="badge bg-warning">pendiente</span>'
                    : '<span class="badge bg-success">completado</span>';
                document.getElementById('detCompletadoPor').textContent = a.completado_por || '—';
                document.getElementById('detNota').value = a.nota || '';
                document.getElementById('detEnlaceGmaps').href = 'https://www.google.com/maps?q=' + a.latitud + ',' + a.longitud;
                document.getElementById('btnCompletarModal').style.display = a.status === 'pendiente' ? '' : 'none';

                var btnEliminarModalDet = document.getElementById('btnEliminarModal');
                if (btnEliminarModalDet) {
                    btnEliminarModalDet.style.display = '';
                }

                iniciarMapa();
                var pos = [parseFloat(a.latitud), parseFloat(a.longitud)];
                marcador.setLatLng(pos);
                mapa.setView(pos, 16);
                setTimeout(function () { if (mapa) mapa.invalidateSize(); }, 250);

                modal.show();
            })
            .catch(function () {
                document.getElementById('msgDetalle').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
            });
    }

    document.querySelectorAll('.btn-ver').forEach(function (btn) {
        btn.addEventListener('click', function () { verAlerta(btn.dataset.id); });
    });

    // ── Guardar nota ────────────────────────────────────
    document.getElementById('btnGuardarNota').addEventListener('click', function () {
        if (!alertaActual) return;
        fetch('../api/guardar_nota.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: document.getElementById('detCsrf').value,
                id: alertaActual.id,
                nota: document.getElementById('detNota').value
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            document.getElementById('msgDetalle').innerHTML = res.success
                ? '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + escapeHtml(res.mensaje || 'Nota guardada.') + '</div>'
                : '<div class="alert alert-danger">' + escapeHtml(res.error || 'Error') + '</div>';
        })
        .catch(function () { document.getElementById('msgDetalle').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>'; });
    });

    // ── Completar desde el modal ────────────────────────
    document.getElementById('btnCompletarModal').addEventListener('click', function () {
        if (!alertaActual) return;
        if (!confirm('¿Marcar la alerta #' + alertaActual.id + ' como completada?')) return;
        fetch('../api/cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: alertaActual.id, status: 'completado' })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) { location.reload(); }
            else { alert(res.error || 'Error al actualizar'); }
        })
        .catch(function () { alert('Error de conexión'); });
    });

    // ── Completar desde la fila ─────────────────────────
    document.querySelectorAll('.btn-completar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            if (!confirm('¿Marcar la alerta #' + id + ' como completada?')) return;
            fetch('../api/cambiar_estado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10), status: 'completado' })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) { location.reload(); }
                else { alert(data.error || 'Error al actualizar'); }
            })
            .catch(function () { alert('Error de conexión'); });
        });
    });

    // ── Eliminar alerta (solo root) ────────────────────
    function eliminarAlerta(id) {
        if (!confirm('¿Seguro que deseas ELIMINAR la alerta #' + id + '? Esta acción no se puede deshacer.')) return;
        fetch('../api/eliminar_alerta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: document.getElementById('detCsrf').value, id: parseInt(id, 10) })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) { location.reload(); }
            else { alert(data.error || 'Error al eliminar'); }
        })
        .catch(function () { alert('Error de conexión'); });
    }

    document.querySelectorAll('.btn-eliminar').forEach(function (btn) {
        btn.addEventListener('click', function () { eliminarAlerta(btn.dataset.id); });
    });

    var btnEliminarModal = document.getElementById('btnEliminarModal');
    if (btnEliminarModal) {
        btnEliminarModal.addEventListener('click', function () {
            if (!alertaActual) return;
            eliminarAlerta(alertaActual.id);
        });
    }

    // ── Exportar CSV ────────────────────────────────────
    document.getElementById('btnExportar').addEventListener('click', function () {
        var form = document.getElementById('formExport');
        if (!form) {
            form = document.createElement('form');
            form.id = 'formExport';
            form.method = 'post';
            form.action = '../api/exportar_alertas.php';
            form.style.display = 'none';
            document.body.appendChild(form);
        }
        form.replaceChildren();

        var parametros = new URLSearchParams(location.search);
        var nombres = {
            csrf_token: document.getElementById('detCsrf').value,
            status: parametros.get('status') || 'todos',
            q: parametros.get('q') || '',
            dispositivo: parametros.get('dispositivo') || '',
            fecha_desde: parametros.get('fecha_desde') || '',
            fecha_hasta: parametros.get('fecha_hasta') || ''
        };
        Object.keys(nombres).forEach(function (k) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = nombres[k];
            form.appendChild(inp);
        });
        form.submit();
    });
})();
</script>
HTML;

include __DIR__ . '/../includes/footer.php';