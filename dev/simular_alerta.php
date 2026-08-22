<?php
/**
 * simular_alerta.php — Inserta alertas de prueba
 * SOLO PARA DESARROLLO LOCAL. Protegido por .htaccess en producción.
 *
 * Uso:
 *   php dev/simular_alerta.php              -> 1 alerta
 *   php dev/simular_alerta.php 5            -> 5 alertas
 *   php dev/simular_alerta.php 10 -12.08    -> 10 alertas, lat base -12.08
 */

$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // Bloqueado en web — solo CLI
    http_response_code(403);
    echo 'Acceso denegado. Usa: php dev/simular_alerta.php [cantidad]';
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

$cantidad = max(1, (int)($argv[1] ?? 1));
$latBase = (float)($argv[2] ?? -12.078066);
$lngBase = (float)($argv[3] ?? -75.245236);

$dispositivos = ['Samsung-A54', 'Xiaomi-Redmi-Note-12', 'Motorola-G84', 'Google-Pixel-7a', 'iPhone-13'];

try {
    $stmt = $pdo->prepare(
        'INSERT INTO alertas (dispositivo, numero, bateria, fecha_hora, latitud, longitud, status)
         VALUES (:dispositivo, :numero, :bateria, :fecha_hora, :latitud, :longitud, :status)'
    );

    for ($i = 1; $i <= $cantidad; $i++) {
        $lat = $latBase + (random_int(-200, 200) / 100000);
        $lng = $lngBase + (random_int(-200, 200) / 100000);

        $stmt->execute([
            ':dispositivo' => $dispositivos[array_rand($dispositivos)],
            ':numero'      => '+5198' . random_int(1000000, 9999999),
            ':bateria'     => random_int(5, 100),
            ':fecha_hora'  => date('Y-m-d H:i:s'),
            ':latitud'     => $lat,
            ':longitud'    => $lng,
            ':status'      => 'pendiente',
        ]);

        echo "  Alerta #$i insertada (id " . $pdo->lastInsertId() . ") -> [$lat, $lng]\n";
    }

    echo "\n  Total: $cantidad alertas creadas\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
