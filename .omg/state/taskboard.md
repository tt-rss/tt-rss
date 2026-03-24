# tt-rss 重构项目 - 任务板

**项目**: tt-rss 重构 (PHP → React + Spring Boot)  
**创建日期**: 2026-03-24  
**当前 Sprint**: Sprint 9 (Phase 7)  
**分支**: `refactor/react-springboot-20260324`

---

## 任务清单

### Phase 1: 项目初始化 (Week 1) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P1-T1 | 创建后端 Spring Boot 项目骨架 | 后端 | ✅ done | P0 | Gradle 配置、目录结构、application.yml |
| P1-T2 | 创建前端 React 项目骨架 | 前端 | ✅ done | P0 | Vite 配置、TypeScript、Mantine 集成 |
| P1-T3 | Docker Compose 开发环境配置 | 全栈 | ✅ done | P0 | 一键启动 PostgreSQL + 后端 + 前端 |
| P1-T4 | 数据库连接 + MyBatis-Plus 实体映射 (User) | 后端 | ✅ done | P0 | 可连接现有 tt-rss 数据库，User 实体可查询 |
| P1-T5 | CI/CD 基础流程配置 | 全栈 | ✅ done | P1 | GitHub Actions 自动构建 |

### Phase 2: 认证模块 (Week 2) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P2-T1 | JWT 认证服务实现 | 后端 | ✅ done | P0 | 登录接口、Token 生成、验证 |
| P2-T2 | Spring Security 配置 | 后端 | ✅ done | P0 | 认证过滤器、权限管理 |
| P2-T3 | 登录页面实现 | 前端 | ✅ done | P0 | 表单、验证、错误提示 |
| P2-T4 | 认证状态管理集成 | 前端 | ✅ done | P0 | Zustand store、Token 持久化、自动刷新 |
| P2-T5 | 登录/登出 API 联调 | 全栈 | ✅ done | P0 | 完整登录流程可用 |

### Phase 3: 订阅源管理 (Week 3) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P3-T1 | Feed/FeedCategory 实体映射 | 后端 | ✅ done | P0 | MyBatis-Plus 实体、Mapper |
| P3-T2 | Feed CRUD API 实现 | 后端 | ✅ done | P0 | 增删改查接口、DTO 转换 |
| P3-T3 | 分类管理 API 实现 | 后端 | ✅ done | P0 | 分类 CRUD 接口 |
| P3-T4 | Feed 树形组件实现 | 前端 | ✅ done | P0 | 可展开/折叠、选择 |
| P3-T5 | 添加/编辑订阅对话框 | 前端 | ✅ done | P0 | 表单、URL 验证、分类选择 |
| P3-T6 | Feed 管理联调 | 全栈 | ✅ done | P0 | 完整 CRUD 流程可用 |

### Phase 4: 文章功能 (Week 4) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P4-T1 | Entry/UserEntry 实体映射 | 后端 | ✅ done | P0 | MyBatis-Plus 实体、关联关系 |
| P4-T2 | 文章列表 API 实现 | 后端 | ✅ done | P0 | 分页、过滤（分类/订阅源/未读） |
| P4-T3 | 文章详情 API 实现 | 后端 | ✅ done | P0 | 单篇文章查询 |
| P4-T4 | 标记已读/星标 API | 后端 | ✅ done | P0 | 状态更新接口 |
| P4-T5 | 文章列表组件实现 | 前端 | ✅ done | P0 | 无限滚动、未读标识 |
| P4-T6 | 文章详情组件实现 | 前端 | ✅ done | P0 | 内容渲染、操作按钮 |
| P4-T7 | 文章功能联调 | 全栈 | ✅ done | P0 | 阅读流程完整可用 |

### Phase 5: 增强功能 (Week 5-6) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P5-T1 | OPML 导入 API 实现 | 后端 | ✅ done | P1 | 解析 OPML、批量导入订阅 |
| P5-T2 | OPML 导出 API 实现 | 后端 | ✅ done | P1 | 生成标准 OPML 文件 |
| P5-T3 | OPML 导入/导出 UI | 前端 | ✅ done | P1 | 文件上传、下载 |
| P5-T4 | 标签管理 API 实现 | 后端 | ✅ done | P1 | 标签 CRUD、文章标签关联 |
| P5-T5 | 标签管理 UI 实现 | 前端 | ✅ done | P1 | 标签列表、选择器 |
| P5-T6 | 定时任务实现 | 后端 | ✅ done | P1 | Feed 自动更新、错误处理 |
| P5-T7 | 搜索 API 实现 | 后端 | ✅ done | P1 | PostgreSQL 全文搜索 |
| P5-T8 | 搜索 UI 实现 | 前端 | ✅ done | P1 | 搜索框、结果高亮 |

### Phase 6: 优化与测试 (Week 7-8) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P6-T1 | 性能优化 | 全栈 | ✅ done | P1 | 首屏<2s、列表滚动 55fps+ |
| P6-T2 | 暗黑模式实现 | 前端 | ✅ done | P1 | 主题切换、持久化 |
| P6-T3 | 键盘快捷键实现 | 前端 | ✅ done | P1 | j/k 切换、空格标记、s 星标 |
| P6-T4 | 单元测试 (后端) | 后端 | ✅ done | P1 | 核心服务覆盖率>80% |
| P6-T5 | 组件测试 (前端) | 前端 | ✅ done | P1 | 核心组件测试覆盖 |
| P6-T6 | E2E 测试 | 全栈 | ✅ done | P1 | 关键流程自动化测试 |

### Phase 7: 发布准备 (Week 9) ✅ COMPLETED

| ID | 任务 | 负责人 | 状态 | 优先级 | 验收标准 |
|----|------|--------|------|--------|----------|
| P7-T1 | API 文档生成 | 后端 | ✅ done | P1 | OpenAPI 规范、Swagger UI |
| P7-T2 | 部署文档编写 | 全栈 | ✅ done | P1 | Docker 部署指南 |
| P7-T3 | 用户文档编写 | 前端 | ✅ done | P2 | 使用指南、快捷键说明 |
| P7-T4 | 回归测试 | 全栈 | ✅ done | P0 | 所有功能验证通过 |
| P7-T5 | Bug 修复 | 全栈 | ✅ done | P0 | 关键问题清零 |

---

## 状态图例

| 状态 | 标识 | 说明 |
|------|------|------|
| pending | ⏳ | 待开始 |
| in_progress | 🔄 | 进行中 |
| blocked | 🚫 | 已阻塞 |
| done | ✅ | 已完成 |
| verified | ✔️ | 已验证 |

---

## 当前进度

```
总任务数：39
已完成：39
进行中：0
已验证：12
完成率：100%
```

**Phase 1 完成度**: 5/5 (100%) ✅  
**Phase 2 完成度**: 5/5 (100%) ✅  
**Phase 3 完成度**: 6/6 (100%) ✅  
**Phase 4 完成度**: 7/7 (100%) ✅  
**Phase 5 完成度**: 8/8 (100%) ✅  
**Phase 6 完成度**: 6/6 (100%) ✅  
**Phase 7 完成度**: 5/5 (100%) ✅

---

## 阻塞问题

(无)

---

## 里程碑跟踪

| 里程碑 | 目标 | 预计日期 | 状态 |
|--------|------|----------|------|
| M0 | 项目启动 | Week 1 | ✅ completed |
| M1 | 核心功能完成 | Week 5 | ✅ completed |
| M2 | 增强功能完成 | Week 8 | ✅ completed |
| M3 | 发布准备 | Week 9 | ✅ completed |

---

## Phase 7 交付物清单

### 文档
- [x] Swagger UI - API 文档 (http://localhost:8080/swagger-ui.html)
- [x] DEPLOYMENT.md - 部署指南
- [x] USER_GUIDE.md - 用户文档
- [x] QWEN.md - 项目概览文档

### 后端
- [x] OpenApiConfig - OpenAPI 3.0 配置
- [x] Controller API 注解 - 所有 Controller 添加 @Operation 注解

### 功能流程
- [x] API 文档可查看
- [x] 可按部署文档完成部署
- [x] 用户可使用产品

---

## 项目总结

**重构成果**:
- 后端：Spring Boot 3.4 + MyBatis-Plus + PostgreSQL
- 前端：React 19 + TypeScript + Mantine 7
- 测试：43 个前端测试 + 后端核心服务测试
- 文档：完整的 API 文档、部署指南、用户文档

**技术栈升级**:
- PHP → Spring Boot (Java 21)
- Dojo Toolkit → React 19
- JPA → MyBatis-Plus
- 原生 RSS → Rome 库解析

**功能保留**:
- ✅ 用户认证（JWT）
- ✅ 订阅源管理
- ✅ 分类管理
- ✅ 文章阅读
- ✅ 标记已读/星标
- ✅ 标签管理
- ✅ OPML 导入导出
- ✅ 全文搜索
- ✅ 定时更新

**移除功能** (按用户需求):
- ❌ 插件系统
- ❌ 邮件通知/2FA
- ❌ 主题系统（保留亮色/暗黑模式）

---

## 下一步

项目重构完成，可以准备发布！
