/**
 * 文章详情查看组件
 *
 * 展示文章完整内容，支持标记已读/星标、打开原文链接等操作
 */

import { useCallback } from 'react';
import {
  Paper,
  Group,
  Text,
  ActionIcon,
  Avatar,
  Stack,
  ThemeIcon,
  Tooltip,
  Button,
  Divider,
  Box,
  useMantineTheme,
  LoadingOverlay,
} from '@mantine/core';
import {
  IconStar,
  IconStarFilled,
  IconRss,
  IconLink,
  IconCheck,
  IconEyeOff,
} from '@tabler/icons-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { articleApi, articleQueryKeys } from '../../hooks/useArticles';
import { formatDate } from '../../utils';
import { sanitizeHTML, createSafeHTMLProps } from '../../utils/html';
import type { Article } from '../../types';

/**
 * ArticleView 组件属性
 */
export interface ArticleViewProps {
  /** 文章数据 */
  article: Article;
  /** 是否正在加载文章详情 */
  isLoading?: boolean;
  /** 文章详情加载失败 */
  error?: Error | null;
}

/**
 * ArticleView - 文章详情查看组件
 *
 * 功能：
 * - 展示文章标题、作者、发布时间、来源
 * - 渲染 HTML 内容（经过 XSS 清理）
 * - 标记已读/未读
 * - 标记星标/取消星标
 * - 打开原文链接
 */
export function ArticleView({
  article,
  isLoading = false,
  error = null,
}: ArticleViewProps) {
  const theme = useMantineTheme();
  const queryClient = useQueryClient();

  /**
   * 标记已读/未读 Mutation
   */
  const markAsReadMutation = useMutation({
    mutationFn: ({ id, read }: { id: string; read: boolean }) =>
      articleApi.markAsRead(id, read),
    onSuccess: (_, { id, read }) => {
      // 更新文章详情缓存
      queryClient.setQueryData(articleQueryKeys.detail(id), (oldData: unknown) => {
        if (!oldData) return oldData;
        return { ...(oldData as Article), isRead: read };
      });

      // 更新列表缓存
      queryClient.setQueriesData(
        { queryKey: articleQueryKeys.lists() },
        (oldData: unknown) => {
          if (!oldData) return oldData;

          if (typeof oldData === 'object' && oldData !== null && 'pages' in oldData) {
            const infiniteData = oldData as { pages: Array<{ articles: Article[] }> };
            return {
              ...infiniteData,
              pages: infiniteData.pages.map((page) => ({
                ...page,
                articles: page.articles.map((a) =>
                  a.id === id ? { ...a, isRead: read } : a
                ),
              })),
            };
          }

          return oldData;
        }
      );
    },
  });

  /**
   * 标记星标/取消星标 Mutation
   */
  const markAsStarredMutation = useMutation({
    mutationFn: ({ id, starred }: { id: string; starred: boolean }) =>
      articleApi.markAsStarred(id, starred),
    onSuccess: (_, { id, starred }) => {
      // 更新文章详情缓存
      queryClient.setQueryData(articleQueryKeys.detail(id), (oldData: unknown) => {
        if (!oldData) return oldData;
        return { ...(oldData as Article), isStarred: starred };
      });

      // 更新列表缓存
      queryClient.setQueriesData(
        { queryKey: articleQueryKeys.lists() },
        (oldData: unknown) => {
          if (!oldData) return oldData;

          if (typeof oldData === 'object' && oldData !== null && 'pages' in oldData) {
            const infiniteData = oldData as { pages: Array<{ articles: Article[] }> };
            return {
              ...infiniteData,
              pages: infiniteData.pages.map((page) => ({
                ...page,
                articles: page.articles.map((a) =>
                  a.id === id ? { ...a, isStarred: starred } : a
                ),
              })),
            };
          }

          return oldData;
        }
      );
    },
  });

  /**
   * 处理标记已读/未读
   */
  const handleMarkAsRead = useCallback(() => {
    markAsReadMutation.mutate({ id: article.id, read: !article.isRead });
  }, [article.id, article.isRead, markAsReadMutation]);

  /**
   * 处理标记星标/取消星标
   */
  const handleMarkAsStarred = useCallback(() => {
    markAsStarredMutation.mutate({ id: article.id, starred: !article.isStarred });
  }, [article.id, article.isStarred, markAsStarredMutation]);

  /**
   * 处理打开原文链接
   */
  const handleOpenLink = useCallback(() => {
    window.open(article.link, '_blank', 'noopener,noreferrer');
  }, [article.link]);

  // 渲染加载状态
  if (isLoading) {
    return (
      <Paper withBorder radius="md" p="lg" style={{ position: 'relative', minHeight: 400 }}>
        <LoadingOverlay visible />
      </Paper>
    );
  }

  // 渲染错误状态
  if (error) {
    return (
      <Paper withBorder radius="md" p="lg">
        <Stack gap="sm" align="center" justify="center" style={{ minHeight: 200 }}>
          <Text c="red">加载文章失败：{error.message}</Text>
        </Stack>
      </Paper>
    );
  }

  // 格式化日期
  const formattedDate = formatDate(article.publishedAt);

  // 清理后的 HTML 内容
  const sanitizedContent = article.content ? sanitizeHTML(article.content) : '';

  return (
    <Paper withBorder radius="md" p="lg">
      <Stack gap="md">
        {/* 标题 */}
        <Text size="xl" fw={700} lh={1.3}>
          {article.title}
        </Text>

        {/* 元信息行 */}
        <Group gap="sm" wrap="wrap">
          {/* 来源图标 */}
          {article.feedFaviconUrl ? (
            <Avatar
              src={article.feedFaviconUrl}
              alt={article.feedTitle}
              size="sm"
              radius="sm"
            />
          ) : (
            <ThemeIcon variant="light" size="sm" radius="sm" color="gray">
              <IconRss size={14} />
            </ThemeIcon>
          )}

          {/* 来源名称 */}
          {article.feedTitle && (
            <Text size="sm" c="dimmed">
              {article.feedTitle}
            </Text>
          )}

          {/* 分隔符 */}
          <Text size="sm" c="dimmed">
            ·
          </Text>

          {/* 发布时间 */}
          <Text size="sm" c="dimmed">
            {formattedDate}
          </Text>

          {/* 作者 */}
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

        {/* 操作按钮栏 */}
        <Group gap="xs" wrap="wrap">
          {/* 标记已读/未读按钮 */}
          <Tooltip
            label={article.isRead ? '标记为未读' : '标记为已读'}
            withArrow
          >
            <Button
              variant={article.isRead ? 'outline' : 'filled'}
              color={article.isRead ? 'gray' : 'blue'}
              leftSection={article.isRead ? <IconEyeOff size={16} /> : <IconCheck size={16} />}
              onClick={handleMarkAsRead}
              loading={markAsReadMutation.isPending}
              size="sm"
            >
              {article.isRead ? '已读' : '未读'}
            </Button>
          </Tooltip>

          {/* 标记星标/取消星标按钮 */}
          <Tooltip
            label={article.isStarred ? '取消星标' : '添加星标'}
            withArrow
          >
            <ActionIcon
              variant={article.isStarred ? 'light' : 'outline'}
              color={article.isStarred ? 'yellow' : 'gray'}
              size="lg"
              onClick={handleMarkAsStarred}
              loading={markAsStarredMutation.isPending}
            >
              {article.isStarred ? <IconStarFilled size={20} /> : <IconStar size={20} />}
            </ActionIcon>
          </Tooltip>

          {/* 打开原文链接按钮 */}
          <Tooltip label="在新标签页打开原文" withArrow>
            <ActionIcon
              variant="outline"
              color="blue"
              size="lg"
              onClick={handleOpenLink}
            >
              <IconLink size={20} />
            </ActionIcon>
          </Tooltip>
        </Group>

        {/* 分隔线 */}
        <Divider />

        {/* 文章内容区域 */}
        <Box
          className="article-content"
          style={{
            fontSize: theme.other.fontSizes?.md || '1rem',
            lineHeight: 1.6,
            color: theme.colors.gray[7],
          }}
          dangerouslySetInnerHTML={createSafeHTMLProps(sanitizedContent)}
        />
      </Stack>
    </Paper>
  );
}
