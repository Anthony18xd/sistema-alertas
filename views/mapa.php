<?php
require_once __DIR__ . '/../includes/auth.php';

$titulo = 'Mapa de alertas';
$extra_css = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
$extra_js = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script src="../assets/js/mapa.js"></script>';

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-map-marked-alt"></i> Ubicación de alertas activas</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="irAlCentro()">
            <i class="fas fa-crosshairs"></i> Centrar mapa
        </button>
    </div>
    <div class="card-body p-0">
        <div id="map" style="height: 70vh; width: 100%;"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
