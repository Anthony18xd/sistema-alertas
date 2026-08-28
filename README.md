# ALERTA — Sistema de Monitoreo de Alertas de Emergencia (Botón de Pánico)

Sistema web que recibe, geolocaliza y gestiona alertas de emergencia enviadas por
dispositivos con **botón de pánico** (app Android / dispositivo), usando una API
o directamente desde la base de datos. Incluye dashboard con estadísticas, mapa,
control de dispositivos, gestión de usuarios por roles y bitácora.

---

## Requisitos

- **PHP** 7.4+ (probado en PHP 8.x)
- Extensión **PDO SQLite** (`pdo_sqlite`) para el entorno local
- *(Opcional)* **MySQL** para producción

Verifica tu entorno:

```bash
php -v
php -m | grep -i sqlite
```

---

## Cómo correr el sistema (Entorno Local)

### 1. Preparar la base de datos (solo la primera vez)

```bash
php dev/crear_db_local.php
```

> Esto crea `data/alerta_local.db` (SQLite) con las tablas y un usuario por
> defecto. Si la DB ya existe y quieres reiniciarla desde cero:

```bash
php dev/crear_db_local.php --force
```

### 2. (Opcional) Insertar alertas de prueba

```bash
php dev/simular_alerta.php 5
```

> Esto inserta 5 alertas de muestra para que veas datos en el dashboard y en el
> mapa. Puedes cambiar el número (ej. `10`) para más alertas.

### 3. Levantar el servidor

```bash
php -S 0.0.0.0:8000
```

O usa el script incluido:

```bash
./dev/servidor.sh
```

### 4. Entrar al sistema

Abre en tu navegador:

- **Panel (esta PC):** http://localhost:8000/login.php
- **Panel (celular en la misma red):** http://TU_IP:8000/login.php

**Credenciales por defecto (¡cámbialas en producción!):**

| Usuario | Contraseña |
|---------|------------|
| `admin`  | `password`  |

---

## Uso de la API (Botón de Emergencia)

Los dispositivos con botón de pánico envían la alerta a `POST /api/alerta.php`
con su **API Key** en el encabezado `X-API-Key`.

**API Key por defecto:**
```
alerta_muni_2026_xK9mP2vL8nQ4wR7jT3yH6bC5fD1gE0sA
```

**Ejemplo de petición (curl):**

```bash
curl -X POST http://localhost:8000/api/alerta.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: alerta_muni_2026_xK9mP2vL8nQ4wR7jT3yH6bC5fD1gE0sA" \
  -d '{
    "dispositivo": "Samsung-A54",
    "numero": "+51981112233",
    "bateria": 87,
    "fecha_hora": "2026-08-28 10:30:00",
    "latitud": -12.078066,
    "longitud": -75.245236
  }'
```

**Campos requeridos:** `dispositivo`, `numero`, `bateria` (0-100),
`fecha_hora` (YYYY-MM-DD HH:MM:SS), `latitud`, `longitud`.

> 💡 Administra y genera nuevas API Keys desde el panel: **Configuración**.

---

## Estructura del proyecto

```
api/          → Endpoints (alerta, estadísticas, gestión usuarios, exportar CSV…)
views/        → Páginas del panel (dashboard, alertas, mapa, bitácora, config…)
includes/     → Header/footer, autenticación, seguridad, actividad
config/       → Conexión BD y seguridad (⚠ no exponer / no subir)
database/     → Esquema SQL (MySQL)
data/         → Base SQLite local (⚠ generada automáticamente)
assets/       → CSS, JS, librerías y recursos
dev/          → Scripts de desarrollo (crear DB, simular alertas, servidor)
```

---

## Notas

- En **local** se usa SQLite automáticamente; en **producción** se usa MySQL
  (configurado en `config/database.php`).
- El login incluye protección contra fuerza bruta y tokens **CSRF**.
- Roles de usuario: `operador`, `admin`, `root` (este último puede eliminar alertas).
- Para detener el servidor presiona `Ctrl+C`.
