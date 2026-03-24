#!/bin/bash

# tt-rss 生产环境部署脚本
# 用于自动化部署、升级和健康检查

set -e

# ===========================================
# 配置
# ===========================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
COMPOSE_FILE="docker-compose.prod.yml"
LOG_FILE="$SCRIPT_DIR/deploy_$(date +%Y%m%d_%H%M%S).log"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ===========================================
# 函数定义
# ===========================================

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✓${NC} $1" | tee -a "$LOG_FILE"
}

warn() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ✗${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

check_env() {
    log "检查环境变量..."
    
    if [ ! -f "$PROJECT_DIR/.env" ]; then
        error ".env 文件不存在！请运行：cp .env.example .env"
    fi
    
    # 检查 JWT_SECRET 是否安全
    JWT_SECRET=$(grep "^JWT_SECRET=" "$PROJECT_DIR/.env" | cut -d '=' -f 2)
    DEFAULT_SECRET="your-secret-key-change-in-production-minimum-32-characters-for-security"
    
    if [ "$JWT_SECRET" = "$DEFAULT_SECRET" ]; then
        error "JWT_SECRET 使用默认值，存在安全风险！请修改 .env 中的 JWT_SECRET"
    fi
    
    if [ ${#JWT_SECRET} -lt 32 ]; then
        error "JWT_SECRET 长度至少为 32 个字符"
    fi
    
    # 检查 POSTGRES_PASSWORD 是否设置
    POSTGRES_PASSWORD=$(grep "^POSTGRES_PASSWORD=" "$PROJECT_DIR/.env" | cut -d '=' -f 2)
    if [ -z "$POSTGRES_PASSWORD" ]; then
        error "POSTGRES_PASSWORD 未设置"
    fi
    
    success "环境变量检查通过"
}

check_requirements() {
    log "检查系统要求..."
    
    # 检查 Docker
    if ! command -v docker &> /dev/null; then
        error "Docker 未安装"
    fi
    
    # 检查 Docker Compose
    if ! command -v docker compose &> /dev/null; then
        error "Docker Compose 未安装"
    fi
    
    # 检查 Git
    if ! command -v git &> /dev/null; then
        error "Git 未安装"
    fi
    
    # 检查 Docker 是否运行
    if ! docker info &> /dev/null; then
        error "Docker 未运行"
    fi
    
    success "系统要求检查通过"
}

pull_code() {
    log "拉取最新代码..."
    cd "$PROJECT_DIR"
    git pull origin refactor/react-springboot-20260324
    success "代码拉取完成"
}

backup_data() {
    log "备份数据库..."
    BACKUP_DIR="$PROJECT_DIR/backups"
    mkdir -p "$BACKUP_DIR"
    
    BACKUP_FILE="$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).sql"
    
    if docker ps | grep -q ttrss-db; then
        docker compose -f "$COMPOSE_FILE" exec -T db pg_dump -U "${POSTGRES_USER:-ttrss_user}" "${POSTGRES_DB:-ttrss}" > "$BACKUP_FILE"
        success "数据库备份完成：$BACKUP_FILE"
    else
        warn "数据库容器未运行，跳过备份"
    fi
}

build_images() {
    log "构建 Docker 镜像..."
    cd "$PROJECT_DIR"
    
    docker compose -f "$COMPOSE_FILE" build
    
    success "镜像构建完成"
}

start_services() {
    log "启动服务..."
    cd "$PROJECT_DIR"
    
    docker compose -f "$COMPOSE_FILE" up -d
    
    success "服务启动完成"
}

wait_for_services() {
    log "等待服务就绪..."
    
    # 等待数据库
    log "等待数据库启动..."
    timeout=60
    elapsed=0
    while ! docker compose -f "$COMPOSE_FILE" exec -T db pg_isready -U "${POSTGRES_USER:-ttrss_user}" -d "${POSTGRES_DB:-ttrss}" &> /dev/null; do
        sleep 2
        elapsed=$((elapsed + 2))
        if [ $elapsed -ge $timeout ]; then
            error "数据库启动超时"
        fi
    done
    success "数据库已就绪"
    
    # 等待后端
    log "等待后端服务启动..."
    timeout=90
    elapsed=0
    while ! curl -f http://localhost:"${BACKEND_PORT:-8080}"/actuator/health &> /dev/null; do
        sleep 3
        elapsed=$((elapsed + 3))
        if [ $elapsed -ge $timeout ]; then
            error "后端服务启动超时"
        fi
    done
    success "后端服务已就绪"
    
    # 等待前端
    log "等待前端服务启动..."
    timeout=30
    elapsed=0
    while ! curl -f http://localhost:"${FRONTEND_PORT:-80}"/ &> /dev/null; do
        sleep 2
        elapsed=$((elapsed + 2))
        if [ $elapsed -ge $timeout ]; then
            error "前端服务启动超时"
        fi
    done
    success "前端服务已就绪"
}

health_check() {
    log "执行健康检查..."
    
    # 后端健康检查
    if curl -f http://localhost:"${BACKEND_PORT:-8080}"/actuator/health &> /dev/null; then
        success "后端健康检查通过"
    else
        error "后端健康检查失败"
    fi
    
    # 前端健康检查
    if curl -f http://localhost:"${FRONTEND_PORT:-80}"/ &> /dev/null; then
        success "前端健康检查通过"
    else
        error "前端健康检查失败"
    fi
    
    # 数据库健康检查
    if docker compose -f "$COMPOSE_FILE" exec -T db pg_isready -U "${POSTGRES_USER:-ttrss_user}" -d "${POSTGRES_DB:-ttrss}" &> /dev/null; then
        success "数据库健康检查通过"
    else
        error "数据库健康检查失败"
    fi
}

show_status() {
    log "服务状态:"
    docker compose -f "$COMPOSE_FILE" ps
    
    echo ""
    echo "============================================"
    success "部署完成！"
    echo "============================================"
    echo ""
    echo "访问地址:"
    echo "  前端：http://localhost:${FRONTEND_PORT:-80}"
    echo "  API: http://localhost:${BACKEND_PORT:-8080}"
    echo "  Swagger UI: http://localhost:${BACKEND_PORT:-8080}/swagger-ui.html"
    echo ""
    echo "日志查看:"
    echo "  docker compose -f $COMPOSE_FILE logs -f"
    echo ""
}

# ===========================================
# 主流程
# ===========================================

main() {
    echo "============================================"
    echo "  tt-rss 生产环境部署脚本"
    echo "  $(date)"
    echo "============================================"
    echo ""
    
    check_requirements
    check_env
    backup_data
    pull_code
    build_images
    start_services
    wait_for_services
    health_check
    show_status
    
    log "部署日志已保存到：$LOG_FILE"
}

# 清理函数
cleanup() {
    if [ $? -ne 0 ]; then
        error "部署失败！请检查日志：$LOG_FILE"
    fi
}

trap cleanup EXIT

# 执行主流程
main "$@"
