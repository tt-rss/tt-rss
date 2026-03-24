package com.ttrss.module.auth.controller;

import com.ttrss.module.auth.dto.JwtResponse;
import com.ttrss.module.auth.dto.LoginRequest;
import com.ttrss.module.auth.dto.RefreshTokenRequest;
import com.ttrss.module.auth.entity.User;
import com.ttrss.module.auth.service.JwtService;
import com.ttrss.module.auth.service.UserService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.Parameter;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.HashMap;
import java.util.Map;

/**
 * 认证控制器
 * 处理登录、登出、刷新 Token 和获取当前用户信息
 */
@Slf4j
@RestController
@RequestMapping("/api/auth")
@RequiredArgsConstructor
@Tag(name = "认证管理", description = "用户认证相关 API")
public class AuthController {

    private final JwtService jwtService;
    private final UserService userService;

    /**
     * 用户登录
     *
     * @param request 登录请求（用户名和密码）
     * @return JWT Token 和用户信息
     */
    @Operation(summary = "用户登录", description = "使用用户名和密码进行登录，返回 JWT Token")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "登录成功"),
        @ApiResponse(responseCode = "400", description = "用户名或密码错误")
    })
    @PostMapping("/login")
    public ResponseEntity<JwtResponse> login(
            @Parameter(description = "登录请求（用户名和密码）") @Valid @RequestBody LoginRequest request) {
        log.info("用户登录请求：login={}", request.getLogin());

        // 用户认证
        User user = userService.authenticate(request.getLogin(), request.getPassword());
        if (user == null) {
            log.warn("登录失败：用户名或密码错误，login={}", request.getLogin());
            return ResponseEntity.badRequest().build();
        }

        // 生成 Token
        String accessToken = jwtService.generateToken(user);
        String refreshToken = jwtService.generateRefreshToken(user);

        log.info("用户登录成功：userId={}, login={}", user.getId(), user.getLogin());

        // 构建响应
        JwtResponse response = JwtResponse.fromUser(accessToken, refreshToken, user);
        return ResponseEntity.ok(response);
    }

    /**
     * 用户登出
     *
     * @return 空响应
     */
    @Operation(summary = "用户登出", description = "用户登出（无状态认证，客户端删除 Token 即可）")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "登出成功")
    })
    @PostMapping("/logout")
    public ResponseEntity<Void> logout() {
        log.info("用户登出");
        // 无状态认证，登出时不需要额外操作
        // 客户端删除 Token 即可
        return ResponseEntity.ok().build();
    }

    /**
     * 刷新 Token
     *
     * @param request 刷新 Token 请求
     * @return 新的 JWT Token
     */
    @Operation(summary = "刷新 Token", description = "使用 Refresh Token 获取新的 Access Token")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "刷新成功"),
        @ApiResponse(responseCode = "400", description = "Refresh Token 无效或过期")
    })
    @PostMapping("/refresh")
    public ResponseEntity<JwtResponse> refreshToken(
            @Parameter(description = "刷新 Token 请求") @Valid @RequestBody RefreshTokenRequest request) {
        String refreshToken = request.getRefreshToken();
        log.info("刷新 Token 请求");

        // 验证 Refresh Token
        if (!jwtService.validateToken(refreshToken)) {
            log.warn("刷新 Token 失败：Token 无效");
            return ResponseEntity.badRequest().build();
        }

        // 检查 Token 类型
        String tokenType = jwtService.getTokenType(refreshToken);
        if (!"refresh".equals(tokenType)) {
            log.warn("刷新 Token 失败：Token 类型不正确");
            return ResponseEntity.badRequest().build();
        }

        // 从 Token 中获取用户信息
        Integer userId = jwtService.getUserIdFromToken(refreshToken);
        String username = jwtService.getUsernameFromToken(refreshToken);

        // 查询用户
        User user = userService.getUserByLogin(username);
        if (user == null || !user.getId().equals(userId)) {
            log.warn("刷新 Token 失败：用户不存在");
            return ResponseEntity.badRequest().build();
        }

        // 生成新的 Token
        String newAccessToken = jwtService.generateToken(user);
        String newRefreshToken = jwtService.generateRefreshToken(user);

        log.info("刷新 Token 成功：userId={}", user.getId());

        // 构建响应
        JwtResponse response = JwtResponse.fromUser(newAccessToken, newRefreshToken, user);
        return ResponseEntity.ok(response);
    }

    /**
     * 获取当前用户信息
     *
     * @param userDetails 认证用户信息（由 JWT 过滤器设置）
     * @return 用户信息
     */
    @Operation(summary = "获取当前用户信息", description = "获取当前登录用户的详细信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "用户不存在")
    })
    @GetMapping("/me")
    public ResponseEntity<Map<String, Object>> getCurrentUser(
            @Parameter(description = "认证用户信息（由 JWT 过滤器设置）") @AuthenticationPrincipal UserDetails userDetails) {
        if (userDetails == null) {
            log.warn("获取当前用户失败：未认证");
            return ResponseEntity.status(401).build();
        }

        String username = userDetails.getUsername();
        User user = userService.getUserByLogin(username);
        if (user == null) {
            log.warn("获取当前用户失败：用户不存在，username={}", username);
            return ResponseEntity.status(404).build();
        }

        log.info("获取当前用户成功：userId={}", user.getId());

        // 构建响应（不包含敏感信息）
        Map<String, Object> response = new HashMap<>();
        response.put("id", user.getId());
        response.put("username", user.getLogin());
        response.put("email", user.getEmail());

        return ResponseEntity.ok(response);
    }
}
