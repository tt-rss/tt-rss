package com.ttrss.module.label.controller;

import com.ttrss.module.label.dto.LabelDTO;
import com.ttrss.module.label.service.LabelService;
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
 * 标签控制器
 * 处理标签的增删改查请求
 */
@Slf4j
@RestController
@RequestMapping("/labels")
@RequiredArgsConstructor
@Tag(name = "标签管理", description = "文章标签相关 API")
public class LabelController {

    private final LabelService labelService;

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
     * 获取标签列表
     *
     * @param userDetails 认证用户信息
     * @return 标签列表
     */
    @Operation(summary = "获取标签列表", description = "获取当前用户的所有标签")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @GetMapping
    public ResponseEntity<List<LabelDTO>> getLabels(
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取标签列表失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取标签列表：userId={}", userId);
        List<LabelDTO> labels = labelService.getLabelsByUserId(userId);
        return ResponseEntity.ok(labels);
    }

    /**
     * 获取标签详情
     *
     * @param id 标签 ID
     * @param userDetails 认证用户信息
     * @return 标签详情
     */
    @Operation(summary = "获取标签详情", description = "根据 ID 获取标签详细信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "标签不存在")
    })
    @GetMapping("/{id}")
    public ResponseEntity<LabelDTO> getLabel(
            @Parameter(description = "标签 ID") @PathVariable Integer id,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取标签详情失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取标签详情：id={}, userId={}", id, userId);
        LabelDTO label = labelService.getLabelById(id, userId);
        if (label == null) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
        }
        return ResponseEntity.ok(label);
    }

    /**
     * 创建标签
     *
     * @param labelDTO 标签 DTO
     * @param userDetails 认证用户信息
     * @return 创建的标签
     */
    @Operation(summary = "创建标签", description = "添加新的标签")
    @ApiResponses({
        @ApiResponse(responseCode = "201", description = "创建成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @PostMapping
    public ResponseEntity<LabelDTO> createLabel(
            @Parameter(description = "标签 DTO") @Valid @RequestBody LabelDTO labelDTO,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("创建标签失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("创建标签：userId={}, caption={}", userId, labelDTO.getCaption());
        try {
            LabelDTO createdLabel = labelService.createLabel(labelDTO, userId);
            return ResponseEntity.status(HttpStatus.CREATED).body(createdLabel);
        } catch (IllegalArgumentException e) {
            log.warn("创建标签失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 更新标签
     *
     * @param id 标签 ID
     * @param labelDTO 标签 DTO
     * @param userDetails 认证用户信息
     * @return 更新后的标签
     */
    @Operation(summary = "更新标签", description = "更新指定标签的信息")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "更新成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "标签不存在")
    })
    @PutMapping("/{id}")
    public ResponseEntity<LabelDTO> updateLabel(
            @Parameter(description = "标签 ID") @PathVariable Integer id,
            @Parameter(description = "标签 DTO") @Valid @RequestBody LabelDTO labelDTO,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("更新标签失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("更新标签：id={}, userId={}", id, userId);
        try {
            LabelDTO updatedLabel = labelService.updateLabel(id, labelDTO, userId);
            if (updatedLabel == null) {
                return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
            }
            return ResponseEntity.ok(updatedLabel);
        } catch (IllegalArgumentException e) {
            log.warn("更新标签失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 删除标签
     *
     * @param id 标签 ID
     * @param userDetails 认证用户信息
     * @return 删除结果
     */
    @Operation(summary = "删除标签", description = "删除指定的标签")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "删除成功"),
        @ApiResponse(responseCode = "401", description = "未认证"),
        @ApiResponse(responseCode = "404", description = "标签不存在")
    })
    @DeleteMapping("/{id}")
    public ResponseEntity<Map<String, Object>> deleteLabel(
            @Parameter(description = "标签 ID") @PathVariable Integer id,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("删除标签失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("删除标签：id={}, userId={}", id, userId);
        boolean deleted = labelService.deleteLabel(id, userId);
        if (!deleted) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
        }

        Map<String, Object> response = new HashMap<>();
        response.put("success", true);
        response.put("message", "标签已删除");
        return ResponseEntity.ok(response);
    }

    /**
     * 获取文章的标签列表
     *
     * @param articleId 文章 ID
     * @param userDetails 认证用户信息
     * @return 标签列表
     */
    @Operation(summary = "获取文章的标签列表", description = "获取指定文章的所有标签")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "获取成功"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @GetMapping("/articles/{articleId}")
    public ResponseEntity<List<LabelDTO>> getLabelsByArticleId(
            @Parameter(description = "文章 ID") @PathVariable Integer articleId,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("获取文章标签失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("获取文章标签：articleId={}, userId={}", articleId, userId);
        List<LabelDTO> labels = labelService.getLabelsByArticleId(articleId, userId);
        return ResponseEntity.ok(labels);
    }

    /**
     * 为文章添加标签
     *
     * @param articleId 文章 ID
     * @param labelIds 标签 ID 列表
     * @param userDetails 认证用户信息
     * @return 操作结果
     */
    @Operation(summary = "为文章添加标签", description = "为指定文章添加一个或多个标签")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "添加成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @PostMapping("/articles/{articleId}")
    public ResponseEntity<Map<String, Object>> addLabelsToArticle(
            @Parameter(description = "文章 ID") @PathVariable Integer articleId,
            @Parameter(description = "标签 ID 列表") @RequestBody List<Integer> labelIds,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("添加文章标签失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("添加文章标签：articleId={}, userId={}, labelIds={}", articleId, userId, labelIds);
        try {
            labelService.addLabelsToArticle(articleId, labelIds, userId);
            Map<String, Object> response = new HashMap<>();
            response.put("success", true);
            response.put("message", "标签已添加");
            return ResponseEntity.ok(response);
        } catch (IllegalArgumentException e) {
            log.warn("添加文章标签失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }

    /**
     * 从文章移除标签
     *
     * @param articleId 文章 ID
     * @param labelId 标签 ID
     * @param userDetails 认证用户信息
     * @return 操作结果
     */
    @Operation(summary = "从文章移除标签", description = "从指定文章移除一个标签")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "移除成功"),
        @ApiResponse(responseCode = "400", description = "请求参数无效"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @DeleteMapping("/articles/{articleId}/{labelId}")
    public ResponseEntity<Map<String, Object>> removeLabelFromArticle(
            @Parameter(description = "文章 ID") @PathVariable Integer articleId,
            @Parameter(description = "标签 ID") @PathVariable Integer labelId,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("移除文章标签失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("移除文章标签：articleId={}, labelId={}, userId={}", articleId, labelId, userId);
        try {
            labelService.removeLabelFromArticle(articleId, labelId, userId);
            Map<String, Object> response = new HashMap<>();
            response.put("success", true);
            response.put("message", "标签已移除");
            return ResponseEntity.ok(response);
        } catch (IllegalArgumentException e) {
            log.warn("移除文章标签失败：{}", e.getMessage());
            return ResponseEntity.badRequest().build();
        }
    }
}
