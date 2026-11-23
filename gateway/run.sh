#!/bin/bash
# Mantém o gateway do /sync no PM2. Roda a cada minuto pela tarefa agendada do Plesk:
#   bash /var/www/vhosts/br.ivao.aero/production/discord.br.ivao.aero/gateway/run.sh \
#     >> /var/www/vhosts/br.ivao.aero/logs/brauth-gateway.log 2>&1

set -eu
export PATH=/opt/plesk/node/20/bin:$PATH

APP=brauth-gateway
DIR=$(cd "$(dirname "$0")" && pwd)
PM2=/var/www/vhosts/br.ivao.aero/.npm-global/bin/pm2

exec 9>/tmp/brauth-gateway.lock
flock -n 9 || exit 0

cd "$DIR"
ts() { date -u +%FT%TZ; }

# Deploy novo: reinstala e reinicia quando o código muda
HASH=$(cat package.json bot.js | sha256sum | awk '{print $1}')
if [ "$HASH" != "$(cat .applied 2>/dev/null || true)" ] || [ ! -d node_modules ]; then
  echo "[$(ts)] código novo, instalando e reiniciando"
  npm install --omit=dev --no-audit --no-fund --ignore-scripts
  "$PM2" delete "$APP" >/dev/null 2>&1 || true
  "$PM2" start bot.js --name "$APP"
  "$PM2" save
  echo "$HASH" > .applied
  exit 0
fi

PID=$("$PM2" pid "$APP" 2>/dev/null | tr -d '[:space:]' || true)
if [ -z "$PID" ] || [ "$PID" = "0" ]; then
  echo "[$(ts)] $APP fora do ar, iniciando"
  "$PM2" start bot.js --name "$APP"
  "$PM2" save
fi
