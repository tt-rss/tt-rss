package com.ttrss.infrastructure.rss;

import com.rometools.rome.feed.synd.SyndEntry;
import com.rometools.rome.feed.synd.SyndFeed;
import com.rometools.rome.io.SyndFeedInput;
import com.rometools.rome.io.XmlReader;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Component;

import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.time.Instant;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;

/**
 * Rome RSS/Atom Feed 解析适配器
 * 使用 Rome 库解析 RSS 和 Atom Feed，提取标题、链接、内容、作者、时间等信息
 *
 * @author ttrss
 * @since 2026-03-24
 */
@Slf4j
@Component
public class RomeAdapter {

    private static final int CONNECT_TIMEOUT = 10000;
    private static final int READ_TIMEOUT = 30000;
    private static final String USER_AGENT = "Mozilla/5.0 (compatible; TTRSS Feed Reader)";

    /**
     * 从 URL 获取并解析 Feed
     *
     * @param feedUrl Feed URL
     * @return 解析后的 Feed 数据
     * @throws Exception 解析异常
     */
    public FeedData fetchAndParse(String feedUrl) throws Exception {
        log.debug("Fetching and parsing feed: {}", feedUrl);
        
        HttpURLConnection connection = (HttpURLConnection) new URL(feedUrl).openConnection();
        connection.setConnectTimeout(CONNECT_TIMEOUT);
        connection.setReadTimeout(READ_TIMEOUT);
        connection.setRequestProperty("User-Agent", USER_AGENT);
        connection.setRequestProperty("Accept", "application/rss+xml, application/xml, application/atom+xml, text/xml");
        
        try (InputStream inputStream = connection.getInputStream()) {
            SyndFeedInput input = new SyndFeedInput();
            SyndFeed syndFeed = input.build(new XmlReader(inputStream));
            return convertToFeedData(syndFeed);
        } finally {
            connection.disconnect();
        }
    }

    /**
     * 从输入流解析 Feed
     *
     * @param inputStream Feed 输入流
     * @return 解析后的 Feed 数据
     * @throws Exception 解析异常
     */
    public FeedData parse(InputStream inputStream) throws Exception {
        SyndFeedInput input = new SyndFeedInput();
        SyndFeed syndFeed = input.build(new XmlReader(inputStream, StandardCharsets.UTF_8.name()));
        return convertToFeedData(syndFeed);
    }

    /**
     * 将 Rome SyndFeed 转换为 FeedData
     *
     * @param syndFeed Rome SyndFeed
     * @return FeedData
     */
    private FeedData convertToFeedData(SyndFeed syndFeed) {
        FeedData feedData = new FeedData();
        feedData.setTitle(syndFeed.getTitle());
        feedData.setLink(syndFeed.getLink());
        feedData.setDescription(syndFeed.getDescription());
        feedData.setFeedType(syndFeed.getFeedType());
        
        if (syndFeed.getPublishedDate() != null) {
            feedData.setPublishedDate(dateToLocalDateTime(syndFeed.getPublishedDate()));
        }
        
        List<EntryData> entries = new ArrayList<>();
        for (SyndEntry entry : syndFeed.getEntries()) {
            EntryData entryData = convertToEntryData(entry);
            entries.add(entryData);
        }
        feedData.setEntries(entries);
        
        log.debug("Parsed feed: {}, entries: {}", feedData.getTitle(), entries.size());
        return feedData;
    }

    /**
     * 将 Rome SyndEntry 转换为 EntryData
     *
     * @param entry Rome SyndEntry
     * @return EntryData
     */
    private EntryData convertToEntryData(SyndEntry entry) {
        EntryData entryData = new EntryData();
        entryData.setTitle(entry.getTitle());
        entryData.setLink(entry.getLink());
        entryData.setGuid(entry.getUri() != null ? entry.getUri() : entry.getLink());
        
        // 获取内容
        if (entry.getContents() != null && !entry.getContents().isEmpty()) {
            entryData.setContent(entry.getContents().get(0).getValue());
        } else if (entry.getDescription() != null) {
            entryData.setContent(entry.getDescription().getValue());
        }
        
        // 获取作者
        if (entry.getAuthor() != null) {
            entryData.setAuthor(entry.getAuthor());
        } else if (entry.getAuthors() != null && !entry.getAuthors().isEmpty()) {
            entryData.setAuthor(entry.getAuthors().get(0).getName());
        }
        
        // 获取发布时间
        Date publishedDate = entry.getPublishedDate();
        if (publishedDate == null) {
            publishedDate = entry.getUpdatedDate();
        }
        if (publishedDate != null) {
            entryData.setPublishedDate(dateToLocalDateTime(publishedDate));
        }
        
        // 获取更新时间
        if (entry.getUpdatedDate() != null) {
            entryData.setUpdatedDate(dateToLocalDateTime(entry.getUpdatedDate()));
        }
        
        return entryData;
    }

    /**
     * Date 转 LocalDateTime
     *
     * @param date Date
     * @return LocalDateTime
     */
    private LocalDateTime dateToLocalDateTime(Date date) {
        if (date == null) {
            return null;
        }
        return LocalDateTime.ofInstant(date.toInstant(), ZoneId.systemDefault());
    }

    /**
     * Feed 数据类
     */
    public static class FeedData {
        private String title;
        private String link;
        private String description;
        private String feedType;
        private LocalDateTime publishedDate;
        private List<EntryData> entries;

        public String getTitle() {
            return title;
        }

        public void setTitle(String title) {
            this.title = title;
        }

        public String getLink() {
            return link;
        }

        public void setLink(String link) {
            this.link = link;
        }

        public String getDescription() {
            return description;
        }

        public void setDescription(String description) {
            this.description = description;
        }

        public String getFeedType() {
            return feedType;
        }

        public void setFeedType(String feedType) {
            this.feedType = feedType;
        }

        public LocalDateTime getPublishedDate() {
            return publishedDate;
        }

        public void setPublishedDate(LocalDateTime publishedDate) {
            this.publishedDate = publishedDate;
        }

        public List<EntryData> getEntries() {
            return entries;
        }

        public void setEntries(List<EntryData> entries) {
            this.entries = entries;
        }
    }

    /**
     * Entry 数据类
     */
    public static class EntryData {
        private String title;
        private String link;
        private String guid;
        private String content;
        private String author;
        private LocalDateTime publishedDate;
        private LocalDateTime updatedDate;

        public String getTitle() {
            return title;
        }

        public void setTitle(String title) {
            this.title = title;
        }

        public String getLink() {
            return link;
        }

        public void setLink(String link) {
            this.link = link;
        }

        public String getGuid() {
            return guid;
        }

        public void setGuid(String guid) {
            this.guid = guid;
        }

        public String getContent() {
            return content;
        }

        public void setContent(String content) {
            this.content = content;
        }

        public String getAuthor() {
            return author;
        }

        public void setAuthor(String author) {
            this.author = author;
        }

        public LocalDateTime getPublishedDate() {
            return publishedDate;
        }

        public void setPublishedDate(LocalDateTime publishedDate) {
            this.publishedDate = publishedDate;
        }

        public LocalDateTime getUpdatedDate() {
            return updatedDate;
        }

        public void setUpdatedDate(LocalDateTime updatedDate) {
            this.updatedDate = updatedDate;
        }
    }
}
