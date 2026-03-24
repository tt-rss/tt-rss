package com.ttrss.module.article.entity;

import com.baomidou.mybatisplus.annotation.IdType;
import com.baomidou.mybatisplus.annotation.TableId;
import com.baomidou.mybatisplus.annotation.TableName;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;

/**
 * tt-rss 用户文章实体类
 * 对应数据库表：ttrss_user_entries
 */
@Data
@TableName("ttrss_user_entries")
public class UserEntry implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 用户文章 ID (主键，自增)
     */
    @TableId(type = IdType.AUTO)
    private Integer intId;

    /**
     * 关联 ttrss_entries.id (文章 ID)
     */
    private Integer refId;

    /**
     * 订阅源 ID
     */
    private Integer feedId;

    /**
     * 用户 ID (所有者)
     */
    private Integer ownerUid;

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
     * 关联的文章实体 (多对一)
     * 注意：此字段仅用于 MyBatis 关联查询，不映射到数据库字段
     */
    @com.baomidou.mybatisplus.annotation.TableField(exist = false)
    private Entry entry;
}
