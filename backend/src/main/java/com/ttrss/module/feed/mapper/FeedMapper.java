package com.ttrss.module.feed.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.feed.entity.Feed;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.time.LocalDateTime;
import java.util.List;

/**
 * 订阅源 Mapper 接口
 */
@Mapper
public interface FeedMapper extends BaseMapper<Feed> {

    /**
     * 根据用户 ID 查询订阅源列表
     *
     * @param ownerUid 用户 ID
     * @return 订阅源列表
     */
    List<Feed> selectByOwnerUid(@Param("ownerUid") Integer ownerUid);

    /**
     * 根据分类 ID 查询订阅源列表
     *
     * @param catId 分类 ID
     * @return 订阅源列表
     */
    List<Feed> selectByCatId(@Param("catId") Integer catId);

    /**
     * 根据订阅源 URL 查询
     *
     * @param feedUrl 订阅源 URL
     * @return 订阅源实体
     */
    Feed selectByFeedUrl(@Param("feedUrl") String feedUrl);

    /**
     * 查询需要更新的订阅源（到期且未在更新中）
     *
     * @param now 当前时间
     * @return 需要更新的订阅源列表
     */
    List<Feed> selectDueFeeds(@Param("now") LocalDateTime now);

    /**
     * 更新订阅源的更新状态
     *
     * @param feedId 订阅源 ID
     * @param isUpdating 是否正在更新
     * @param updateStamp 更新时间戳
     * @return 影响行数
     */
    int updateUpdatingStatus(@Param("feedId") Integer feedId, 
                             @Param("isUpdating") Boolean isUpdating,
                             @Param("updateStamp") Long updateStamp);
}
