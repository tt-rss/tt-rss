package com.ttrss.module.feed.dto;

import lombok.Data;

/**
 * 订阅源分类数据传输对象
 */
@Data
public class FeedCategoryDTO {

    /**
     * 分类 ID
     */
    private Integer id;

    /**
     * 分类标题
     */
    private String title;

    /**
     * 父分类 ID
     */
    private Integer parentCat;

    /**
     * 排序 ID
     */
    private Integer orderId;

    /**
     * 订阅源数量 (可选，用于前端展示)
     */
    private Integer feedCount;
}
