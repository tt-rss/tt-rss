# tt-rss 重构项目 - 团队组装记录

## 项目目标

将 tt-rss 从 PHP + PostgreSQL 重构为 **React (前端) + Spring Boot (后端)** 的现代化架构。

## 需求确认

| 维度 | 决策 |
|------|------|
| **项目范围** | 核心 + 部分增强（订阅、文章、标签、OPML 导入导出） |
| **数据迁移** | 需要兼容现有 tt-rss PostgreSQL 数据库 |
| **UI 保真度** | 现代化改进（保持风格一致，使用现代 React 组件库优化） |
| **移除功能** | 插件系统、主题系统 |
| **保留设计** | 邮件通知（暂不实现，为 AI 周报预留） |

## 团队组成

| 角色 | 职责 | 状态 |
|------|------|------|
| **omg-director** | 项目协调与决策 | ✅ 已激活 |
| **omg-architect** | 架构设计和技术选型 | ✅ 已完成 |
| **omg-product** | 产品范围定义和验收标准 | ✅ 已完成 |
| **omg-planner** | 任务分解和计划 | ✅ 已完成 |
| **omg-executor** | 执行开发 | ⏳ 待命 |
| **omg-reviewer** | 代码审查 | ⏳ 待命 |
| **omg-verifier** | 验收验证 | ⏳ 待命 |

## 技术栈确认

### 后端
- Spring Boot 3.4.x
- Java 21 (LTS)
- Spring Security 6.x (JWT)
- MyBatis-Plus 3.5.x (ORM 框架)
- PostgreSQL JDBC 42.7.x
- Rome 2.1.0 (RSS 解析)
- Spring Scheduler + Quartz (定时任务)
- Gradle 8.x (构建)

### 前端
- React 19.x
- TypeScript 5.x
- Mantine 7.x (UI 组件库)
- Zustand 5.x (状态管理)
- TanStack Query 5.x (数据获取)
- React Router 7.x (路由)
- Vite 6.x (构建)

### 部署
- Docker + Docker Compose
- PostgreSQL 15+

## 关键决策

1. **仓库组织**: Monorepo (前后端同仓库)
2. **数据库策略**: 兼容现有 tt-rss schema，不破坏性修改
3. **ORM 框架**: MyBatis-Plus (替代 JPA，更灵活的 SQL 控制)
4. **认证方案**: JWT + Refresh Token (HttpOnly Cookie)
5. **邮件通知**: 保留设计但暂不实现（为 AI 周报预留）
6. **RSS 解析**: Rome 2.1.0
7. **定时任务**: Spring Scheduler + 数据库锁
8. **UI 组件**: Mantine (轻量、现代、主题灵活)

## 交付里程碑

| 里程碑 | 内容 | 周期 |
|--------|------|------|
| M0 | 项目启动、环境搭建 | Week 1 |
| M1 | 核心功能 (P0) 完成 | Week 2-5 |
| M2 | 增强功能 (P1) 完成 | Week 6-8 |
| M3 | 优化测试、发布准备 | Week 9-11 |

## 沟通协议

- 每日站会：同步进度和阻塞
- 每周演示：可工作软件演示
- 关键决策：omg-director 审批
- 代码审查：omg-reviewer 执行
- 验收验证：omg-verifier 执行

## 状态文件

| 文件 | 用途 |
|------|------|
| `.omg/state/workspace.json` | 车道所有权、清洁度、信任、交接准备 |
| `.omg/state/taskboard.md` | 任务清单 (ready/blocked/done/verified) |
| `.omg/state/workflow.md` | 阶段状态和阻塞问题 |
| `.omg/state/team-assembly.md` | 本文件，团队组装记录 |

---

**创建日期**: 2026-03-24
**状态**: 团队已组装，等待启动确认
