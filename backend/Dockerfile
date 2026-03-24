# Backend Spring Boot Dockerfile
# 用于本地开发环境，支持热更新

FROM eclipse-temurin:17-jdk-alpine AS build

WORKDIR /app

# 复制 Gradle 配置文件
COPY build.gradle gradlew settings.gradle ./
COPY gradle gradle

# 下载依赖（利用 Docker 缓存层）
RUN chmod +x gradlew && ./gradlew dependencies --no-daemon || true

# 复制源代码并构建
COPY src src
RUN ./gradlew bootJar --no-daemon

# ===========================================
# 运行时镜像
# ===========================================
FROM eclipse-temurin:17-jre-alpine

WORKDIR /app

# 安装必要的工具（用于开发环境）
RUN apk add --no-cache bash curl

# 从构建阶段复制 JAR 文件
COPY --from=build /app/build/libs/*.jar app.jar

# 暴露 Spring Boot 端口
EXPOSE 8080

# JVM 参数
ENV JAVA_OPTS="-Xmx512m -Xms256m"

# 健康检查
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8080/actuator/health || exit 1

# 启动应用
ENTRYPOINT ["sh", "-c", "java $JAVA_OPTS -jar app.jar"]
