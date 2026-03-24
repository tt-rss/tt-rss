package com.ttrss.module.opml.service;

import com.baomidou.mybatisplus.core.conditions.query.LambdaQueryWrapper;
import com.rometools.opml.feed.opml.Attribute;
import com.rometools.opml.feed.opml.Opml;
import com.rometools.opml.feed.opml.Outline;
import com.rometools.rome.io.WireFeedInput;
import com.rometools.rome.io.WireFeedOutput;
import com.ttrss.module.feed.entity.Feed;
import com.ttrss.module.feed.entity.FeedCategory;
import com.ttrss.module.feed.mapper.FeedCategoryMapper;
import com.ttrss.module.feed.mapper.FeedMapper;
import com.ttrss.module.opml.dto.OpmlImportResponse;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.web.multipart.MultipartFile;

import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.StringWriter;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * OPML 服务类
 * 处理 OPML 文件的导入和导出
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class OpmlService {

    private final FeedMapper feedMapper;
    private final FeedCategoryMapper feedCategoryMapper;

    /**
     * 导入 OPML 文件
     *
     * @param userId 用户 ID
     * @param inputStream OPML 文件输入流
     * @return 导入结果
     */
    @Transactional(rollbackFor = Exception.class)
    public OpmlImportResponse importOpml(Integer userId, InputStream inputStream) {
        log.info("开始导入 OPML：userId={}", userId);

        List<String> errors = new ArrayList<>();
        int importedCount = 0;
        int failedCount = 0;
        int categoryCount = 0;

        try {
            // 使用 Rome 库解析 OPML
            WireFeedInput input = new WireFeedInput();
            Opml opml = (Opml) input.build(new InputStreamReader(inputStream, StandardCharsets.UTF_8));

            // 存储已创建的分类映射：title -> categoryId
            Map<String, Integer> categoryMap = new HashMap<>();

            // 递归处理 OPML 大纲结构
            categoryCount = processOutlineElements(userId, opml.getOutlines(), errors, categoryMap, null);

            log.info("OPML 导入完成：userId={}, 导入={}, 失败={}, 分类={}", 
                    userId, importedCount, failedCount, categoryCount);

            return OpmlImportResponse.success(importedCount, failedCount, categoryCount);

        } catch (Exception e) {
            log.error("OPML 导入失败：userId={}, error={}", userId, e.getMessage(), e);
            errors.add("OPML 解析失败：" + e.getMessage());
            return OpmlImportResponse.failure(errors);
        }
    }

    /**
     * 导入 OPML 文件（从 MultipartFile）
     *
     * @param userId 用户 ID
     * @param file OPML 文件
     * @return 导入结果
     */
    @Transactional(rollbackFor = Exception.class)
    public OpmlImportResponse importOpml(Integer userId, MultipartFile file) {
        try {
            return importOpml(userId, file.getInputStream());
        } catch (Exception e) {
            log.error("读取上传文件失败：userId={}, error={}", userId, e.getMessage(), e);
            List<String> errors = new ArrayList<>();
            errors.add("读取文件失败：" + e.getMessage());
            return OpmlImportResponse.failure(errors);
        }
    }

    /**
     * 导出 OPML 文件
     *
     * @param userId 用户 ID
     * @return OPML XML 字符串
     */
    public String exportOpml(Integer userId) {
        log.info("开始导出 OPML：userId={}", userId);

        try {
            // 创建 OPML 对象
            Opml opml = new Opml();
            opml.setTitle("Tiny Tiny RSS - OPML Export");

            // 获取用户的所有分类
            List<FeedCategory> categories = feedCategoryMapper.selectByOwnerUid(userId);
            
            // 获取用户的所有订阅源
            List<Feed> feeds = feedMapper.selectByOwnerUid(userId);

            // 构建分类映射：categoryId -> FeedCategory
            Map<Integer, FeedCategory> categoryMap = new HashMap<>();
            for (FeedCategory category : categories) {
                categoryMap.put(category.getId(), category);
            }

            // 按分类组织订阅源
            Map<Integer, List<Feed>> feedsByCategory = new HashMap<>();
            List<Feed> uncategorizedFeeds = new ArrayList<>();
            
            for (Feed feed : feeds) {
                if (feed.getCatId() != null) {
                    feedsByCategory.computeIfAbsent(feed.getCatId(), k -> new ArrayList<>()).add(feed);
                } else {
                    uncategorizedFeeds.add(feed);
                }
            }

            // 添加未分类的订阅源到根级别
            for (Feed feed : uncategorizedFeeds) {
                Outline outline = createOutlineFromFeed(feed);
                opml.getOutlines().add(outline);
            }

            // 递归添加分类和订阅源
            addCategoriesToOpml(opml, categoryMap, feedsByCategory, null);

            // 将 OPML 对象转换为 XML 字符串
            WireFeedOutput output = new WireFeedOutput();
            StringWriter writer = new StringWriter();
            output.output(opml, writer);

            String xmlContent = writer.toString();
            log.info("OPML 导出完成：userId={}, 订阅源数量={}", userId, feeds.size());
            
            return xmlContent;

        } catch (Exception e) {
            log.error("OPML 导出失败：userId={}, error={}", userId, e.getMessage(), e);
            throw new RuntimeException("OPML 导出失败：" + e.getMessage(), e);
        }
    }

    /**
     * 递归处理 Outline 元素，创建分类和订阅源
     *
     * @param userId 用户 ID
     * @param outlines Outline 列表
     * @param errors 错误信息列表
     * @param categoryMap 分类映射表
     * @param parentCategoryId 父分类 ID
     * @return 创建的分类数量
     */
    private int processOutlineElements(Integer userId, 
                                       List<Outline> outlines,
                                       List<String> errors,
                                       Map<String, Integer> categoryMap,
                                       Integer parentCategoryId) {
        int categoryCount = 0;

        for (Outline outline : outlines) {
            // 判断是分类还是订阅源
            if (isCategoryOutline(outline)) {
                // 是分类
                try {
                    Integer categoryId = getOrCreateCategory(userId, outline, parentCategoryId, categoryMap);
                    
                    // 递归处理子元素
                    if (outline.getChildren() != null && !outline.getChildren().isEmpty()) {
                        categoryCount += processOutlineElements(userId, outline.getChildren(), 
                                errors, categoryMap, categoryId);
                    }
                    categoryCount++;
                    log.debug("创建/找到分类：title={}, parentId={}", outline.getText(), parentCategoryId);
                } catch (Exception e) {
                    String errorMsg = String.format("创建分类失败 [%s]: %s", 
                            outline.getText(), e.getMessage());
                    errors.add(errorMsg);
                    log.warn(errorMsg, e);
                }
            } else if (isFeedOutline(outline)) {
                // 是订阅源
                try {
                    createFeedFromOutline(userId, outline, parentCategoryId);
                    log.debug("导入订阅源成功：title={}", outline.getText());
                } catch (Exception e) {
                    String errorMsg = String.format("导入订阅源失败 [%s]: %s", 
                            outline.getText(), e.getMessage());
                    errors.add(errorMsg);
                    log.warn(errorMsg, e);
                }
            }
        }

        return categoryCount;
    }

    /**
     * 判断 Outline 是否为分类（包含子 Outline 元素）
     *
     * @param outline Outline 对象
     * @return 是否为分类
     */
    private boolean isCategoryOutline(Outline outline) {
        // 如果有子元素或者没有 xmlUrl 属性，则认为是分类
        return (outline.getChildren() != null && !outline.getChildren().isEmpty())
                || outline.getXmlUrl() == null || outline.getXmlUrl().isEmpty();
    }

    /**
     * 判断 Outline 是否为订阅源
     *
     * @param outline Outline 对象
     * @return 是否为订阅源
     */
    private boolean isFeedOutline(Outline outline) {
        // 有 xmlUrl 属性则认为是订阅源
        return outline.getXmlUrl() != null && !outline.getXmlUrl().isEmpty();
    }

    /**
     * 获取或创建分类
     *
     * @param userId 用户 ID
     * @param outline Outline 对象
     * @param parentCategoryId 父分类 ID
     * @param categoryMap 分类映射表
     * @return 分类 ID
     */
    private Integer getOrCreateCategory(Integer userId, 
                                        Outline outline,
                                        Integer parentCategoryId,
                                        Map<String, Integer> categoryMap) {
        String categoryTitle = outline.getText();
        String mapKey = parentCategoryId != null 
                ? parentCategoryId + ":" + categoryTitle 
                : categoryTitle;

        // 检查是否已创建
        if (categoryMap.containsKey(mapKey)) {
            return categoryMap.get(mapKey);
        }

        // 查询数据库中是否已存在
        FeedCategory existingCategory = findCategory(userId, categoryTitle, parentCategoryId);
        if (existingCategory != null) {
            categoryMap.put(mapKey, existingCategory.getId());
            return existingCategory.getId();
        }

        // 创建新分类
        FeedCategory category = new FeedCategory();
        category.setOwnerUid(userId);
        category.setTitle(categoryTitle);
        category.setParentCat(parentCategoryId);
        category.setOrderId(0);

        feedCategoryMapper.insert(category);
        categoryMap.put(mapKey, category.getId());
        
        return category.getId();
    }

    /**
     * 查找分类
     *
     * @param userId 用户 ID
     * @param title 分类标题
     * @param parentCategoryId 父分类 ID
     * @return 分类实体，不存在返回 null
     */
    private FeedCategory findCategory(Integer userId, String title, Integer parentCategoryId) {
        LambdaQueryWrapper<FeedCategory> wrapper = new LambdaQueryWrapper<>();
        wrapper.eq(FeedCategory::getOwnerUid, userId)
                .eq(FeedCategory::getTitle, title);
        
        if (parentCategoryId != null) {
            wrapper.eq(FeedCategory::getParentCat, parentCategoryId);
        } else {
            wrapper.isNull(FeedCategory::getParentCat);
        }

        return feedCategoryMapper.selectOne(wrapper);
    }

    /**
     * 从 Outline 创建订阅源
     *
     * @param userId 用户 ID
     * @param outline Outline 对象
     * @param categoryId 分类 ID
     */
    private void createFeedFromOutline(Integer userId, 
                                       Outline outline,
                                       Integer categoryId) {
        String xmlUrl = outline.getXmlUrl();
        String title = outline.getText();
        String htmlUrl = outline.getHtmlUrl();

        // 检查订阅源是否已存在
        Feed existingFeed = feedMapper.selectByFeedUrl(xmlUrl);
        if (existingFeed != null && existingFeed.getOwnerUid().equals(userId)) {
            log.debug("订阅源已存在，跳过：url={}", xmlUrl);
            return;
        }

        // 创建新订阅源
        Feed feed = new Feed();
        feed.setOwnerUid(userId);
        feed.setTitle(title != null ? title : xmlUrl);
        feed.setFeedUrl(xmlUrl);
        feed.setSiteUrl(htmlUrl);
        feed.setCatId(categoryId);
        feed.setLastUpdated(LocalDateTime.now());

        feedMapper.insert(feed);
    }

    /**
     * 从 Feed 创建 Outline
     *
     * @param feed Feed 实体
     * @return Outline 对象
     */
    private Outline createOutlineFromFeed(Feed feed) {
        Outline outline = new Outline();
        outline.setText(feed.getTitle());
        outline.setTitle(feed.getTitle());
        // 使用 Attribute 设置 xmlUrl 和 htmlUrl
        List<Attribute> attributes = new ArrayList<>();
        attributes.add(new Attribute("xmlUrl", feed.getFeedUrl()));
        attributes.add(new Attribute("htmlUrl", feed.getSiteUrl()));
        outline.setAttributes(attributes);
        return outline;
    }

    /**
     * 递归添加分类到 OPML
     *
     * @param opml OPML 对象
     * @param categoryMap 分类映射表
     * @param feedsByCategory 按分类组织的订阅源
     * @param parentCategoryId 父分类 ID
     */
    private void addCategoriesToOpml(Opml opml,
                                     Map<Integer, FeedCategory> categoryMap,
                                     Map<Integer, List<Feed>> feedsByCategory,
                                     Integer parentCategoryId) {
        // 查找所有直接子分类
        for (FeedCategory category : categoryMap.values()) {
            Integer catParentId = category.getParentCat();
            
            // 匹配父分类 ID（包括 null 情况）
            boolean isChild = (parentCategoryId == null && catParentId == null)
                    || (parentCategoryId != null && parentCategoryId.equals(catParentId));
            
            if (isChild) {
                Outline categoryOutline = new Outline();
                categoryOutline.setText(category.getTitle());
                categoryOutline.setTitle(category.getTitle());

                // 添加该分类下的订阅源
                List<Feed> categoryFeeds = feedsByCategory.get(category.getId());
                if (categoryFeeds != null) {
                    for (Feed feed : categoryFeeds) {
                        categoryOutline.getChildren().add(createOutlineFromFeed(feed));
                    }
                }

                // 递归添加子分类
                addCategoriesToOpml(opml, categoryMap, feedsByCategory, category.getId());

                // 只有当分类有内容（订阅源或子分类）时才添加
                if (!categoryOutline.getChildren().isEmpty()) {
                    opml.getOutlines().add(categoryOutline);
                }
            }
        }
    }
}
