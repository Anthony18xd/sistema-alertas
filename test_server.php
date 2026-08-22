<?php
echo json_encode([
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT_SET',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'NOT_SET',
    'isLocal' => in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']) || ($_SERVER['SERVER_PORT'] ?? '80') === '8000'
]);
