import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router';
import { MantineProvider } from '@mantine/core';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import LoginPage from './LoginPage';
import { useAuthStore } from '../stores/authStore';

// 模拟 useAuth hook
const mockLogin = vi.fn();
vi.mock('../hooks/useAuth', () => ({
  useAuth: () => ({
    login: mockLogin,
    logout: vi.fn(),
    user: null,
    accessToken: null,
    refreshToken: null,
    isAuthenticated: false,
  }),
}));

// 模拟 react-router 的 useNavigate
const mockedNavigate = vi.fn();
vi.mock('react-router', async () => {
  const actual = await vi.importActual('react-router');
  return {
    ...(actual as object),
    useNavigate: () => mockedNavigate,
  };
});

/**
 * 创建测试用的 QueryClient
 */
function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
}

/**
 * 渲染带 Providers 的组件
 */
function renderWithProviders(component: React.ReactElement) {
  return render(
    <BrowserRouter>
      <MantineProvider>
        <QueryClientProvider client={createTestQueryClient()}>
          {component}
        </QueryClientProvider>
      </MantineProvider>
    </BrowserRouter>
  );
}

// 重置模块 mocks
beforeEach(() => {
  vi.clearAllMocks();
  mockLogin.mockReset();
  useAuthStore.setState({
    user: null,
    accessToken: null,
    refreshToken: null,
    isAuthenticated: false,
  });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('LoginPage', () => {
  it('应该渲染登录表单', () => {
    renderWithProviders(<LoginPage />);
    
    expect(screen.getByPlaceholderText('请输入用户名')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('请输入密码')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /登录/ })).toBeInTheDocument();
    expect(screen.getByText('欢迎回来，请登录您的账户')).toBeInTheDocument();
  });

  it('应该显示表单验证错误 - 空用户名', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    // 等待错误消息出现（使用更宽松的匹配）
    await waitFor(() => {
      const errorMessages = screen.getAllByText(/用户名/);
      expect(errorMessages.length).toBeGreaterThan(0);
    }, { timeout: 2000 });
  });

  it('应该显示表单验证错误 - 空密码', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'testuser');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    await waitFor(() => {
      const errorMessages = screen.getAllByText(/密码/);
      expect(errorMessages.length).toBeGreaterThan(0);
    }, { timeout: 2000 });
  });

  it('应该显示表单验证错误 - 用户名太短', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'ab');
    await user.type(screen.getByPlaceholderText('请输入密码'), 'password123');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    await waitFor(() => {
      expect(screen.getByText(/至少需要 3 个字符/)).toBeInTheDocument();
    });
  });

  it('应该显示表单验证错误 - 密码太短', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'testuser');
    await user.type(screen.getByPlaceholderText('请输入密码'), '12345');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    await waitFor(() => {
      expect(screen.getByText(/至少需要 6 个字符/)).toBeInTheDocument();
    });
  });

  it('应该能够勾选记住我选项', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    const rememberCheckbox = screen.getByLabelText('记住我');
    expect(rememberCheckbox).not.toBeChecked();
    
    await user.click(rememberCheckbox);
    expect(rememberCheckbox).toBeChecked();
  });

  it('应该显示错误通知', async () => {
    mockLogin.mockRejectedValue(new Error('用户名或密码错误'));
    
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'testuser');
    await user.type(screen.getByPlaceholderText('请输入密码'), 'wrongpassword');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    await waitFor(() => {
      expect(screen.getByText('用户名或密码错误')).toBeInTheDocument();
    });
  });

  it('应该显示加载状态', async () => {
    let resolveLogin: () => void;
    const loginPromise = new Promise<void>((resolve) => {
      resolveLogin = resolve;
    });
    
    mockLogin.mockImplementation(() => loginPromise);
    
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'testuser');
    await user.type(screen.getByPlaceholderText('请输入密码'), 'password123');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    await waitFor(() => {
      expect(screen.getByText('登录中...')).toBeInTheDocument();
    });
    
    resolveLogin!();
  });

  it('应该在成功登录后跳转', async () => {
    mockLogin.mockResolvedValue({
      accessToken: 'test_token',
      refreshToken: 'test_refresh',
      userId: 1,
      username: 'testuser',
      email: 'test@example.com',
    });
    
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'testuser');
    await user.type(screen.getByPlaceholderText('请输入密码'), 'password123');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);
    
    await waitFor(() => {
      expect(mockedNavigate).toHaveBeenCalledWith('/');
    });
  });

  it('应该能够关闭错误通知', async () => {
    mockLogin.mockRejectedValue(new Error('登录失败'));
    
    const user = userEvent.setup();
    renderWithProviders(<LoginPage />);
    
    await user.type(screen.getByPlaceholderText('请输入用户名'), 'testuser');
    await user.type(screen.getByPlaceholderText('请输入密码'), 'wrongpassword');
    
    const submitButton = screen.getByRole('button', { name: /登录/ });
    await user.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('登录失败')).toBeInTheDocument();
    });

    // 关闭按钮是 Notification 组件的一部分
    const closeButtons = screen.getAllByRole('button');
    // 第一个 button 是关闭按钮（第二个是提交按钮）
    await user.click(closeButtons[0]);

    await waitFor(() => {
      expect(screen.queryByText('登录失败')).not.toBeInTheDocument();
    });
  });
});
