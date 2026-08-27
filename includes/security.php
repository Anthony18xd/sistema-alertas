<?php
/**
 * MIDDLEWARE DE SEGURIDAD — API
 * Usar en todas las APIs: require_once __DIR__ . '/../includes/security.php';
 */

require_once __DIR__ . '/../config/security.php';

// ── 0. Conexión a BD (scope global para todas las APIs) ──
require_once __DIR__ . '/../config/database.php';

// ── 1. Headers de Seguridad ───────────────────────────────
foreach ($SECURITY_HEADERS as $header => $value) {
    header("$header: $value");
}

// ── 2. Directorio de datos protegido ──────────────────────
function getDataDir(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

// ── 3. Rate Limiting por IP ───────────────────────────────
function getClientIP(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Solo confiar en proxies si están configurados explícitamente
    // Por seguridad, NO confiar en X-Forwarded-For spoofable
    // Si usas Cloudflare, descomenta la siguiente línea:
    // if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    //     $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    // }

    // Validar que sea una IP válida
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

function checkRateLimit(): void {
    global $RATE_LIMIT_ANON_MAX, $RATE_LIMIT_AUTH_MAX, $RATE_LIMIT_BLOCK;

    $ip = getClientIP();
    $dataDir = getDataDir();

    // Detectar si tiene API key válida (para rate limit diferenciado)
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? '';
    $hasValidKey = false;

    if (!empty($apiKey) && strlen($apiKey) >= 20) {
        global $API_KEYS_PERMITIDAS;
        if (in_array($apiKey, $API_KEYS_PERMITIDAS)) {
            $hasValidKey = true;
        }
    }

    // Usuarios del panel con sesión iniciada cuentan como "autenticados"
    $isWebSession = false;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $isWebSession = true;
    }

    // Rate limit por IP/API key (anónimo) o por usuario autenticado
    if ($hasValidKey) {
        $rateKey = 'key_' . md5($apiKey);
    } elseif ($isWebSession) {
        $rateKey = 'user_' . md5('u' . (int)$_SESSION['user_id'] . '_' . $ip);
    } else {
        $rateKey = 'ip_' . md5($ip);
    }
    $rateFile = $dataDir . '/rate_' . $rateKey . '.json';
    $maxRequests = ($hasValidKey || $isWebSession) ? $RATE_LIMIT_AUTH_MAX : $RATE_LIMIT_ANON_MAX;

    $data = [];
    if (file_exists($rateFile)) {
        $raw = file_get_contents($rateFile);
        $data = json_decode($raw, true) ?: [];
    }

    $now = time();

    // Limpiar si ya pasó el bloqueo
    if (isset($data['blocked_until']) && $now > $data['blocked_until']) {
        unset($data['blocked_until']);
        $data['requests'] = 0;
        $data['window_start'] = $now;
    }

    // Si está bloqueado
    if (isset($data['blocked_until']) && $now <= $data['blocked_until']) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . ($data['blocked_until'] - $now));
        echo json_encode([
            'error' => 'Demasiadas peticiones. Intenta más tarde.',
            'retry_after' => $data['blocked_until'] - $now
        ]);
        exit;
    }

    // Nueva ventana de tiempo (1 minuto)
    if (!isset($data['window_start']) || ($now - $data['window_start']) >= 60) {
        $data['requests'] = 0;
        $data['window_start'] = $now;
    }

    $data['requests']++;

    // Si excede el límite, bloquear
    if ($data['requests'] > $maxRequests) {
        $data['blocked_until'] = $now + $RATE_LIMIT_BLOCK;
        file_put_contents($rateFile, json_encode($data));
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $RATE_LIMIT_BLOCK);
        echo json_encode([
            'error' => 'Límite de peticiones excedido. Bloqueado temporalmente.',
            'retry_after' => $RATE_LIMIT_BLOCK
        ]);
        exit;
    }

    // Guardar estado
    file_put_contents($rateFile, json_encode($data));

    // Headers informativos
    header('X-RateLimit-Limit: ' . $maxRequests);
    header('X-RateLimit-Remaining: ' . max(0, $maxRequests - $data['requests']));
}

checkRateLimit();

// ── 4. Validación de API Key ──────────────────────────────
function validateApiKey(): array {
    global $API_KEYS_PERMITIDAS, $pdo;

    // Aceptar header X-API-Key o query ?key= (para dashboard)
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? '';

    // Fallback: sesión web del panel (dashboard/mapa consultan sin key)
    if (empty($apiKey)) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            return ['id' => 0, 'nombre_dispositivo' => 'Panel web (' . ($_SESSION['username'] ?? '') . ')'];
        }
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API Key requerida. Envía el header X-API-Key o el parámetro ?key=']);
        exit;
    }

    // Validar longitud de API key (mínimo 20, máximo 128)
    if (strlen($apiKey) < 20 || strlen($apiKey) > 128) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API Key inválida']);
        exit;
    }

    // Verificar contra la BD
    try {
        $stmt = $pdo->prepare('SELECT id, nombre_dispositivo FROM api_keys WHERE api_key = :key AND activa = 1');
        $stmt->execute([':key' => $apiKey]);
        $keyData = $stmt->fetch();

        if ($keyData) {
            // Actualizar last_used_at (solo cada 5 minutos para reducir writes)
            $fiveMinAgo  = date('Y-m-d H:i:s', time() - 300);
            $nowFunc     = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') ? 'NOW()' : "strftime('%Y-%m-%d %H:%M:%S', 'now')";
            $update = $pdo->prepare("UPDATE api_keys SET last_used_at = {$nowFunc} WHERE id = :id AND (last_used_at IS NULL OR last_used_at < :five_min_ago)");
            $update->execute([':id' => $keyData['id'], ':five_min_ago' => $fiveMinAgo]);
            return $keyData;
        }
    } catch (PDOException $e) {
        // Si la BD no tiene la tabla aún, fallback a la lista estática
        if (in_array($apiKey, $API_KEYS_PERMITIDAS)) {
            return ['id' => 0, 'nombre_dispositivo' => 'Key estática'];
        }
    }

    logSuspiciousActivity('API Key inválida: ' . substr($apiKey, 0, 8) . '...');
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API Key inválida o desactivada']);
    exit;
}

// ── 5. Validación de Content-Type ─────────────────────────
function validateJsonInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') === false) {
        http_response_code(415);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Content-Type debe ser application/json']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }

    // Limitar tamaño del body (1MB máximo)
    if (strlen(file_get_contents('php://input')) > 1048576) {
        http_response_code(413);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cuerpo de solicitud demasiado grande']);
        exit;
    }

    return $input;
}

// ── 6. Sanitización de strings ────────────────────────────
function sanitizeString(string $value, int $maxLen = 0): string {
    global $MAX_STRING_LENGTH;
    $maxLen = $maxLen > 0 ? $maxLen : $MAX_STRING_LENGTH;
    $value = trim($value);
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $value = mb_substr($value, 0, $maxLen);
    return $value;
}

// ── 7. CORS configurado ──────────────────────────────────
function setCorsHeaders(): void {
    global $ALLOWED_ORIGINS;

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (empty($ALLOWED_ORIGINS)) {
        return;
    }

    if (in_array($origin, $ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
        header('Access-Control-Max-Age: 86400');
    }
}

setCorsHeaders();

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── 8. Log de actividad sospechosa ────────────────────────
function logSuspiciousActivity(string $reason): void {
    $ip = getClientIP();
    $url = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] IP: $ip | $method $url | $reason\n";

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents($logDir . '/security.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// ── 9. Detección de user-agents maliciosos ──────────────
$blockedAgents = ['sqlmap', 'nikto', 'nmap', 'masscan', 'scanner', 'python-requests'];
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
foreach ($blockedAgents as $agent) {
    if (strpos($userAgent, $agent) !== false) {
        logSuspiciousActivity('Blocked user agent: ' . $agent);
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }
}
