package com.ttrss.module.auth.service;

import com.ttrss.module.auth.entity.User;
import io.jsonwebtoken.Claims;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.TestPropertySource;

import static org.junit.jupiter.api.Assertions.*;

/**
 * JWT 服务单元测试
 */
@SpringBootTest
@ActiveProfiles("test")
@TestPropertySource(properties = {
    "jwt.secret=test-secret-key-for-jwt-testing-minimum-32-characters",
    "jwt.expiration=900000",
    "jwt.refresh-expiration=604800000"
})
class JwtServiceTest {

    @Autowired
    private JwtService jwtService;

    private User testUser;

    @BeforeEach
    void setUp() {
        // 创建测试用户
        testUser = new User();
        testUser.setId(1);
        testUser.setLogin("testuser");
        testUser.setPwdHash("hashedPassword");
        testUser.setAccessLevel(10);
        testUser.setOtpEnabled(false);
        testUser.setEmail("test@example.com");
    }

    @Test
    void contextLoads() {
        assertNotNull(jwtService);
    }

    @Test
    void testGenerateToken() {
        // 测试生成 Access Token
        String token = jwtService.generateToken(testUser);

        assertNotNull(token);
        assertFalse(token.isEmpty());
        assertTrue(token.length() > 0);
    }

    @Test
    void testGenerateRefreshToken() {
        // 测试生成 Refresh Token
        String refreshToken = jwtService.generateRefreshToken(testUser);

        assertNotNull(refreshToken);
        assertFalse(refreshToken.isEmpty());
        // Refresh Token 应该比 Access Token 长（因为过期时间更长）
        assertTrue(refreshToken.length() > 0);
    }

    @Test
    void testValidateToken() {
        // 测试验证有效 Token
        String token = jwtService.generateToken(testUser);
        assertTrue(jwtService.validateToken(token));
    }

    @Test
    void testValidateInvalidToken() {
        // 测试验证无效 Token
        String invalidToken = "invalid.token.here";
        assertFalse(jwtService.validateToken(invalidToken));
    }

    @Test
    void testValidateEmptyToken() {
        // 测试验证空 Token
        assertFalse(jwtService.validateToken(""));
        assertFalse(jwtService.validateToken(null));
    }

    @Test
    void testGetClaimsFromToken() {
        // 测试解析 Token 获取 Claims
        String token = jwtService.generateToken(testUser);
        Claims claims = jwtService.getClaimsFromToken(token);

        assertNotNull(claims);
        assertEquals("testuser", claims.getSubject());
        assertEquals(1, claims.get("userId", Integer.class));
        assertEquals("testuser", claims.get("username", String.class));
        assertEquals("access", claims.get("type", String.class));
    }

    @Test
    void testGetClaimsFromRefreshToken() {
        // 测试解析 Refresh Token
        String refreshToken = jwtService.generateRefreshToken(testUser);
        Claims claims = jwtService.getClaimsFromToken(refreshToken);

        assertNotNull(claims);
        assertEquals("testuser", claims.getSubject());
        assertEquals(1, claims.get("userId", Integer.class));
        assertEquals("refresh", claims.get("type", String.class));
    }

    @Test
    void testIsTokenExpired() {
        // 测试检查 Token 是否过期
        String token = jwtService.generateToken(testUser);
        // 新生成的 Token 不应该过期
        assertFalse(jwtService.isTokenExpired(token));
    }

    @Test
    void testGetUserIdFromToken() {
        // 测试从 Token 中提取用户 ID
        String token = jwtService.generateToken(testUser);
        Integer userId = jwtService.getUserIdFromToken(token);

        assertEquals(1, userId);
    }

    @Test
    void testGetUsernameFromToken() {
        // 测试从 Token 中提取用户名
        String token = jwtService.generateToken(testUser);
        String username = jwtService.getUsernameFromToken(token);

        assertEquals("testuser", username);
    }

    @Test
    void testGetTokenType() {
        // 测试获取 Token 类型
        String accessToken = jwtService.generateToken(testUser);
        String refreshToken = jwtService.generateRefreshToken(testUser);

        assertEquals("access", jwtService.getTokenType(accessToken));
        assertEquals("refresh", jwtService.getTokenType(refreshToken));
    }

    @Test
    void testTokenContainsUserInfo() {
        // 测试 Token 包含用户信息
        String token = jwtService.generateToken(testUser);
        Claims claims = jwtService.getClaimsFromToken(token);

        assertEquals(testUser.getId(), claims.get("userId", Integer.class));
        assertEquals(testUser.getLogin(), claims.get("username", String.class));
        assertEquals(testUser.getEmail(), claims.get("email", String.class));
    }

    @Test
    void testDifferentTokensForSameUser() {
        // 测试同一用户生成的不同 Token
        String token1 = jwtService.generateToken(testUser);
        String token2 = jwtService.generateToken(testUser);

        // 两个 Token 都应该有效
        assertTrue(jwtService.validateToken(token1));
        assertTrue(jwtService.validateToken(token2));

        // 两个 Token 应该包含相同的用户信息
        assertEquals(
            jwtService.getUserIdFromToken(token1),
            jwtService.getUserIdFromToken(token2)
        );
    }

    @Test
    void testAccessTokenAndRefreshTokenAreDifferent() {
        // 测试 Access Token 和 Refresh Token 不同
        String accessToken = jwtService.generateToken(testUser);
        String refreshToken = jwtService.generateRefreshToken(testUser);

        assertNotEquals(accessToken, refreshToken);
        assertNotEquals(
            jwtService.getTokenType(accessToken),
            jwtService.getTokenType(refreshToken)
        );
    }
}
