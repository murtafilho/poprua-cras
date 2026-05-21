#!/bin/bash

# Script de instalação do Supervisor para workers do Laravel POPRUA

set -e

PROJECT_DIR="/home/murta/projects/popruav2"
SUPERVISOR_CONF_DIR="/etc/supervisor/conf.d"
CONF_FILE="poprua-worker.conf"

echo "🚀 Instalando Supervisor para workers do Laravel POPRUA..."
echo ""

# Verificar se o arquivo de configuração existe
if [ ! -f "$PROJECT_DIR/supervisor/$CONF_FILE" ]; then
    echo "❌ Erro: Arquivo de configuração não encontrado em $PROJECT_DIR/supervisor/$CONF_FILE"
    exit 1
fi

# Criar diretório de logs se não existir
echo "📁 Criando diretório de logs..."
mkdir -p "$PROJECT_DIR/storage/logs"
touch "$PROJECT_DIR/storage/logs/worker.log"
chmod 664 "$PROJECT_DIR/storage/logs/worker.log"

# Criar link simbólico
echo "🔗 Criando link simbólico..."
if [ -L "$SUPERVISOR_CONF_DIR/$CONF_FILE" ]; then
    echo "⚠️  Link simbólico já existe. Removendo..."
    sudo rm "$SUPERVISOR_CONF_DIR/$CONF_FILE"
fi

sudo ln -s "$PROJECT_DIR/supervisor/$CONF_FILE" "$SUPERVISOR_CONF_DIR/$CONF_FILE"
echo "✅ Link simbólico criado"

# Recarregar configuração do Supervisor
echo "🔄 Recarregando configuração do Supervisor..."
sudo supervisorctl reread

# Atualizar Supervisor
echo "📝 Atualizando Supervisor..."
sudo supervisorctl update

# Iniciar workers
echo "▶️  Iniciando workers..."
sudo supervisorctl start poprua-worker:*

# Verificar status
echo ""
echo "📊 Status dos workers:"
sudo supervisorctl status poprua-worker:*

echo ""
echo "✅ Instalação concluída!"
echo ""
echo "📋 Comandos úteis:"
echo "  - Ver status: sudo supervisorctl status"
echo "  - Parar workers: sudo supervisorctl stop poprua-worker:*"
echo "  - Reiniciar workers: sudo supervisorctl restart poprua-worker:*"
echo "  - Ver logs: tail -f $PROJECT_DIR/storage/logs/worker.log"
