package com.ttrss.module.article.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.article.entity.Entry;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.util.List;

/**
 * 文章 Mapper 接口
 */
@Mapper
public interface EntryMapper extends BaseMapper<Entry> {

    /**
     * 根据 GUID 查询文章
     *
     * @param guid 文章 GUID
     * @return 文章实体
     */
    Entry selectByGuid(@Param("guid") String guid);

    /**
     * 根据文章 ID 列表查询
     *
     * @param ids 文章 ID 列表
     * @return 文章列表
     */
    List<Entry> selectByIds(@Param("ids") List<Integer> ids);

    /**
     * 根据文章链接查询
     *
     * @param link 文章链接
     * @return 文章实体
     */
    Entry selectByLink(@Param("link") String link);
}
