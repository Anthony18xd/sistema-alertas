        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var PANEL_PREF = window.PANEL_PREF || { sonido: true, intervalo: 30 };
var notifUltimoId = 0;
var notifInterval = null;
var notifAudioCtx = null;

function initAudio() {
    if (!notifAudioCtx) {
        notifAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (notifAudioCtx.state === 'suspended') {
        notifAudioCtx.resume();
    }
}

document.addEventListener('click', initAudio, { once: true });
document.addEventListener('touchstart', initAudio, { once: true });

function playNotifSound() {
    if (!PANEL_PREF.sonido) return;
    try {
        initAudio();
        if (!notifAudioCtx || notifAudioCtx.state === 'suspended') return;
        var osc = notifAudioCtx.createOscillator();
        var gain = notifAudioCtx.createGain();
        osc.connect(gain);
        gain.connect(notifAudioCtx.destination);
        osc.frequency.value = 800;
        osc.type = 'sine';
        gain.gain.value = 0.15;
        osc.start(notifAudioCtx.currentTime);
        osc.stop(notifAudioCtx.currentTime + 0.12);
        setTimeout(function () {
            if (!notifAudioCtx) return;
            var osc2 = notifAudioCtx.createOscillator();
            var gain2 = notifAudioCtx.createGain();
            osc2.connect(gain2);
            gain2.connect(notifAudioCtx.destination);
            osc2.frequency.value = 1000;
            osc2.type = 'sine';
            gain2.gain.value = 0.15;
            osc2.start(notifAudioCtx.currentTime);
            osc2.stop(notifAudioCtx.currentTime + 0.15);
        }, 150);
    } catch (e) {}
}

function renderNotificaciones(alertas) {
    var list = document.getElementById('notifList');
    if (!list) return;
    if (alertas.length === 0) {
        list.innerHTML = '<div class="notif-empty">No hay alertas recientes</div>';
        return;
    }
    var html = '';
    for (var i = 0; i < alertas.length; i++) {
        var a = alertas[i];
        var esNueva = a.id > notifUltimoId;
        var clase = esNueva ? 'notif-item notif-item-new' : 'notif-item';
        var estadoClase = a.status === 'pendiente' ? 'bg-warning' : 'bg-success';
        html += '<div class="' + clase + '">' +
            '<div class="notif-icon"><i class="fas fa-bell"></i></div>' +
            '<div class="notif-body">' +
            '<div class="notif-title">' + escapeHtml(a.dispositivo) + '</div>' +
            '<div class="notif-meta">' +
            '<span><i class="fas fa-battery-three-quarters"></i> ' + a.bateria + '%</span>' +
            '<span><i class="fas fa-clock"></i> ' + a.fecha_hora + '</span>' +
            '<span class="badge ' + estadoClase + '" style="font-size:0.6rem;">' + a.status + '</span>' +
            '</div>' +
            '</div>' +
            '</div>';
    }
    list.innerHTML = html;
}

function actualizarNotificaciones() {
    fetch('../api/ultimas_alertas.php' + (notifUltimoId > 0 ? '?ultimo_id=' + notifUltimoId : ''))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var badge = document.getElementById('notifBadge');
            if (data.hay_nuevas && notifUltimoId > 0) {
                playNotifSound();
            }
            if (data.latest_id > notifUltimoId) {
                notifUltimoId = data.latest_id;
            }
            var pendientes = 0;
            for (var i = 0; i < data.alerts.length; i++) {
                if (data.alerts[i].status === 'pendiente') {
                    pendientes++;
                }
            }
            if (pendientes > 0) {
                badge.textContent = pendientes;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
            renderNotificaciones(data.alerts);
        })
        .catch(function () {});
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
    actualizarNotificaciones();
    notifInterval = setInterval(actualizarNotificaciones, PANEL_PREF.intervalo * 1000);
});
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
