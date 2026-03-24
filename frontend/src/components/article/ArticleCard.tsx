/**
 * 文章卡片组件
 * 
 * 展示单篇文章的信息，包括标题、来源、时间、摘要等
 * 支持复选框选择、未读/星标状态显示
 */

import { useState } from 'react';
import {
  Paper,
  Group,
  Text,
  Checkbox,
  ActionIcon,
  Avatar,
  Stack,
  ThemeIcon,
  Tooltip,
  useMantineTheme,
} from '@mantine/core';
import {
  IconStar,
  IconStarFilled,
  IconRss,
  IconLink,
} from '@tabler/icons-react';
import { formatRelativeTime } from '../../utils';
import type { Article } from '../../types';

/**
 * ArticleCard 组件属性
 */
export interface ArticleCardProps {
  /** 文章数据 */
  article: Article;
  /** 是否被选中（用于批量操作） */
  isSelected?: boolean;
  /** 选择状态变化回调 */
  onSelectionChange?: (selected: boolean) => void;
  /** 星标状态变化回调 */
  onStarToggle?: (starred: boolean) => void;
  /** 点击文章卡片回调 */
  onClick?: () => void;
  /** 是否正在加载操作 */
  isLoading?: boolean;
}

/**
 * ArticleCard - 文章卡片组件
 * 
 * 功能：
 * - 显示文章标题、摘要、来源、时间
 * - 复选框选择（用于批量操作）
 * - 星标按钮
 * - 未读文章加粗显示
 */
export function ArticleCard({
  article,
  isSelected = false,
  onSelectionChange,
  onStarToggle,
  onClick,
  isLoading = false,
}: ArticleCardProps) {
  const theme = useMantineTheme();
  const [isHovered, setIsHovered] = useState(false);

  // 格式化相对时间
  const formattedDate = formatRelativeTime(article.publishedAt);

  // 处理复选框变化
  const handleCheckboxChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    onSelectionChange?.(event.currentTarget.checked);
    event.stopPropagation();
  };

  // 处理星标点击
  const handleStarClick = (event: React.MouseEvent) => {
    event.stopPropagation();
    onStarToggle?.(!article.isStarred);
  };

  // 处理卡片点击
  const handleCardClick = () => {
    onClick?.();
  };

  // 处理链接点击
  const handleLinkClick = (event: React.MouseEvent) => {
    event.stopPropagation();
    // 在新标签页打开链接
    window.open(article.link, '_blank', 'noopener,noreferrer');
  };

  return (
    <Paper
      withBorder
      radius="md"
      p="md"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      onClick={handleCardClick}
      style={{
        cursor: 'pointer',
        transition: 'background-color 150ms ease',
        backgroundColor: isSelected
          ? theme.colors.blue[0]
          : isHovered
          ? theme.colors.gray[0]
          : undefined,
      }}
    >
      <Group gap="sm" align="flex-start" wrap="nowrap">
        {/* 复选框 */}
        <Checkbox
          checked={isSelected}
          onChange={handleCheckboxChange}
          onClick={(e) => e.stopPropagation()}
          mt={3}
          disabled={isLoading}
        />

        {/* 来源图标 */}
        {article.feedFaviconUrl ? (
          <Avatar
            src={article.feedFaviconUrl}
            alt={article.feedTitle}
            size="sm"
            radius="sm"
            mt={3}
          />
        ) : (
          <ThemeIcon
            variant="light"
            size="sm"
            radius="sm"
            mt={3}
            color="gray"
          >
            <IconRss size={14} />
          </ThemeIcon>
        )}

        {/* 文章内容 */}
        <Stack gap="xs" flex={1} miw={0}>
          {/* 标题行 */}
          <Group gap="xs" wrap="nowrap" justify="space-between">
            <Text
              fw={article.isRead ? 400 : 700}
              lineClamp={2}
              c={article.isRead ? 'dimmed' : 'dark'}
            >
              {article.title}
            </Text>

            {/* 操作按钮 */}
            <Group gap="xs" wrap="nowrap">
              {/* 星标按钮 */}
              <Tooltip
                label={article.isStarred ? '取消星标' : '添加星标'}
                withArrow
              >
                <ActionIcon
                  variant={article.isStarred ? 'light' : 'subtle'}
                  color={article.isStarred ? 'yellow' : 'gray'}
                  size="sm"
                  onClick={handleStarClick}
                  loading={isLoading}
                >
                  {article.isStarred ? (
                    <IconStarFilled size={16} />
                  ) : (
                    <IconStar size={16} />
                  )}
                </ActionIcon>
              </Tooltip>

              {/* 打开链接按钮 */}
              <Tooltip label="在新标签页打开" withArrow>
                <ActionIcon
                  variant="subtle"
                  color="blue"
                  size="sm"
                  onClick={handleLinkClick}
                >
                  <IconLink size={16} />
                </ActionIcon>
              </Tooltip>
            </Group>
          </Group>

          {/* 来源和时间 */}
          <Group gap="xs" wrap="wrap">
            {article.feedTitle && (
              <Text size="sm" c="dimmed">
                {article.feedTitle}
              </Text>
            )}
            <Text size="sm" c="dimmed">
              ·
            </Text>
            <Text size="sm" c="dimmed">
              {formattedDate}
            </Text>
            {article.author && (
              <>
                <Text size="sm" c="dimmed">
                  ·
                </Text>
                <Text size="sm" c="dimmed">
                  {article.author}
                </Text>
              </>
            )}
          </Group>

          {/* 摘要 */}
          {article.excerpt && (
            <Text size="sm" c="dimmed" lineClamp={2}>
              {article.excerpt}
            </Text>
          )}

          {/* 标签 */}
          {article.tags && article.tags.length > 0 && (
            <Group gap="xs" wrap="wrap">
              {article.tags.slice(0, 5).map((tag) => (
                <Text
                  key={tag}
                  size="xs"
                  c="blue"
                  bg="blue.0"
                  px="xs"
                  py={2}
                  style={{
                    borderRadius: theme.radius.sm,
                  }}
                >
                  #{tag}
                </Text>
              ))}
            </Group>
          )}
        </Stack>
      </Group>
    </Paper>
  );
}
