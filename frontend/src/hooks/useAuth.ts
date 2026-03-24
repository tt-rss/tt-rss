import { useCallback } from 'react';
import { useAuthStore } from '../stores/authStore';
import { authApi } from '../services/authApi';
import type { LoginCredentials, User } from '../types';

/**
 * 认证 Hook
 * 封装 authStore 和 authApi，提供便捷的认证方法
 */
export function useAuth() {
  const { user, accessToken, refreshToken, isAuthenticated, login, logout, setTokens, clearTokens } = useAuthStore();

  /**
   * 执行登录
   */
  const handleLogin = useCallback(async (credentials: LoginCredentials) => {
    const response = await authApi.login(credentials);
    // 从响应中构建用户对象
    const user: User = {
      id: response.userId,
      username: response.username,
      email: response.email,
    };
    login(user, response.accessToken, response.refreshToken);
    return response;
  }, [login]);

  /**
   * 执行登出
   */
  const handleLogout = useCallback(async () => {
    await authApi.logout(accessToken ?? undefined);
    logout();
  }, [accessToken, logout]);

  /**
   * 刷新 Token
   * 当 accessToken 过期时调用
   */
  const refreshAccessToken = useCallback(async (): Promise<boolean> => {
    if (!refreshToken) {
      return false;
    }

    try {
      const response = await authApi.refreshToken(refreshToken);
      setTokens(response.accessToken, response.refreshToken);
      return true;
    } catch (error) {
      console.error('刷新 Token 失败:', error);
      clearTokens();
      return false;
    }
  }, [refreshToken, setTokens, clearTokens]);

  return {
    // 状态
    user,
    accessToken,
    refreshToken,
    isAuthenticated,

    // 方法
    login: handleLogin,
    logout: handleLogout,
    refreshAccessToken,
    setTokens,
    clearTokens,
  };
}
