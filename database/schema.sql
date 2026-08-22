-- ============================================================
-- ESQUEMA COMPLETO — Sistema ALERTA
-- Ejecutar en phpMyAdmin o consola MySQL
-- ============================================================

-- ── Tabla de usuarios (panel admin) ─────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario admin por defecto (contraseña: password)
-- CAMBIAR EN PRODUCCIÓN con: php -r "echo password_hash('TU_NUEVA_CONTRASEÑA', PASSWORD_BCRYPT);"
INSERT IGNORE INTO usuarios (username, password_hash) VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);

-- ── Tabla de alertas ────────────────────────────────────
CREATE TABLE IF NOT EXISTS alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispositivo VARCHAR(150) NOT NULL,
    numero VARCHAR(20) DEFAULT '' NOT NULL,
    bateria INT DEFAULT 0 NOT NULL,
    fecha_hora DATETIME NOT NULL,
    latitud DECIMAL(10, 7) NOT NULL,
    longitud DECIMAL(10, 7) NOT NULL,
    status ENUM('pendiente', 'completado') DEFAULT 'pendiente' NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_dispositivo (dispositivo),
    INDEX idx_id_status (id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla de API Keys ───────────────────────────────────
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    nombre_dispositivo VARCHAR(150) NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_api_key (api_key),
    INDEX idx_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API Key inicial para el proyecto
INSERT IGNORE INTO api_keys (api_key, nombre_dispositivo, activa)
VALUES ('alerta_muni_2026_xK9mP2vL8nQ4wR7jT3yH6bC5fD1gE0sA', 'App Android ALERTA', 1);
