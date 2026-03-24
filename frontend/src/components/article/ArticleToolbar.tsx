/**
 * 文章工具栏组件
 * 
 * 提供批量操作按钮：标记已读、标记星标、删除等
 */

import {
  Group,
  ActionIcon,
  Text,
  Tooltip,
  Box,
  Divider,
  useMantineTheme,
} from '@mantine/core';
import {
  IconCheck,
  IconEye,
  IconEyeOff,
  IconStar,
  IconStarFilled,
  IconTrash,
  IconRefresh,
} from '@tabler/icons-react';

/**
 * ArticleToolbar 组件属性
 */
export interface ArticleToolbarProps {
  /** 选中的文章数量 */
  selectedCount: number;
  /** 是否全部选中 */
  isAllSelected?: boolean;
  /** 全选/取消全选回调 */
  onToggleSelectAll?: () => void;
  /** 标记选中文章为已读回调 */
  onMarkSelectedAsRead?: (read: boolean) => void;
  /** 标记选中文章为星标回调 */
  onMarkSelectedAsStarred?: (starred: boolean) => void;
  /** 删除选中文章回调 */
  onDeleteSelected?: () => void;
  /** 刷新列表回调 */
  onRefresh?: () => void;
  /** 是否正在加载操作 */
  isLoading?: boolean;
  /** 当前操作类型 */
  loadingType?: 'read' | 'unread' | 'star' | 'unstar' | 'delete' | null;
}

/**
 * ArticleToolbar - 文章工具栏组件
 * 
 * 功能：
 * - 显示选中数量
 * - 全选/取消全选
 * - 批量标记已读/未读
 * - 批量标记星标/取消星标
 * - 批量删除
 * - 刷新列表
 */
export function ArticleToolbar({
  selectedCount,
  isAllSelected = false,
  onToggleSelectAll,
  onMarkSelectedAsRead,
  onMarkSelectedAsStarred,
  onDeleteSelected,
  onRefresh,
  isLoading = false,
  loadingType = null,
}: ArticleToolbarProps) {
  const theme = useMantineTheme();
  const hasSelection = selectedCount > 0;

  return (
    <Box
      style={{
        backgroundColor: theme.colors.gray[0],
        padding: theme.spacing.sm,
        borderRadius: theme.radius.md,
      }}
    >
      <Group justify="space-between" wrap="nowrap">
        {/* 左侧：选择和刷新 */}
        <Group gap="sm" wrap="nowrap">
          {/* 全选按钮 */}
          <Tooltip label={isAllSelected ? '取消全选' : '全选'} withArrow>
            <ActionIcon
              variant={isAllSelected ? 'filled' : 'outline'}
              color="blue"
              size="lg"
              onClick={onToggleSelectAll}
              disabled={isLoading}
            >
              {isAllSelected ? (
                <IconCheck size={18} />
              ) : (
                <IconCheck size={18} stroke={1.5} />
              )}
            </ActionIcon>
          </Tooltip>

          {/* 刷新按钮 */}
          <Tooltip label="刷新列表" withArrow>
            <ActionIcon
              variant="outline"
              color="gray"
              size="lg"
              onClick={onRefresh}
              loading={isLoading && loadingType === null}
            >
              <IconRefresh size={18} />
            </ActionIcon>
          </Tooltip>

          {/* 选中数量提示 */}
          {hasSelection && (
            <Text fw={500} size="sm">
              已选择 {selectedCount} 篇文章
            </Text>
          )}
        </Group>

        {/* 右侧：批量操作 */}
        {hasSelection ? (
          <Group gap="sm" wrap="nowrap">
            <Divider orientation="vertical" />

            {/* 标记已读 */}
            <Tooltip label="标记为已读" withArrow>
              <ActionIcon
                variant="light"
                color="green"
                size="lg"
                onClick={() => onMarkSelectedAsRead?.(true)}
                loading={isLoading && loadingType === 'read'}
              >
                <IconEye size={18} />
              </ActionIcon>
            </Tooltip>

            {/* 标记未读 */}
            <Tooltip label="标记为未读" withArrow>
              <ActionIcon
                variant="light"
                color="blue"
                size="lg"
                onClick={() => onMarkSelectedAsRead?.(false)}
                loading={isLoading && loadingType === 'unread'}
              >
                <IconEyeOff size={18} />
              </ActionIcon>
            </Tooltip>

            <Divider orientation="vertical" />

            {/* 标记星标 */}
            <Tooltip label="添加星标" withArrow>
              <ActionIcon
                variant="light"
                color="yellow"
                size="lg"
                onClick={() => onMarkSelectedAsStarred?.(true)}
                loading={isLoading && loadingType === 'star'}
              >
                <IconStar size={18} />
              </ActionIcon>
            </Tooltip>

            {/* 取消星标 */}
            <Tooltip label="取消星标" withArrow>
              <ActionIcon
                variant="light"
                color="gray"
                size="lg"
                onClick={() => onMarkSelectedAsStarred?.(false)}
                loading={isLoading && loadingType === 'unstar'}
              >
                <IconStarFilled size={18} />
              </ActionIcon>
            </Tooltip>

            <Divider orientation="vertical" />

            {/* 删除 */}
            <Tooltip label="删除选中" withArrow>
              <ActionIcon
                variant="light"
                color="red"
                size="lg"
                onClick={onDeleteSelected}
                loading={isLoading && loadingType === 'delete'}
              >
                <IconTrash size={18} />
              </ActionIcon>
            </Tooltip>
          </Group>
        ) : (
          <Text size="sm" c="dimmed">
            选择文章以进行批量操作
          </Text>
        )}
      </Group>
    </Box>
  );
}
