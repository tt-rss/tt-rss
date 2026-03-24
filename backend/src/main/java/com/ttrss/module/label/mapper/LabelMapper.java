package com.ttrss.module.label.mapper;

import com.baomidou.mybatisplus.core.mapper.BaseMapper;
import com.ttrss.module.label.entity.Label;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

import java.util.List;

/**
 * 标签 Mapper 接口
 */
@Mapper
public interface LabelMapper extends BaseMapper<Label> {

    /**
     * 根据用户 ID 查询标签列表
     *
     * @param ownerUid 用户 ID
     * @return 标签列表
     */
    List<Label> selectByOwnerUid(@Param("ownerUid") Integer ownerUid);

    /**
     * 根据用户 ID 和标签名称查询
     *
     * @param ownerUid 用户 ID
     * @param caption 标签名称
     * @return 标签实体
     */
    Label selectByCaption(@Param("ownerUid") Integer ownerUid, @Param("caption") String caption);

    /**
     * 删除用户的标签
     *
     * @param id 标签 ID
     * @param ownerUid 用户 ID
     * @return 影响行数
     */
    int deleteByOwnerUid(@Param("id") Integer id, @Param("ownerUid") Integer ownerUid);
}
