package com.ttrss.module.feed.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.feed.entity.FeedCategory;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.util.List;

/**
 * 订阅源分类 Mapper 接口
 */
@Mapper
public interface FeedCategoryMapper extends BaseMapper<FeedCategory> {

    /**
     * 根据用户 ID 查询分类列表
     *
     * @param ownerUid 用户 ID
     * @return 分类列表
     */
    List<FeedCategory> selectByOwnerUid(@Param("ownerUid") Integer ownerUid);

    /**
     * 根据父分类 ID 查询子分类
     *
     * @param parentCat 父分类 ID
     * @return 子分类列表
     */
    List<FeedCategory> selectByParentCat(@Param("parentCat") Integer parentCat);
}
