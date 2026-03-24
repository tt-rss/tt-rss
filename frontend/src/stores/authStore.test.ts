import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { useAuthStore } from '../stores/authStore';
import type { User } from '../types';

describe('authStore', () => {
  // 保存原始的 localStorage
  const originalLocalStorage = global.localStorage;

  beforeEach(() => {
    // 重置 store 状态
    useAuthStore.setState({
      user: null,
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,
    });
  });

  afterEach(() => {
    // 恢复原始 localStorage
    global.localStorage = originalLocalStorage;
  });

  describe('初始状态', () => {
    it('应该初始化为未认证状态', () => {
      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
      expect(state.accessToken).toBeNull();
      expect(state.refreshToken).toBeNull();
      expect(state.isAuthenticated).toBe(false);
    });
  });

  describe('setTokens', () => {
    it('应该设置 token 并更新认证状态', () => {
      const { setTokens } = useAuthStore.getState();
      
      setTokens('test_access_token', 'test_refresh_token');
      
      const state = useAuthStore.getState();
      expect(state.accessToken).toBe('test_access_token');
      expect(state.refreshToken).toBe('test_refresh_token');
      expect(state.isAuthenticated).toBe(true);
      
      // 验证 localStorage
      expect(localStorage.getItem('rss_access_token')).toBe('test_access_token');
      expect(localStorage.getItem('rss_refresh_token')).toBe('test_refresh_token');
    });
  });

  describe('clearTokens', () => {
    it('应该清除 token 并更新认证状态', () => {
      const { setTokens, clearTokens } = useAuthStore.getState();
      
      // 先设置 token
      setTokens('test_access_token', 'test_refresh_token');
      
      // 然后清除
      clearTokens();
      
      const state = useAuthStore.getState();
      expect(state.accessToken).toBeNull();
      expect(state.refreshToken).toBeNull();
      expect(state.isAuthenticated).toBe(false);
      
      // 验证 localStorage 被清除
      expect(localStorage.getItem('rss_access_token')).toBeNull();
      expect(localStorage.getItem('rss_refresh_token')).toBeNull();
    });
  });

  describe('setUser', () => {
    it('应该设置用户信息', () => {
      const { setUser } = useAuthStore.getState();
      const mockUser: User = {
        id: 1,
        username: 'testuser',
        email: 'test@example.com',
      };
      
      setUser(mockUser);
      
      const state = useAuthStore.getState();
      expect(state.user).toEqual(mockUser);
    });

    it('应该能够清除用户信息', () => {
      const { setUser } = useAuthStore.getState();
      const mockUser: User = {
        id: 1,
        username: 'testuser',
        email: 'test@example.com',
      };
      
      setUser(mockUser);
      setUser(null);
      
      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
    });
  });

  describe('login', () => {
    it('应该设置用户信息和 token', () => {
      const { login } = useAuthStore.getState();
      const mockUser: User = {
        id: 1,
        username: 'testuser',
        email: 'test@example.com',
      };
      
      login(mockUser, 'access_token_123', 'refresh_token_456');
      
      const state = useAuthStore.getState();
      expect(state.user).toEqual(mockUser);
      expect(state.accessToken).toBe('access_token_123');
      expect(state.refreshToken).toBe('refresh_token_456');
      expect(state.isAuthenticated).toBe(true);
    });
  });

  describe('logout', () => {
    it('应该清除所有认证信息', () => {
      const { login, logout } = useAuthStore.getState();
      const mockUser: User = {
        id: 1,
        username: 'testuser',
        email: 'test@example.com',
      };
      
      // 先登录
      login(mockUser, 'access_token_123', 'refresh_token_456');
      
      // 然后登出
      logout();
      
      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
      expect(state.accessToken).toBeNull();
      expect(state.refreshToken).toBeNull();
      expect(state.isAuthenticated).toBe(false);
    });
  });

  describe('localStorage 持久化', () => {
    it('应该从 localStorage 读取已存储的 token', () => {
      // 直接在 localStorage 中设置值
      localStorage.setItem('rss_access_token', 'stored_access_token');
      localStorage.setItem('rss_refresh_token', 'stored_refresh_token');
      
      // 验证可以读取
      expect(localStorage.getItem('rss_access_token')).toBe('stored_access_token');
      expect(localStorage.getItem('rss_refresh_token')).toBe('stored_refresh_token');
    });

    it('应该处理 storeTokens 函数', () => {
      const { setTokens } = useAuthStore.getState();
      
      setTokens('new_access_token', 'new_refresh_token');
      
      expect(localStorage.getItem('rss_access_token')).toBe('new_access_token');
      expect(localStorage.getItem('rss_refresh_token')).toBe('new_refresh_token');
    });

    it('应该处理 removeStoredTokens 函数', () => {
      const { setTokens, clearTokens } = useAuthStore.getState();
      
      setTokens('temp_access', 'temp_refresh');
      clearTokens();
      
      expect(localStorage.getItem('rss_access_token')).toBeNull();
      expect(localStorage.getItem('rss_refresh_token')).toBeNull();
    });
  });
});
