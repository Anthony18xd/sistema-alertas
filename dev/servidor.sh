#!/bin/zsh
# Levanta el servidor de desarrollo ALERTA en el puerto 8000
# Uso: ./dev/servidor.sh   (Ctrl+C para detener)

cd "$(dirname "$0")/.."

IP=$(hostname -I 2>/dev/null | awk '{print $1}')

echo "═══════════════════════════════════════════"
echo "  Servidor ALERTA iniciado"
echo "═══════════════════════════════════════════"
echo "  Panel (esta PC):  http://localhost:8000/login.php"
[ -n "$IP" ] && echo "  Panel (celular):  http://$IP:8000/login.php"
echo "  API alerta:       /api/alerta.php"
echo "═══════════════════════════════════════════"
echo ""

php -S 0.0.0.0:8000
