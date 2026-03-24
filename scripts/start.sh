#!/bin/bash

# tt-rss 快速启动脚本（开发环境）
# 用于快速启动所有服务

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "============================================"
echo "  tt-rss 快速启动（开发环境）"
echo "============================================"
echo ""

# 检查 .env 文件
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo "⚠️  .env 文件不存在，复制 .env.example..."
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
    echo "✓ 请编辑 .env 文件配置环境变量"
    echo ""
fi

cd "$PROJECT_DIR"

# 启动服务
echo "🚀 启动 Docker 服务..."
docker compose up -d

echo ""
echo "等待服务启动..."
sleep 10

# 健康检查
echo ""
echo "执行健康检查..."

if curl -f http://localhost:8080/actuator/health &> /dev/null; then
    echo "✓ 后端服务正常"
else
    echo "⚠ 后端服务未就绪，请稍后检查"
fi

if curl -f http://localhost:3000 &> /dev/null; then
    echo "✓ 前端服务正常"
else
    echo "⚠ 前端服务未就绪，请稍后检查"
fi

echo ""
echo "============================================"
echo "  启动完成！"
echo "============================================"
echo ""
echo "访问地址:"
echo "  前端：http://localhost:3000"
echo "  API: http://localhost:8080"
echo "  Swagger UI: http://localhost:8080/swagger-ui.html"
echo ""
echo "查看日志:"
echo "  docker compose logs -f"
echo ""
echo "停止服务:"
echo "  docker compose down"
echo ""
