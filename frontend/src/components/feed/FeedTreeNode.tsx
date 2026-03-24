import { Box, Group, Text, Badge, ActionIcon, Tooltip } from '@mantine/core';
import { IconRss, IconFolder, IconDots } from '@tabler/icons-react';
import type { FeedTreeNodeProps } from '../../types';

/**
 * FeedTreeNode - 单个 Feed 树节点组件
 * 
 * 功能：
 * - 显示分类或订阅源图标
 * - 显示未读计数
 * - 支持右键菜单
 * - 选中状态高亮
 */
export function FeedTreeNode({
  node,
  selectedId,
  onSelect,
  onContextMenu,
}: FeedTreeNodeProps) {
  const isSelected = selectedId === node.id;
  const isCategory = node.type === 'category';
  const hasUnread = (node.unreadCount ?? 0) > 0;

  const handleClick = (event: React.MouseEvent<HTMLDivElement>) => {
    // 左键点击选择
    if (event.button === 0 && onSelect && node.value) {
      onSelect(node.value, node.type);
    }
  };

  const handleContextMenu = (event: React.MouseEvent<HTMLDivElement>) => {
    event.preventDefault();
    event.stopPropagation();
    if (onContextMenu) {
      onContextMenu(event, node);
    }
  };

  return (
    <Box
      onClick={handleClick}
      onContextMenu={handleContextMenu}
      style={{
        padding: '4px 8px',
        borderRadius: '4px',
        cursor: 'pointer',
        backgroundColor: isSelected ? 'var(--mantine-color-blue-light)' : 'transparent',
        transition: 'background-color 0.15s ease',
        userSelect: 'none',
      }}
      data-node-id={node.id}
      data-node-type={node.type}
    >
      <Group gap="xs" wrap="nowrap">
        {/* 图标 */}
        <Box style={{ flexShrink: 0 }}>
          {isCategory ? (
            <IconFolder
              size={18}
              stroke={1.5}
              color="var(--mantine-color-gray-6)"
            />
          ) : (
            node.faviconUrl ? (
              <img
                src={node.faviconUrl}
                alt=""
                style={{
                  width: 18,
                  height: 18,
                  borderRadius: '2px',
                  objectFit: 'contain',
                }}
                onError={(e) => {
                  // favicon 加载失败时显示默认 RSS 图标
                  const target = e.target as HTMLImageElement;
                  target.style.display = 'none';
                  const fallback = target.nextElementSibling as HTMLElement;
                  if (fallback) {
                    fallback.style.display = 'flex';
                  }
                }}
              />
            ) : null
          )}
          {!isCategory && !node.faviconUrl && (
            <IconRss
              size={18}
              stroke={1.5}
              color="var(--mantine-color-orange-7)"
            />
          )}
        </Box>

        {/* 标签 */}
        <Text
          size="sm"
          style={{
            flex: 1,
            minWidth: 0,
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
            fontWeight: isSelected ? 600 : 400,
            color: isSelected
              ? 'var(--mantine-color-blue-7)'
              : 'var(--mantine-color-gray-9)',
          }}
        >
          {node.label}
        </Text>

        {/* 未读计数 */}
        {hasUnread && (
          <Tooltip label={`${node.unreadCount} 条未读`} withArrow>
            <Badge
              size="sm"
              color="blue"
              variant="light"
              style={{ flexShrink: 0, minWidth: 'auto', padding: '0 6px' }}
            >
              {node.unreadCount}
            </Badge>
          </Tooltip>
        )}

        {/* 右键菜单触发器（可选，用于提示用户可右键） */}
        <ActionIcon
          variant="transparent"
          size="sm"
          style={{
            flexShrink: 0,
            opacity: 0,
            transition: 'opacity 0.15s ease',
          }}
          data-context-trigger
          onClick={(e) => {
            e.stopPropagation();
            const rect = e.currentTarget.getBoundingClientRect();
            const mockEvent = new MouseEvent('contextmenu', {
              clientX: rect.right,
              clientY: rect.top,
              bubbles: true,
              cancelable: true,
            });
            e.currentTarget.dispatchEvent(mockEvent);
          }}
          onMouseEnter={(e) => {
            e.currentTarget.style.opacity = '1';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.opacity = '0';
          }}
        >
          <IconDots size={16} stroke={1.5} />
        </ActionIcon>
      </Group>
    </Box>
  );
}
