package com.ttrss.module.label.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import lombok.Data;

import java.io.Serial;
import java.io.Serializable;

/**
 * 标签 DTO
 */
@Data
public class LabelDTO implements Serializable {

    @Serial
    private static final long serialVersionUID = 1L;

    /**
     * 标签 ID
     */
    private Integer id;

    /**
     * 标签名称
     */
    @NotBlank(message = "标签名称不能为空")
    @Size(max = 250, message = "标签名称长度不能超过 250 个字符")
    private String caption;

    /**
     * 前景颜色
     */
    @Size(max = 15, message = "前景颜色长度不能超过 15 个字符")
    private String fgColor;

    /**
     * 背景颜色
     */
    @Size(max = 15, message = "背景颜色长度不能超过 15 个字符")
    private String bgColor;
}
