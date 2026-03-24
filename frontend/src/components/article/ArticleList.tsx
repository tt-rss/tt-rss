/**
 * 文章列表组件
 * 
 * 展示文章列表，支持无限滚动加载、批量选择、批量操作
 */

import { useCallback, useState, useEffect, useRef } from 'react';
import {
  Stack,
  Box,
  Text,
  Loader,
  Center,
} from '@mantine/core';
import { useIntersection } from '@mantine/hooks';
import { ArticleCard } from './ArticleCard';
import { ArticleToolbar } from './ArticleToolbar';
import { useArticles } from '../../hooks/useArticles';
import type { ArticleListParams } from '../../types';

/**
 * 加载状态类型
 */
type LoadingType = 'read' | 'unread' | 'star' | 'unstar' | 'delete' | null;

/**
 * ArticleList 组件属性
 */
export interface ArticleListProps {
  /** 查询参数 */
  params?: Omit<ArticleListParams, 'page'>;
  /** 每页数量 */
  pageSize?: number;
  /** 文章点击回调 */
  onArticleClick?: (articleId: string) => void;
  /** 空列表时的提示文本 */
  emptyMessage?: string;
}

/**
 * ArticleList - 文章列表组件
 * 
 * 功能：
 * - 无限滚动加载文章
 * - 复选框批量选择
 * - 工具栏批量操作（标记已读、星标、删除）
 * - 未读文章高亮显示
 */
export function ArticleList({
  params = {},
  pageSize = 20,
  onArticleClick,
  emptyMessage = '暂无文章',
}: ArticleListProps) {
  // 选中状态
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  // 加载状态
  const [isLoading, setIsLoading] = useState(false);
  const [loadingType, setLoadingType] = useState<LoadingType>(null);

  // 使用文章 Hook
  const {
    articles,
    isLoading: isLoadingArticles,
    error,
    hasNextPage,
    isFetchingNextPage,
    loadMore,
    refetch,
    markAsStarred,
    batchMarkAsRead,
    batchMarkAsStarred,
    batchDelete,
  } = useArticles(params, pageSize);

  // 无限滚动引用
  const lastArticleRef = useRef<HTMLDivElement | null>(null);
  const { ref, entry } = useIntersection({
    threshold: 0.5,
  });

  // 监听交叉观察器，触发加载下一页
  useEffect(() => {
    if (entry?.isIntersecting && hasNextPage && !isFetchingNextPage) {
      loadMore();
    }
  }, [entry?.isIntersecting, hasNextPage, isFetchingNextPage, loadMore]);

  // 设置最后一个文章的引用
  const setLastArticleRef = useCallback(
    (node: HTMLDivElement | null) => {
      lastArticleRef.current = node;
      ref(node);
    },
    [ref]
  );

  // 处理单个文章选择变化
  const handleSelectionChange = useCallback((articleId: string, selected: boolean) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (selected) {
        next.add(articleId);
      } else {
        next.delete(articleId);
      }
      return next;
    });
  }, []);

  // 处理全选/取消全选
  const handleToggleSelectAll = useCallback(() => {
    setSelectedIds((prev) => {
      if (prev.size === articles.length) {
        return new Set();
      }
      return new Set(articles.map((a) => a.id));
    });
  }, [articles]);

  // 处理单个文章星标切换
  const handleStarToggle = useCallback(
    async (articleId: string, starred: boolean) => {
      try {
        await markAsStarred(articleId, starred);
      } catch (err) {
        console.error('标记星标失败:', err);
      }
    },
    [markAsStarred]
  );

  // 处理批量标记已读
  const handleMarkSelectedAsRead = useCallback(
    async (read: boolean) => {
      if (selectedIds.size === 0) return;

      setIsLoading(true);
      setLoadingType(read ? 'read' : 'unread');

      try {
        await batchMarkAsRead(Array.from(selectedIds), read);
        setSelectedIds(new Set());
      } catch (err) {
        console.error('批量标记已读失败:', err);
      } finally {
        setIsLoading(false);
        setLoadingType(null);
      }
    },
    [selectedIds, batchMarkAsRead]
  );

  // 处理批量标记星标
  const handleMarkSelectedAsStarred = useCallback(
    async (starred: boolean) => {
      if (selectedIds.size === 0) return;

      setIsLoading(true);
      setLoadingType(starred ? 'star' : 'unstar');

      try {
        await batchMarkAsStarred(Array.from(selectedIds), starred);
        setSelectedIds(new Set());
      } catch (err) {
        console.error('批量标记星标失败:', err);
      } finally {
        setIsLoading(false);
        setLoadingType(null);
      }
    },
    [selectedIds, batchMarkAsStarred]
  );

  // 处理批量删除
  const handleDeleteSelected = useCallback(async () => {
    if (selectedIds.size === 0) return;

    if (!window.confirm(`确定要删除选中的 ${selectedIds.size} 篇文章吗？`)) {
      return;
    }

    setIsLoading(true);
    setLoadingType('delete');

    try {
      await batchDelete(Array.from(selectedIds));
      setSelectedIds(new Set());
    } catch (err) {
      console.error('批量删除失败:', err);
    } finally {
      setIsLoading(false);
      setLoadingType(null);
    }
  }, [selectedIds, batchDelete]);

  // 处理刷新
  const handleRefresh = useCallback(async () => {
    try {
      await refetch();
    } catch (err) {
      console.error('刷新失败:', err);
    }
  }, [refetch]);

  // 判断是否全部选中
  const isAllSelected = articles.length > 0 && selectedIds.size === articles.length;

  // 渲染加载状态
  if (isLoadingArticles) {
    return (
      <Center py="xl">
        <Loader />
      </Center>
    );
  }

  // 渲染错误状态
  if (error) {
    return (
      <Center py="xl">
        <Text c="red">加载失败：{error.message}</Text>
      </Center>
    );
  }

  // 渲染空状态
  if (articles.length === 0) {
    return (
      <Center py="xl">
        <Text c="dimmed">{emptyMessage}</Text>
      </Center>
    );
  }

  return (
    <Stack gap="md">
      {/* 工具栏 */}
      <ArticleToolbar
        selectedCount={selectedIds.size}
        isAllSelected={isAllSelected}
        onToggleSelectAll={handleToggleSelectAll}
        onMarkSelectedAsRead={handleMarkSelectedAsRead}
        onMarkSelectedAsStarred={handleMarkSelectedAsStarred}
        onDeleteSelected={handleDeleteSelected}
        onRefresh={handleRefresh}
        isLoading={isLoading}
        loadingType={loadingType}
      />

      {/* 文章列表 */}
      <Stack gap="sm">
        {articles.map((article, index) => {
          const isLast = index === articles.length - 1;
          return (
            <Box
              key={article.id}
              ref={isLast ? setLastArticleRef : undefined}
            >
              <ArticleCard
                article={article}
                isSelected={selectedIds.has(article.id)}
                onSelectionChange={(selected) =>
                  handleSelectionChange(article.id, selected)
                }
                onStarToggle={(starred) =>
                  handleStarToggle(article.id, starred)
                }
                onClick={() => onArticleClick?.(article.id)}
                isLoading={isLoading}
              />
            </Box>
          );
        })}
      </Stack>

      {/* 加载更多指示器 */}
      {isFetchingNextPage && (
        <Center py="md">
          <Loader size="sm" />
        </Center>
      )}

      {/* 没有更多数据提示 */}
      {!hasNextPage && articles.length > 0 && (
        <Center py="md">
          <Text size="sm" c="dimmed">
            没有更多文章了
          </Text>
        </Center>
      )}
    </Stack>
  );
}
