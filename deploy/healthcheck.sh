#!/usr/bin/env bash
#
# 部署前健康检查包装脚本。
# 用法：
#   sudo bash deploy/healthcheck.sh [运行用户]   # 默认 www
#
# 铁律：服务器上执行 PHP artisan / php 一律使用站点运行用户（www），
# 避免 root 写入 storage/framework 缓存导致线上 500。
set -euo pipefail

cd "$(dirname "$0")/.."

RUN_AS="${1:-www}"

if [ -n "${SUDO_USER:-}" ] && [ "$(id -u)" = "0" ]; then
  echo "以 ${RUN_AS} 运行健康检查..."
  sudo -u "${RUN_AS}" php deploy/healthcheck.php
else
  echo "警告：未检测到 sudo 环境，将以当前用户（$(id -un)）直接运行。"
  php deploy/healthcheck.php
fi