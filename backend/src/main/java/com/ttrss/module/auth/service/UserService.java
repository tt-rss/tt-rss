package com.ttrss.module.auth.service;

import com.baomidou.mybatisplus.extension.service.impl.ServiceImpl;
import com.ttrss.module.auth.entity.User;
import com.ttrss.module.auth.mapper.UserMapper;
import org.springframework.stereotype.Service;

/**
 * 用户服务类
 */
@Service
public class UserService extends ServiceImpl<UserMapper, User> {

    /**
     * 根据用户 ID 查询用户
     *
     * @param id 用户 ID
     * @return 用户实体
     */
    public User getUserById(Integer id) {
        return baseMapper.selectById(id);
    }

    /**
     * 根据用户名查询用户
     *
     * @param login 用户名
     * @return 用户实体
     */
    public User getUserByLogin(String login) {
        return baseMapper.selectByLogin(login);
    }

    /**
     * 用户认证
     *
     * @param login 用户名
     * @param password 密码（明文）
     * @return 认证成功返回用户实体，失败返回 null
     */
    public User authenticate(String login, String password) {
        User user = baseMapper.selectByLogin(login);
        if (user == null) {
            return null;
        }
        // 使用 BCrypt 验证密码
        org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder encoder = 
                new org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder();
        if (encoder.matches(password, user.getPwdHash())) {
            return user;
        }
        return null;
    }

    /**
     * 根据邮箱查询用户
     *
     * @param email 邮箱
     * @return 用户实体
     */
    public User getByEmail(String email) {
        return baseMapper.selectByEmail(email);
    }
}
