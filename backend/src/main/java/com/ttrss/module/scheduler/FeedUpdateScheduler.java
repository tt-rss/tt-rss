package com.ttrss.module.scheduler;

import com.ttrss.module.feed.service.FeedUpdateService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

/**
 * 订阅源更新调度器
 * 使用 Spring Scheduler 定时更新订阅源
 * 并发控制通过数据库锁（is_updating 字段）实现
 *
 * @author ttrss
 * @since 2026-03-24
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class FeedUpdateScheduler {

    private final FeedUpdateService feedUpdateService;

    @Value("${feed.scheduler.enabled:true}")
    private boolean schedulerEnabled;

    @Value("${feed.scheduler.fixed-delay:300000}")
    private long fixedDelay;

    @Value("${feed.scheduler.initial-delay:60000}")
    private long initialDelay;

    /**
     * 定时更新到期的订阅源
     * 每 5 分钟执行一次（可配置）
     * 使用数据库锁避免重复执行
     */
    @Scheduled(fixedDelayString = "${feed.scheduler.fixed-delay:300000}", 
               initialDelayString = "${feed.scheduler.initial-delay:60000}")
    public void scheduleFeedUpdate() {
        if (!schedulerEnabled) {
            log.debug("Feed update scheduler is disabled");
            return;
        }

        log.debug("Starting scheduled feed update task");
        
        try {
            int updatedCount = feedUpdateService.updateDueFeeds();
            log.info("Scheduled feed update completed, updated {} feeds", updatedCount);
        } catch (Exception e) {
            log.error("Scheduled feed update failed: {}", e.getMessage(), e);
        }
    }
}
