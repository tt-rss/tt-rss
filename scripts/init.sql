-- tt-rss 数据库初始化脚本
-- 用于生产环境首次部署

-- 创建扩展（全文搜索）
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- ===========================================
-- 用户表（兼容现有 tt-rss）
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_users (
    id SERIAL PRIMARY KEY,
    login VARCHAR(255) UNIQUE NOT NULL,
    pwd_hash VARCHAR(255) NOT NULL,
    access_level INTEGER DEFAULT 0,
    otp_enabled BOOLEAN DEFAULT FALSE,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_users_login ON ttrss_users(login);

-- ===========================================
-- 订阅源分类表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_feed_categories (
    id SERIAL PRIMARY KEY,
    owner_uid INTEGER REFERENCES ttrss_users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    parent_cat INTEGER DEFAULT 0,
    order_id INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_categories_owner_uid ON ttrss_feed_categories(owner_uid);
CREATE INDEX IF NOT EXISTS idx_categories_parent_cat ON ttrss_feed_categories(parent_cat);

-- ===========================================
-- 订阅源表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_feeds (
    id SERIAL PRIMARY KEY,
    owner_uid INTEGER REFERENCES ttrss_users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    feed_url VARCHAR(512) NOT NULL,
    site_url VARCHAR(512),
    cat_id INTEGER REFERENCES ttrss_feed_categories(id) ON DELETE SET NULL,
    last_updated TIMESTAMP,
    last_error TEXT,
    update_interval INTEGER DEFAULT 60,
    last_update_check TIMESTAMP,
    is_updating BOOLEAN DEFAULT FALSE,
    update_stamp VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(owner_uid, feed_url)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_feeds_owner_uid ON ttrss_feeds(owner_uid);
CREATE INDEX IF NOT EXISTS idx_feeds_cat_id ON ttrss_feeds(cat_id);
CREATE INDEX IF NOT EXISTS idx_feeds_last_updated ON ttrss_feeds(last_updated);

-- ===========================================
-- 文章表（全局）
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_entries (
    id SERIAL PRIMARY KEY,
    guid VARCHAR(512) UNIQUE,
    title TEXT,
    content TEXT,
    link VARCHAR(512),
    updated TIMESTAMP,
    author VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_entries_guid ON ttrss_entries(guid);
CREATE INDEX IF NOT EXISTS idx_entries_updated ON ttrss_entries(updated);

-- 全文搜索索引（使用 pg_trgm）
CREATE INDEX IF NOT EXISTS idx_entries_title_trgm ON ttrss_entries USING gin (title gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_entries_content_trgm ON ttrss_entries USING gin (content gin_trgm_ops);

-- ===========================================
-- 用户文章关联表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_user_entries (
    int_id SERIAL PRIMARY KEY,
    ref_id INTEGER REFERENCES ttrss_entries(id) ON DELETE CASCADE,
    feed_id INTEGER REFERENCES ttrss_feeds(id) ON DELETE CASCADE,
    owner_uid INTEGER REFERENCES ttrss_users(id) ON DELETE CASCADE,
    unread BOOLEAN DEFAULT TRUE,
    marked BOOLEAN DEFAULT FALSE,
    published BOOLEAN DEFAULT FALSE,
    score INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ref_id, owner_uid)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_user_entries_ref_id ON ttrss_user_entries(ref_id);
CREATE INDEX IF NOT EXISTS idx_user_entries_feed_id ON ttrss_user_entries(feed_id);
CREATE INDEX IF NOT EXISTS idx_user_entries_owner_uid ON ttrss_user_entries(owner_uid);
CREATE INDEX IF NOT EXISTS idx_user_entries_unread ON ttrss_user_entries(unread);
CREATE INDEX IF NOT EXISTS idx_user_entries_marked ON ttrss_user_entries(marked);
CREATE INDEX IF NOT EXISTS idx_user_entries_owner_unread ON ttrss_user_entries(owner_uid, unread);

-- ===========================================
-- 标签表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_labels2 (
    id SERIAL PRIMARY KEY,
    owner_uid INTEGER REFERENCES ttrss_users(id) ON DELETE CASCADE,
    caption VARCHAR(255) NOT NULL,
    fg_color VARCHAR(7),
    bg_color VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(owner_uid, caption)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_labels_owner_uid ON ttrss_labels2(owner_uid);

-- ===========================================
-- 用户标签关联表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_user_labels2 (
    id SERIAL PRIMARY KEY,
    label_id INTEGER REFERENCES ttrss_labels2(id) ON DELETE CASCADE,
    article_id INTEGER REFERENCES ttrss_user_entries(int_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(label_id, article_id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_user_labels_label_id ON ttrss_user_labels2(label_id);
CREATE INDEX IF NOT EXISTS idx_user_labels_article_id ON ttrss_user_labels2(article_id);

-- ===========================================
-- 附件表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_enclosures (
    id SERIAL PRIMARY KEY,
    post_id INTEGER REFERENCES ttrss_user_entries(int_id) ON DELETE CASCADE,
    content_url VARCHAR(512) NOT NULL,
    content_type VARCHAR(255),
    title VARCHAR(255),
    duration INTEGER,
    width INTEGER,
    height INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(post_id, content_url)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_enclosures_post_id ON ttrss_enclosures(post_id);

-- ===========================================
-- 计数器缓存表
-- ===========================================
CREATE TABLE IF NOT EXISTS ttrss_counters_cache (
    id SERIAL PRIMARY KEY,
    feed_id INTEGER REFERENCES ttrss_feeds(id) ON DELETE CASCADE,
    owner_uid INTEGER REFERENCES ttrss_users(id) ON DELETE CASCADE,
    value INTEGER DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(feed_id, owner_uid)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_counters_feed_id ON ttrss_counters_cache(feed_id);
CREATE INDEX IF NOT EXISTS idx_counters_owner_uid ON ttrss_counters_cache(owner_uid);

-- ===========================================
-- 插入默认管理员用户
-- 密码：admin123 (BCrypt 哈希，cost=12)
-- 注意：生产环境请修改密码！
-- ===========================================
INSERT INTO ttrss_users (login, pwd_hash, access_level, email)
VALUES ('admin', '$2a$12$L7eFzE3qZ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5qJ5q', 10, 'admin@example.com')
ON CONFLICT (login) DO NOTHING;

-- ===========================================
-- 创建更新触发器函数（自动更新 updated_at）
-- ===========================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 为各表添加触发器
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON ttrss_users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON ttrss_feed_categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_feeds_updated_at BEFORE UPDATE ON ttrss_feeds
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_user_entries_updated_at BEFORE UPDATE ON ttrss_user_entries
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_labels_updated_at BEFORE UPDATE ON ttrss_labels2
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ===========================================
-- 权限设置（如果使用非 postgres 用户）
-- ===========================================
-- GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ttrss_user;
-- GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ttrss_user;
