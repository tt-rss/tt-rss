/**
 * 搜索框组件
 *
 * 支持快捷键聚焦、防抖搜索
 */

import { useEffect, useRef, useState, useCallback } from 'react';
import { TextInput, ActionIcon, Box, type TextInputProps } from '@mantine/core';
import { IconSearch, IconX, IconKeyboard } from '@tabler/icons-react';
import { useHotkeys } from '@mantine/hooks';
import { useNavigate } from 'react-router';
import { useDebouncedValue } from '@mantine/hooks';

export interface SearchBoxProps extends Omit<TextInputProps, 'value' | 'onChange'> {
  /** 初始搜索词 */
  initialValue?: string;
  /** 搜索变化回调 */
  onSearchChange?: (query: string) => void;
  /** 是否自动聚焦 */
  autoFocus?: boolean;
  /** 防抖延迟（毫秒） */
  debounceMs?: number;
  /** 是否显示快捷键提示 */
  showShortcutHint?: boolean;
}

/**
 * 搜索框组件
 */
export function SearchBox({
  initialValue = '',
  onSearchChange,
  autoFocus = false,
  debounceMs = 300,
  showShortcutHint = true,
  ...props
}: SearchBoxProps) {
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);
  const [value, setValue] = useState(initialValue);
  const [debouncedValue] = useDebouncedValue(value, debounceMs);

  // 快捷键：Ctrl/Cmd + K 聚焦搜索框
  useHotkeys([
    ['mod+K', () => {
      inputRef.current?.focus();
      inputRef.current?.select();
    }],
  ]);

  // 监听防抖后的值变化
  useEffect(() => {
    if (debouncedValue !== initialValue) {
      onSearchChange?.(debouncedValue);
    }
  }, [debouncedValue, initialValue, onSearchChange]);

  // 处理清除
  const handleClear = useCallback(() => {
    setValue('');
    inputRef.current?.focus();
  }, []);

  // 处理提交
  const handleSubmit = useCallback((event: React.FormEvent) => {
    event.preventDefault();
    if (value.trim()) {
      navigate(`/search?q=${encodeURIComponent(value.trim())}`);
    }
  }, [value, navigate]);

  // 处理输入变化
  const handleChange = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    setValue(event.currentTarget.value);
  }, []);

  // 处理聚焦
  const handleFocus = useCallback(() => {
    // 如果已有搜索词，跳转到搜索页面
    if (value.trim()) {
      navigate(`/search?q=${encodeURIComponent(value.trim())}`);
    }
  }, [value, navigate]);

  return (
    <Box component="form" onSubmit={handleSubmit}>
      <TextInput
        ref={inputRef}
        placeholder="搜索文章... (Ctrl+K)"
        value={value}
        onChange={handleChange}
        onFocus={handleFocus}
        leftSection={<IconSearch size={18} stroke={1.5} />}
        rightSection={
          value ? (
            <ActionIcon
              size="sm"
              variant="transparent"
              color="gray"
              onClick={handleClear}
              aria-label="清除搜索"
            >
              <IconX size={18} stroke={1.5} />
            </ActionIcon>
          ) : showShortcutHint ? (
            <IconKeyboard size={18} stroke={1.5} style={{ opacity: 0.5 }} />
          ) : null
        }
        size="md"
        radius="md"
        {...props}
      />
    </Box>
  );
}
