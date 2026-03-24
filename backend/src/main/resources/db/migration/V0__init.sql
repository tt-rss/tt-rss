-- V0__init.sql
-- tt-rss users 表结构初始化脚本 (用于测试环境)

CREATE TABLE IF NOT EXISTS ttrss_users (
    id SERIAL PRIMARY KEY,
    login VARCHAR(255) NOT NULL UNIQUE,
    pwd_hash VARCHAR(255) NOT NULL,
    access_level INTEGER NOT NULL DEFAULT 0,
    otp_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    email VARCHAR(255) DEFAULT ''
);

-- 插入默认管理员用户 (密码为 'password' 的哈希)
INSERT INTO ttrss_users (login, pwd_hash, access_level, otp_enabled, email)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 10, FALSE, 'admin@localhost')
ON CONFLICT (login) DO NOTHING;

-- 插入测试用户
INSERT INTO ttrss_users (login, pwd_hash, access_level, otp_enabled, email)
VALUES ('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, FALSE, 'test@example.com')
ON CONFLICT (login) DO NOTHING;
