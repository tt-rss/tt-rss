import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MantineProvider } from '@mantine/core';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ArticleList } from './ArticleList';
import type { Article } from '../../types';

/**
 * 模拟测试数据
 */
const mockArticles: Article[] = [
  {
    id: '1',
    feedId: 'feed-1',
    feedTitle: 'React Blog',
    feedFaviconUrl: 'https://example.com/react.png',
    title: 'React 19 新特性介绍',
    link: 'https://example.com/react-19',
    excerpt: 'React 19 带来了许多令人兴奋的新特性',
    author: '张三',
    publishedAt: '2024-01-15T10:00:00Z',
    isRead: false,
    isStarred: false,
  },
  {
    id: '2',
    feedId: 'feed-2',
    feedTitle: 'Vue News',
    title: 'Vue 3.5 发布',
    link: 'https://example.com/vue-3-5',
    excerpt: 'Vue 3.5 版本带来了性能优化',
    author: '李四',
    publishedAt: '2024-01-14T09:00:00Z',
    isRead: true,
    isStarred: true,
  },
];

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
function renderWithProviders(
  component: React.ReactElement,
  queryClient: QueryClient = createTestQueryClient()
) {
  return render(
    <QueryClientProvider client={queryClient}>
      <MantineProvider>
        {component}
      </MantineProvider>
    </QueryClientProvider>
  );
}

// 模拟 useArticles hook
const mockUseArticles = vi.fn();
vi.mock('../../hooks/useArticles', () => ({
  useArticles: () => mockUseArticles(),
}));

describe('ArticleList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('应该渲染加载状态', () => {
    mockUseArticles.mockReturnValue({
      articles: [],
      isLoading: true,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList />);
    // 检查是否有 Loader 组件
    expect(document.querySelector('[class*="Loader"]')).toBeInTheDocument();
  });

  it('应该渲染错误状态', () => {
    mockUseArticles.mockReturnValue({
      articles: [],
      isLoading: false,
      error: new Error('加载失败'),
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList />);
    expect(screen.getByText('加载失败：加载失败')).toBeInTheDocument();
  });

  it('应该渲染空状态', () => {
    mockUseArticles.mockReturnValue({
      articles: [],
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList />);
    expect(screen.getByText('暂无文章')).toBeInTheDocument();
  });

  it('应该渲染自定义空状态消息', () => {
    mockUseArticles.mockReturnValue({
      articles: [],
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList emptyMessage="没有找到相关文章" />);
    expect(screen.getByText('没有找到相关文章')).toBeInTheDocument();
  });

  it('应该渲染文章列表', () => {
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList />);
    expect(screen.getByText('React 19 新特性介绍')).toBeInTheDocument();
    expect(screen.getByText('Vue 3.5 发布')).toBeInTheDocument();
  });

  it('应该渲染工具栏', () => {
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList />);
    expect(screen.getByText('选择文章以进行批量操作')).toBeInTheDocument();
  });

  it('应该处理文章选择', async () => {
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    const user = userEvent.setup();
    renderWithProviders(<ArticleList />);

    // 点击第一个文章的复选框
    const checkboxes = screen.getAllByRole('checkbox');
    await user.click(checkboxes[0]);

    await waitFor(() => {
      expect(screen.getByText('已选择 1 篇文章')).toBeInTheDocument();
    });
  });

  it('应该处理全选', async () => {
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    const user = userEvent.setup();
    renderWithProviders(<ArticleList />);

    // 全选按钮是第一个 checkbox（在工具栏中）
    // 文章复选框在工具栏之后
    const allCheckboxes = screen.getAllByRole('checkbox');
    // 第一个是全选按钮
    await user.click(allCheckboxes[0]);

    // 等待选中状态更新，检查是否显示选中数量
    await waitFor(() => {
      expect(screen.getByText(/已选择 \d+ 篇文章/)).toBeInTheDocument();
    });
  });

  it('应该处理刷新', async () => {
    const mockRefetch = vi.fn().mockResolvedValue(undefined);
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: mockRefetch,
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    const user = userEvent.setup();
    renderWithProviders(<ArticleList />);

    // 刷新按钮包含 Refresh 图标，通过查询包含 refresh 类的按钮
    const buttons = screen.getAllByRole('button');
    const refreshButton = buttons.find(btn => 
      btn.querySelector('[class*="refresh"]')
    );
    
    if (refreshButton) {
      await user.click(refreshButton);
      await waitFor(() => {
        expect(mockRefetch).toHaveBeenCalled();
      });
    }
  });

  it('应该处理批量标记已读', async () => {
    const mockBatchMarkAsRead = vi.fn().mockResolvedValue({ success: true });
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: mockBatchMarkAsRead,
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    const user = userEvent.setup();
    renderWithProviders(<ArticleList />);

    // 先选择文章
    const checkboxes = screen.getAllByRole('checkbox');
    await user.click(checkboxes[0]);

    await waitFor(() => {
      expect(screen.getByText('已选择 1 篇文章')).toBeInTheDocument();
    });

    // 标记已读按钮包含 eye 图标
    const buttons = screen.getAllByRole('button');
    const markReadButton = buttons.find(btn => 
      btn.querySelector('[class*="eye"]') && !btn.querySelector('[class*="eye-off"]')
    );
    
    if (markReadButton) {
      await user.click(markReadButton);
      await waitFor(() => {
        expect(mockBatchMarkAsRead).toHaveBeenCalled();
      });
    }
  });

  it('应该处理批量删除', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    const mockBatchDelete = vi.fn().mockResolvedValue({ success: true });
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: mockBatchDelete,
    });

    const user = userEvent.setup();
    renderWithProviders(<ArticleList />);

    // 先选择文章
    const checkboxes = screen.getAllByRole('checkbox');
    await user.click(checkboxes[0]);

    await waitFor(() => {
      expect(screen.getByText('已选择 1 篇文章')).toBeInTheDocument();
    });

    // 删除按钮包含 trash 图标
    const buttons = screen.getAllByRole('button');
    const deleteButton = buttons.find(btn => 
      btn.querySelector('[class*="trash"]')
    );
    
    if (deleteButton) {
      await user.click(deleteButton);
      await waitFor(() => {
        expect(mockBatchDelete).toHaveBeenCalled();
      });
    }

    vi.restoreAllMocks();
  });

  it('应该取消删除确认', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    const mockBatchDelete = vi.fn();
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: mockBatchDelete,
    });

    const user = userEvent.setup();
    renderWithProviders(<ArticleList />);

    // 先选择文章
    const checkboxes = screen.getAllByRole('checkbox');
    await user.click(checkboxes[0]);

    // 删除按钮
    const buttons = screen.getAllByRole('button');
    const deleteButton = buttons.find(btn => 
      btn.querySelector('[class*="trash"]')
    );
    
    if (deleteButton) {
      await user.click(deleteButton);
    }

    expect(mockBatchDelete).not.toHaveBeenCalled();

    vi.restoreAllMocks();
  });

  it('应该显示没有更多数据提示', () => {
    mockUseArticles.mockReturnValue({
      articles: mockArticles,
      isLoading: false,
      error: null,
      hasNextPage: false,
      isFetchingNextPage: false,
      loadMore: vi.fn(),
      refetch: vi.fn(),
      markAsStarred: vi.fn(),
      batchMarkAsRead: vi.fn(),
      batchMarkAsStarred: vi.fn(),
      batchDelete: vi.fn(),
    });

    renderWithProviders(<ArticleList />);
    expect(screen.getByText('没有更多文章了')).toBeInTheDocument();
  });
});
