package com.ttrss.module.label.service;

import com.baomidou.mybatisplus.core.conditions.query.LambdaQueryWrapper;
import com.baomidou.mybatisplus.extension.service.impl.ServiceImpl;
import com.ttrss.module.label.dto.LabelDTO;
import com.ttrss.module.label.entity.Label;
import com.ttrss.module.label.entity.UserLabel;
import com.ttrss.module.label.mapper.LabelMapper;
import com.ttrss.module.label.mapper.UserLabelMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

/**
 * 标签服务类
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class LabelService extends ServiceImpl<LabelMapper, Label> {

    private final LabelMapper labelMapper;
    private final UserLabelMapper userLabelMapper;

    /**
     * 根据用户 ID 获取标签列表
     *
     * @param userId 用户 ID
     * @return 标签 DTO 列表
     */
    public List<LabelDTO> getLabelsByUserId(Integer userId) {
        log.debug("获取用户标签列表：userId={}", userId);
        List<Label> labels = labelMapper.selectByOwnerUid(userId);
        return labels.stream()
                .map(this::convertToDTO)
                .collect(Collectors.toList());
    }

    /**
     * 根据 ID 获取标签
     *
     * @param id 标签 ID
     * @param userId 用户 ID（用于权限验证）
     * @return 标签 DTO，不存在或无权限返回 null
     */
    public LabelDTO getLabelById(Integer id, Integer userId) {
        log.debug("获取标签详情：id={}, userId={}", id, userId);
        Label label = labelMapper.selectById(id);
        if (label == null) {
            log.warn("标签不存在：id={}", id);
            return null;
        }
        // 权限验证：只能查看自己的标签
        if (!label.getOwnerUid().equals(userId)) {
            log.warn("无权限访问标签：id={}, ownerId={}, userId={}", id, label.getOwnerUid(), userId);
            return null;
        }
        return convertToDTO(label);
    }

    /**
     * 创建标签
     *
     * @param labelDTO 标签 DTO
     * @param userId 用户 ID
     * @return 创建的标签 DTO
     */
    @Transactional(rollbackFor = Exception.class)
    public LabelDTO createLabel(LabelDTO labelDTO, Integer userId) {
        log.info("创建标签：userId={}, caption={}", userId, labelDTO.getCaption());

        // 检查标签是否已存在（同用户下标签名唯一）
        Label existingLabel = labelMapper.selectByCaption(userId, labelDTO.getCaption());
        if (existingLabel != null) {
            log.warn("标签已存在：caption={}, userId={}", labelDTO.getCaption(), userId);
            throw new IllegalArgumentException("该标签已存在");
        }

        // 创建标签实体
        Label label = new Label();
        label.setOwnerUid(userId);
        label.setCaption(labelDTO.getCaption());
        label.setFgColor(labelDTO.getFgColor() != null ? labelDTO.getFgColor() : "");
        label.setBgColor(labelDTO.getBgColor() != null ? labelDTO.getBgColor() : "");

        save(label);
        log.info("标签创建成功：id={}", label.getId());

        return convertToDTO(label);
    }

    /**
     * 更新标签
     *
     * @param id 标签 ID
     * @param labelDTO 标签 DTO
     * @param userId 用户 ID（用于权限验证）
     * @return 更新后的标签 DTO，不存在或无权限返回 null
     */
    @Transactional(rollbackFor = Exception.class)
    public LabelDTO updateLabel(Integer id, LabelDTO labelDTO, Integer userId) {
        log.info("更新标签：id={}, userId={}, caption={}", id, userId, labelDTO.getCaption());

        Label label = labelMapper.selectById(id);
        if (label == null) {
            log.warn("标签不存在：id={}", id);
            return null;
        }

        // 权限验证：只能更新自己的标签
        if (!label.getOwnerUid().equals(userId)) {
            log.warn("无权限更新标签：id={}, ownerId={}, userId={}", id, label.getOwnerUid(), userId);
            return null;
        }

        // 检查新标签名是否已被当前用户使用（排除当前标签）
        if (labelDTO.getCaption() != null && !labelDTO.getCaption().equals(label.getCaption())) {
            Label existingByCaption = labelMapper.selectByCaption(userId, labelDTO.getCaption());
            if (existingByCaption != null && !existingByCaption.getId().equals(id)) {
                log.warn("新标签名已被使用：caption={}", labelDTO.getCaption());
                throw new IllegalArgumentException("该标签名已被使用");
            }
        }

        // 更新字段
        if (labelDTO.getCaption() != null) {
            label.setCaption(labelDTO.getCaption());
        }
        if (labelDTO.getFgColor() != null) {
            label.setFgColor(labelDTO.getFgColor());
        }
        if (labelDTO.getBgColor() != null) {
            label.setBgColor(labelDTO.getBgColor());
        }

        updateById(label);
        log.info("标签更新成功：id={}", id);

        return convertToDTO(label);
    }

    /**
     * 删除标签
     *
     * @param id 标签 ID
     * @param userId 用户 ID（用于权限验证）
     * @return 是否删除成功
     */
    @Transactional(rollbackFor = Exception.class)
    public boolean deleteLabel(Integer id, Integer userId) {
        log.info("删除标签：id={}, userId={}", id, userId);

        Label label = labelMapper.selectById(id);
        if (label == null) {
            log.warn("标签不存在：id={}", id);
            return false;
        }

        // 权限验证：只能删除自己的标签
        if (!label.getOwnerUid().equals(userId)) {
            log.warn("无权限删除标签：id={}, ownerId={}, userId={}", id, label.getOwnerUid(), userId);
            return false;
        }

        // 删除标签关联（级联删除）
        userLabelMapper.delete(new LambdaQueryWrapper<UserLabel>()
                .eq(UserLabel::getLabelId, id));

        // 删除标签
        labelMapper.deleteByOwnerUid(id, userId);
        log.info("标签删除成功：id={}", id);
        return true;
    }

    /**
     * 获取文章的标签列表
     *
     * @param articleId 文章 ID
     * @param userId 用户 ID
     * @return 标签 DTO 列表
     */
    public List<LabelDTO> getLabelsByArticleId(Integer articleId, Integer userId) {
        log.debug("获取文章标签列表：articleId={}, userId={}", articleId, userId);
        List<Integer> labelIds = userLabelMapper.selectLabelIdsByArticleIdAndOwnerUid(userId, articleId);
        if (labelIds == null || labelIds.isEmpty()) {
            return new ArrayList<>();
        }
        List<Label> labels = listByIds(labelIds);
        return labels.stream()
                .map(this::convertToDTO)
                .collect(Collectors.toList());
    }

    /**
     * 为文章添加标签
     *
     * @param articleId 文章 ID
     * @param labelIds 标签 ID 列表
     * @param userId 用户 ID
     */
    @Transactional(rollbackFor = Exception.class)
    public void addLabelsToArticle(Integer articleId, List<Integer> labelIds, Integer userId) {
        log.info("为文章添加标签：articleId={}, labelIds={}, userId={}", articleId, labelIds, userId);

        if (labelIds == null || labelIds.isEmpty()) {
            return;
        }

        // 验证标签所有权
        for (Integer labelId : labelIds) {
            Label label = labelMapper.selectById(labelId);
            if (label == null || !label.getOwnerUid().equals(userId)) {
                log.warn("无权限使用标签：labelId={}, userId={}", labelId, userId);
                throw new IllegalArgumentException("无权限使用标签：" + labelId);
            }
        }

        // 先删除现有标签关联
        userLabelMapper.deleteByArticleId(articleId);

        // 批量添加新标签
        List<UserLabel> userLabels = labelIds.stream()
                .map(labelId -> {
                    UserLabel userLabel = new UserLabel();
                    userLabel.setLabelId(labelId);
                    userLabel.setArticleId(articleId);
                    return userLabel;
                })
                .collect(Collectors.toList());

        if (!userLabels.isEmpty()) {
            userLabelMapper.batchInsert(userLabels);
        }

        log.info("文章标签添加成功：articleId={}, count={}", articleId, labelIds.size());
    }

    /**
     * 从文章移除标签
     *
     * @param articleId 文章 ID
     * @param labelId 标签 ID
     * @param userId 用户 ID
     */
    @Transactional(rollbackFor = Exception.class)
    public void removeLabelFromArticle(Integer articleId, Integer labelId, Integer userId) {
        log.info("从文章移除标签：articleId={}, labelId={}, userId={}", articleId, labelId, userId);

        Label label = labelMapper.selectById(labelId);
        if (label == null || !label.getOwnerUid().equals(userId)) {
            log.warn("无权限操作标签：labelId={}, userId={}", labelId, userId);
            throw new IllegalArgumentException("无权限操作标签");
        }

        userLabelMapper.deleteByArticleIdAndLabelId(articleId, labelId);
        log.info("文章标签移除成功：articleId={}, labelId={}", articleId, labelId);
    }

    /**
     * 将 Label 实体转换为 DTO
     *
     * @param label Label 实体
     * @return LabelDTO
     */
    private LabelDTO convertToDTO(Label label) {
        LabelDTO dto = new LabelDTO();
        dto.setId(label.getId());
        dto.setCaption(label.getCaption());
        dto.setFgColor(label.getFgColor());
        dto.setBgColor(label.getBgColor());
        return dto;
    }

    /**
     * 检查标签名是否已存在
     *
     * @param caption 标签名
     * @param userId 用户 ID
     * @return 是否存在
     */
    public boolean existsByCaption(String caption, Integer userId) {
        Label label = labelMapper.selectByCaption(userId, caption);
        return label != null;
    }
}
