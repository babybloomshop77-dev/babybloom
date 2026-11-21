#!/usr/bin/env bash
# Первичная настройка нового сервера (Ubuntu 24.04). Запуск с вашего компьютера:
#   ssh root@IP 'bash -s' < deploy/bootstrap-droplet.sh
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

echo "▶ Обновление системы"
apt-get update -y && apt-get upgrade -y
apt-get install -y git ufw fail2ban unattended-upgrades curl

echo "▶ Swap 2 ГБ (чтобы не падало на маленьком сервере)"
if ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "▶ Docker"
command -v docker >/dev/null || curl -fsSL https://get.docker.com | sh

echo "▶ Файрвол: только SSH, HTTP, HTTPS"
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443 >/dev/null
ufw --force enable

echo "▶ Ключ для доступа к GitHub (только чтение)"
mkdir -p /root/.ssh && chmod 700 /root/.ssh
[ -f /root/.ssh/babybloom_deploy ] || ssh-keygen -t ed25519 -N '' -C "babybloom-server" -f /root/.ssh/babybloom_deploy >/dev/null
grep -q "babybloom_deploy" /root/.ssh/config 2>/dev/null || cat >> /root/.ssh/config <<'CFG'
Host github.com
  IdentityFile /root/.ssh/babybloom_deploy
  StrictHostKeyChecking accept-new
CFG
chmod 600 /root/.ssh/config

echo
echo "✅ Сервер готов. Добавьте этот ключ в GitHub (репозиторий → Settings → Deploy keys → Add deploy key, без галочки Write):"
echo
cat /root/.ssh/babybloom_deploy.pub
