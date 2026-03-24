package com.ttrss.module.article.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.article.dto.ArticleListDTO;
import com.ttrss.module.article.entity.Entry;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.util.List;

/**
 * 文章 Mapper 接口
 */
@Mapper
public interface ArticleMapper extends BaseMapper<Entry> {

    /**
     * 搜索文章（全文搜索）
     *
     * @param userId 用户 ID
     * @param keyword 搜索关键词
     * @param feedId 订阅源 ID（可选）
     * @param categoryId 分类 ID（可选）
     * @param offset 偏移量
     * @param limit 每页大小
     * @return 文章列表 DTO
     */
    List<ArticleListDTO> searchArticles(
            @Param("userId") Integer userId,
            @Param("keyword") String keyword,
            @Param("feedId") Integer feedId,
            @Param("categoryId") Integer categoryId,
            @Param("offset") Integer offset,
            @Param("limit") Integer limit
    );

    /**
     * 搜索文章总数（用于分页）
     *
     * @param userId 用户 ID
     * @param keyword 搜索关键词
     * @param feedId 订阅源 ID（可选）
     * @param categoryId 分类 ID（可选）
     * @return 符合条件的文章总数
     */
    Long countSearchArticles(
            @Param("userId") Integer userId,
            @Param("keyword") String keyword,
            @Param("feedId") Integer feedId,
            @Param("categoryId") Integer categoryId
    );
}
