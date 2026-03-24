package com.ttrss.module.article.dto;

import lombok.Data;

import java.util.List;

/**
 * 文章分页响应 DTO
 */
@Data
public class ArticlePageResponse {

    /**
     * 文章列表
     */
    private List<ArticleListDTO> articles;

    /**
     * 当前页码（从 1 开始）
     */
    private Integer page;

    /**
     * 每页大小
     */
    private Integer size;

    /**
     * 总记录数
     */
    private Long total;

    /**
     * 总页数
     */
    private Integer totalPages;

    /**
     * 是否有上一页
     */
    private Boolean hasPrevious;

    /**
     * 是否有下一页
     */
    private Boolean hasNext;

    /**
     * 构建分页响应
     *
     * @param articles 文章列表
     * @param page 当前页码
     * @param size 每页大小
     * @param total 总记录数
     * @return 分页响应对象
     */
    public static ArticlePageResponse of(List<ArticleListDTO> articles, Integer page, Integer size, Long total) {
        ArticlePageResponse response = new ArticlePageResponse();
        response.setArticles(articles);
        response.setPage(page);
        response.setSize(size);
        response.setTotal(total);

        int totalPages = (int) Math.ceil((double) total / size);
        response.setTotalPages(totalPages);
        response.setHasPrevious(page > 1);
        response.setHasNext(page < totalPages);

        return response;
    }
}
