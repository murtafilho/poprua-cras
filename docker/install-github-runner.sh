#!/bin/bash
# ============================================
# Instala GitHub Actions self-hosted runner (deploy imediato no push).
#
# Pre-requisito: token de registro em
#   GitHub > poprua-cras > Settings > Actions > Runners > New self-hosted runner
#
# Uso no host vlcp-sufis01 (como root):
#   sudo RUNNER_TOKEN='AAAA...' bash docker/install-github-runner.sh
#
# Depois de instalado, cada push em main dispara .github/workflows/deploy-production.yml
# ============================================
set -euo pipefail

REPO="murtafilho/poprua-cras"
RUNNER_VERSION="${RUNNER_VERSION:-2.325.0}"
INSTALL_DIR="${INSTALL_DIR:-/opt/github-runners/poprua-cras}"
RUNNER_NAME="${RUNNER_NAME:-vlcp-sufis01-poprua-cras}"
RUNNER_LABELS="${RUNNER_LABELS:-poprua-cras,self-hosted,linux}"

if [ -z "${RUNNER_TOKEN:-}" ]; then
    echo "ERRO: defina RUNNER_TOKEN (GitHub > repo > Settings > Actions > Runners > New)"
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

./config.sh \
    --url "https://github.com/${REPO}" \
    --token "$RUNNER_TOKEN" \
    --name "$RUNNER_NAME" \
    --labels "$RUNNER_LABELS" \
    --unattended \
    --replace

./svc.sh install
./svc.sh start

echo ""
echo "Runner instalado em $INSTALL_DIR"
echo "Labels: $RUNNER_LABELS"
echo "Workflow: .github/workflows/deploy-production.yml (push em main)"
