--
-- Synthetic test data seed for integration tests.
-- Based on tt-rss-dev.sql dump structure.
-- Run after schema migration, before tests.
--
-- NOTE: This file must be executed within a single transaction (wrapped by PHP).
-- Deleting in FK dependency order (children first) avoids constraint violations.
-- The entire operation is wrapped in a transaction to minimize lock duration
-- and avoid deadlocks when the PHP dev server concurrently accesses the DB.
-- The readiness check in IntegrationTestCase::waitForApiReady() ensures the
-- PHP dev server is ready before seeding begins.
--

-- Clear all tables in FK dependency order.
-- Must be executed within a single transaction (wrapped by PHP seedDatabase()).
-- Delete order follows FK references: child tables first, then parent tables.
--
-- FK order (child -> parent):
--   ttrss_tags -> ttrss_user_entries -> ttrss_entries
--   ttrss_user_labels2 -> ttrss_entries, ttrss_labels2
--   ttrss_enclosures -> ttrss_entries
--   ttrss_user_entries -> ttrss_entries, ttrss_feeds
--   ttrss_feeds -> ttrss_feed_categories

DELETE FROM ttrss_tags;
DELETE FROM ttrss_user_labels2;
DELETE FROM ttrss_enclosures;
DELETE FROM ttrss_user_entries;
DELETE FROM ttrss_feeds;
DELETE FROM ttrss_feed_categories;
DELETE FROM ttrss_labels2;
DELETE FROM ttrss_entries;

-- 2. Feed categories
INSERT INTO ttrss_feed_categories (id, owner_uid, title) VALUES
(1, 1, 'Technology'),
(2, 1, 'Science & Nature');

SELECT setval(pg_get_serial_sequence('ttrss_feed_categories', 'id'), (SELECT MAX(id) FROM ttrss_feed_categories));

-- 2b. Labels
INSERT INTO ttrss_labels2 (id, owner_uid, caption) VALUES
(1, 1, 'test-label-1'),
(2, 1, 'test-label-2');

SELECT setval(pg_get_serial_sequence('ttrss_labels2', 'id'), (SELECT MAX(id) FROM ttrss_labels2));

-- 3. Feeds
-- Columns: id, owner_uid, title, cat_id, feed_url, update_interval, purge_interval, site_url
INSERT INTO ttrss_feeds (id, owner_uid, title, cat_id, feed_url, update_interval, purge_interval, site_url) VALUES
(1, 1, 'Hacker News', 1, 'https://hnrss.org/frontpage', 30, 0, 'https://news.ycombinator.com'),
(2, 1, 'The Register', 1, 'https://www.theregister.com/headlines.atom', 60, 0, 'https://www.theregister.com'),
(3, 1, 'Ars Technica', 1, 'https://feeds.arstechnica.com/arstechnica/index', 60, 0, 'https://arstechnica.com'),
(4, 1, 'MIT Tech Review', 2, 'https://www.technologyreview.com/feed/', 120, 0, 'https://www.technologyreview.com');

SELECT setval(pg_get_serial_sequence('ttrss_feeds', 'id'), (SELECT MAX(id) FROM ttrss_feeds));

-- 4. Articles (ttrss_entries)
INSERT INTO ttrss_entries (id, title, guid, link, updated, content, content_hash, date_entered, date_updated) VALUES
-- Hacker News articles
(1, 'Show HN: I built a distributed task queue in Rust',
 'tag:hnrss.org,2026:hn-001', 'https://news.ycombinator.com/item?id=40001',
 '2026-05-25 10:00:00',
 '<p>Today I am excited to share my open-source distributed task queue written in Rust. It supports at-least-once delivery, automatic retries, and priority queues.</p>',
 'SHA1:a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
 '2026-05-25 10:00:00', '2026-05-25 10:00:00'),

(2, 'PostgreSQL 18 released with major performance improvements',
 'tag:hnrss.org,2026:hn-002', 'https://news.ycombinator.com/item?id=40002',
 '2026-05-25 09:30:00',
 '<p>PostgreSQL 18 brings significant performance improvements including parallel query optimizations, better memory management, and enhanced JSONB support.</p>',
 'SHA1:b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3',
 '2026-05-25 09:30:00', '2026-05-25 09:30:00'),

(3, 'The rise of WebAssembly in server-side applications',
 'tag:hnrss.org,2026:hn-003', 'https://news.ycombinator.com/item?id=40003',
 '2026-05-24 18:00:00',
 '<p>WebAssembly is no longer just for the browser. Companies are increasingly using WASM for server-side plugins, microservices, and edge computing.</p>',
 'SHA1:c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
 '2026-05-24 18:00:00', '2026-05-24 18:00:00'),

-- The Register articles
(4, 'AI startup raises $500M to build autonomous software engineers',
 'tag:theregister.com,2026:reg-001', 'https://www.theregister.com/2026/05/25/ai_startup_autonomous/',
 '2026-05-25 08:00:00',
 '<p>A new AI startup has secured half a billion dollars in funding to develop autonomous software engineering agents that can write, test, and deploy code without human intervention.</p>',
 'SHA1:d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5',
 '2026-05-25 08:00:00', '2026-05-25 08:00:00'),

(5, 'Major cloud provider suffers 6-hour outage across all regions',
 'tag:theregister.com,2026:reg-002', 'https://www.theregister.com/2026/05/24/cloud_outage/',
 '2026-05-24 14:00:00',
 '<p>A leading cloud provider experienced a complete outage lasting six hours, affecting thousands of customer services worldwide. The cause appears to be a cascading failure in the networking layer.</p>',
 'SHA1:e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6',
 '2026-05-24 14:00:00', '2026-05-24 14:00:00'),

(6, 'European Union proposes strict AI transparency rules for enterprises',
 'tag:theregister.com,2026:reg-003', 'https://www.theregister.com/2026/05/24/eu_ai_transparency/',
 '2026-05-24 11:00:00',
 '<p>The European Commission has unveiled new regulations requiring enterprises to disclose when AI systems are used in decision-making processes affecting employees and customers.</p>',
 'SHA1:f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1',
 '2026-05-24 11:00:00', '2026-05-24 11:00:00'),

-- Ars Technica articles
(7, 'Apple announces new M5 chip with 40% faster neural engine',
 'tag:arstechnica.com,2026:ars-001', 'https://arstechnica.com/gadgets/2026/05/apple-m5-chip/',
 '2026-05-25 07:00:00',
 '<p>Apple has unveiled its next-generation M5 chip, featuring a dramatically improved neural engine designed for on-device AI workloads. Early benchmarks show significant gains over the M4.</p>',
 'SHA1:a1a2a3a4a5a6a7a8a9a0b1b2b3b4b5b6b7b8b9b0',
 '2026-05-25 07:00:00', '2026-05-25 07:00:00'),

(8, 'How Kubernetes evolved from a research project to cloud infrastructure backbone',
 'tag:arstechnica.com,2026:ars-002', 'https://arstechnica.com/information-technology/2026/05/kubernetes-history/',
 '2026-05-24 16:00:00',
 '<p>A deep dive into the 15-year history of Kubernetes, from its origins at Google to becoming the de facto standard for container orchestration worldwide.</p>',
 'SHA1:b1b2b3b4b5b6b7b8b9b0c1c2c3c4c5c6c7c8c9c0',
 '2026-05-24 16:00:00', '2026-05-24 16:00:00'),

-- MIT Tech Review articles
(9, 'New battery technology could double electric vehicle range',
 'tag:technologyreview.com,2026:mit-001', 'https://www.technologyreview.com/2026/05/25/battery-tech/',
 '2026-05-25 06:00:00',
 '<p>Researchers at MIT have developed a solid-state battery prototype that could double the range of electric vehicles while reducing charging time to under 15 minutes.</p>',
 'SHA1:c1c2c3c4c5c6c7c8c9c0d1d2d3d4d5d6d7d8d9d0',
 '2026-05-25 06:00:00', '2026-05-25 06:00:00'),

(10, 'CRISPR gene therapy shows promise in treating rare genetic diseases',
 'tag:technologyreview.com,2026:mit-002', 'https://www.technologyreview.com/2026/05/24/crispr-therapy/',
 '2026-05-24 12:00:00',
 '<p>Clinical trials of a new CRISPR-based gene therapy have shown remarkable results in treating sickle cell disease and beta-thalassemia, with 95% of patients showing significant improvement.</p>',
 'SHA1:d1d2d3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3',
 '2026-05-24 12:00:00', '2026-05-24 12:00:00'),

(11, 'The quantum computing race: IBM and Google push qubit records',
 'tag:technologyreview.com,2026:mit-003', 'https://www.technologyreview.com/2026/05/23/quantum-race/',
 '2026-05-23 15:00:00',
 '<p>Both IBM and Google have announced breakthroughs in quantum computing, with each claiming to have achieved quantum advantage in specific optimization problems.</p>',
 'SHA1:e1e2e3e4e5e6e7e8e9e0f1f2f3f4f5f6f7f8f9f0',
 '2026-05-23 15:00:00', '2026-05-23 15:00:00');

SELECT setval(pg_get_serial_sequence('ttrss_entries', 'id'), (SELECT MAX(id) FROM ttrss_entries));

-- 5. User entries (ttrss_user_entries) — mix of read/unread
-- Columns: ref_id, uuid, feed_id, owner_uid, unread, marked, tag_cache, label_cache
INSERT INTO ttrss_user_entries (ref_id, uuid, feed_id, owner_uid, unread, marked, tag_cache, label_cache) VALUES
-- HN: 2 unread, 1 read
(1, 'uuid-hn-001', 1, 1, true, false, 'rust|open-source', ''),
(2, 'uuid-hn-002', 1, 1, true, false, 'database|postgresql', ''),
(3, 'uuid-hn-003', 1, 1, false, true, 'webassembly|cloud', ''),

-- The Register: 3 unread
(4, 'uuid-reg-001', 2, 1, true, false, 'ai|startup', ''),
(5, 'uuid-reg-002', 2, 1, true, false, 'cloud|outage', ''),
(6, 'uuid-reg-003', 2, 1, true, false, 'eu|regulation|ai', ''),

-- Ars Technica: 1 unread, 1 read
(7, 'uuid-ars-001', 3, 1, true, false, 'apple|hardware', ''),
(8, 'uuid-ars-002', 3, 1, false, true, 'kubernetes|history', ''),

-- MIT Tech Review: 2 unread, 1 read
(9, 'uuid-mit-001', 4, 1, true, false, 'battery|ev', ''),
(10, 'uuid-mit-002', 4, 1, true, false, 'crispr|healthcare', ''),
(11, 'uuid-mit-003', 4, 1, false, true, 'quantum|computing', '');

-- 5b. Enclosures for article id=1 (YouTube + image enclosure)
INSERT INTO ttrss_enclosures (content_url, content_type, title, duration, width, height, post_id) VALUES
('https://example.com/video.mp4', 'video/mp4', 'Demo Video', '05:30', 1920, 1080, 1),
('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', 'image/jpeg', 'Thumbnail', 0, 480, 360, 1);

SELECT setval(pg_get_serial_sequence('ttrss_enclosures', 'id'), (SELECT MAX(id) FROM ttrss_enclosures));
