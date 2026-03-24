package com.ttrss.module.article.dto;

import lombok.Data;

import java.time.LocalDateTime;

/**
 * 文章列表项数据传输对象 (列表摘要)
 */
@Data
public class ArticleListDTO {

    /**
     * 用户文章 ID (ttrss_user_entries.int_id)
     */
    private Integer intId;

    /**
     * 文章 ID (ttrss_entries.id)
     */
    private Integer id;

    /**
     * 文章标题
     */
    private String title;

    /**
     * 原文链接
     */
    private String link;

    /**
     * 更新时间
     */
    private LocalDateTime updated;

    /**
     * 作者
     */
    private String author;

    /**
     * 订阅源 ID
     */
    private Integer feedId;

    /**
     * 订阅源标题 (可选，用于前端展示)
     */
    private String feedTitle;

    /**
     * 未读状态
     */
    private Boolean unread;

    /**
     * 星标状态
     */
    private Boolean marked;

    /**
     * 发布状态
     */
    private Boolean published;

    /**
     * 分数
     */
    private Integer score;

    /**
     * 搜索相关度（仅搜索接口返回）
     */
    private Double relevance;
}
