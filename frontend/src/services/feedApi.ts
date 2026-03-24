/**
 * 订阅源 API 服务
 */

import type { FeedWithUnread, Category, ApiError } from '../types';
import type { FeedFormData } from '../components/feed/FeedForm';

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
 * 获取订阅源列表
 *
 * @returns 订阅源列表
 * @throws 获取失败时抛出错误
 */
export async function getFeeds(): Promise<FeedWithUnread[]> {
  const response = await fetch(`${API_BASE_URL}/feeds`, {
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
 * 获取单个订阅源
 *
 * @param id 订阅源 ID
 * @returns 订阅源详情
 * @throws 获取失败时抛出错误
 */
export async function getFeed(id: string): Promise<FeedWithUnread> {
  const response = await fetch(`${API_BASE_URL}/feeds/${id}`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('订阅源不存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 创建订阅源
 *
 * @param data 订阅源数据
 * @returns 创建的订阅源
 * @throws 创建失败时抛出错误
 */
export async function createFeed(data: FeedFormData): Promise<FeedWithUnread> {
  const response = await fetch(`${API_BASE_URL}/feeds`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    if (response.status === 400) {
      throw new Error('订阅源数据无效');
    }
    if (response.status === 409) {
      throw new Error('该订阅源已存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 更新订阅源
 *
 * @param id 订阅源 ID
 * @param data 更新的订阅源数据
 * @returns 更新后的订阅源
 * @throws 更新失败时抛出错误
 */
export async function updateFeed(
  id: string,
  data: Partial<FeedFormData>
): Promise<FeedWithUnread> {
  const response = await fetch(`${API_BASE_URL}/feeds/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('订阅源不存在');
    }
    if (response.status === 400) {
      throw new Error('订阅源数据无效');
    }
    if (response.status === 409) {
      throw new Error('该订阅源已存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 删除订阅源
 *
 * @param id 订阅源 ID
 * @returns void
 * @throws 删除失败时抛出错误
 */
export async function deleteFeed(id: string): Promise<void> {
  const response = await fetch(`${API_BASE_URL}/feeds/${id}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    if (response.status === 404) {
      throw new Error('订阅源不存在');
    }
    return handleResponseError(response);
  }
}

/**
 * 获取分类列表
 *
 * @returns 分类列表
 * @throws 获取失败时抛出错误
 */
export async function getCategories(): Promise<Category[]> {
  const response = await fetch(`${API_BASE_URL}/categories`, {
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
 * 创建分类
 *
 * @param title 分类标题
 * @param parentId 父分类 ID（可选）
 * @returns 创建的分类
 * @throws 创建失败时抛出错误
 */
export async function createCategory(
  title: string,
  parentId?: string | null
): Promise<Category> {
  const response = await fetch(`${API_BASE_URL}/categories`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ title, parentId }),
  });

  if (!response.ok) {
    if (response.status === 400) {
      throw new Error('分类数据无效');
    }
    if (response.status === 409) {
      throw new Error('该分类已存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

export const feedApi = {
  getFeeds,
  getFeed,
  createFeed,
  updateFeed,
  deleteFeed,
  getCategories,
  createCategory,
};
