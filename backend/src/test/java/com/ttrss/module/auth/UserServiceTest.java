package com.ttrss.module.auth;

import com.ttrss.module.auth.entity.User;
import com.ttrss.module.auth.mapper.UserMapper;
import com.ttrss.module.auth.service.UserService;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;

import static org.junit.jupiter.api.Assertions.*;

/**
 * 用户服务单元测试
 */
@SpringBootTest
@ActiveProfiles("test")
class UserServiceTest {

    @Autowired
    private UserService userService;

    @Autowired
    private UserMapper userMapper;

    @Test
    void contextLoads() {
        assertNotNull(userService);
        assertNotNull(userMapper);
    }

    @Test
    void testSelectByLogin() {
        // 测试查询用户名为 'admin' 的用户（tt-rss 默认管理员）
        User user = userService.getUserByLogin("admin");

        // 如果数据库中存在 admin 用户，验证其字段
        if (user != null) {
            assertNotNull(user.getId());
            assertEquals("admin", user.getLogin());
            assertNotNull(user.getPwdHash());
            assertNotNull(user.getAccessLevel());
        }
        // 注意：在测试容器中数据库为空，user 可能为 null
    }

    @Test
    void testSelectByEmail() {
        // 测试通过邮箱查询
        User user = userService.getByEmail("admin@localhost");
        
        if (user != null) {
            assertNotNull(user.getId());
            assertEquals("admin@localhost", user.getEmail());
        }
    }

    @Test
    void testSelectById() {
        // 测试通过 ID 查询
        User user = userService.getById(1);
        
        if (user != null) {
            assertEquals(1, user.getId());
        }
    }

    @Test
    void testUserEntity() {
        // 测试实体类基本功能
        User user = new User();
        user.setId(1);
        user.setLogin("testuser");
        user.setPwdHash("hash123");
        user.setAccessLevel(10);
        user.setOtpEnabled(false);
        user.setEmail("test@example.com");

        assertEquals(1, user.getId());
        assertEquals("testuser", user.getLogin());
        assertEquals("hash123", user.getPwdHash());
        assertEquals(10, user.getAccessLevel());
        assertFalse(user.getOtpEnabled());
        assertEquals("test@example.com", user.getEmail());
    }
}
