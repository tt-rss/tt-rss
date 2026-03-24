package com.ttrss.module.auth.filter;

import com.ttrss.module.auth.service.JwtService;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.lang.NonNull;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.authentication.WebAuthenticationDetailsSource;
import org.springframework.stereotype.Component;
import org.springframework.util.StringUtils;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.util.ArrayList;

/**
 * JWT 认证过滤器
 * 从请求头提取 JWT Token，验证并设置 SecurityContext
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class JwtAuthenticationFilter extends OncePerRequestFilter {

    /**
     * Authorization 请求头名称
     */
    private static final String AUTHORIZATION_HEADER = "Authorization";

    /**
     * Bearer Token 前缀
     */
    private static final String BEARER_PREFIX = "Bearer ";

    private final JwtService jwtService;

    /**
     * 执行过滤器逻辑
     *
     * @param request HTTP 请求
     * @param response HTTP 响应
     * @param filterChain 过滤器链
     * @throws ServletException Servlet 异常
     * @throws IOException IO 异常
     */
    @Override
    protected void doFilterInternal(
            @NonNull HttpServletRequest request,
            @NonNull HttpServletResponse response,
            @NonNull FilterChain filterChain
    ) throws ServletException, IOException {
        try {
            // 从请求头提取 JWT Token
            String jwt = extractJwtFromRequest(request);

            // 验证 Token 并设置 SecurityContext
            if (StringUtils.hasText(jwt)) {
                if (jwtService.validateToken(jwt)) {
                    Integer userId = jwtService.getUserIdFromToken(jwt);
                    String username = jwtService.getUsernameFromToken(jwt);

                    // 创建认证对象
                    UsernamePasswordAuthenticationToken authentication =
                            new UsernamePasswordAuthenticationToken(
                                    username,
                                    null,
                                    new ArrayList<>() // 权限列表（可后续扩展）
                            );

                    // 设置认证详情
                    authentication.setDetails(
                            new WebAuthenticationDetailsSource().buildDetails(request)
                    );

                    // 设置到 SecurityContext
                    SecurityContextHolder.getContext().setAuthentication(authentication);

                    log.debug("用户认证成功：userId={}, username={}", userId, username);
                } else {
                    log.debug("Token 验证失败");
                }
            }
        } catch (Exception e) {
            log.error("JWT 认证过滤器异常：{}", e.getMessage());
            // 不抛出异常，让请求继续（由 AuthEntryPoint 处理未认证情况）
        }

        // 继续执行过滤器链
        filterChain.doFilter(request, response);
    }

    /**
     * 从请求头提取 JWT Token
     *
     * @param request HTTP 请求
     * @return JWT Token（不含前缀）
     */
    private String extractJwtFromRequest(HttpServletRequest request) {
        String bearerToken = request.getHeader(AUTHORIZATION_HEADER);

        if (StringUtils.hasText(bearerToken) && bearerToken.startsWith(BEARER_PREFIX)) {
            return bearerToken.substring(BEARER_PREFIX.length());
        }

        return null;
    }
}
