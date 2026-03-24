package com.ttrss.module.label.entity;

import lombok.Data;

import java.io.Serial;
import java.io.Serializable;

/**
 * tt-rss 用户标签关联实体类
 * 对应数据库表：ttrss_user_labels2
 */
@Data
public class UserLabel implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 标签 ID (关联 ttrss_labels2.id)
     */
    private Integer labelId;

    /**
     * 文章 ID (关联 ttrss_entries.id)
     */
    private Integer articleId;
}
