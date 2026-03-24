/**
 * OPML API 服务
 * 处理 OPML 文件的导入和导出
 */

import type { OpmlImportResult } from '../types';

const API_BASE_URL = '/api';

/**
 * 处理 API 响应错误
 */
async function handleResponseError(response: Response): Promise<never> {
  let errorData: { message: string } | { error: string } = { message: '请求失败' };

  try {
    const contentType = response.headers.get('content-type');
    if (contentType?.includes('application/json')) {
      errorData = await response.json();
    }
  } catch {
    // 忽略解析错误，使用默认错误信息
  }

  const errorMessage = 'message' in errorData ? errorData.message : ('error' in errorData ? errorData.error : `HTTP error! status: ${response.status}`);
  throw new Error(errorMessage);
}

/**
 * 导入 OPML 文件
 *
 * @param file OPML 文件
 * @returns 导入结果
 * @throws 导入失败时抛出错误
 */
export async function importOpml(file: File): Promise<OpmlImportResult> {
  const formData = new FormData();
  formData.append('file', file);

  const response = await fetch(`${API_BASE_URL}/opml/import`, {
    method: 'POST',
    body: formData,
  });

  if (!response.ok) {
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 导出 OPML 文件
 *
 * @returns OPML 文件 Blob
 * @throws 导出失败时抛出错误
 */
export async function exportOpml(): Promise<Blob> {
  const response = await fetch(`${API_BASE_URL}/opml/export`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    return handleResponseError(response);
  }

  const blob = await response.blob();
  return blob;
}

export const opmlApi = {
  importOpml,
  exportOpml,
};
