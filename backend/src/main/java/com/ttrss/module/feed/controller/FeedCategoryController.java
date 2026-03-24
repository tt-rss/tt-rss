package com.ttrss.module.feed.controller;

import com.ttrss.module.feed.dto.FeedCategoryDTO;
import com.ttrss.module.feed.service.FeedCategoryService;
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
 * 订阅源分类控制器
 * 处理分类的增删改查请求
 */
@Slf4j
@RestController
@RequestMapping("/api/categories")
@RequiredArgsConstructor
@Tag(name = "分类管理", description = "订阅源分类相关 API")
public class FeedCategoryController {

    private final FeedCategoryService feedCategoryService;

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
     * 获取分类列表
     *
     * @param userDetails 认证用户信息
     * @return 分类列表
     */
    @Operation(summary = "获取分类列表", description = "获取当前用户的所有订阅源分类")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @GetMapping
    public ResponseEntity<List<FeedCategoryDTO>> getCategories(
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取分类列表失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取分类列表：userId={}", userId);
        List<FeedCategoryDTO> categories = feedCategoryService.getCategoriesByUserId(userId);
        return ResponseEntity.ok(categories);
    }

    /**
     * 获取分类详情
     *
     * @param id 分类 ID
     * @param userDetails 认证用户信息
     * @return 分类详情
     */
    @Operation(summary = "获取分类详情", description = "根据 ID 获取分类详细信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "分类不存在")
    })
    @GetMapping("/{id}")
    public ResponseEntity<FeedCategoryDTO> getCategory(
            @Parameter(description = "分类 ID") @PathVariable Integer id,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取分类详情失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取分类详情：id={}, userId={}", id, userId);
        FeedCategoryDTO category = feedCategoryService.getCategoryById(id, userId);
        if (category == null) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
        }
        return ResponseEntity.ok(category);
    }

    /**
     * 创建分类
     *
     * @param categoryDTO 分类 DTO
     * @param userDetails 认证用户信息
     * @return 创建的分类
     */
    @Operation(summary = "创建分类", description = "添加新的订阅源分类")
    @ApiResponses({
        @ApiResponse(responseCode = "201", description = "创建成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @PostMapping
    public ResponseEntity<FeedCategoryDTO> createCategory(
            @Parameter(description = "分类 DTO") @Valid @RequestBody FeedCategoryDTO categoryDTO,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("创建分类失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("创建分类：userId={}, title={}", userId, categoryDTO.getTitle());
        try {
            FeedCategoryDTO createdCategory = feedCategoryService.createCategory(categoryDTO, userId);
            return ResponseEntity.status(HttpStatus.CREATED).body(createdCategory);
        } catch (IllegalArgumentException e) {
            log.warn("创建分类失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 更新分类
     *
     * @param id 分类 ID
     * @param categoryDTO 分类 DTO
     * @param userDetails 认证用户信息
     * @return 更新后的分类
     */
    @Operation(summary = "更新分类", description = "更新指定分类的信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "更新成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "分类不存在")
    })
    @PutMapping("/{id}")
    public ResponseEntity<FeedCategoryDTO> updateCategory(
            @Parameter(description = "分类 ID") @PathVariable Integer id,
            @Parameter(description = "分类 DTO") @Valid @RequestBody FeedCategoryDTO categoryDTO,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("更新分类失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("更新分类：id={}, userId={}", id, userId);
        try {
            FeedCategoryDTO updatedCategory = feedCategoryService.updateCategory(id, categoryDTO, userId);
            if (updatedCategory == null) {
                return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
            }
            return ResponseEntity.ok(updatedCategory);
        } catch (IllegalArgumentException e) {
            log.warn("更新分类失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 删除分类
     *
     * @param id 分类 ID
     * @param userDetails 认证用户信息
     * @return 删除结果
     */
    @Operation(summary = "删除分类", description = "删除指定的订阅源分类（需先清空分类下的订阅源）")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "删除成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "分类不存在"),
        @ApiResponse(responseCode = "409", description = "分类下还有订阅源，无法删除")
    })
    @DeleteMapping("/{id}")
    public ResponseEntity<Map<String, Object>> deleteCategory(
            @Parameter(description = "分类 ID") @PathVariable Integer id,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("删除分类失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("删除分类：id={}, userId={}", id, userId);
        try {
            boolean deleted = feedCategoryService.deleteCategory(id, userId);
            if (!deleted) {
                return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
            }

            Map<String, Object> response = new HashMap<>();
            response.put("success", true);
            response.put("message", "分类已删除");
            return ResponseEntity.ok(response);
        } catch (IllegalStateException e) {
            log.warn("删除分类失败：{}", e.getMessage());
            Map<String, Object> response = new HashMap<>();
            response.put("success", false);
            response.put("message", e.getMessage());
            return ResponseEntity.status(HttpStatus.CONFLICT).body(response);
        }
    }
}
