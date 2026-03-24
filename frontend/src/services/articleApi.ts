/**
 * 文章 API 服务
 * 
 * 提供文章列表获取、详情获取、标记已读/星标等操作
 */

import type {
  Article,
  ArticleListParams,
  ArticleListResponse,
  BatchOperationParams,
  ArticleOperationResult,
  ApiError,
} from '../types';

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
 * 获取文章列表
 *
 * @param params 查询参数
 * @returns 文章列表响应
 * @throws 获取失败时抛出错误
 */
export async function getArticles(params: ArticleListParams = {}): Promise<ArticleListResponse> {
  const searchParams = new URLSearchParams();

  if (params.page !== undefined) searchParams.set('page', String(params.page));
  if (params.pageSize !== undefined) searchParams.set('pageSize', String(params.pageSize));
  if (params.feedId) searchParams.set('feedId', params.feedId);
  if (params.categoryId) searchParams.set('categoryId', params.categoryId);
  if (params.isRead !== undefined) searchParams.set('isRead', String(params.isRead));
  if (params.isStarred !== undefined) searchParams.set('isStarred', String(params.isStarred));
  if (params.search) searchParams.set('search', params.search);
  if (params.orderBy) searchParams.set('orderBy', params.orderBy);
  if (params.orderDirection) searchParams.set('orderDirection', params.orderDirection);

  const queryString = searchParams.toString();
  const url = queryString ? `${API_BASE_URL}/articles?${queryString}` : `${API_BASE_URL}/articles`;

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

/**
 * 获取文章详情
 *
 * @param id 文章 ID
 * @returns 文章详情
 * @throws 获取失败时抛出错误
 */
export async function getArticle(id: string): Promise<Article> {
  const response = await fetch(`${API_BASE_URL}/articles/${id}`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('文章不存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 标记文章为已读/未读
 *
 * @param id 文章 ID
 * @param read 是否已读
 * @returns 操作结果
 * @throws 操作失败时抛出错误
 */
export async function markAsRead(id: string, read: boolean): Promise<ArticleOperationResult> {
  const response = await fetch(`${API_BASE_URL}/articles/${id}/read`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ read }),
  });

  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('文章不存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 标记文章为星标/取消星标
 *
 * @param id 文章 ID
 * @param starred 是否星标
 * @returns 操作结果
 * @throws 操作失败时抛出错误
 */
export async function markAsStarred(id: string, starred: boolean): Promise<ArticleOperationResult> {
  const response = await fetch(`${API_BASE_URL}/articles/${id}/starred`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ starred }),
  });

  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('文章不存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 批量标记文章为已读/未读
 *
 * @param params 批量操作参数
 * @returns 操作结果
 * @throws 操作失败时抛出错误
 */
export async function batchMarkAsRead(params: BatchOperationParams): Promise<ArticleOperationResult> {
  const response = await fetch(`${API_BASE_URL}/articles/batch/read`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(params),
  });

  if (!response.ok) {
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 批量标记文章为星标/取消星标
 *
 * @param params 批量操作参数
 * @returns 操作结果
 * @throws 操作失败时抛出错误
 */
export async function batchMarkAsStarred(params: BatchOperationParams): Promise<ArticleOperationResult> {
  const response = await fetch(`${API_BASE_URL}/articles/batch/starred`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(params),
  });

  if (!response.ok) {
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 批量删除文章
 *
 * @param ids 文章 ID 列表
 * @returns 操作结果
 * @throws 操作失败时抛出错误
 */
export async function batchDelete(ids: string[]): Promise<ArticleOperationResult> {
  const response = await fetch(`${API_BASE_URL}/articles/batch`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ ids }),
  });

  if (!response.ok) {
    return handleResponseError(response);
  }

  return response.json();
}

export const articleApi = {
  getArticles,
  getArticle,
  markAsRead,
  markAsStarred,
  batchMarkAsRead,
  batchMarkAsStarred,
  batchDelete,
};
