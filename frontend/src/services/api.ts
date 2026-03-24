/**
 * API 服务基础配置
 */

const API_BASE_URL = '/api';

export async function fetchApi<T>(
  endpoint: string,
  options?: RequestInit
): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options?.headers,
    },
  });

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  return response.json();
}

export const api = {
  // 示例 API 方法
  getFeeds: () => fetchApi<unknown[]>('/feeds'),
  getFeedItems: (feedId: string) => fetchApi<unknown[]>(`/feeds/${feedId}/items`),
};
