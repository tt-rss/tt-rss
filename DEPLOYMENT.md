# tt-rss 重构项目 - 部署指南

**项目**: tt-rss 重构 (React + Spring Boot)  
**版本**: 1.0.0  
**更新日期**: 2026-03-24

---

## 📋 目录

1. [前置要求](#前置要求)
2. [快速开始 (Docker Compose)](#快速开始)
3. [生产环境部署](#生产环境部署)
4. [环境变量配置](#环境变量配置)
5. [数据库初始化](#数据库初始化)
6. [常见问题](#常见问题)

---

## 前置要求

### 必需软件

| 软件 | 最低版本 | 推荐版本 |
|------|----------|----------|
| Docker | 20.10 | 24.0+ |
| Docker Compose | 2.0 | 2.20+ |
| PostgreSQL | 14 | 15+ |
| Node.js (本地开发) | 18 | 20+ |
| Java (本地开发) | 17 | 21+ |

### 硬件要求

| 组件 | 最低配置 | 推荐配置 |
|------|----------|----------|
| CPU | 2 核 | 4 核+ |
| 内存 | 2GB | 4GB+ |
| 磁盘 | 10GB | 50GB+ SSD |

---

## 快速开始

### 1. 克隆项目

```bash
git clone -b refactor/react-springboot-20260324 https://github.com/tt-rss/tt-rss.git
cd tt-rss
```

### 2. 配置环境变量

```bash
# 复制环境变量模板
cp .env.example .env

# 编辑环境变量（可选）
vim .env
```

### 3. 一键启动

```bash
# 启动所有服务
docker compose up -d

# 查看日志
docker compose logs -f

# 停止服务
docker compose down
```

### 4. 访问应用

| 服务 | 地址 | 说明 |
|------|------|------|
| 前端 | http://localhost:3000 | Vite 开发服务器 |
| 后端 API | http://localhost:8080 | Spring Boot API |
| Swagger UI | http://localhost:8080/swagger-ui.html | API 文档 |
| PostgreSQL | localhost:5432 | 数据库 |

---

## 生产环境部署

### 方案一：Docker Compose (推荐)

适合中小型部署，简单易维护。

#### 1. 准备生产环境配置

创建 `docker-compose.prod.yml`:

```yaml
version: '3.8'

services:
  # ===========================================
  # PostgreSQL 数据库（生产环境）
  # ===========================================
  db:
    image: postgres:15-alpine
    container_name: ttrss-db
    restart: always
    environment:
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_DB: ${POSTGRES_DB}
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./scripts/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    ports:
      - "127.0.0.1:${POSTGRES_PORT:-5432}:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - ttrss-network
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G

  # ===========================================
  # 后端 Spring Boot 服务（生产环境）
  # ===========================================
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile.prod
    container_name: ttrss-backend
    restart: always
    environment:
      # Spring 配置
      SPRING_PROFILES_ACTIVE: prod
      SERVER_PORT: 8080

      # 数据库连接
      SPRING_DATASOURCE_URL: jdbc:postgresql://db:5432/${POSTGRES_DB}
      SPRING_DATASOURCE_USERNAME: ${POSTGRES_USER}
      SPRING_DATASOURCE_PASSWORD: ${POSTGRES_PASSWORD}

      # JPA 配置
      SPRING_JPA_HIBERNATE_DDL_AUTO: validate
      SPRING_JPA_SHOW_SQL: "false"

      # JWT 配置（必须修改为安全密钥）
      JWT_SECRET: ${JWT_SECRET}
      JWT_EXPIRATION: 900000
      JWT_REFRESH_EXPIRATION: 604800000

      # 日志配置
      LOGGING_LEVEL_ROOT: INFO
      LOGGING_LEVEL_COM_TTRSS: INFO
    ports:
      - "${BACKEND_PORT:-8080}:8080"
    depends_on:
      db:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/actuator/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s
    networks:
      - ttrss-network
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 1G

  # ===========================================
  # 前端 Nginx 服务（生产环境）
  # ===========================================
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile.prod
    container_name: ttrss-frontend
    restart: always
    ports:
      - "${FRONTEND_PORT:-80}:80"
    depends_on:
      - backend
    networks:
      - ttrss-network
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 512M

  # ===========================================
  # 定时任务服务（Feed 自动更新）
  # ===========================================
  scheduler:
    build:
      context: ./backend
      dockerfile: Dockerfile.prod
    container_name: ttrss-scheduler
    restart: always
    environment:
      SPRING_PROFILES_ACTIVE: prod
      # 复用后端配置
      SPRING_DATASOURCE_URL: jdbc:postgresql://db:5432/${POSTGRES_DB}
      SPRING_DATASOURCE_USERNAME: ${POSTGRES_USER}
      SPRING_DATASOURCE_PASSWORD: ${POSTGRES_PASSWORD}
      JWT_SECRET: ${JWT_SECRET}
    depends_on:
      - db
    networks:
      - ttrss-network

networks:
  ttrss-network:
    driver: bridge

volumes:
  postgres_data:
    driver: local
```

#### 2. 创建生产环境 Dockerfile

**backend/Dockerfile.prod**:

```dockerfile
# 构建阶段
FROM eclipse-temurin:17-jdk-alpine AS build

WORKDIR /app

# 复制 Gradle 配置
COPY build.gradle gradlew settings.gradle ./
COPY gradle gradle

# 下载依赖
RUN chmod +x gradlew && ./gradlew dependencies --no-daemon || true

# 复制源代码
COPY src src

# 构建生产版本
RUN ./gradlew bootJar -Pprod --no-daemon

# ===========================================
# 运行阶段
# ===========================================
FROM eclipse-temurin:17-jre-alpine

WORKDIR /app

# 安装必要工具
RUN apk add --no-cache curl

# 复制 JAR 文件
COPY --from=build /app/build/libs/*.jar app.jar

# 创建非 root 用户
RUN addgroup -S appgroup && adduser -S appuser -G appgroup
USER appuser

EXPOSE 8080

# JVM 生产参数
ENV JAVA_OPTS="-Xmx512m -Xms256m -XX:+UseG1GC -XX:MaxGCPauseMillis=200"

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8080/actuator/health || exit 1

ENTRYPOINT ["sh", "-c", "java $JAVA_OPTS -jar app.jar"]
```

**frontend/Dockerfile.prod**:

```dockerfile
# 构建阶段
FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ===========================================
# Nginx 运行阶段
# ===========================================
FROM nginx:alpine

# 复制 Nginx 配置
COPY nginx.conf /etc/nginx/conf.d/default.conf

# 复制构建产物
COPY --from=build /app/dist /usr/share/nginx/html

# 暴露端口
EXPOSE 80

# 健康检查
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD wget -q --spider http://localhost/ || exit 1

CMD ["nginx", "-g", "daemon off;"]
```

**frontend/nginx.conf**:

```nginx
server {
    listen 80;
    server_name localhost;
    root /usr/share/nginx/html;
    index index.html;

    # Gzip 压缩
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript;

    # 缓存静态资源
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # API 代理
    location /api/ {
        proxy_pass http://backend:8080/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # SPA 路由支持
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

#### 3. 部署脚本

创建 `scripts/deploy.sh`:

```bash
#!/bin/bash

set -e

echo "🚀 开始部署 tt-rss..."

# 1. 拉取最新代码
echo "📦 拉取最新代码..."
git pull origin refactor/react-springboot-20260324

# 2. 检查环境变量
echo "🔧 检查环境变量..."
if [ ! -f .env ]; then
    echo "❌ .env 文件不存在！"
    echo "请运行：cp .env.example .env 并配置环境变量"
    exit 1
fi

# 3. 检查 JWT_SECRET 是否安全
JWT_SECRET=$(grep JWT_SECRET .env | cut -d '=' -f 2)
if [ "$JWT_SECRET" = "your-secret-key-change-in-production-minimum-32-characters-for-security" ]; then
    echo "⚠️  警告：JWT_SECRET 使用默认值，存在安全风险！"
    echo "请修改 .env 中的 JWT_SECRET 为安全的随机字符串"
    exit 1
fi

# 4. 构建镜像
echo "🏗️  构建 Docker 镜像..."
docker compose -f docker-compose.prod.yml build

# 5. 启动服务
echo "🚀 启动服务..."
docker compose -f docker-compose.prod.yml up -d

# 6. 等待服务就绪
echo "⏳ 等待服务就绪..."
sleep 30

# 7. 健康检查
echo "🏥 执行健康检查..."
if curl -f http://localhost:8080/actuator/health > /dev/null 2>&1; then
    echo "✅ 后端服务健康检查通过"
else
    echo "❌ 后端服务健康检查失败"
    docker compose -f docker-compose.prod.yml logs backend
    exit 1
fi

if curl -f http://localhost > /dev/null 2>&1; then
    echo "✅ 前端服务健康检查通过"
else
    echo "❌ 前端服务健康检查失败"
    docker compose -f docker-compose.prod.yml logs frontend
    exit 1
fi

echo "🎉 部署完成！"
echo ""
echo "访问地址："
echo "  前端：http://localhost"
echo "  API: http://localhost:8080"
echo "  Swagger: http://localhost:8080/swagger-ui.html"
```

#### 4. 执行部署

```bash
# 赋予执行权限
chmod +x scripts/deploy.sh

# 执行部署
./scripts/deploy.sh
```

---

### 方案二：传统部署（无 Docker）

适合已有基础设施的环境。

#### 1. 后端部署

```bash
# 1. 安装 Java 21
sudo apt update
sudo apt install openjdk-21-jdk -y

# 2. 安装 PostgreSQL 15
sudo apt install postgresql-15 -y

# 3. 创建数据库
sudo -u postgres psql
CREATE DATABASE ttrss;
CREATE USER ttrss_user WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE ttrss TO ttrss_user;
\q

# 4. 构建后端
cd backend
./gradlew bootJar -Pprod

# 5. 创建 systemd 服务
sudo vim /etc/systemd/system/ttrss-backend.service
```

**ttrss-backend.service**:

```ini
[Unit]
Description=tt-rss Backend Service
After=network.target postgresql.service

[Service]
Type=simple
User=ttrss
WorkingDirectory=/opt/ttrss/backend
Environment=SPRING_PROFILES_ACTIVE=prod
Environment=JWT_SECRET=your-secret-key
ExecStart=/usr/bin/java -jar build/libs/*.jar
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
# 6. 启动服务
sudo systemctl daemon-reload
sudo systemctl enable ttrss-backend
sudo systemctl start ttrss-backend
sudo systemctl status ttrss-backend
```

#### 2. 前端部署

```bash
# 1. 安装 Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs -y

# 2. 构建前端
cd frontend
npm ci
npm run build

# 3. 配置 Nginx
sudo vim /etc/nginx/sites-available/ttrss
```

**Nginx 配置** (参考上方 nginx.conf)

```bash
# 4. 启用站点
sudo ln -s /etc/nginx/sites-available/ttrss /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## 环境变量配置

### 完整环境变量列表

```bash
# ===========================================
# 数据库配置
# ===========================================
POSTGRES_VERSION=15
POSTGRES_USER=ttrss_user
POSTGRES_PASSWORD=your_secure_password  # 必须修改
POSTGRES_DB=ttrss
POSTGRES_PORT=5432

# ===========================================
# 后端配置
# ===========================================
BACKEND_PORT=8080
SPRING_PROFILES_ACTIVE=prod

# JWT 配置（必须修改为安全密钥）
JWT_SECRET=your-very-secure-random-key-minimum-32-characters  # 必须修改
JWT_EXPIRATION=900000           # 15 分钟
JWT_REFRESH_EXPIRATION=604800000 # 7 天

# ===========================================
# 前端配置
# ===========================================
FRONTEND_PORT=80
VITE_API_BASE_URL=/api

# ===========================================
# 调度器配置
# ===========================================
SCHEDULER_ENABLED=true
SCHEDULER_FIXED_DELAY=300000    # 5 分钟
SCHEDULER_INITIAL_DELAY=60000   # 1 分钟
```

### 生成安全的 JWT_SECRET

```bash
# 方法 1: 使用 openssl
openssl rand -base64 32

# 方法 2: 使用 Python
python3 -c "import secrets; print(secrets.token_urlsafe(32))"

# 方法 3: 使用 /dev/urandom
cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1
```

---

## 数据库初始化

### 方案一：自动初始化（推荐）

使用 Flyway 自动迁移（已配置在 application.yml）:

```yaml
spring:
  flyway:
    enabled: true
    locations: classpath:db/migration
    baseline-on-migrate: true
```

### 方案二：手动初始化

创建 `scripts/init.sql`:

```sql
-- 创建扩展
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- 创建用户表（兼容现有 tt-rss）
CREATE TABLE IF NOT EXISTS ttrss_users (
    id SERIAL PRIMARY KEY,
    login VARCHAR(255) UNIQUE NOT NULL,
    pwd_hash VARCHAR(255) NOT NULL,
    access_level INTEGER DEFAULT 0,
    otp_enabled BOOLEAN DEFAULT FALSE,
    email VARCHAR(255)
);

-- 创建订阅源分类表
CREATE TABLE IF NOT EXISTS ttrss_feed_categories (
    id SERIAL PRIMARY KEY,
    owner_uid INTEGER REFERENCES ttrss_users(id),
    title VARCHAR(255) NOT NULL,
    parent_cat INTEGER DEFAULT 0,
    order_id INTEGER DEFAULT 0
);

-- 创建订阅源表
CREATE TABLE IF NOT EXISTS ttrss_feeds (
    id SERIAL PRIMARY KEY,
    owner_uid INTEGER REFERENCES ttrss_users(id),
    title VARCHAR(255) NOT NULL,
    feed_url VARCHAR(512) NOT NULL,
    site_url VARCHAR(512),
    cat_id INTEGER REFERENCES ttrss_feed_categories(id),
    last_updated TIMESTAMP,
    last_error TEXT,
    update_interval INTEGER DEFAULT 60
);

-- 创建文章表
CREATE TABLE IF NOT EXISTS ttrss_entries (
    id SERIAL PRIMARY KEY,
    guid VARCHAR(512) UNIQUE,
    title TEXT,
    content TEXT,
    link VARCHAR(512),
    updated TIMESTAMP,
    author VARCHAR(255)
);

-- 创建用户文章关联表
CREATE TABLE IF NOT EXISTS ttrss_user_entries (
    int_id SERIAL PRIMARY KEY,
    ref_id INTEGER REFERENCES ttrss_entries(id),
    feed_id INTEGER REFERENCES ttrss_feeds(id),
    owner_uid INTEGER REFERENCES ttrss_users(id),
    unread BOOLEAN DEFAULT TRUE,
    marked BOOLEAN DEFAULT FALSE,
    published BOOLEAN DEFAULT FALSE
);

-- 创建标签表
CREATE TABLE IF NOT EXISTS ttrss_labels2 (
    id SERIAL PRIMARY KEY,
    owner_uid INTEGER REFERENCES ttrss_users(id),
    caption VARCHAR(255) NOT NULL,
    fg_color VARCHAR(7),
    bg_color VARCHAR(7)
);

-- 创建全文搜索索引
CREATE INDEX IF NOT EXISTS idx_entries_title_trgm ON ttrss_entries USING gin (title gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_entries_content_trgm ON ttrss_entries USING gin (content gin_trgm_ops);

-- 创建其他必要索引
CREATE INDEX IF NOT EXISTS idx_feeds_owner_uid ON ttrss_feeds(owner_uid);
CREATE INDEX IF NOT EXISTS idx_user_entries_owner_uid ON ttrss_user_entries(owner_uid);
CREATE INDEX IF NOT EXISTS idx_user_entries_unread ON ttrss_user_entries(unread);
```

执行初始化:

```bash
# Docker 环境
docker compose exec db psql -U postgres -d ttrss -f /docker-entrypoint-initdb.d/init.sql

# 或直接执行
psql -U ttrss_user -d ttrss -f scripts/init.sql
```

---

## 常见问题

### 1. 容器启动失败

**问题**: 容器启动后立即退出

**解决**:
```bash
# 查看日志
docker compose logs backend

# 检查端口占用
netstat -tlnp | grep :8080

# 重启服务
docker compose down
docker compose up -d
```

### 2. 数据库连接失败

**问题**: `Connection refused` 或 `Authentication failed`

**解决**:
```bash
# 检查数据库是否启动
docker compose ps db

# 查看数据库日志
docker compose logs db

# 验证凭据
docker compose exec db psql -U postgres -c "\l"
```

### 3. JWT 认证失败

**问题**: 登录后请求返回 401

**解决**:
```bash
# 检查 JWT_SECRET 是否一致
grep JWT_SECRET .env

# 确保后端和调度器使用相同的 JWT_SECRET
# 重启服务
docker compose restart backend scheduler
```

### 4. 前端无法访问后端 API

**问题**: 浏览器控制台显示 CORS 错误或网络错误

**解决**:
```bash
# 检查 VITE_API_BASE_URL 配置
grep VITE_API_BASE_URL .env

# 开发环境应该是 http://localhost:8080
# 生产环境应该是 /api（通过 Nginx 代理）

# 重新构建前端
docker compose build frontend
docker compose up -d frontend
```

### 5. 定时任务不执行

**问题**: Feed 不自动更新

**解决**:
```bash
# 检查调度器服务
docker compose ps scheduler

# 查看调度器日志
docker compose logs scheduler

# 确认数据库中有待更新的 Feed
docker compose exec db psql -U postgres -d ttrss -c \
  "SELECT COUNT(*) FROM ttrss_feeds WHERE last_updated IS NULL OR last_updated < NOW() - INTERVAL '1 hour'"
```

### 6. 内存不足

**问题**: OOM Killer 杀死容器

**解决**:
```bash
# 调整 JVM 参数
vim .env
# JAVA_OPTS=-Xmx256m -Xms128m

# 或调整 Docker Compose 资源限制
vim docker-compose.prod.yml
# 修改 deploy.resources.limits.memory
```

### 7. 备份和恢复

**备份数据库**:
```bash
docker compose exec db pg_dump -U postgres ttrss > backup_$(date +%Y%m%d).sql
```

**恢复数据库**:
```bash
cat backup_20260324.sql | docker compose exec -T db psql -U postgres -d ttrss
```

---

## 监控和维护

### 健康检查

```bash
# 后端健康
curl http://localhost:8080/actuator/health

# 前端健康
curl http://localhost/

# 数据库健康
docker compose exec db pg_isready
```

### 日志查看

```bash
# 查看所有服务日志
docker compose logs -f

# 查看特定服务日志
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f db
```

### 性能监控

```bash
# 查看容器资源使用
docker stats

# 查看数据库连接数
docker compose exec db psql -U postgres -d ttrss -c \
  "SELECT count(*) FROM pg_stat_activity"
```

---

## 升级指南

```bash
# 1. 备份数据
docker compose exec db pg_dump -U postgres ttrss > backup_before_upgrade.sql

# 2. 拉取新代码
git pull origin refactor/react-springboot-20260324

# 3. 停止服务
docker compose down

# 4. 重新构建
docker compose build

# 5. 启动服务
docker compose up -d

# 6. 验证
curl http://localhost:8080/actuator/health
```

---

**部署文档版本**: 1.0  
**最后更新**: 2026-03-24
