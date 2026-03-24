package com.ttrss.module.article.service;

import com.baomidou.mybatisplus.core.conditions.query.LambdaQueryWrapper;
import com.baomidou.mybatisplus.extension.plugins.pagination.Page;
import com.baomidou.mybatisplus.extension.service.impl.ServiceImpl;
import com.ttrss.module.article.dto.ArticleDTO;
import com.ttrss.module.article.dto.ArticleListDTO;
import com.ttrss.module.article.dto.ArticlePageResponse;
import com.ttrss.module.article.entity.Entry;
import com.ttrss.module.article.entity.UserEntry;
import com.ttrss.module.article.mapper.ArticleMapper;
import com.ttrss.module.article.mapper.EntryMapper;
import com.ttrss.module.article.mapper.UserEntryMapper;
import com.ttrss.module.feed.entity.Feed;
import com.ttrss.module.feed.mapper.FeedMapper;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.stream.Collectors;

/**
 * 文章服务类
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class ArticleService extends ServiceImpl<UserEntryMapper, UserEntry> {

    private final ArticleMapper articleMapper;
    private final UserEntryMapper userEntryMapper;
    private final EntryMapper entryMapper;
    private final FeedMapper feedMapper;

    @Value("${article.pagination.default-size:20}")
    private Integer defaultPageSize;

    @Value("${article.pagination.max-size:100}")
    private Integer maxPageSize;

    /**
     * 根据 ID 获取文章详情
     *
     * @param intId 用户文章 ID
     * @param userId 用户 ID
     * @return 文章详情 DTO，不存在或无权限返回 null
     */
    @Transactional(readOnly = true)
    public ArticleDTO getArticleById(Integer intId, Integer userId) {
        log.debug("获取文章详情：intId={}, userId={}", intId, userId);

        if (intId == null || userId == null) {
            return null;
        }

        // 查询用户文章（包含权限验证）
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(UserEntry::getIntId, intId)
                .eq(UserEntry::getOwnerUid, userId);
        UserEntry userEntry = userEntryMapper.selectOne(wrapper);

        if (userEntry == null) {
            log.debug("文章不存在或无权限：intId={}, userId={}", intId, userId);
            return null;
        }

        return convertToDetailDTO(userEntry);
    }

    /**
     * 标记文章已读/未读
     *
     * @param intId 用户文章 ID
     * @param userId 用户 ID
     * @param read 已读状态（true=已读，false=未读）
     * @return 操作是否成功
     */
    @Transactional
    public boolean markAsRead(Integer intId, Integer userId, Boolean read) {
        log.debug("标记文章已读/未读：intId={}, userId={}, read={}", intId, userId, read);

        if (intId == null || userId == null || read == null) {
            return false;
        }

        // 查询并验证权限
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(UserEntry::getIntId, intId)
                .eq(UserEntry::getOwnerUid, userId);
        UserEntry userEntry = userEntryMapper.selectOne(wrapper);

        if (userEntry == null) {
            log.debug("文章不存在或无权限：intId={}, userId={}", intId, userId);
            return false;
        }

        // 更新未读状态
        userEntry.setUnread(!read);
        return userEntryMapper.updateById(userEntry) > 0;
    }

    /**
     * 标记文章星标/取消星标
     *
     * @param intId 用户文章 ID
     * @param userId 用户 ID
     * @param starred 星标状态（true=星标，false=取消星标）
     * @return 操作是否成功
     */
    @Transactional
    public boolean markAsStarred(Integer intId, Integer userId, Boolean starred) {
        log.debug("标记文章星标：intId={}, userId={}, starred={}", intId, userId, starred);

        if (intId == null || userId == null || starred == null) {
            return false;
        }

        // 查询并验证权限
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(UserEntry::getIntId, intId)
                .eq(UserEntry::getOwnerUid, userId);
        UserEntry userEntry = userEntryMapper.selectOne(wrapper);

        if (userEntry == null) {
            log.debug("文章不存在或无权限：intId={}, userId={}", intId, userId);
            return false;
        }

        // 更新星标状态
        userEntry.setMarked(starred);
        return userEntryMapper.updateById(userEntry) > 0;
    }

    /**
     * 批量标记文章已读/未读
     *
     * @param intIds 用户文章 ID 列表
     * @param userId 用户 ID
     * @param read 已读状态（true=已读，false=未读）
     * @return 成功更新的数量
     */
    @Transactional
    public int batchMarkAsRead(List<Integer> intIds, Integer userId, Boolean read) {
        log.debug("批量标记文章已读/未读：intIds={}, userId={}, read={}", intIds, userId, read);

        if (intIds == null || intIds.isEmpty() || userId == null || read == null) {
            return 0;
        }

        // 查询用户文章（包含权限验证）
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.in(UserEntry::getIntId, intIds)
                .eq(UserEntry::getOwnerUid, userId);
        List<UserEntry> userEntries = userEntryMapper.selectList(wrapper);

        if (userEntries.isEmpty()) {
            log.debug("没有可操作的文章：intIds={}, userId={}", intIds, userId);
            return 0;
        }

        // 批量更新
        int count = 0;
        for (UserEntry userEntry : userEntries) {
            userEntry.setUnread(!read);
            if (userEntryMapper.updateById(userEntry) > 0) {
                count++;
            }
        }

        log.info("批量标记已读完成：成功{}条，userId={}", count, userId);
        return count;
    }

    /**
     * 批量标记文章星标/取消星标
     *
     * @param intIds 用户文章 ID 列表
     * @param userId 用户 ID
     * @param starred 星标状态（true=星标，false=取消星标）
     * @return 成功更新的数量
     */
    @Transactional
    public int batchMarkAsStarred(List<Integer> intIds, Integer userId, Boolean starred) {
        log.debug("批量标记文章星标：intIds={}, userId={}, starred={}", intIds, userId, starred);

        if (intIds == null || intIds.isEmpty() || userId == null || starred == null) {
            return 0;
        }

        // 查询用户文章（包含权限验证）
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.in(UserEntry::getIntId, intIds)
                .eq(UserEntry::getOwnerUid, userId);
        List<UserEntry> userEntries = userEntryMapper.selectList(wrapper);

        if (userEntries.isEmpty()) {
            log.debug("没有可操作的文章：intIds={}, userId={}", intIds, userId);
            return 0;
        }

        // 批量更新
        int count = 0;
        for (UserEntry userEntry : userEntries) {
            userEntry.setMarked(starred);
            if (userEntryMapper.updateById(userEntry) > 0) {
                count++;
            }
        }

        log.info("批量标记星标完成：成功{}条，userId={}", count, userId);
        return count;
    }

    /**
     * 将 UserEntry 转换为 ArticleDTO（完整详情）
     *
     * @param userEntry 用户文章实体
     * @return 文章详情 DTO
     */
    private ArticleDTO convertToDetailDTO(UserEntry userEntry) {
        ArticleDTO dto = new ArticleDTO();
        dto.setIntId(userEntry.getIntId());
        dto.setFeedId(userEntry.getFeedId());
        dto.setOwnerUid(userEntry.getOwnerUid());
        dto.setUnread(userEntry.getUnread());
        dto.setMarked(userEntry.getMarked());
        dto.setPublished(userEntry.getPublished());
        dto.setScore(userEntry.getScore());

        // 获取关联的文章信息
        Entry entry = entryMapper.selectById(userEntry.getRefId());
        if (entry != null) {
            dto.setId(entry.getId());
            dto.setGuid(entry.getGuid());
            dto.setTitle(entry.getTitle());
            dto.setContent(entry.getContent());
            dto.setLink(entry.getLink());
            dto.setUpdated(entry.getUpdated());
            dto.setAuthor(entry.getAuthor());
        }

        // 获取订阅源标题
        if (userEntry.getFeedId() != null) {
            Feed feed = feedMapper.selectById(userEntry.getFeedId());
            if (feed != null) {
                dto.setFeedTitle(feed.getTitle());
            }
        }

        return dto;
    }

    /**
     * 获取文章列表
     *
     * @param userId 用户 ID
     * @param feedId 订阅源 ID（可选）
     * @param categoryId 分类 ID（可选）
     * @param unread 未读状态（可选）
     * @param starred 星标状态（可选）
     * @param page 页码（从 1 开始，默认 1）
     * @param size 每页大小（默认 20，最大 100）
     * @return 分页文章列表
     */
    @Transactional(readOnly = true)
    public ArticlePageResponse getArticles(Integer userId, Integer feedId, Integer categoryId,
                                           Boolean unread, Boolean starred,
                                           Integer page, Integer size) {
        log.debug("获取文章列表：userId={}, feedId={}, categoryId={}, unread={}, starred={}, page={}, size={}",
                userId, feedId, categoryId, unread, starred, page, size);

        // 参数验证和默认值处理
        if (page == null || page < 1) {
            page = 1;
        }
        if (size == null || size < 1) {
            size = defaultPageSize;
        }
        // 限制最大分页大小
        if (size > maxPageSize) {
            size = maxPageSize;
        }

        // 构建查询条件
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        
        // 必须按用户 ID 过滤
        wrapper.eq(UserEntry::getOwnerUid, userId);

        // 按订阅源 ID 过滤
        if (feedId != null) {
            wrapper.eq(UserEntry::getFeedId, feedId);
        }

        // 按分类 ID 过滤（需要关联订阅源表）
        if (categoryId != null) {
            List<Integer> feedIds = getFeedIdsByCategoryId(categoryId, userId);
            if (feedIds.isEmpty()) {
                // 分类下没有订阅源，返回空列表
                return ArticlePageResponse.of(List.of(), page, size, 0L);
            }
            wrapper.in(UserEntry::getFeedId, feedIds);
        }

        // 按未读状态过滤
        if (unread != null) {
            wrapper.eq(UserEntry::getUnread, unread);
        }

        // 按星标状态过滤
        if (starred != null) {
            wrapper.eq(UserEntry::getMarked, starred);
        }

        // 按更新时间倒序排序
        wrapper.orderByDesc(UserEntry::getIntId);

        // 执行分页查询
        Page<UserEntry> userEntryPage = new Page<>(page, size);
        Page<UserEntry> resultPage = userEntryMapper.selectPage(userEntryPage, wrapper);

        // 转换为 DTO 列表
        List<ArticleListDTO> articleList = resultPage.getRecords().stream()
                .map(this::convertToListDTO)
                .collect(Collectors.toList());

        // 构建分页响应
        return ArticlePageResponse.of(articleList, page, size, resultPage.getTotal());
    }

    /**
     * 根据分类 ID 获取订阅源 ID 列表
     *
     * @param categoryId 分类 ID
     * @param userId 用户 ID
     * @return 订阅源 ID 列表
     */
    private List<Integer> getFeedIdsByCategoryId(Integer categoryId, Integer userId) {
        LambdaQueryWrapper<Feed> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(Feed::getCatId, categoryId)
                .eq(Feed::getOwnerUid, userId);
        List<Feed> feeds = feedMapper.selectList(wrapper);
        return feeds.stream()
                .map(Feed::getId)
                .collect(Collectors.toList());
    }

    /**
     * 将 UserEntry 转换为 ArticleListDTO
     *
     * @param userEntry 用户文章实体
     * @return 文章列表 DTO
     */
    private ArticleListDTO convertToListDTO(UserEntry userEntry) {
        ArticleListDTO dto = new ArticleListDTO();
        dto.setIntId(userEntry.getIntId());
        dto.setFeedId(userEntry.getFeedId());
        dto.setUnread(userEntry.getUnread());
        dto.setMarked(userEntry.getMarked());
        dto.setPublished(userEntry.getPublished());
        dto.setScore(userEntry.getScore());

        // 获取关联的文章信息
        Entry entry = entryMapper.selectById(userEntry.getRefId());
        if (entry != null) {
            dto.setId(entry.getId());
            dto.setTitle(entry.getTitle());
            dto.setLink(entry.getLink());
            dto.setUpdated(entry.getUpdated());
            dto.setAuthor(entry.getAuthor());
        }

        // 获取订阅源标题
        if (userEntry.getFeedId() != null) {
            Feed feed = feedMapper.selectById(userEntry.getFeedId());
            if (feed != null) {
                dto.setFeedTitle(feed.getTitle());
            }
        }

        return dto;
    }

    /**
     * 获取用户未读文章数量
     *
     * @param userId 用户 ID
     * @return 未读文章数量
     */
    @Transactional(readOnly = true)
    public Long getUnreadCount(Integer userId) {
        log.debug("获取用户未读文章数量：userId={}", userId);
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(UserEntry::getOwnerUid, userId)
                .eq(UserEntry::getUnread, true);
        return userEntryMapper.selectCount(wrapper);
    }

    /**
     * 获取用户星标文章数量
     *
     * @param userId 用户 ID
     * @return 星标文章数量
     */
    @Transactional(readOnly = true)
    public Long getStarredCount(Integer userId) {
        log.debug("获取用户星标文章数量：userId={}", userId);
        LambdaQueryWrapper<UserEntry> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(UserEntry::getOwnerUid, userId)
                .eq(UserEntry::getMarked, true);
        return userEntryMapper.selectCount(wrapper);
    }

    /**
     * 搜索文章（全文搜索）
     *
     * @param userId 用户 ID
     * @param keyword 搜索关键词
     * @param feedId 订阅源 ID（可选）
     * @param categoryId 分类 ID（可选）
     * @param page 页码（从 1 开始，默认 1）
     * @param size 每页大小（默认 20，最大 100）
     * @return 分页文章列表
     */
    @Transactional(readOnly = true)
    public ArticlePageResponse searchArticles(Integer userId, String keyword,
                                               Integer feedId, Integer categoryId,
                                               Integer page, Integer size) {
        log.debug("搜索文章：userId={}, keyword={}, feedId={}, categoryId={}, page={}, size={}",
                userId, keyword, feedId, categoryId, page, size);

        // 参数验证和默认值处理
        if (page == null || page < 1) {
            page = 1;
        }
        if (size == null || size < 1) {
            size = defaultPageSize;
        }
        // 限制最大分页大小
        if (size > maxPageSize) {
            size = maxPageSize;
        }

        // 计算偏移量
        int offset = (page - 1) * size;

        // 执行搜索
        List<ArticleListDTO> articleList = articleMapper.searchArticles(
                userId, keyword, feedId, categoryId, offset, size);

        // 统计总数
        Long total = articleMapper.countSearchArticles(
                userId, keyword, feedId, categoryId);

        log.info("搜索完成：userId={}, keyword={}, 结果数={}, 总数={}", userId, keyword, articleList.size(), total);

        // 构建分页响应
        return ArticlePageResponse.of(articleList, page, size, total);
    }
}
