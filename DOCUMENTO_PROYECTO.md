# Documento de Proyecto — Sistema ALERTA (Botón de Emergencia)

> Documento elaborado a partir del sistema existente en este repositorio.
> Describe la empresa, su modelo de negocio, el problema y el título tentativo
> del proyecto, todo relacionado con el **sistema ALERTA** y el **botón de emergencia**.

---

## 1. Organigrama Estructural de la Empresa

**Área de donde se obtiene el problema / necesidad / requerimiento:**
el requerimiento surge del **Área de Seguridad y Monitoreo** (protección civil
/ vigilancia), que es la encargada de atender situaciones de emergencia en campo.

```
                    DIRECCIÓN GENERAL / GERENCIA
                              │
        ┌─────────────────────┼──────────────────────┐
        │                     │                      │
   ÁREA DE                ÁREA DE                ÁREA DE
   SEGURIDAD Y           SISTEMAS /             OPERACIONES /
   MONITOREO  ◄──(NEED)   TECNOLOGÍA             LOGÍSTICA
        │                     │                      │
        │   ┌─────────────────┼────────────────┐     │
        │   │                 │                │     │
   MONITOREO            SOPORTE TÉCNICO     BASE DE
   DE ALERTAS           / INFRAESTRUCTURA    DATOS /
   (PANEL WEB)          (SERVIDOR, API)      REPORTES
        │
   PERSONAL EN CAMPO
   (CON BOTÓN DE EMERGENCIA)
```

**Del Área de Seguridad y Monitoreo** se obtiene la necesidad que este
proyecto resuelve: recibir y gestionar alertas de emergencia en tiempo real.

---

## 2. Modelo de Negocio de la Empresa

Tipo de empresa: **organización/servicio de seguridad y atención de emergencias**
(protección civil, seguridad pública o vigilancia privada).

### Propuesta de valor
Atención oportuna de emergencias mediante dispositivos con **botón de pánico**,
permitiendo conocer en tiempo real la ubicación (latitud/longitud) de la persona
o vehículo que solicita ayuda.

### Principales procesos y actividades

| Proceso | Actividad que desarrolla |
|---------|--------------------------|
| **Registro de alertas** | El botón de emergencia (app/dispositivo) envía la alerta vía API con dispositivo, número, nivel de batería, fecha/hora y coordenadas. |
| **Recepción y monitoreo** | El panel web (dashboard) recibe, lista y muestra en mapa cada alerta en tiempo real. |
| **Geolocalización** | Se ubica la emergencia sobre el mapa (Leaflet / Google Maps) para enviar ayuda al punto exacto. |
| **Gestión de alertas** | El operador clasifica, atiende y **completa** las alertas, registrando notas de la atención. |
| **Control de dispositivos** | Administra los dispositivos que activan el botón mediante **API Keys**, revisando su estado (batería, en línea/activo/inactivo). |
| **Seguridad y auditoría** | Control de acceso por roles (admin/operador/root), bitácora de actividades y respaldo de la información. |
| **Reportes y estadísticas** | Generación de estadísticas y exportación de alertas (CSV) para la toma de decisiones. |

---

## 3. Necesidad, Problema o Requerimiento Tentativo

**Necesidad:**
El área de Seguridad y Monitoreo necesita **recibir, localizar y atender en
tiempo real las emergencias** notificadas a través de un botón de pánico, ya que
actualmente no existe un medio automatizado que registre y georreferencie estas
solicitudes de auxilio.

**Problema:**
Cuando una persona o unidad en campo presiona el botón de emergencia, no se
conoce de forma inmediata y confiable **quién pide ayuda, desde dónde y en qué
momento**, dificultando la atención oportuna de la emergencia.

**Requerimiento tentativo:**
Desarrollar un **sistema de monitoreo de alertas de emergencia** que:
- Reciba la alerta del botón de pánico con su geolocalización.
- La muestre en tiempo real en un panel y sobre un mapa.
- Permita gestionarla (atender, completar, registrar notas) y llevar control
  del estado de los dispositivos y de la batería.
- Sea seguro, auditable y con control de acceso por roles.

---

## 4. Título Tentativo del Proyecto de Software

**"Sistema Web ALERTA: Plataforma de Monitoreo y Georreferenciación de Alertas
de Emergencia mediante Botón de Pánico (Pánico/ALERTA v1.0)"**

*Alternativas:*
- "ALERTA – Sistema de monitoreo de emergencias con botón de pánico y
  geolocalización en tiempo real".
- "Plataforma ALERTA para la atención de emergencias mediante dispositivos con
  botón de emergencia".

---

### Relación con el código del repositorio

| Punto | Archivos/sistema relacionados |
|-------|-------------------------------|
| Botón de emergencia (envía alerta) | `api/alerta.php`, `api/api_keys` (validación de API Key) |
| Geolocalización | tablas `latitud`/`longitud` en `database/schema.sql`, `views/mapa.php` |
| Monitoreo / dashboard | `views/dashboard.php`, `views/alertas.php`, `api/estadisticas.php` |
| Gestión y estado | `api/cambiar_estado.php`, `api/guardar_nota.php` |
| Dispositivos / batería | `views/estado_dispositivos.php` |
| Seguridad y roles | `includes/auth.php`, `includes/security.php`, `api/gestionar_usuario.php` |
| Auditoría | `includes/actividad.php`, `views/bitacora.php`, `logs_actividad` |
