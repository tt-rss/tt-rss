package com.ttrss.module.auth.dto;

import jakarta.validation.constraints.NotBlank;
import lombok.Data;

/**
 * 登录请求 DTO
 */
@Data
public class LoginRequest {

    /**
     * 用户名
     */
    @NotBlank(message = "用户名不能为空")
    private String login;

    /**
     * 密码
     */
    @NotBlank(message = "密码不能为空")
    private String password;
}
