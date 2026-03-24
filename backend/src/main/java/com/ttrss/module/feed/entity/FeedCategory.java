package com.ttrss.module.feed.entity;

import com.baomidou.mybatisplus.annotation.IdType;
import com.baomidou.mybatisplus.annotation.TableId;
import com.baomidou.mybatisplus.annotation.TableName;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;

/**
 * tt-rss 订阅源分类实体类
 * 对应数据库表：ttrss_feed_categories
 */
@Data
@TableName("ttrss_feed_categories")
public class FeedCategory implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 分类 ID (主键，自增)
     */
    @TableId(type = IdType.AUTO)
    private Integer id;

    /**
     * 用户 ID (所有者)
     */
    private Integer ownerUid;

    /**
     * 分类标题
     */
    private String title;

    /**
     * 父分类 ID (支持层级分类)
     */
    private Integer parentCat;

    /**
     * 排序 ID
     */
    private Integer orderId;
}
