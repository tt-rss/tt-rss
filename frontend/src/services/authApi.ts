/**
 * 认证 API 服务
 */

import type { LoginCredentials, LoginResponse, RefreshTokenResponse, User, ApiError } from '../types';

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
 * 用户登录
 *
 * @param credentials 登录凭证（用户名和密码）
 * @returns 登录响应（包含 Token 和用户信息）
 * @throws 登录失败时抛出错误
 */
export async function login(credentials: LoginCredentials): Promise<LoginResponse> {
  const response = await fetch(`${API_BASE_URL}/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    // 将前端 username 字段映射为后端期望的 login 字段
    body: JSON.stringify({
      login: credentials.username,
      password: credentials.password,
    }),
  });

  if (!response.ok) {
    if (response.status === 400) {
      throw new Error('用户名或密码错误');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 用户登出
 * 
 * @param accessToken 访问令牌（可选）
 * @returns void
 */
export async function logout(accessToken?: string): Promise<void> {
  try {
    await fetch(`${API_BASE_URL}/auth/logout`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
      },
    });
  } catch {
    // 登出失败也继续执行本地清理
  }
}

/**
 * 刷新 Token
 * 
 * @param refreshToken 刷新令牌
 * @returns 新的 Token 响应
 * @throws 刷新失败时抛出错误
 */
export async function refreshToken(refreshToken: string): Promise<RefreshTokenResponse> {
  const response = await fetch(`${API_BASE_URL}/auth/refresh`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ refreshToken }),
  });

  if (!response.ok) {
    if (response.status === 400) {
      throw new Error('Refresh Token 无效或已过期');
    }
    return handleResponseError(response);
  }

  return response.json();
}

/**
 * 获取当前用户信息
 * 
 * @param accessToken 访问令牌
 * @returns 用户信息
 * @throws 获取失败时抛出错误
 */
export async function getCurrentUser(accessToken: string): Promise<User> {
  const response = await fetch(`${API_BASE_URL}/auth/me`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${accessToken}`,
    },
  });

  if (!response.ok) {
    if (response.status === 401) {
      throw new Error('未认证或 Token 已过期');
    }
    if (response.status === 404) {
      throw new Error('用户不存在');
    }
    return handleResponseError(response);
  }

  return response.json();
}

export const authApi = {
  login,
  logout,
  refreshToken,
  getCurrentUser,
};
