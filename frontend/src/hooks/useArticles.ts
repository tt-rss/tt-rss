/**
 * 文章数据 Hook
 * 
 * 封装文章相关的数据获取和 mutation 操作
 * 使用 TanStack Query 进行状态管理，支持无限滚动
 */

import { useCallback } from 'react';
import {
  useQuery,
  useMutation,
  useQueryClient,
  useInfiniteQuery,
} from '@tanstack/react-query';
import { articleApi } from '../services/articleApi';
import type {
  Article,
  ArticleListParams,
  ArticleListResponse,
  BatchOperationParams,
} from '../types';

/**
 * 默认每页文章数量
 */
const DEFAULT_PAGE_SIZE = 20;

/**
 * Query Keys
 */
export const articleQueryKeys = {
  all: ['articles'] as const,
  lists: () => [...articleQueryKeys.all, 'list'] as const,
  list: (filters: ArticleListParams) =>
    [...articleQueryKeys.lists(), filters] as const,
  infiniteList: (filters: Omit<ArticleListParams, 'page'>) =>
    [...articleQueryKeys.lists(), 'infinite', filters] as const,
  details: () => [...articleQueryKeys.all, 'detail'] as const,
  detail: (id: string) => [...articleQueryKeys.details(), id] as const,
};

/**
 * 文章列表 Hook（无限滚动）
 * 
 * @param params 查询参数（不包含 page）
 * @param pageSize 每页数量
 */
export function useArticles(
  params: Omit<ArticleListParams, 'page'> = {},
  pageSize: number = DEFAULT_PAGE_SIZE
) {
  const queryClient = useQueryClient();

  const {
    data,
    isLoading,
    error,
    hasNextPage,
    fetchNextPage,
    isFetchingNextPage,
    refetch,
  } = useInfiniteQuery<ArticleListResponse, Error>({
    queryKey: articleQueryKeys.infiniteList(params),
    queryFn: ({ pageParam = 1 }) =>
      articleApi.getArticles({
        ...params,
        page: pageParam as number,
        pageSize,
      }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      if (lastPage.hasNextPage) {
        return lastPage.page + 1;
      }
      return undefined;
    },
  });

  /**
   * 扁平化所有文章
   */
  const articles = data?.pages.flatMap((page) => page.articles) ?? [];

  /**
   * 加载下一页
   */
  const loadMore = useCallback(() => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  /**
   * 更新单篇文章缓存
   */
  const updateArticleInCache = useCallback(
    (id: string, updater: (article: Article) => Partial<Article>) => {
      queryClient.setQueriesData(
        { queryKey: articleQueryKeys.lists() },
        (oldData: unknown) => {
          if (!oldData) return oldData;

          // 处理无限滚动数据
          if (typeof oldData === 'object' && oldData !== null && 'pages' in oldData) {
            const infiniteData = oldData as { pages: ArticleListResponse[] };
            return {
              ...infiniteData,
              pages: infiniteData.pages.map((page) => ({
                ...page,
                articles: page.articles.map((article) =>
                  article.id === id
                    ? { ...article, ...updater(article) }
                    : article
                ),
              })),
            };
          }

          // 处理普通列表数据
          if (Array.isArray(oldData)) {
            return oldData.map((article) =>
              article.id === id
                ? { ...article, ...updater(article) }
                : article
            );
          }

          return oldData;
        }
      );
    },
    [queryClient]
  );

  /**
   * 标记文章为已读/未读 Mutation
   */
  const markAsReadMutation = useMutation({
    mutationFn: ({ id, read }: { id: string; read: boolean }) =>
      articleApi.markAsRead(id, read),
    onSuccess: (_, { id, read }) => {
      updateArticleInCache(id, () => ({ isRead: read }));
    },
  });

  /**
   * 标记文章为星标/取消星标 Mutation
   */
  const markAsStarredMutation = useMutation({
    mutationFn: ({ id, starred }: { id: string; starred: boolean }) =>
      articleApi.markAsStarred(id, starred),
    onSuccess: (_, { id, starred }) => {
      updateArticleInCache(id, () => ({ isStarred: starred }));
    },
  });

  /**
   * 批量标记已读 Mutation
   */
  const batchMarkAsReadMutation = useMutation({
    mutationFn: (params: BatchOperationParams) =>
      articleApi.batchMarkAsRead(params),
    onSuccess: (result, { ids, read }) => {
      if (result.success && read !== undefined) {
        ids.forEach((id) => {
          updateArticleInCache(id, () => ({ isRead: read }));
        });
      }
    },
  });

  /**
   * 批量标记星标 Mutation
   */
  const batchMarkAsStarredMutation = useMutation({
    mutationFn: (params: BatchOperationParams) =>
      articleApi.batchMarkAsStarred(params),
    onSuccess: (result, { ids, starred }) => {
      if (result.success && starred !== undefined) {
        ids.forEach((id) => {
          updateArticleInCache(id, () => ({ isStarred: starred }));
        });
      }
    },
  });

  /**
   * 批量删除 Mutation
   */
  const batchDeleteMutation = useMutation({
    mutationFn: articleApi.batchDelete,
    onSuccess: (result, ids) => {
      if (result.success) {
        // 从缓存中移除已删除的文章
        queryClient.setQueriesData(
          { queryKey: articleQueryKeys.lists() },
          (oldData: unknown) => {
            if (!oldData) return oldData;

            if (typeof oldData === 'object' && oldData !== null && 'pages' in oldData) {
              const infiniteData = oldData as { pages: ArticleListResponse[] };
              return {
                ...infiniteData,
                pages: infiniteData.pages.map((page) => ({
                  ...page,
                  articles: page.articles.filter(
                    (article) => !ids.includes(article.id)
                  ),
                })),
              };
            }

            return oldData;
          }
        );
      }
    },
  });

  /**
   * 标记文章为已读
   */
  const markAsRead = useCallback(
    async (id: string, read: boolean = true) => {
      return markAsReadMutation.mutateAsync({ id, read });
    },
    [markAsReadMutation]
  );

  /**
   * 标记文章为星标
   */
  const markAsStarred = useCallback(
    async (id: string, starred: boolean = true) => {
      return markAsStarredMutation.mutateAsync({ id, starred });
    },
    [markAsStarredMutation]
  );

  /**
   * 批量标记已读
   */
  const batchMarkAsRead = useCallback(
    async (ids: string[], read: boolean = true) => {
      return batchMarkAsReadMutation.mutateAsync({ ids, read });
    },
    [batchMarkAsReadMutation]
  );

  /**
   * 批量标记星标
   */
  const batchMarkAsStarred = useCallback(
    async (ids: string[], starred: boolean = true) => {
      return batchMarkAsStarredMutation.mutateAsync({ ids, starred });
    },
    [batchMarkAsStarredMutation]
  );

  /**
   * 批量删除
   */
  const batchDelete = useCallback(
    async (ids: string[]) => {
      return batchDeleteMutation.mutateAsync(ids);
    },
    [batchDeleteMutation]
  );

  return {
    // 数据
    articles,
    data,

    // 状态
    isLoading,
    error,
    hasNextPage,
    isFetchingNextPage,

    // 方法
    loadMore,
    refetch,

    // 操作
    markAsRead,
    markAsStarred,
    batchMarkAsRead,
    batchMarkAsStarred,
    batchDelete,

    // Mutation 状态
    isMarkingAsRead: markAsReadMutation.isPending,
    isMarkingAsStarred: markAsStarredMutation.isPending,
    isBatchMarkingAsRead: batchMarkAsReadMutation.isPending,
    isBatchMarkingAsStarred: batchMarkAsStarredMutation.isPending,
    isBatchDeleting: batchDeleteMutation.isPending,
  };
}

/**
 * 单篇文章 Hook
 *
 * @param id 文章 ID
 */
export function useArticle(id: string) {
  const {
    data: article,
    isLoading,
    error,
  } = useQuery<Article, Error>({
    queryKey: articleQueryKeys.detail(id),
    queryFn: () => articleApi.getArticle(id),
    enabled: !!id,
  });

  return {
    article,
    isLoading,
    error,
  };
}

// 导出 articleApi 供组件直接使用
export { articleApi };
