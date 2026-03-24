package com.ttrss.module.label.entity;

import com.baomidou.mybatisplus.annotation.IdType;
import com.baomidou.mybatisplus.annotation.TableId;
import com.baomidou.mybatisplus.annotation.TableName;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;

/**
 * tt-rss 标签实体类
 * 对应数据库表：ttrss_labels2
 */
@Data
@TableName("ttrss_labels2")
public class Label implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 标签 ID (主键，自增)
     */
    @TableId(type = IdType.AUTO)
    private Integer id;

    /**
     * 用户 ID (所有者)
     */
    private Integer ownerUid;

    /**
     * 前景颜色
     */
    private String fgColor;

    /**
     * 背景颜色
     */
    private String bgColor;

    /**
     * 标签名称
     */
    private String caption;
}
