/**
 * 搜索 API 服务
 *
 * 提供文章搜索、关键词高亮等功能
 */

import type { SearchParams, SearchResponse, ApiError } from '../types';

const API_BASE_URL = '/api';

/**
 * 处理 API 响应错误
 */
async function handleResponseError(response: Response): Promise<never> {
  let errorData: ApiError | { message: string } = { message: '请求失败' };

  try {
    const contentType = response.headers.get('content-type');
    if (contentType?.includes('application/json')) {
      errorData = await response.json();
    }
  } catch {
    // 忽略解析错误，使用默认错误信息
  }

  const errorMessage = 'message' in errorData ? errorData.message : `HTTP error! status: ${response.status}`;
  throw new Error(errorMessage);
}

/**
 * 搜索文章
 *
 * @param params 搜索参数
 * @returns 搜索结果响应
 * @throws 搜索失败时抛出错误
 */
export async function searchArticles(params: SearchParams = {}): Promise<SearchResponse> {
  const searchParams = new URLSearchParams();

  if (params.query) searchParams.set('query', params.query);
  if (params.feedId) searchParams.set('feedId', params.feedId);
  if (params.categoryId) searchParams.set('categoryId', params.categoryId);
  if (params.page !== undefined) searchParams.set('page', String(params.page));
  if (params.pageSize !== undefined) searchParams.set('pageSize', String(params.pageSize));

  const queryString = searchParams.toString();
  const url = queryString ? `${API_BASE_URL}/search?${queryString}` : `${API_BASE_URL}/search`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    return handleResponseError(response);
  }

  return response.json();
}

export const searchApi = {
  searchArticles,
};
