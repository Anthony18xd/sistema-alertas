var alertaMap = null;
var CENTRO_BASE = [-12.078066, -75.245236];

function irAlCentro() {
    if (alertaMap) {
        alertaMap.setView(CENTRO_BASE, 14);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    alertaMap = L.map('map').setView(CENTRO_BASE, 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(alertaMap);

    var redIcon = L.divIcon({
        html: '<i class="fas fa-bell" style="font-size: 1.5rem; color: #dc3545; text-shadow: 0 0 4px rgba(0,0,0,0.5);"></i>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -20],
        className: 'custom-marker-icon'
    });

    fetch('../api/obtener_alertas.php')
        .then(function (res) { return res.json(); })
        .then(function (resp) {
            var alertas = resp.data || [];
            alertas.forEach(function (a) {
                var popupContent =
                    '<div style="min-width: 220px;">' +
                    '<h6 class="text-danger mb-2"><i class="fas fa-bell"></i> Alerta</h6>' +
                    '<p class="mb-1"><strong><i class="fas fa-mobile-alt"></i> Dispositivo:</strong> ' + escapeHtml(a.dispositivo) + '</p>' +
                    '<p class="mb-1"><strong><i class="fas fa-battery-three-quarters"></i> Batería:</strong> ' + a.bateria + '%</p>' +
                    '<p class="mb-1"><strong><i class="fas fa-clock"></i> Fecha:</strong> ' + a.fecha_hora + '</p>' +
                    '<p class="mb-2"><strong><i class="fas fa-map-pin"></i> Coordenadas:</strong> ' + a.latitud + ', ' + a.longitud + '</p>' +
                    '<div class="d-flex gap-2">' +
                    '<a href="https://www.google.com/maps?q=' + a.latitud + ',' + a.longitud + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-map-marker-alt"></i> Ver en Google Maps</a>' +
                    '<a href="https://www.google.com/maps/dir/-12.078066,-75.245236/' + a.latitud + ',' + a.longitud + '" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-route"></i> Ver Ruta</a>' +
                    '</div>' +
                    '</div>';

                L.marker([a.latitud, a.longitud], { icon: redIcon })
                    .addTo(alertaMap)
                    .bindPopup(popupContent);
            });

            if (alertas.length > 0) {
                var group = L.featureGroup([
                    L.marker([alertas[0].latitud, alertas[0].longitud])
                ]);
                alertas.forEach(function (a) {
                    group.addLayer(L.marker([a.latitud, a.longitud]));
                });
                alertaMap.fitBounds(group.getBounds().pad(0.1));
            }
        })
        .catch(function (err) {
            console.error('Error al cargar alertas:', err);
        });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
});
