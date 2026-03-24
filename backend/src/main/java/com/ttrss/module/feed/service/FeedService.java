package com.ttrss.module.feed.service;

import com.baomidou.mybatisplus.core.conditions.query.LambdaQueryWrapper;
import com.baomidou.mybatisplus.extension.service.impl.ServiceImpl;
import com.ttrss.module.feed.dto.FeedDTO;
import com.ttrss.module.feed.entity.Feed;
import com.ttrss.module.feed.mapper.FeedMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.net.InetAddress;
import java.net.URL;
import java.time.LocalDateTime;
import java.util.List;
import java.util.stream.Collectors;

/**
 * 订阅源服务类
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class FeedService extends ServiceImpl<FeedMapper, Feed> {

    private final FeedMapper feedMapper;

    /**
     * 根据用户 ID 获取订阅源列表
     *
     * @param userId 用户 ID
     * @return 订阅源 DTO 列表
     */
    public List<FeedDTO> getFeedsByUserId(Integer userId) {
        log.debug("获取用户订阅源列表：userId={}", userId);
        List<Feed> feeds = feedMapper.selectByOwnerUid(userId);
        return feeds.stream()
                .map(this::convertToDTO)
                .collect(Collectors.toList());
    }

    /**
     * 根据 ID 获取订阅源
     *
     * @param id 订阅源 ID
     * @param userId 用户 ID（用于权限验证）
     * @return 订阅源 DTO，不存在或无权限返回 null
     */
    public FeedDTO getFeedById(Integer id, Integer userId) {
        log.debug("获取订阅源详情：id={}, userId={}", id, userId);
        Feed feed = feedMapper.selectById(id);
        if (feed == null) {
            log.warn("订阅源不存在：id={}", id);
            return null;
        }
        // 权限验证：只能查看自己的订阅源
        if (!feed.getOwnerUid().equals(userId)) {
            log.warn("无权限访问订阅源：id={}, ownerId={}, userId={}", id, feed.getOwnerUid(), userId);
            return null;
        }
        return convertToDTO(feed);
    }

    /**
     * 创建订阅源
     *
     * @param feedDTO 订阅源 DTO
     * @param userId 用户 ID
     * @return 创建的订阅源 DTO
     */
    @Transactional(rollbackFor = Exception.class)
    public FeedDTO createFeed(FeedDTO feedDTO, Integer userId) {
        log.info("创建订阅源：userId={}, title={}, feedUrl={}", userId, feedDTO.getTitle(), feedDTO.getFeedUrl());

        // 验证 URL 有效性
        if (!validateFeedUrl(feedDTO.getFeedUrl())) {
            log.warn("无效的 RSS URL: {}", feedDTO.getFeedUrl());
            throw new IllegalArgumentException("无效的 RSS URL 格式");
        }

        // 检查 URL 是否已存在
        Feed existingFeed = feedMapper.selectByFeedUrl(feedDTO.getFeedUrl());
        if (existingFeed != null && existingFeed.getOwnerUid().equals(userId)) {
            log.warn("订阅源已存在：feedUrl={}, userId={}", feedDTO.getFeedUrl(), userId);
            throw new IllegalArgumentException("该订阅源已存在");
        }

        // 创建订阅源实体
        Feed feed = new Feed();
        feed.setOwnerUid(userId);
        feed.setTitle(feedDTO.getTitle());
        feed.setFeedUrl(feedDTO.getFeedUrl());
        feed.setSiteUrl(feedDTO.getSiteUrl());
        feed.setCatId(feedDTO.getCatId());
        feed.setLastUpdated(LocalDateTime.now());

        save(feed);
        log.info("订阅源创建成功：id={}", feed.getId());

        return convertToDTO(feed);
    }

    /**
     * 更新订阅源
     *
     * @param id 订阅源 ID
     * @param feedDTO 订阅源 DTO
     * @param userId 用户 ID（用于权限验证）
     * @return 更新后的订阅源 DTO，不存在或无权限返回 null
     */
    @Transactional(rollbackFor = Exception.class)
    public FeedDTO updateFeed(Integer id, FeedDTO feedDTO, Integer userId) {
        log.info("更新订阅源：id={}, userId={}, title={}", id, userId, feedDTO.getTitle());

        Feed feed = feedMapper.selectById(id);
        if (feed == null) {
            log.warn("订阅源不存在：id={}", id);
            return null;
        }

        // 权限验证：只能更新自己的订阅源
        if (!feed.getOwnerUid().equals(userId)) {
            log.warn("无权限更新订阅源：id={}, ownerId={}, userId={}", id, feed.getOwnerUid(), userId);
            return null;
        }

        // 验证 URL 有效性（如果 URL 发生变化）
        if (feedDTO.getFeedUrl() != null && !feedDTO.getFeedUrl().equals(feed.getFeedUrl())) {
            if (!validateFeedUrl(feedDTO.getFeedUrl())) {
                log.warn("无效的 RSS URL: {}", feedDTO.getFeedUrl());
                throw new IllegalArgumentException("无效的 RSS URL 格式");
            }

            // 检查新 URL 是否已被当前用户使用
            Feed existingByNewUrl = feedMapper.selectByFeedUrl(feedDTO.getFeedUrl());
            if (existingByNewUrl != null && !existingByNewUrl.getId().equals(id)) {
                log.warn("新 URL 已被使用：feedUrl={}", feedDTO.getFeedUrl());
                throw new IllegalArgumentException("该订阅源 URL 已被使用");
            }
        }

        // 更新字段
        feed.setTitle(feedDTO.getTitle());
        feed.setFeedUrl(feedDTO.getFeedUrl());
        feed.setSiteUrl(feedDTO.getSiteUrl());
        feed.setCatId(feedDTO.getCatId());
        feed.setLastUpdated(LocalDateTime.now());

        updateById(feed);
        log.info("订阅源更新成功：id={}", id);

        return convertToDTO(feed);
    }

    /**
     * 删除订阅源
     *
     * @param id 订阅源 ID
     * @param userId 用户 ID（用于权限验证）
     * @return 是否删除成功
     */
    @Transactional(rollbackFor = Exception.class)
    public boolean deleteFeed(Integer id, Integer userId) {
        log.info("删除订阅源：id={}, userId={}", id, userId);

        Feed feed = feedMapper.selectById(id);
        if (feed == null) {
            log.warn("订阅源不存在：id={}", id);
            return false;
        }

        // 权限验证：只能删除自己的订阅源
        if (!feed.getOwnerUid().equals(userId)) {
            log.warn("无权限删除订阅源：id={}, ownerId={}, userId={}", id, feed.getOwnerUid(), userId);
            return false;
        }

        removeById(id);
        log.info("订阅源删除成功：id={}", id);
        return true;
    }

    /**
     * 验证 RSS URL 有效性
     *
     * @param url RSS URL 字符串
     * @return 是否有效
     */
    public boolean validateFeedUrl(String url) {
        if (url == null || url.isBlank()) {
            return false;
        }

        try {
            URL parsedUrl = new URL(url);

            // 检查协议
            String protocol = parsedUrl.getProtocol();
            if (!"http".equalsIgnoreCase(protocol) && !"https".equalsIgnoreCase(protocol)) {
                return false;
            }

            // 检查主机是否有效（防止内网访问）
            String host = parsedUrl.getHost();
            if (host == null || host.isEmpty()) {
                return false;
            }

            // 检查是否为内网地址（简单检查）
            InetAddress address = InetAddress.getByName(host);
            if (address.isAnyLocalAddress() || address.isLoopbackAddress()) {
                return false;
            }

            return true;
        } catch (Exception e) {
            log.warn("URL 验证失败：url={}, error={}", url, e.getMessage());
            return false;
        }
    }

    /**
     * 将 Feed 实体转换为 DTO
     *
     * @param feed Feed 实体
     * @return FeedDTO
     */
    private FeedDTO convertToDTO(Feed feed) {
        FeedDTO dto = new FeedDTO();
        dto.setId(feed.getId());
        dto.setTitle(feed.getTitle());
        dto.setFeedUrl(feed.getFeedUrl());
        dto.setSiteUrl(feed.getSiteUrl());
        dto.setCatId(feed.getCatId());
        dto.setLastUpdated(feed.getLastUpdated());
        dto.setLastError(feed.getLastError());
        return dto;
    }

    /**
     * 检查订阅源 URL 是否已存在
     *
     * @param feedUrl RSS URL
     * @param userId 用户 ID
     * @return 是否存在
     */
    public boolean existsByFeedUrl(String feedUrl, Integer userId) {
        LambdaQueryWrapper<Feed> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(Feed::getFeedUrl, feedUrl)
                .eq(Feed::getOwnerUid, userId);
        return count(wrapper) > 0;
    }
}
