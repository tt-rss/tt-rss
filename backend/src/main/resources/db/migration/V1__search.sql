-- V1__search.sql
-- PostgreSQL 全文搜索支持 - 使用 tsvector/tsquery

-- 1. 为 ttrss_entries 表添加 tsvector 列
ALTER TABLE ttrss_entries ADD COLUMN IF NOT EXISTS search_vector tsvector;

-- 2. 创建函数用于更新 search_vector
CREATE OR REPLACE FUNCTION update_entries_search_vector()
RETURNS TRIGGER AS $$
BEGIN
    NEW.search_vector := 
        setweight(to_tsvector('simple', COALESCE(NEW.title, '')), 'A') ||
        setweight(to_tsvector('simple', COALESCE(NEW.content, '')), 'B');
    RETURN NEW;
END
$$ LANGUAGE plpgsql;

-- 3. 创建触发器，在 INSERT/UPDATE 时自动更新 search_vector
DROP TRIGGER IF EXISTS update_entries_search_vector_trigger ON ttrss_entries;
CREATE TRIGGER update_entries_search_vector_trigger
    BEFORE INSERT OR UPDATE ON ttrss_entries
    FOR EACH ROW
    EXECUTE FUNCTION update_entries_search_vector();

-- 4. 为现有数据初始化 search_vector
UPDATE ttrss_entries 
SET search_vector = 
    setweight(to_tsvector('simple', COALESCE(title, '')), 'A') ||
    setweight(to_tsvector('simple', COALESCE(content, '')), 'B')
WHERE search_vector IS NULL;

-- 5. 创建 GIN 索引以加速全文搜索
CREATE INDEX IF NOT EXISTS idx_entries_search_vector 
ON ttrss_entries USING GIN (search_vector);

-- 6. 创建普通索引优化关键词搜索（用于部分匹配）
CREATE INDEX IF NOT EXISTS idx_entries_title 
ON ttrss_entries (title);

-- 注释说明：
-- - 使用 'simple' 配置避免中文分词问题，按字符匹配
-- - 标题权重为 'A'，内容权重为 'B'，标题匹配的相关度更高
-- - GIN 索引支持高效的 tsquery 查询
-- - 触发器确保数据变更时 search_vector 自动同步
