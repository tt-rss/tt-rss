package com.ttrss.module.auth.service;

import io.jsonwebtoken.Claims;
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.security.Keys;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import com.ttrss.module.auth.entity.User;

import javax.crypto.SecretKey;
import java.nio.charset.StandardCharsets;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;
import java.util.function.Function;

/**
 * JWT 服务类
 * 负责 JWT Token 的生成、验证和解析
 */
@Slf4j
@Service
public class JwtService {

    /**
     * JWT 密钥
     */
    @Value("${jwt.secret}")
    private String secret;

    /**
     * Access Token 过期时间（毫秒）
     */
    @Value("${jwt.expiration}")
    private Long expiration;

    /**
     * Refresh Token 过期时间（毫秒）
     */
    @Value("${jwt.refresh-expiration}")
    private Long refreshExpiration;

    /**
     * 获取签名密钥
     *
     * @return SecretKey
     */
    private SecretKey getSigningKey() {
        byte[] keyBytes = secret.getBytes(StandardCharsets.UTF_8);
        return Keys.hmacShaKeyFor(keyBytes);
    }

    /**
     * 从 Token 中提取 Claims
     *
     * @param token JWT Token
     * @return Claims
     */
    private Claims extractAllClaims(String token) {
        return Jwts.parser()
                .verifyWith(getSigningKey())
                .build()
                .parseSignedClaims(token)
                .getPayload();
    }

    /**
     * 从 Token 中提取指定类型的 Claim
     *
     * @param token JWT Token
     * @param claimsResolver Claim 解析函数
     * @param <T> Claim 类型
     * @return Claim 值
     */
    private <T> T extractClaim(String token, Function<Claims, T> claimsResolver) {
        final Claims claims = extractAllClaims(token);
        return claimsResolver.apply(claims);
    }

    /**
     * 生成 Access Token
     *
     * @param user 用户实体
     * @return Access Token
     */
    public String generateToken(User user) {
        Map<String, Object> claims = new HashMap<>();
        claims.put("userId", user.getId());
        claims.put("username", user.getLogin());
        claims.put("email", user.getEmail());
        claims.put("type", "access");
        return createToken(claims, user.getLogin(), expiration);
    }

    /**
     * 生成 Refresh Token
     *
     * @param user 用户实体
     * @return Refresh Token
     */
    public String generateRefreshToken(User user) {
        Map<String, Object> claims = new HashMap<>();
        claims.put("userId", user.getId());
        claims.put("username", user.getLogin());
        claims.put("email", user.getEmail());
        claims.put("type", "refresh");
        return createToken(claims, user.getLogin(), refreshExpiration);
    }

    /**
     * 创建 JWT Token
     *
     * @param claims Claims
     * @param subject 主题（用户名）
     * @param expirationTime 过期时间（毫秒）
     * @return JWT Token
     */
    private String createToken(Map<String, Object> claims, String subject, Long expirationTime) {
        Date now = new Date();
        Date expirationDate = new Date(now.getTime() + expirationTime);

        return Jwts.builder()
                .claims(claims)
                .subject(subject)
                .issuedAt(now)
                .expiration(expirationDate)
                .signWith(getSigningKey())
                .compact();
    }

    /**
     * 验证 Token 有效性
     *
     * @param token JWT Token
     * @return Token 是否有效
     */
    public boolean validateToken(String token) {
        try {
            Claims claims = extractAllClaims(token);
            // 检查 Token 类型（可选）
            String type = claims.get("type", String.class);
            if (type == null) {
                log.warn("Token 缺少 type 字段");
                return false;
            }
            return !isTokenExpired(token);
        } catch (Exception e) {
            log.warn("Token 验证失败：{}", e.getMessage());
            return false;
        }
    }

    /**
     * 解析 Token 获取 Claims
     *
     * @param token JWT Token
     * @return Claims
     */
    public Claims getClaimsFromToken(String token) {
        return extractAllClaims(token);
    }

    /**
     * 检查 Token 是否过期
     *
     * @param token JWT Token
     * @return Token 是否过期
     */
    public boolean isTokenExpired(String token) {
        return extractExpiration(token).before(new Date());
    }

    /**
     * 从 Token 中提取过期时间
     *
     * @param token JWT Token
     * @return 过期时间
     */
    private Date extractExpiration(String token) {
        return extractClaim(token, Claims::getExpiration);
    }

    /**
     * 从 Token 中提取用户 ID
     *
     * @param token JWT Token
     * @return 用户 ID
     */
    public Integer getUserIdFromToken(String token) {
        return extractClaim(token, claims -> claims.get("userId", Integer.class));
    }

    /**
     * 从 Token 中提取用户名
     *
     * @param token JWT Token
     * @return 用户名
     */
    public String getUsernameFromToken(String token) {
        return extractClaim(token, Claims::getSubject);
    }

    /**
     * 从 Token 中提取 Token 类型
     *
     * @param token JWT Token
     * @return Token 类型 (access/refresh)
     */
    public String getTokenType(String token) {
        return extractClaim(token, claims -> claims.get("type", String.class));
    }
}
