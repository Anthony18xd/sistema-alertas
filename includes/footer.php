        </div>
    </div>
</div>

<script src="/assets/lib/bootstrap/bootstrap.bundle.min.js"></script>
<script>
var PANEL_PREF = window.PANEL_PREF || { sonido: true, intervalo: 30 };
var notifUltimoId = 0;
var notifInterval = null;
var notifAudioCtx = null;

// ── Alarma de 3 minutos ────────────────────────────────────
var ALARMA_DURACION = 3 * 60 * 1000;
var notifAlarma = {
    activa: false,
    finAt: 0,
    timer: null,
    countdown: null,
    timerAuto: null,
    banner: null
};

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

function actualizarContador() {
    var restante = Math.max(0, notifAlarma.finAt - Date.now());
    if (restante <= 0) {
        detenerAlarma();
        return;
    }
    var s = Math.ceil(restante / 1000);
    var m = Math.floor(s / 60);
    var ss = s % 60;
    var el = document.getElementById('alarmaContador');
    if (el) el.textContent = m + ':' + (ss < 10 ? '0' : '') + ss;
}

function tocarBip() {
    if (!PANEL_PREF.sonido) return;
    try {
        initAudio();
        if (!notifAudioCtx || notifAudioCtx.state === 'suspended') return;
        var ahora = notifAudioCtx.currentTime;
        for (var k = 0; k < 3; k++) {
            var t = ahora + k * 0.35;
            var osc = notifAudioCtx.createOscillator();
            var gain = notifAudioCtx.createGain();
            osc.connect(gain);
            gain.connect(notifAudioCtx.destination);
            osc.type = 'square';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0, t);
            gain.gain.linearRampToValueAtTime(0.18, t + 0.02);
            gain.gain.setValueAtTime(0.18, t + 0.18);
            gain.gain.linearRampToValueAtTime(0.0, t + 0.22);
            osc.start(t);
            osc.stop(t + 0.25);
        }
    } catch (e) {}
}

function detenerAlarma() {
    if (!notifAlarma.activa) return;
    notifAlarma.activa = false;
    if (notifAlarma.timer) clearInterval(notifAlarma.timer);
    if (notifAlarma.countdown) clearInterval(notifAlarma.countdown);
    if (notifAlarma.timerAuto) clearTimeout(notifAlarma.timerAuto);
    notifAlarma.timer = null;
    notifAlarma.countdown = null;
    notifAlarma.timerAuto = null;
    if (notifAlarma.banner && notifAlarma.banner.parentNode) {
        notifAlarma.banner.parentNode.removeChild(notifAlarma.banner);
    }
    notifAlarma.banner = null;
    document.body.classList.remove('alarma-activa');
}

function crearBannerAlarma() {
    var banner = document.createElement('div');
    banner.className = 'alarma-banner';
    banner.innerHTML =
        '<div class="alarma-banner-ic"><i class="fas fa-exclamation-triangle"></i></div>' +
        '<div class="alarma-banner-txt">' +
            '<strong>¡ALERTA NUEVA!</strong>' +
            '<span>Sonando por <b id="alarmaContador">3:00</b> min</span>' +
        '</div>' +
        '<button type="button" class="btn btn-sm btn-light" id="btnDetenerAlarma">Detener alarma</button>';
    document.body.appendChild(banner);
    banner.querySelector('#btnDetenerAlarma').addEventListener('click', detenerAlarma);
    notifAlarma.banner = banner;
    document.body.classList.add('alarma-activa');
}

function iniciarAlarma() {
    if (!PANEL_PREF.sonido) return;
    detenerAlarma();
    notifAlarma.activa = true;
    notifAlarma.finAt = Date.now() + ALARMA_DURACION;
    crearBannerAlarma();
    tocarBip();
    notifAlarma.timer = setInterval(tocarBip, 1000);
    actualizarContador();
    notifAlarma.countdown = setInterval(actualizarContador, 1000);
    notifAlarma.timerAuto = setTimeout(function () {
        detenerAlarma();
    }, ALARMA_DURACION);
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
                iniciarAlarma();
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
    // ── Detener alarma al abrir notificaciones ──────────
    var dropdownNotif = document.getElementById('notifDropdown');
    if (dropdownNotif) {
        dropdownNotif.addEventListener('show.bs.dropdown', detenerAlarma);
    }
    var notifFooter = document.querySelector('.notif-footer');
    if (notifFooter) {
        notifFooter.addEventListener('click', detenerAlarma);
    }

    // ── Menú móvil (off-canvas) ─────────────────────────
    var toggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('backdropSidebar');

    function abrirMenu() {
        if (sidebar) sidebar.classList.add('abierta');
        if (backdrop) backdrop.classList.add('visible');
    }
    function cerrarMenu() {
        if (sidebar) sidebar.classList.remove('abierta');
        if (backdrop) backdrop.classList.remove('visible');
    }
    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('abierta')) {
                cerrarMenu();
            } else {
                abrirMenu();
            }
        });
    }
    if (backdrop) backdrop.addEventListener('click', cerrarMenu);
    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.innerWidth <= 768) cerrarMenu();
            });
        });
    }

    actualizarNotificaciones();
    notifInterval = setInterval(actualizarNotificaciones, PANEL_PREF.intervalo * 1000);
});
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
