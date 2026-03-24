package com.ttrss.module.label.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.label.entity.UserLabel;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.util.List;

/**
 * 用户标签关联 Mapper 接口
 */
@Mapper
public interface UserLabelMapper extends BaseMapper<UserLabel> {

    /**
     * 根据文章 ID 查询标签 ID 列表
     *
     * @param articleId 文章 ID
     * @return 标签 ID 列表
     */
    List<Integer> selectLabelIdsByArticleId(@Param("articleId") Integer articleId);

    /**
     * 根据用户 ID 和文章 ID 查询标签 ID 列表
     *
     * @param ownerUid 用户 ID
     * @param articleId 文章 ID
     * @return 标签 ID 列表
     */
    List<Integer> selectLabelIdsByArticleIdAndOwnerUid(
            @Param("ownerUid") Integer ownerUid,
            @Param("articleId") Integer articleId);

    /**
     * 批量添加文章标签
     *
     * @param userLabels 用户标签关联列表
     * @return 影响行数
     */
    int batchInsert(@Param("list") List<UserLabel> userLabels);

    /**
     * 删除文章的标签
     *
     * @param articleId 文章 ID
     * @return 影响行数
     */
    int deleteByArticleId(@Param("articleId") Integer articleId);

    /**
     * 删除文章的特定标签
     *
     * @param articleId 文章 ID
     * @param labelId 标签 ID
     * @return 影响行数
     */
    int deleteByArticleIdAndLabelId(
            @Param("articleId") Integer articleId,
            @Param("labelId") Integer labelId);
}
