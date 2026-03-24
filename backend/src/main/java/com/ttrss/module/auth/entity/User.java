package com.ttrss.module.auth.entity;

import com.baomidou.mybatisplus.annotation.IdType;
import com.baomidou.mybatisplus.annotation.TableId;
import com.baomidou.mybatisplus.annotation.TableName;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;

/**
 * tt-rss 用户实体类
 * 对应数据库表：ttrss_users
 */
@Data
@TableName("ttrss_users")
public class User implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 用户 ID (主键，自增)
     */
    @TableId(type = IdType.AUTO)
    private Integer id;

    /**
     * 用户名 (登录名)
     */
    private String login;

    /**
     * 密码哈希
     */
    private String pwdHash;

    /**
     * 访问级别
     */
    private Integer accessLevel;

    /**
     * 是否启用 OTP (2FA)
     */
    private Boolean otpEnabled;

    /**
     * 邮箱地址
     */
    private String email;
}
