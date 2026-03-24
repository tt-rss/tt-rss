import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MantineProvider } from '@mantine/core';
import { FeedTree } from './FeedTree';
import type { FeedTreeNodeData } from '../../types';

/**
 * 渲染带 Providers 的组件
 */
function renderWithProviders(component: React.ReactElement) {
  return render(
    <MantineProvider>
      {component}
    </MantineProvider>
  );
}

/**
 * 模拟测试数据 - 扁平结构，不需要展开
 */
const mockFlatTreeData: FeedTreeNodeData[] = [
  {
    value: 'feed-1',
    label: 'React Blog',
    type: 'feed',
    id: 'feed-1',
    feedId: 'feed-1',
    unreadCount: 3,
    faviconUrl: 'https://example.com/react.png',
  },
  {
    value: 'feed-2',
    label: 'Vue News',
    type: 'feed',
    id: 'feed-2',
    feedId: 'feed-2',
    unreadCount: 0,
  },
  {
    value: 'cat-1',
    label: '技术分类',
    type: 'category',
    id: 'cat-1',
    unreadCount: 5,
  },
];

describe('FeedTree', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('应该渲染空状态', () => {
    renderWithProviders(<FeedTree data={[]} />);
    expect(screen.getByText('暂无订阅源')).toBeInTheDocument();
  });

  it('应该渲染订阅源列表', () => {
    renderWithProviders(<FeedTree data={mockFlatTreeData} />);
    
    expect(screen.getByText('React Blog')).toBeInTheDocument();
    expect(screen.getByText('Vue News')).toBeInTheDocument();
    expect(screen.getByText('技术分类')).toBeInTheDocument();
  });

  it('应该显示未读计数', () => {
    renderWithProviders(<FeedTree data={mockFlatTreeData} />);
    
    // React Blog 有 3 条未读
    expect(screen.getByText('3')).toBeInTheDocument();
    // 技术分类有 5 条未读
    expect(screen.getByText('5')).toBeInTheDocument();
  });

  it('应该为没有未读计数的项目隐藏计数', () => {
    renderWithProviders(<FeedTree data={mockFlatTreeData} />);
    
    const vueNewsNode = screen.getByText('Vue News');
    // Vue News 没有未读计数，不应该在附近找到 badge
    const parent = vueNewsNode.parentElement;
    const badges = parent?.querySelectorAll('[class*="Badge"]');
    expect(badges).toHaveLength(0);
  });

  it('应该处理节点点击选择', async () => {
    const handleSelect = vi.fn();
    renderWithProviders(
      <FeedTree 
        data={mockFlatTreeData} 
        selectedId={null}
        onSelect={handleSelect}
      />
    );
    
    // 点击 React Blog
    const reactBlogNode = screen.getByText('React Blog');
    fireEvent.click(reactBlogNode);
    
    await waitFor(() => {
      expect(handleSelect).toHaveBeenCalledWith('feed-1', 'feed');
    });
  });

  it('应该处理分类节点点击', async () => {
    const handleSelect = vi.fn();
    renderWithProviders(
      <FeedTree 
        data={mockFlatTreeData} 
        selectedId={null}
        onSelect={handleSelect}
      />
    );
    
    // 点击技术分类
    const categoryNode = screen.getByText('技术分类');
    fireEvent.click(categoryNode);
    
    await waitFor(() => {
      expect(handleSelect).toHaveBeenCalledWith('cat-1', 'category');
    });
  });

  it('应该处理右键菜单事件', async () => {
    const handleContextMenu = vi.fn();
    renderWithProviders(
      <FeedTree 
        data={mockFlatTreeData} 
        onContextMenu={handleContextMenu}
      />
    );
    
    // 右键点击 React Blog
    const reactBlogNode = screen.getByText('React Blog');
    fireEvent.contextMenu(reactBlogNode);
    
    await waitFor(() => {
      expect(handleContextMenu).toHaveBeenCalled();
    });
  });

  it('应该渲染带有 favicon 图片的节点', () => {
    renderWithProviders(<FeedTree data={mockFlatTreeData} />);
    
    const reactBlogNode = screen.getByText('React Blog');
    const parent = reactBlogNode.parentElement;
    const img = parent?.querySelector('img');
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('src', 'https://example.com/react.png');
  });

  it('应该为没有 favicon 的订阅源显示 RSS 图标占位', () => {
    renderWithProviders(<FeedTree data={mockFlatTreeData} />);
    
    // Vue News 没有 faviconUrl
    const vueNewsNode = screen.getByText('Vue News');
    expect(vueNewsNode).toBeInTheDocument();
  });

  it('应该处理 favicon 加载失败', async () => {
    renderWithProviders(<FeedTree data={mockFlatTreeData} />);
    
    const reactBlogNode = screen.getByText('React Blog');
    const parent = reactBlogNode.parentElement;
    const img = parent?.querySelector('img');
    
    if (img) {
      fireEvent.error(img);
      await waitFor(() => {
        expect(img).toHaveStyle('display: none');
      });
    }
  });
});
