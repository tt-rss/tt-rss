package com.ttrss.module.article.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.article.entity.UserEntry;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.util.List;

/**
 * 用户文章 Mapper 接口
 */
@Mapper
public interface UserEntryMapper extends BaseMapper<UserEntry> {

    /**
     * 根据用户 ID 查询用户文章列表
     *
     * @param ownerUid 用户 ID
     * @return 用户文章列表
     */
    List<UserEntry> selectByOwnerUid(@Param("ownerUid") Integer ownerUid);

    /**
     * 根据订阅源 ID 查询用户文章列表
     *
     * @param feedId 订阅源 ID
     * @return 用户文章列表
     */
    List<UserEntry> selectByFeedId(@Param("feedId") Integer feedId);

    /**
     * 根据用户 ID 和未读状态查询
     *
     * @param ownerUid 用户 ID
     * @param unread 未读状态
     * @return 用户文章列表
     */
    List<UserEntry> selectByOwnerUidAndUnread(@Param("ownerUid") Integer ownerUid, @Param("unread") Boolean unread);

    /**
     * 根据用户 ID 和星标状态查询
     *
     * @param ownerUid 用户 ID
     * @param marked 星标状态
     * @return 用户文章列表
     */
    List<UserEntry> selectByOwnerUidAndMarked(@Param("ownerUid") Integer ownerUid, @Param("marked") Boolean marked);
}
