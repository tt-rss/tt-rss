# tt-rss

Tiny Tiny RSS 现代化版本，基于 React + Spring Boot 技术栈。

> **注意**: 这是 tt-rss 的现代化重构版本，原始项目已停止维护。本版本由社区继续维护。

## 🚀 快速开始

### 开发环境

```bash
# 1. 复制环境变量
cp .env.example .env

# 2. 启动所有服务
docker compose up -d

# 3. 访问应用
# 前端：http://localhost:3000
# API: http://localhost:8080
# Swagger UI: http://localhost:8080/swagger-ui.html
```

### 生产环境

```bash
# 1. 配置环境变量（必须修改 JWT_SECRET）
vim .env

# 2. 执行部署脚本
./scripts/deploy.sh
```

详细部署指南请参考 [DEPLOYMENT.md](DEPLOYMENT.md)

## 📁 项目结构

```
.
├── backend/              # Spring Boot 后端
│   ├── src/main/java/
│   │   └── com/ttrss/
│   │       ├── config/          # 配置类
│   │       ├── module/          # 功能模块
│   │       │   ├── auth/        # 认证模块
│   │       │   ├── feed/        # 订阅源管理
│   │       │   ├── article/     # 文章管理
│   │       │   ├── label/       # 标签管理
│   │       │   ├── opml/        # OPML 导入导出
│   │       │   └── scheduler/   # 定时任务
│   │       └── common/          # 通用工具
│   └── src/main/resources/
│       ├── application.yml      # 应用配置
│       └── db/migration/        # 数据库迁移
│
├── frontend/             # React 前端
│   ├── src/
│   │   ├── components/        # React 组件
│   │   ├── pages/             # 页面
│   │   ├── hooks/             # 自定义 Hooks
│   │   ├── stores/            # Zustand 状态管理
│   │   ├── services/          # API 服务
│   │   └── types/             # TypeScript 类型
│   └── public/
│
├── scripts/              # 部署脚本
│   ├── deploy.sh          # 生产环境部署
│   ├── start.sh           # 开发环境启动
│   └── init.sql           # 数据库初始化
│
├── docker-compose.yml          # 开发环境配置
├── docker-compose.prod.yml     # 生产环境配置
├── DEPLOYMENT.md               # 部署指南
├── USER_GUIDE.md               # 用户文档
└── PROJECT_SUMMARY.md          # 项目总结
```

## 🛠️ 技术栈

### 后端
- **框架**: Spring Boot 3.4
- **语言**: Java 21
- **ORM**: MyBatis-Plus 3.5
- **安全**: Spring Security 6 + JWT
- **数据库**: PostgreSQL 15
- **RSS 解析**: Rome 2.1

### 前端
- **框架**: React 19
- **语言**: TypeScript 5
- **UI 库**: Mantine 7
- **状态管理**: Zustand + TanStack Query
- **构建**: Vite 6

## ✨ 功能特性

- ✅ 用户认证（JWT）
- ✅ 订阅源管理（CRUD）
- ✅ 分类管理
- ✅ 文章阅读（列表/详情）
- ✅ 标记已读/星标
- ✅ 标签管理
- ✅ OPML 导入导出
- ✅ 全文搜索
- ✅ 定时更新订阅源
- ✅ 暗黑模式
- ✅ 键盘快捷键

## 📚 文档

- [部署指南](DEPLOYMENT.md) - Docker 部署、环境变量、常见问题
- [用户文档](USER_GUIDE.md) - 快速入门、功能说明、快捷键
- [项目总结](PROJECT_SUMMARY.md) - 重构过程、技术决策

## 🔧 开发

### 后端

```bash
cd backend
./gradlew bootRun
```

### 前端

```bash
cd frontend
npm install
npm run dev
```

## 📝 许可证

GNU General Public License v3.0

## 🙏 致谢

原始 tt-rss 项目由 Andrew Dolgov 创建和维护。
