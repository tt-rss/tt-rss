package com.ttrss.module.feed.controller;

import com.ttrss.module.feed.dto.FeedDTO;
import com.ttrss.module.feed.service.FeedService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.Parameter;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * 订阅源控制器
 * 处理订阅源的增删改查请求
 */
@Slf4j
@RestController
@RequestMapping("/feeds")
@RequiredArgsConstructor
@Tag(name = "订阅源管理", description = "RSS 订阅源相关 API")
public class FeedController {

    private final FeedService feedService;

    /**
     * 获取当前用户 ID
     *
     * @param userDetails 认证用户信息
     * @return 用户 ID
     */
    private Integer getCurrentUserId(UserDetails userDetails) {
        if (userDetails == null) {
            return null;
        }
        // 用户名格式为 "id:username"，提取 ID
        String username = userDetails.getUsername();
        if (username.contains(":")) {
            String[] parts = username.split(":");
            try {
                return Integer.parseInt(parts[0]);
            } catch (NumberFormatException e) {
                log.warn("无法解析用户 ID: username={}", username);
            }
        }
        return null;
    }

    /**
     * 获取订阅源列表
     *
     * @param userDetails 认证用户信息
     * @return 订阅源列表
     */
    @Operation(summary = "获取订阅源列表", description = "获取当前用户的所有订阅源")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @GetMapping
    public ResponseEntity<List<FeedDTO>> getFeeds(
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取订阅源列表失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取订阅源列表：userId={}", userId);
        List<FeedDTO> feeds = feedService.getFeedsByUserId(userId);
        return ResponseEntity.ok(feeds);
    }

    /**
     * 获取订阅源详情
     *
     * @param id 订阅源 ID
     * @param userDetails 认证用户信息
     * @return 订阅源详情
     */
    @Operation(summary = "获取订阅源详情", description = "根据 ID 获取订阅源详细信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "订阅源不存在")
    })
    @GetMapping("/{id}")
    public ResponseEntity<FeedDTO> getFeed(
            @Parameter(description = "订阅源 ID") @PathVariable Integer id,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取订阅源详情失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取订阅源详情：id={}, userId={}", id, userId);
        FeedDTO feed = feedService.getFeedById(id, userId);
        if (feed == null) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
        }
        return ResponseEntity.ok(feed);
    }

    /**
     * 创建订阅源
     *
     * @param feedDTO 订阅源 DTO
     * @param userDetails 认证用户信息
     * @return 创建的订阅源
     */
    @Operation(summary = "创建订阅源", description = "添加新的 RSS 订阅源")
    @ApiResponses({
        @ApiResponse(responseCode = "201", description = "创建成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @PostMapping
    public ResponseEntity<FeedDTO> createFeed(
            @Parameter(description = "订阅源 DTO") @Valid @RequestBody FeedDTO feedDTO,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("创建订阅源失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("创建订阅源：userId={}, title={}, feedUrl={}", userId, feedDTO.getTitle(), feedDTO.getFeedUrl());
        try {
            FeedDTO createdFeed = feedService.createFeed(feedDTO, userId);
            return ResponseEntity.status(HttpStatus.CREATED).body(createdFeed);
        } catch (IllegalArgumentException e) {
            log.warn("创建订阅源失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 更新订阅源
     *
     * @param id 订阅源 ID
     * @param feedDTO 订阅源 DTO
     * @param userDetails 认证用户信息
     * @return 更新后的订阅源
     */
    @Operation(summary = "更新订阅源", description = "更新指定订阅源的信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "更新成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "订阅源不存在")
    })
    @PutMapping("/{id}")
    public ResponseEntity<FeedDTO> updateFeed(
            @Parameter(description = "订阅源 ID") @PathVariable Integer id,
            @Parameter(description = "订阅源 DTO") @Valid @RequestBody FeedDTO feedDTO,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("更新订阅源失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("更新订阅源：id={}, userId={}", id, userId);
        try {
            FeedDTO updatedFeed = feedService.updateFeed(id, feedDTO, userId);
            if (updatedFeed == null) {
                return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
            }
            return ResponseEntity.ok(updatedFeed);
        } catch (IllegalArgumentException e) {
            log.warn("更新订阅源失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 删除订阅源
     *
     * @param id 订阅源 ID
     * @param userDetails 认证用户信息
     * @return 删除结果
     */
    @Operation(summary = "删除订阅源", description = "删除指定的订阅源")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "删除成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "订阅源不存在")
    })
    @DeleteMapping("/{id}")
    public ResponseEntity<Map<String, Object>> deleteFeed(
            @Parameter(description = "订阅源 ID") @PathVariable Integer id,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("删除订阅源失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("删除订阅源：id={}, userId={}", id, userId);
        boolean deleted = feedService.deleteFeed(id, userId);
        if (!deleted) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
        }

        Map<String, Object> response = new HashMap<>();
        response.put("success", true);
        response.put("message", "订阅源已删除");
        return ResponseEntity.ok(response);
    }
}
