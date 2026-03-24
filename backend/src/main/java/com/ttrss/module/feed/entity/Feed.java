package com.ttrss.module.feed.entity;

import com.baomidou.mybatisplus.annotation.IdType;
import com.baomidou.mybatisplus.annotation.TableId;
import com.baomidou.mybatisplus.annotation.TableName;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;
import java.time.LocalDateTime;

/**
 * tt-rss 订阅源实体类
 * 对应数据库表：ttrss_feeds
 */
@Data
@TableName("ttrss_feeds")
public class Feed implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 订阅源 ID (主键，自增)
     */
    @TableId(type = IdType.AUTO)
    private Integer id;

    /**
     * 用户 ID (所有者)
     */
    private Integer ownerUid;

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
     * 分类 ID (关联 ttrss_feed_categories)
     */
    private Integer catId;

    /**
     * 最后更新时间
     */
    private LocalDateTime lastUpdated;

    /**
     * 错误信息
     */
    private String lastError;

    /**
     * 更新间隔（分钟）
     */
    private Integer updateInterval;

    /**
     * 最后检查时间
     */
    private LocalDateTime lastUpdateCheck;

    /**
     * 是否正在更新中（并发控制）
     */
    private Boolean isUpdating;

    /**
     * 更新时间戳（用于乐观锁）
     */
    private Long updateStamp;
}
