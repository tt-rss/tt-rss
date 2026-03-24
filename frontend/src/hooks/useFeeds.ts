import { useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { feedApi } from '../services/feedApi';
import type { FeedWithUnread, Category } from '../types';
import type { FeedFormData } from '../components/feed/FeedForm';

/**
 * Query Keys
 */
export const feedQueryKeys = {
  all: ['feeds'] as const,
  lists: () => [...feedQueryKeys.all, 'list'] as const,
  list: (filters: Record<string, unknown>) =>
    [...feedQueryKeys.lists(), filters] as const,
  details: () => [...feedQueryKeys.all, 'detail'] as const,
  detail: (id: string) => [...feedQueryKeys.details(), id] as const,
  categories: ['categories'] as const,
};

/**
 * 订阅源 Hook
 *
 * 封装订阅源相关的数据获取和 mutation 操作
 * 使用 TanStack Query 进行状态管理
 */
export function useFeeds() {
  const queryClient = useQueryClient();

  /**
   * 获取订阅源列表
   */
  const {
    data: feeds = [],
    isLoading: isLoadingFeeds,
    error: feedsError,
    refetch: refetchFeeds,
  } = useQuery<FeedWithUnread[], Error>({
    queryKey: feedQueryKeys.lists(),
    queryFn: feedApi.getFeeds,
  });

  /**
   * 获取分类列表
   */
  const {
    data: categories = [],
    isLoading: isLoadingCategories,
    error: categoriesError,
  } = useQuery<Category[], Error>({
    queryKey: feedQueryKeys.categories,
    queryFn: feedApi.getCategories,
  });

  /**
   * 创建订阅源 Mutation
   */
  const createMutation = useMutation({
    mutationFn: feedApi.createFeed,
    onSuccess: () => {
      // 使订阅源列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.lists() });
      // 同时使分类列表失效（可能包含新的统计信息）
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.categories });
    },
  });

  /**
   * 更新订阅源 Mutation
   */
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<FeedFormData> }) =>
      feedApi.updateFeed(id, data),
    onSuccess: (updatedFeed) => {
      // 更新缓存中的订阅源
      queryClient.setQueryData(
        feedQueryKeys.detail(updatedFeed.id),
        updatedFeed
      );
      // 使订阅源列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.lists() });
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.categories });
    },
  });

  /**
   * 删除订阅源 Mutation
   */
  const deleteMutation = useMutation({
    mutationFn: feedApi.deleteFeed,
    onSuccess: () => {
      // 使订阅源列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.lists() });
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.categories });
    },
  });

  /**
   * 创建分类 Mutation
   */
  const createCategoryMutation = useMutation({
    mutationFn: ({ title, parentId }: { title: string; parentId?: string | null }) =>
      feedApi.createCategory(title, parentId),
    onSuccess: () => {
      // 使分类列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: feedQueryKeys.categories });
    },
  });

  /**
   * 创建订阅源
   */
  const createFeed = useCallback(
    async (data: FeedFormData) => {
      return createMutation.mutateAsync(data);
    },
    [createMutation]
  );

  /**
   * 更新订阅源
   */
  const updateFeed = useCallback(
    async (id: string, data: Partial<FeedFormData>) => {
      return updateMutation.mutateAsync({ id, data });
    },
    [updateMutation]
  );

  /**
   * 删除订阅源
   */
  const deleteFeed = useCallback(
    async (id: string) => {
      return deleteMutation.mutateAsync(id);
    },
    [deleteMutation]
  );

  /**
   * 创建分类
   */
  const createCategory = useCallback(
    async (title: string, parentId?: string | null) => {
      return createCategoryMutation.mutateAsync({ title, parentId });
    },
    [createCategoryMutation]
  );

  return {
    // 状态
    feeds,
    categories,
    isLoading: isLoadingFeeds || isLoadingCategories,
    feedsError,
    categoriesError,

    // 方法
    createFeed,
    updateFeed,
    deleteFeed,
    createCategory,
    refetchFeeds,

    // Mutation 状态
    isCreating: createMutation.isPending,
    isUpdating: updateMutation.isPending,
    isDeleting: deleteMutation.isPending,
    isCreatingCategory: createCategoryMutation.isPending,
  };
}

/**
 * 单个订阅源 Hook
 *
 * @param id 订阅源 ID
 */
export function useFeed(id: string) {
  const {
    data: feed,
    isLoading,
    error,
  } = useQuery<FeedWithUnread, Error>({
    queryKey: feedQueryKeys.detail(id),
    queryFn: () => feedApi.getFeed(id),
    enabled: !!id,
  });

  return {
    feed,
    isLoading,
    error,
  };
}
