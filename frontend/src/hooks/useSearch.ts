/**
 * 搜索数据 Hook
 *
 * 封装搜索相关的数据获取和状态管理
 */

import { useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { searchApi } from '../services/searchApi';
import type { SearchParams, SearchResponse, SearchResult } from '../types';

/**
 * 默认每页数量
 */
const DEFAULT_PAGE_SIZE = 20;

/**
 * Query Keys
 */
export const searchQueryKeys = {
  all: ['search'] as const,
  results: (params: SearchParams) => [...searchQueryKeys.all, 'results', params] as const,
};

/**
 * 搜索 Hook
 *
 * @param params 搜索参数
 * @param pageSize 每页数量
 * @param enabled 是否启用查询（用于防抖控制）
 */
export function useSearch(
  params: SearchParams = {},
  pageSize: number = DEFAULT_PAGE_SIZE,
  enabled: boolean = true
) {
  const queryClient = useQueryClient();

  const {
    data,
    isLoading,
    error,
    isFetching,
    refetch,
  } = useQuery<SearchResponse, Error>({
    queryKey: searchQueryKeys.results({ ...params, pageSize }),
    queryFn: () => searchApi.searchArticles({ ...params, pageSize }),
    enabled: enabled && !!params.query,
    staleTime: 1000 * 60 * 5, // 5 分钟内使用缓存
  });

  /**
   * 搜索结果
   */
  const results = useMemo(() => data?.results ?? [], [data]);

  /**
   * 是否有下一页
   */
  const hasNextPage = useMemo(() => data?.hasNextPage ?? false, [data]);

  /**
   * 总结果数
   */
  const total = useMemo(() => data?.total ?? 0, [data]);

  /**
   * 当前页码
   */
  const currentPage = useMemo(() => data?.page ?? 1, [data]);

  /**
   * 加载下一页
   */
  const loadMore = useCallback(async () => {
    if (!hasNextPage) return;

    const nextPage = currentPage + 1;
    await queryClient.fetchQuery({
      queryKey: searchQueryKeys.results({ ...params, page: nextPage, pageSize }),
      queryFn: () => searchApi.searchArticles({ ...params, page: nextPage, pageSize }),
    });

    // 合并结果
    queryClient.setQueryData(
      searchQueryKeys.results({ ...params, pageSize }),
      (oldData: SearchResponse | undefined) => {
        if (!oldData) return oldData;
        const newData = queryClient.getQueryData<SearchResponse>(
          searchQueryKeys.results({ ...params, page: nextPage, pageSize })
        );
        if (!newData) return oldData;

        return {
          ...oldData,
          results: [...oldData.results, ...newData.results],
          page: newData.page,
          hasNextPage: newData.hasNextPage,
        };
      }
    );
  }, [hasNextPage, currentPage, params, pageSize, queryClient]);

  /**
   * 更新单条搜索结果缓存
   */
  const updateResultInCache = useCallback(
    (id: string, updater: (result: SearchResult) => Partial<SearchResult>) => {
      queryClient.setQueriesData(
        { queryKey: searchQueryKeys.all },
        (oldData: SearchResponse | undefined) => {
          if (!oldData) return oldData;
          return {
            ...oldData,
            results: oldData.results.map((result) =>
              result.id === id
                ? { ...result, ...updater(result) }
                : result
            ),
          };
        }
      );
    },
    [queryClient]
  );

  return {
    // 数据
    results,
    data,

    // 状态
    isLoading,
    isFetching,
    error,
    hasNextPage,
    total,
    currentPage,

    // 方法
    refetch,
    loadMore,
    updateResultInCache,
  };
}

/**
 * 搜索关键词高亮工具函数
 *
 * @param text 原始文本
 * @param keyword 关键词
 * @returns 高亮后的 HTML 字符串
 */
export function highlightKeyword(text: string, keyword: string): string {
  if (!keyword || !text) return text || '';

  try {
    // 转义 HTML 特殊字符
    const escapedText = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    // 创建不区分大小写的正则表达式
    const regex = new RegExp(`(${escapeRegExp(keyword)})`, 'gi');
    return escapedText.replace(regex, '<mark class="search-highlight">$1</mark>');
  } catch {
    return text;
  }
}

/**
 * 转义正则表达式特殊字符
 */
function escapeRegExp(string: string): string {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export const searchUtils = {
  highlightKeyword,
};
