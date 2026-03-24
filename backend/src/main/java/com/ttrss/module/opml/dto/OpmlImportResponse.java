package com.ttrss.module.opml.dto;

import lombok.Data;

import java.util.List;

/**
 * OPML 导入响应数据传输对象
 */
@Data
public class OpmlImportResponse {

    /**
     * 是否成功
     */
    private boolean success;

    /**
     * 成功导入的订阅源数量
     */
    private int importedCount;

    /**
     * 失败的订阅源数量
     */
    private int failedCount;

    /**
     * 导入的分类数量
     */
    private int categoryCount;

    /**
     * 错误信息列表
     */
    private List<String> errors;

    /**
     * 消息
     */
    private String message;

    /**
     * 创建成功响应
     *
     * @param importedCount 成功导入数量
     * @param failedCount 失败数量
     * @param categoryCount 分类数量
     * @return OpmlImportResponse
     */
    public static OpmlImportResponse success(int importedCount, int failedCount, int categoryCount) {
        OpmlImportResponse response = new OpmlImportResponse();
        response.setSuccess(true);
        response.setImportedCount(importedCount);
        response.setFailedCount(failedCount);
        response.setCategoryCount(categoryCount);
        response.setMessage("OPML 导入完成");
        return response;
    }

    /**
     * 创建失败响应
     *
     * @param errors 错误信息列表
     * @return OpmlImportResponse
     */
    public static OpmlImportResponse failure(List<String> errors) {
        OpmlImportResponse response = new OpmlImportResponse();
        response.setSuccess(false);
        response.setImportedCount(0);
        response.setFailedCount(errors.size());
        response.setCategoryCount(0);
        response.setErrors(errors);
        response.setMessage("OPML 导入失败");
        return response;
    }
}
