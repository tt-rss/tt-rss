package com.ttrss.module.opml.controller;

import com.ttrss.module.opml.dto.OpmlImportResponse;
import com.ttrss.module.opml.service.OpmlService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.Parameter;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.multipart.MultipartFile;

/**
 * OPML 控制器
 * 处理 OPML 文件的导入和导出请求
 */
@Slf4j
@RestController
@RequestMapping("/api/opml")
@RequiredArgsConstructor
@Tag(name = "OPML 管理", description = "OPML 导入导出相关 API")
public class OpmlController {

    private final OpmlService opmlService;

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
     * 导入 OPML 文件
     *
     * @param file OPML 文件（multipart/form-data）
     * @param userDetails 认证用户信息
     * @return 导入结果
     */
    @Operation(summary = "导入 OPML 文件", description = "上传并导入 OPML 文件，批量添加订阅源")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "导入成功"),
        @ApiResponse(responseCode = "400", description = "文件格式不正确或为空"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @PostMapping("/import")
    public ResponseEntity<OpmlImportResponse> importOpml(
            @Parameter(description = "OPML 文件（multipart/form-data）", required = true) @RequestParam("file") MultipartFile file,
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("导入 OPML 失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        // 验证文件
        if (file.isEmpty()) {
            log.warn("导入 OPML 失败：文件为空");
            OpmlImportResponse response = OpmlImportResponse.failure(
                    java.util.List.of("上传文件为空"));
            return ResponseEntity.badRequest().body(response);
        }

        String fileName = file.getOriginalFilename();
        if (fileName == null || (!fileName.toLowerCase().endsWith(".opml") 
                && !fileName.toLowerCase().endsWith(".xml"))) {
            log.warn("导入 OPML 失败：文件格式不正确：fileName={}", fileName);
            OpmlImportResponse response = OpmlImportResponse.failure(
                    java.util.List.of("请上传 .opml 或 .xml 格式的文件"));
            return ResponseEntity.badRequest().body(response);
        }

        log.info("导入 OPML：userId={}, fileName={}, size={}", 
                userId, fileName, file.getSize());

        try {
            OpmlImportResponse response = opmlService.importOpml(userId, file);
            
            if (response.isSuccess()) {
                return ResponseEntity.ok(response);
            } else {
                return ResponseEntity.badRequest().body(response);
            }
        } catch (Exception e) {
            log.error("导入 OPML 异常：userId={}, error={}", userId, e.getMessage(), e);
            OpmlImportResponse response = OpmlImportResponse.failure(
                    java.util.List.of("导入失败：" + e.getMessage()));
            return ResponseEntity.internalServerError().body(response);
        }
    }

    /**
     * 导出 OPML 文件
     *
     * @param userDetails 认证用户信息
     * @return OPML XML 文件
     */
    @Operation(summary = "导出 OPML 文件", description = "导出当前用户的所有订阅源为 OPML 文件")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "导出成功"),
        @ApiResponse(responseCode = "401", description = "未认证")
    })
    @GetMapping("/export")
    public ResponseEntity<String> exportOpml(
            @Parameter(description = "认证用户信息") @AuthenticationPrincipal UserDetails userDetails) {
        
        Integer userId = getCurrentUserId(userDetails);
        if (userId == null) {
            log.warn("导出 OPML 失败：未认证用户");
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).build();
        }

        log.info("导出 OPML：userId={}", userId);

        try {
            String opmlContent = opmlService.exportOpml(userId);
            
            // 设置响应头，触发浏览器下载
            HttpHeaders headers = new HttpHeaders();
            headers.setContentType(MediaType.APPLICATION_XML);
            headers.setContentDispositionFormData("attachment", "feeds.opml");
            
            return ResponseEntity.ok()
                    .headers(headers)
                    .body(opmlContent);
        } catch (Exception e) {
            log.error("导出 OPML 异常：userId={}, error={}", userId, e.getMessage(), e);
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).build();
        }
    }
}
