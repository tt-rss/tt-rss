package com.ttrss.module.article.entity;

import com.baomidou.mybatisplus.annotation.IdType;
import com.baomidou.mybatisplus.annotation.TableId;
import com.baomidou.mybatisplus.annotation.TableName;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;
import java.time.LocalDateTime;

/**
 * tt-rss 文章实体类
 * 对应数据库表：ttrss_entries
 */
@Data
@TableName("ttrss_entries")
public class Entry implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 文章 ID (主键，自增)
     */
    @TableId(type = IdType.AUTO)
    private Integer id;

    /**
     * 文章唯一标识 (GUID)
     */
    private String guid;

    /**
     * 文章标题
     */
    private String title;

    /**
     * 文章内容
     */
    private String content;

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
}
