package com.ttrss.config;

import io.swagger.v3.oas.models.OpenAPI;
import io.swagger.v3.oas.models.info.Contact;
import io.swagger.v3.oas.models.info.Info;
import io.swagger.v3.oas.models.info.License;
import io.swagger.v3.oas.models.security.SecurityRequirement;
import io.swagger.v3.oas.models.security.SecurityScheme;
import io.swagger.v3.oas.models.servers.Server;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.util.List;

/**
 * OpenAPI 配置类
 * 配置 SpringDoc OpenAPI 3.0 文档生成
 */
@Configuration
public class OpenApiConfig {

    @Value("${openapi.server.url:http://localhost:8080}")
    private String serverUrl;

    /**
     * 自定义 OpenAPI 配置
     *
     * @return OpenAPI 实例
     */
    @Bean
    public OpenAPI customOpenAPI() {
        return new OpenAPI()
                .info(new Info()
                        .title("Tiny Tiny RSS API")
                        .version("1.0.0")
                        .description("Tiny Tiny RSS 后端 API 文档")
                        .contact(new Contact()
                                .name("Tiny Tiny RSS")
                                .url("https://tt-rss.org"))
                        .license(new License()
                                .name("Apache 2.0")
                                .url("https://www.apache.org/licenses/LICENSE-2.0")))
                .servers(List.of(
                        new Server()
                                .url(serverUrl)
                                .description("本地开发服务器")))
                .addSecurityItem(new SecurityRequirement()
                        .addList("bearerAuth"))
                .schemaRequirement("bearerAuth", new SecurityScheme()
                        .type(SecurityScheme.Type.HTTP)
                        .scheme("bearer")
                        .bearerFormat("JWT")
                        .description("JWT Token 认证，格式：Bearer {token}"));
    }
}
