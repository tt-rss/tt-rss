# TTRSS Backend

基于 Spring Boot 3.4.x 的后端服务

## 技术栈

- **Spring Boot**: 3.4.1
- **Java**: 17+ (推荐 21)
- **MyBatis-Plus**: 3.5.9
- **PostgreSQL**: JDBC Driver
- **Spring Security**: 6.x (JWT 预留)
- **Gradle**: 8.12

## 项目结构

```
backend/
├── build.gradle              # Gradle 构建配置
├── settings.gradle           # Gradle 项目设置
├── gradlew                   # Gradle Wrapper (Unix)
├── gradlew.bat               # Gradle Wrapper (Windows)
├── src/
│   ├── main/
│   │   ├── java/com/ttrss/
│   │   │   ├── TtrssApplication.java    # 主应用类
│   │   │   ├── config/                   # 配置类
│   │   │   ├── module/                   # 业务模块
│   │   │   └── common/                   # 公共组件
│   │   └── resources/
│   │       ├── application.yml           # 主配置文件
│   │       └── application-dev.yml       # 开发环境配置
│   └── test/
│       └── java/com/ttrss/               # 测试类
```

## 快速开始

### 前置要求

- JDK 17+ (推荐 JDK 21)
- PostgreSQL 15+
- Gradle 8.x (或使用 Gradle Wrapper)

### 构建项目

```bash
# Unix/Linux/macOS
./gradlew build

# Windows
gradlew.bat build
```

### 运行应用

```bash
# 开发环境
./gradlew bootRun

# 或运行 jar
java -jar build/libs/backend-0.0.1-SNAPSHOT.jar
```

### 配置说明

环境变量配置：

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| DB_HOST | 数据库主机 | localhost |
| DB_PORT | 数据库端口 | 5432 |
| DB_NAME | 数据库名称 | ttrss |
| DB_USER | 数据库用户 | postgres |
| DB_PASSWORD | 数据库密码 | postgres |
| JWT_SECRET | JWT 密钥 | your-secret-key-change-in-production |

## API 端点

应用启动后访问：`http://localhost:8080/api`

## 开发计划

- [ ] 用户认证模块 (JWT)
- [ ] 订阅源管理
- [ ] 文章阅读模块
- [ ] 分类标签系统
- [ ] API 文档 (OpenAPI/Swagger)

## License

MIT
