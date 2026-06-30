#!/bin/bash
# ============================================
# Instala GitHub Actions self-hosted runner (deploy imediato no push).
#
# vlcp-sufis01 e Debian 9 (glibc 2.24) — runner NATIVO nao funciona.
# Use: docker/install-github-runner-docker.sh (runner em container bookworm).
#
# Pre-requisito: token de registro (uso unico)
#   GitHub > poprua-cras > Settings > Actions > Runners > New self-hosted runner
#
# Uso:
#   sudo RUNNER_TOKEN='AAAA...' bash docker/install-github-runner-docker.sh
# ============================================
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
exec bash "$DIR/install-github-runner-docker.sh"
