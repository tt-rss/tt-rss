# Tiny Tiny RSS (tt-rss) 项目概览

## 项目概述

Tiny Tiny RSS (tt-rss) 是一个免费、灵活、开源的基于 Web 的新闻订阅（RSS/Atom 及其他格式）阅读器和聚合器。

**重要说明：**
- 原始 tt-rss 项目（https://tt-rss.org）已于 2025-11-01 停止维护
- 本项目是 2025-10-03 基于原项目的 fork，由长期贡献者继续维护
- 目标是继续 tt-rss 的开发，不计划进行重大破坏性变更

## 技术栈

### 后端
- **语言：** PHP（最低版本匹配 Debian stable 发布版本）
- **数据库：** PostgreSQL（唯一支持的数据库）
- **依赖管理：** Composer
- **核心依赖：**
  - `guzzlehttp/guzzle` - HTTP 客户端
  - `spomky-labs/otphp` - OTP/2FA 支持
  - `chillerlan/php-qrcode` - QR 码生成
  - `soundasleep/html2text` - HTML 转文本
  - `dragonmantank/cron-expression` - Cron 表达式解析
  - `j4mie/idiorm` - 轻量级 ORM（tt-rss 定制版）

### 前端
- **构建工具：** Gulp
- **样式：** LESS + CSS
- **代码检查：** ESLint, Stylelint
- **图标：** Material Design Icons

### 部署
- **容器化：** Docker + Docker Compose
- **Web 服务器：** Nginx

## 项目结构

```
rss/
├── classes/          # PHP 核心类文件
├── include/          # PHP 辅助函数和配置文件
├── api/              # API 接口
├── js/               # JavaScript 源代码
├── lib/              # 第三方库
├── plugins/          # 插件目录
├── themes/           # 主题目录
├── locale/           # 国际化翻译文件
├── sql/              # 数据库迁移脚本
├── schema/           # 数据库架构定义
├── templates/        # HTML 模板
├── images/           # 静态图片资源
├── tests/            # 单元测试
├── .docker/          # Docker 配置文件
└── vendor/           # Composer 依赖
```

## 构建与运行

### Docker 开发环境（推荐）

```bash
# 1. 复制环境配置文件
cp .env-dist .env

# 2. 编辑 .env 文件配置数据库连接等参数

# 3. 启动所有服务
docker-compose up -d

# 4. 访问 http://localhost:<HTTP_PORT>
```

服务组成：
- `db` - PostgreSQL 15 数据库
- `app` - tt-rss 应用服务
- `updater` - 自动更新服务
- `web-nginx` - Nginx Web 服务器

### 本地 PHP 环境

```bash
# 1. 安装 Composer 依赖
composer install

# 2. 安装 NPM 依赖
npm install

# 3. 复制配置文件
cp config.php-dist config.php

# 4. 配置 config.php 中的数据库连接等参数

# 5. 初始化数据库（参考安装指南）

# 6. 使用 PHP 内置服务器运行
php -S localhost:8000
```

### 前端资源构建

```bash
# 编译 LESS 样式
npx gulp

# CSS 代码检查
npm run lint:css

# JavaScript 代码检查
npm run lint:js
```

## 测试

```bash
# 运行 PHPUnit 测试
vendor/bin/phpunit

# 或使用 XML 配置
phpunit --configuration phpunit.xml
```

测试目录结构：
- `tests/` - 主测试套件
- `tests/mocked/` - 使用模拟依赖的测试

## 开发规范

### 代码风格
- **PHP：** 遵循 PSR-4 自动加载规范
- **CSS：** 使用 Stylelint 检查（标准配置）
- **JavaScript：** 使用 ESLint 检查（ES 模块）

### 贡献指南

1. **报告问题：** 通过 GitHub Issues 报告 bug
2. **文档改进：** 贡献 https://github.com/tt-rss/tt-rss.github.io
3. **翻译：** 通过 Weblate 参与翻译 https://hosted.weblate.org/engage/tt-rss/
4. **代码贡献：**
   - 重大变更（尤其是用户体验相关）需先通过 Discussion 或 Issue 讨论
   - 插件形式优先于修改核心代码
   - 确保代码通过现有测试

### 配置说明

**环境变量方式（Docker 推荐）：**
```bash
TTRSS_DB_HOST=myserver
TTRSS_SELF_URL_PATH=http://example.com/tt-rss
TTRSS_DB_USER=postgres
TTRSS_DB_PASS=password
TTRSS_DB_NAME=ttrss
```

**config.php 方式：**
```php
putenv('TTRSS_DB_HOST=myserver');
putenv('TTRSS_SELF_URL_PATH=http://example.com/tt-rss');
```

### 许可证

GNU General Public License v3.0 或更高版本

## 相关资源

- **文档：** https://tt-rss.org/docs/Installation-Guide.html
- **Docker 镜像：**
  - Docker Hub: `supahgreg/tt-rss`, `supahgreg/tt-rss-web-nginx`
  - GHCR: `ghcr.io/tt-rss/tt-rss`, `ghcr.io/tt-rss/tt-rss-web-nginx`
- **插件仓库：** https://github.com/tt-rss/ (以 `tt-rss-plugin-*` 为前缀)
