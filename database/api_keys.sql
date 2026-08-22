-- Tabla de API Keys para autenticación de dispositivos Android
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

-- API Key inicial para el proyecto (cambiar en producción)
INSERT INTO api_keys (api_key, nombre_dispositivo, activa)
VALUES ('alerta_muni_2026_xK9mP2vL8nQ4wR7jT3yH6bC5fD1gE0sA', 'App Android ALERTA', 1);
