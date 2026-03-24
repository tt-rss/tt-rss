package com.ttrss.module.feed.dto;

import lombok.Data;

import java.time.LocalDateTime;

/**
 * 订阅源数据传输对象
 */
@Data
public class FeedDTO {

    /**
     * 订阅源 ID
     */
    private Integer id;

    /**
     * 订阅源标题
     */
    private String title;

    /**
     * RSS 地址
     */
    private String feedUrl;

    /**
     * 网站地址
     */
    private String siteUrl;

    /**
     * 分类 ID
     */
    private Integer catId;

    /**
     * 分类标题 (可选，用于前端展示)
     */
    private String categoryTitle;

    /**
     * 最后更新时间
     */
    private LocalDateTime lastUpdated;

    /**
     * 错误信息
     */
    private String lastError;

    /**
     * 未读文章数量 (可选，用于前端展示)
     */
    private Integer unreadCount;
}
