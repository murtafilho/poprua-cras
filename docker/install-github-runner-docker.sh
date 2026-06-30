#!/bin/bash
# ============================================
# GitHub Actions self-hosted runner em Docker
#
# Necessario no vlcp-sufis01 (Debian 9): o runner nativo exige glibc >= 2.28.
# Este script roda o runner em container bookworm com --network host.
#
# Pre-requisito: token de registro (uso unico)
#   GitHub > poprua-cras > Settings > Actions > Runners > New self-hosted runner
#
# Uso:
#   sudo RUNNER_TOKEN='AAAA...' bash docker/install-github-runner-docker.sh
# ============================================
set -euo pipefail

REPO="murtafilho/poprua-cras"
RUNNER_VERSION="${RUNNER_VERSION:-2.325.0}"
INSTALL_DIR="${INSTALL_DIR:-/opt/github-runners/poprua-cras}"
RUNNER_NAME="${RUNNER_NAME:-vlcp-sufis01-poprua-cras}"
CONTAINER="github-runner-poprua-cras"
APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
DEPLOY_KEY="/root/.ssh/poprua_cras_deploy"

if [ -z "${RUNNER_TOKEN:-}" ]; then
    echo "ERRO: defina RUNNER_TOKEN"
    exit 1
fi

mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

if [ ! -f ./config.sh ]; then
    curl -fsSL -o actions-runner-linux-x64.tar.gz \
        "https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/actions-runner-linux-x64-${RUNNER_VERSION}.tar.gz"
    tar xzf actions-runner-linux-x64.tar.gz
    rm -f actions-runner-linux-x64.tar.gz
fi

chown -R cassio.martins:forum "$INSTALL_DIR"
sudo -u cassio.martins ./config.sh \
    --url "https://github.com/${REPO}" \
    --token "$RUNNER_TOKEN" \
    --name "$RUNNER_NAME" \
    --labels "poprua-cras,self-hosted,linux" \
    --unattended \
    --replace

# Para o servico nativo (falha em Debian 9)
systemctl stop "actions.runner.${REPO//\//-}.${RUNNER_NAME}.service" 2>/dev/null || true
systemctl disable "actions.runner.${REPO//\//-}.${RUNNER_NAME}.service" 2>/dev/null || true

docker rm -f "$CONTAINER" 2>/dev/null || true

docker run -d --restart unless-stopped --network host \
  --name "$CONTAINER" \
  -v "$INSTALL_DIR:/actions-runner" \
  -v "$APP_DIR:$APP_DIR:rw" \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v "$DEPLOY_KEY:$DEPLOY_KEY:ro" \
  -w /actions-runner \
  debian:bookworm-slim \
  bash -c "apt-get update -qq && apt-get install -y -qq sudo git curl ca-certificates libicu72 docker.io openssh-client netcat-openbsd >/dev/null && mkdir -p /root/.ssh && ssh-keyscan -t ed25519 github.com >> /root/.ssh/known_hosts 2>/dev/null; echo 'runner ALL=(ALL) NOPASSWD: ALL' > /etc/sudoers.d/runner && chmod 440 /etc/sudoers.d/runner && id -u runner >/dev/null 2>&1 || useradd -m -u 999330183 runner && chown -R runner:runner /actions-runner && su runner -c 'cd /actions-runner && ./run.sh'"

echo ""
echo "Runner Docker: $CONTAINER (--network host)"
echo "Labels: poprua-cras, self-hosted, linux"
echo "Verifique: GitHub > Settings > Actions > Runners"
echo "Logs: docker logs -f $CONTAINER"
