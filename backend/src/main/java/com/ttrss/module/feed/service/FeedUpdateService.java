package com.ttrss.module.feed.service;

import com.ttrss.infrastructure.rss.RomeAdapter;
import com.ttrss.module.article.entity.Entry;
import com.ttrss.module.article.mapper.EntryMapper;
import com.ttrss.module.feed.entity.Feed;
import com.ttrss.module.feed.mapper.FeedMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.List;

/**
 * 订阅源更新服务
 * 负责解析 RSS 并保存文章
 *
 * @author ttrss
 * @since 2026-03-24
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class FeedUpdateService {

    private final FeedMapper feedMapper;
    private final EntryMapper entryMapper;
    private final RomeAdapter romeAdapter;

    @Value("${feed.scheduler.default-update-interval:60}")
    private Integer defaultUpdateInterval;

    @Value("${feed.scheduler.max-articles-per-feed:100}")
    private Integer maxArticlesPerFeed;

    /**
     * 更新单个订阅源
     *
     * @param feedId 订阅源 ID
     * @return 是否更新成功
     */
    @Transactional(rollbackFor = Exception.class)
    public boolean updateFeed(Integer feedId) {
        log.info("Updating feed: {}", feedId);
        
        Feed feed = feedMapper.selectById(feedId);
        if (feed == null) {
            log.warn("Feed not found: {}", feedId);
            return false;
        }

        try {
            // 设置更新状态
            long updateStamp = System.currentTimeMillis();
            feedMapper.updateUpdatingStatus(feedId, true, updateStamp);

            // 获取并解析 Feed
            RomeAdapter.FeedData feedData = romeAdapter.fetchAndParse(feed.getFeedUrl());
            
            // 更新 Feed 信息
            if (feedData.getTitle() != null) {
                feed.setTitle(feedData.getTitle());
            }
            feed.setLastUpdated(LocalDateTime.now());
            feed.setLastUpdateCheck(LocalDateTime.now());
            feed.setLastError(null);
            feedMapper.updateById(feed);

            // 保存文章
            int savedCount = saveEntries(feedData.getEntries(), feedId);
            
            // 清除更新状态
            feedMapper.updateUpdatingStatus(feedId, false, System.currentTimeMillis());
            
            log.info("Feed {} updated successfully, saved {} articles", feedId, savedCount);
            return true;

        } catch (Exception e) {
            log.error("Failed to update feed {}: {}", feedId, e.getMessage(), e);
            
            // 更新错误信息
            feed.setLastError(e.getMessage());
            feed.setLastUpdateCheck(LocalDateTime.now());
            feedMapper.updateById(feed);
            
            // 清除更新状态
            feedMapper.updateUpdatingStatus(feedId, false, System.currentTimeMillis());
            
            return false;
        }
    }

    /**
     * 更新所有到期的订阅源
     *
     * @return 更新的订阅源数量
     */
    @Transactional(rollbackFor = Exception.class)
    public int updateDueFeeds() {
        LocalDateTime now = LocalDateTime.now();
        List<Feed> dueFeeds = feedMapper.selectDueFeeds(now);
        
        if (dueFeeds.isEmpty()) {
            log.debug("No due feeds to update");
            return 0;
        }

        log.info("Found {} due feeds to update", dueFeeds.size());
        
        int successCount = 0;
        for (Feed feed : dueFeeds) {
            try {
                if (updateFeed(feed.getId())) {
                    successCount++;
                }
            } catch (Exception e) {
                log.error("Error updating feed {}: {}", feed.getId(), e.getMessage(), e);
            }
        }

        log.info("Updated {} out of {} feeds", successCount, dueFeeds.size());
        return successCount;
    }

    /**
     * 保存文章列表
     *
     * @param entries 文章数据列表
     * @param feedId 订阅源 ID
     * @return 保存的文章数量
     */
    private int saveEntries(List<RomeAdapter.EntryData> entries, Integer feedId) {
        if (entries == null || entries.isEmpty()) {
            return 0;
        }

        int savedCount = 0;
        int limit = Math.min(entries.size(), maxArticlesPerFeed);

        for (int i = 0; i < limit; i++) {
            RomeAdapter.EntryData entryData = entries.get(i);
            
            // 跳过没有标题或链接的文章
            if (entryData.getTitle() == null || entryData.getLink() == null) {
                continue;
            }

            try {
                // 检查是否已存在
                Entry existingEntry = entryMapper.selectByGuid(entryData.getGuid());
                if (existingEntry == null && entryData.getLink() != null) {
                    // 尝试通过链接查找（有些 Feed 没有 GUID）
                    existingEntry = entryMapper.selectByLink(entryData.getLink());
                }

                if (existingEntry == null) {
                    Entry entry = new Entry();
                    entry.setTitle(truncate(entryData.getTitle(), 512));
                    entry.setContent(entryData.getContent());
                    entry.setLink(truncate(entryData.getLink(), 2048));
                    entry.setGuid(truncate(entryData.getGuid(), 512));
                    entry.setAuthor(truncate(entryData.getAuthor(), 256));
                    
                    LocalDateTime pubDate = entryData.getPublishedDate();
                    if (pubDate == null) {
                        pubDate = entryData.getUpdatedDate();
                    }
                    if (pubDate != null) {
                        entry.setUpdated(pubDate);
                    } else {
                        entry.setUpdated(LocalDateTime.now());
                    }

                    entryMapper.insert(entry);
                    savedCount++;
                }
            } catch (Exception e) {
                log.error("Failed to save entry from feed {}: {}", feedId, e.getMessage(), e);
            }
        }

        return savedCount;
    }

    /**
     * 截断字符串到指定长度
     *
     * @param str 原字符串
     * @param maxLength 最大长度
     * @return 截断后的字符串
     */
    private String truncate(String str, int maxLength) {
        if (str == null) {
            return null;
        }
        if (str.length() <= maxLength) {
            return str;
        }
        return str.substring(0, maxLength);
    }
}
