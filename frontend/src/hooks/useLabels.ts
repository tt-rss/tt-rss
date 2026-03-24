import { useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { labelApi } from '../services/labelApi';
import type { Label } from '../types';
import type { LabelFormData } from '../types';

/**
 * Query Keys
 */
export const labelQueryKeys = {
  all: ['labels'] as const,
  lists: () => [...labelQueryKeys.all, 'list'] as const,
  details: () => [...labelQueryKeys.all, 'detail'] as const,
  detail: (id: number) => [...labelQueryKeys.details(), id] as const,
  articles: ['article-labels'] as const,
  article: (articleId: number) => [...labelQueryKeys.articles, articleId] as const,
};

/**
 * 标签 Hook
 *
 * 封装标签相关的数据获取和 mutation 操作
 * 使用 TanStack Query 进行状态管理
 */
export function useLabels() {
  const queryClient = useQueryClient();

  /**
   * 获取标签列表
   */
  const {
    data: labels = [],
    isLoading: isLoadingLabels,
    error: labelsError,
    refetch: refetchLabels,
  } = useQuery<Label[], Error>({
    queryKey: labelQueryKeys.lists(),
    queryFn: labelApi.getLabels,
  });

  /**
   * 创建标签 Mutation
   */
  const createMutation = useMutation({
    mutationFn: labelApi.createLabel,
    onSuccess: () => {
      // 使标签列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: labelQueryKeys.lists() });
    },
  });

  /**
   * 更新标签 Mutation
   */
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<LabelFormData> }) =>
      labelApi.updateLabel(id, data),
    onSuccess: (updatedLabel) => {
      // 更新缓存中的标签
      queryClient.setQueryData(
        labelQueryKeys.detail(updatedLabel.id),
        updatedLabel
      );
      // 使标签列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: labelQueryKeys.lists() });
    },
  });

  /**
   * 删除标签 Mutation
   */
  const deleteMutation = useMutation({
    mutationFn: labelApi.deleteLabel,
    onSuccess: () => {
      // 使标签列表失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: labelQueryKeys.lists() });
    },
  });

  /**
   * 创建标签
   */
  const createLabel = useCallback(
    async (data: LabelFormData) => {
      return createMutation.mutateAsync(data);
    },
    [createMutation]
  );

  /**
   * 更新标签
   */
  const updateLabel = useCallback(
    async (id: number, data: Partial<LabelFormData>) => {
      return updateMutation.mutateAsync({ id, data });
    },
    [updateMutation]
  );

  /**
   * 删除标签
   */
  const deleteLabel = useCallback(
    async (id: number) => {
      return deleteMutation.mutateAsync(id);
    },
    [deleteMutation]
  );

  return {
    // 状态
    labels,
    isLoading: isLoadingLabels,
    labelsError,

    // 方法
    createLabel,
    updateLabel,
    deleteLabel,
    refetchLabels,

    // Mutation 状态
    isCreating: createMutation.isPending,
    isUpdating: updateMutation.isPending,
    isDeleting: deleteMutation.isPending,
  };
}

/**
 * 单个标签 Hook
 *
 * @param id 标签 ID
 */
export function useLabel(id: number) {
  const {
    data: label,
    isLoading,
    error,
  } = useQuery<Label, Error>({
    queryKey: labelQueryKeys.detail(id),
    queryFn: () => labelApi.getLabel(id),
    enabled: !!id,
  });

  return {
    label,
    isLoading,
    error,
  };
}

/**
 * 文章标签 Hook
 *
 * @param articleId 文章 ID
 */
export function useArticleLabels(articleId: number) {
  const queryClient = useQueryClient();

  const {
    data: labels = [],
    isLoading,
    error,
    refetch,
  } = useQuery<Label[], Error>({
    queryKey: labelQueryKeys.article(articleId),
    queryFn: () => labelApi.getLabelsByArticleId(articleId),
    enabled: !!articleId,
  });

  /**
   * 添加标签 Mutation
   */
  const addMutation = useMutation({
    mutationFn: ({ labelIds }: { labelIds: number[] }) =>
      labelApi.addLabelsToArticle(articleId, labelIds),
    onSuccess: () => {
      // 使文章标签失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: labelQueryKeys.article(articleId) });
    },
  });

  /**
   * 移除标签 Mutation
   */
  const removeMutation = useMutation({
    mutationFn: (labelId: number) =>
      labelApi.removeLabelFromArticle(articleId, labelId),
    onSuccess: () => {
      // 使文章标签失效，触发重新获取
      queryClient.invalidateQueries({ queryKey: labelQueryKeys.article(articleId) });
    },
  });

  /**
   * 添加标签
   */
  const addLabels = useCallback(
    async (labelIds: number[]) => {
      return addMutation.mutateAsync({ labelIds });
    },
    [addMutation]
  );

  /**
   * 移除标签
   */
  const removeLabel = useCallback(
    async (labelId: number) => {
      return removeMutation.mutateAsync(labelId);
    },
    [removeMutation]
  );

  /**
   * 设置标签（替换所有标签）
   */
  const setLabels = useCallback(
    async (labelIds: number[]) => {
      return addMutation.mutateAsync({ labelIds });
    },
    [addMutation]
  );

  return {
    // 状态
    labels,
    isLoading,
    error,

    // 方法
    addLabels,
    removeLabel,
    setLabels,
    refetch,

    // Mutation 状态
    isAdding: addMutation.isPending,
    isRemoving: removeMutation.isPending,
  };
}
