import { useState } from 'react';
import {
  MultiSelect,
  Badge,
  Group,
  ActionIcon,
  Tooltip,
  Box,
  rem,
  Text,
} from '@mantine/core';
import { IconTag, IconPlus, IconX } from '@tabler/icons-react';
import type { Label, LabelPickerProps } from '../../types';

/**
 * LabelPicker - 标签选择器组件
 *
 * 功能：
 * - 多选标签
 * - 显示已选标签的预览（颜色）
 * - 支持创建新标签（可选）
 * - 使用 Mantine MultiSelect 组件
 */
export function LabelPicker({
  value = [],
  labels = [],
  disabled = false,
  onChange,
  placeholder = '选择标签',
  allowCreate = false,
  onCreateLabel,
}: LabelPickerProps) {
  const [isCreating] = useState(false);

  // 将标签转换为 MultiSelect 的 data 格式
  const selectData = labels.map((label: Label) => ({
    value: String(label.id),
    label: label.caption,
  }));

  // 将 value 转换为字符串数组
  const stringValue = value.map(String);

  // 处理值变化
  const handleChange = (values: string[]) => {
    const numberValues = values.map(Number);
    onChange?.(numberValues);
  };

  // 渲染下拉选项
  const renderOption = ({ option }: { option: { value: string; label: string } }) => {
    const label = labels.find((l: Label) => String(l.id) === option.value);
    if (!label) {
      return <Text size="sm">{option.label}</Text>;
    }

    return (
      <Group gap="xs">
        <Box
          style={{
            width: rem(16),
            height: rem(16),
            backgroundColor: label.bgColor || '#ffffff',
            border: `1px solid ${label.fgColor || '#000000'}`,
            borderRadius: rem(2),
          }}
        />
        <Text size="sm">{label.caption}</Text>
      </Group>
    );
  };

  // 如果没有标签且不允许创建，显示空状态
  if (labels.length === 0 && !allowCreate) {
    return (
      <Box>
        <Text c="dimmed" size="sm">
          暂无可用标签
        </Text>
      </Box>
    );
  }

  return (
    <Box>
      {allowCreate && onCreateLabel && (
        <Group gap="xs" mb="sm">
          <MultiSelect
            flex={1}
            data={selectData}
            value={stringValue}
            onChange={handleChange}
            placeholder={placeholder}
            disabled={disabled || isCreating}
            renderOption={renderOption}
            clearable
            searchable
            nothingFoundMessage="没有找到标签"
            leftSection={<IconTag size={18} />}
            maxDropdownHeight={250}
          />
          <Tooltip label="创建新标签">
            <ActionIcon
              variant="filled"
              color="blue"
              onClick={() => {
                const caption = prompt('请输入新标签名称:');
                if (caption && caption.trim()) {
                  onCreateLabel(caption.trim());
                }
              }}
              disabled={disabled || isCreating}
            >
              <IconPlus size={18} />
            </ActionIcon>
          </Tooltip>
        </Group>
      )}

      {!allowCreate && (
        <MultiSelect
          data={selectData}
          value={stringValue}
          onChange={handleChange}
          placeholder={placeholder}
          disabled={disabled}
          renderOption={renderOption}
          clearable
          searchable
          nothingFoundMessage="没有找到标签"
          leftSection={<IconTag size={18} />}
          maxDropdownHeight={250}
        />
      )}

      {/* 已选标签预览 */}
      {value.length > 0 && (
        <Group gap="xs" mt="sm">
          {value.map((labelId: number) => (
            <Badge
              key={labelId}
              variant="light"
              rightSection={
                !disabled ? (
                  <ActionIcon
                    size="xs"
                    variant="transparent"
                    onClick={() => {
                      const newValue = value.filter((id: number) => id !== labelId);
                      onChange?.(newValue);
                    }}
                  >
                    <IconX size={12} />
                  </ActionIcon>
                ) : null
              }
              style={{
                color: labels.find((l: Label) => l.id === labelId)?.fgColor,
                backgroundColor: labels.find((l: Label) => l.id === labelId)?.bgColor,
              }}
            >
              {labels.find((l: Label) => l.id === labelId)?.caption || labelId}
            </Badge>
          ))}
        </Group>
      )}
    </Box>
  );
}
