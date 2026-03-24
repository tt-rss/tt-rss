package com.ttrss.module.feed.service;

import com.baomidou.mybatisplus.core.conditions.query.LambdaQueryWrapper;
import com.baomidou.mybatisplus.extension.service.impl.ServiceImpl;
import com.ttrss.module.feed.dto.FeedCategoryDTO;
import com.ttrss.module.feed.entity.Feed;
import com.ttrss.module.feed.entity.FeedCategory;
import com.ttrss.module.feed.mapper.FeedCategoryMapper;
import com.ttrss.module.feed.mapper.FeedMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.stream.Collectors;

/**
 * 订阅源分类服务类
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class FeedCategoryService extends ServiceImpl<FeedCategoryMapper, FeedCategory> {

    private final FeedCategoryMapper feedCategoryMapper;
    private final FeedMapper feedMapper;

    /**
     * 根据用户 ID 获取分类列表
     *
     * @param userId 用户 ID
     * @return 分类 DTO 列表
     */
    public List<FeedCategoryDTO> getCategoriesByUserId(Integer userId) {
        log.debug("获取用户分类列表：userId={}", userId);
        List<FeedCategory> categories = feedCategoryMapper.selectByOwnerUid(userId);
        return categories.stream()
                .map(this::convertToDTO)
                .collect(Collectors.toList());
    }

    /**
     * 根据 ID 获取分类
     *
     * @param id 分类 ID
     * @param userId 用户 ID（用于权限验证）
     * @return 分类 DTO，不存在或无权限返回 null
     */
    public FeedCategoryDTO getCategoryById(Integer id, Integer userId) {
        log.debug("获取分类详情：id={}, userId={}", id, userId);
        FeedCategory category = feedCategoryMapper.selectById(id);
        if (category == null) {
            log.warn("分类不存在：id={}", id);
            return null;
        }
        // 权限验证：只能查看自己的分类
        if (!category.getOwnerUid().equals(userId)) {
            log.warn("无权限访问分类：id={}, ownerId={}, userId={}", id, category.getOwnerUid(), userId);
            return null;
        }
        return convertToDTO(category);
    }

    /**
     * 创建分类
     *
     * @param categoryDTO 分类 DTO
     * @param userId 用户 ID
     * @return 创建的分类 DTO
     */
    @Transactional(rollbackFor = Exception.class)
    public FeedCategoryDTO createCategory(FeedCategoryDTO categoryDTO, Integer userId) {
        log.info("创建分类：userId={}, title={}", userId, categoryDTO.getTitle());

        // 验证分类标题
        if (categoryDTO.getTitle() == null || categoryDTO.getTitle().isBlank()) {
            log.warn("分类标题不能为空：userId={}", userId);
            throw new IllegalArgumentException("分类标题不能为空");
        }

        // 创建分类实体
        FeedCategory category = new FeedCategory();
        category.setOwnerUid(userId);
        category.setTitle(categoryDTO.getTitle());
        category.setParentCat(categoryDTO.getParentCat());
        category.setOrderId(categoryDTO.getOrderId() != null ? categoryDTO.getOrderId() : 0);

        save(category);
        log.info("分类创建成功：id={}", category.getId());

        return convertToDTO(category);
    }

    /**
     * 更新分类
     *
     * @param id 分类 ID
     * @param categoryDTO 分类 DTO
     * @param userId 用户 ID（用于权限验证）
     * @return 更新后的分类 DTO，不存在或无权限返回 null
     */
    @Transactional(rollbackFor = Exception.class)
    public FeedCategoryDTO updateCategory(Integer id, FeedCategoryDTO categoryDTO, Integer userId) {
        log.info("更新分类：id={}, userId={}, title={}", id, userId, categoryDTO.getTitle());

        FeedCategory category = feedCategoryMapper.selectById(id);
        if (category == null) {
            log.warn("分类不存在：id={}", id);
            return null;
        }

        // 权限验证：只能更新自己的分类
        if (!category.getOwnerUid().equals(userId)) {
            log.warn("无权限更新分类：id={}, ownerId={}, userId={}", id, category.getOwnerUid(), userId);
            return null;
        }

        // 验证分类标题
        if (categoryDTO.getTitle() == null || categoryDTO.getTitle().isBlank()) {
            log.warn("分类标题不能为空：id={}", id);
            throw new IllegalArgumentException("分类标题不能为空");
        }

        // 更新字段
        category.setTitle(categoryDTO.getTitle());
        category.setParentCat(categoryDTO.getParentCat());
        if (categoryDTO.getOrderId() != null) {
            category.setOrderId(categoryDTO.getOrderId());
        }

        updateById(category);
        log.info("分类更新成功：id={}", id);

        return convertToDTO(category);
    }

    /**
     * 删除分类
     *
     * @param id 分类 ID
     * @param userId 用户 ID（用于权限验证）
     * @return 是否删除成功
     */
    @Transactional(rollbackFor = Exception.class)
    public boolean deleteCategory(Integer id, Integer userId) {
        log.info("删除分类：id={}, userId={}", id, userId);

        FeedCategory category = feedCategoryMapper.selectById(id);
        if (category == null) {
            log.warn("分类不存在：id={}", id);
            return false;
        }

        // 权限验证：只能删除自己的分类
        if (!category.getOwnerUid().equals(userId)) {
            log.warn("无权限删除分类：id={}, ownerId={}, userId={}", id, category.getOwnerUid(), userId);
            return false;
        }

        // 检查分类下是否有订阅源
        LambdaQueryWrapper<Feed> feedWrapper = new LambdaQueryWrapper<>();
        feedWrapper.eq(Feed::getCatId, id);
        long feedCount = feedMapper.selectCount(feedWrapper);
        if (feedCount > 0) {
            log.warn("分类下存在订阅源，无法删除：id={}, feedCount={}", id, feedCount);
            throw new IllegalStateException("分类下存在订阅源，无法删除");
        }

        removeById(id);
        log.info("分类删除成功：id={}", id);
        return true;
    }

    /**
     * 将 FeedCategory 实体转换为 DTO
     *
     * @param category FeedCategory 实体
     * @return FeedCategoryDTO
     */
    private FeedCategoryDTO convertToDTO(FeedCategory category) {
        FeedCategoryDTO dto = new FeedCategoryDTO();
        dto.setId(category.getId());
        dto.setTitle(category.getTitle());
        dto.setParentCat(category.getParentCat());
        dto.setOrderId(category.getOrderId());

        // 统计分类下的订阅源数量
        if (category.getId() != null) {
            LambdaQueryWrapper<Feed> feedWrapper = new LambdaQueryWrapper<>();
            feedWrapper.eq(Feed::getCatId, category.getId());
            int count = Math.toIntExact(feedMapper.selectCount(feedWrapper));
            dto.setFeedCount(count);
        }

        return dto;
    }
}
