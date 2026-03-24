# Frontend Vite Dockerfile
# 用于本地开发环境，支持热更新

FROM node:20-alpine

WORKDIR /app

# 安装必要的工具
RUN apk add --no-cache git

# 复制 package 文件
COPY package.json package-lock.json ./

# 安装依赖（利用 Docker 缓存层）
RUN npm ci

# 复制源代码
COPY . .

# 暴露 Vite 开发服务器端口
EXPOSE 3000

# 监听文件系统变化
ENV CHOKIDAR_USEPOLLING=true
ENV WATCHPACK_POLLING=true

# 禁用 Vite 主机检查（Docker 环境需要）
ENV VITE_HOST=0.0.0.0

# 健康检查
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD wget -q --spider http://localhost:3000 || exit 1

# 启动 Vite 开发服务器
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]
