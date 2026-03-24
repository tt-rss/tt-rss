package com.ttrss.module.auth.dto;

import jakarta.validation.constraints.NotBlank;
import lombok.Data;

/**
 * 刷新 Token 请求 DTO
 */
@Data
public class RefreshTokenRequest {

    /**
     * Refresh Token
     */
    @NotBlank(message = "Refresh Token 不能为空")
    private String refreshToken;
}
