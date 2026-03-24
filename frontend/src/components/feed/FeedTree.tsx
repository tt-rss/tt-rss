import { useState, useCallback } from 'react';
import { Tree, ScrollArea, Group, Box, Text, Badge, Tooltip } from '@mantine/core';
import { IconChevronRight, IconRss, IconFolder } from '@tabler/icons-react';
import type { FeedTreeProps, FeedTreeNodeData } from '../../types';
import { useTree, getTreeExpandedState, type RenderTreeNodePayload } from '@mantine/core';

/**
 * FeedTree - Feed 树形结构组件
 * 
 * 功能：
 * - 显示订阅源树形结构（分类 → 订阅源）
 * - 支持展开/折叠分类
 * - 支持选择订阅源/分类
 * - 显示未读计数
 * - 支持右键菜单
 * 
 * 使用 Mantine 7 Tree 组件实现
 */
export function FeedTree({
  data,
  selectedId,
  onSelect,
  onContextMenu,
  expandedIds: controlledExpandedIds,
  onExpandChange,
}: FeedTreeProps) {
  // 计算初始展开状态
  const initialExpandedState = controlledExpandedIds
    ? getTreeExpandedState(data as unknown as import('@mantine/core').TreeNodeData[], controlledExpandedIds)
    : undefined;

  const tree = useTree({
    initialExpandedState,
  });

  // 同步受控的展开状态
  useState(() => {
    if (controlledExpandedIds && onExpandChange) {
      // 受控模式下，展开状态由父组件管理
    }
  });

  // 处理节点点击
  const handleNodeClick = useCallback(
    (node: FeedTreeNodeData, event: React.MouseEvent) => {
      event.stopPropagation();
      if (onSelect) {
        onSelect(node.value, node.type);
      }
    },
    [onSelect]
  );

  // 处理右键菜单
  const handleNodeContextMenu = useCallback(
    (node: FeedTreeNodeData, event: React.MouseEvent) => {
      event.preventDefault();
      event.stopPropagation();
      if (onContextMenu) {
        onContextMenu(event, node);
      }
    },
    [onContextMenu]
  );

  // 切换展开/折叠
  const handleToggleExpand = useCallback(
    (nodeValue: string) => {
      tree.toggleExpanded(nodeValue);
      // 如果提供了受控回调，通知父组件
      if (onExpandChange) {
        // 这里需要从 tree 状态获取当前展开的节点
        // 由于 Mantine 的 tree 对象是响应式的，我们可以在 effect 中同步
      }
    },
    [tree, onExpandChange]
  );

  // 自定义节点渲染
  const renderTreeNode = useCallback(
    ({
      node,
      expanded,
      hasChildren,
      elementProps,
    }: RenderTreeNodePayload) => {
      const feedNode = node as unknown as FeedTreeNodeData;
      const isSelected = selectedId === feedNode.value;
      const isCategory = feedNode.type === 'category';
      const hasUnread = (feedNode.unreadCount ?? 0) > 0;

      return (
        <Group
          gap="xs"
          wrap="nowrap"
          {...elementProps}
          onClick={(e) => handleNodeClick(feedNode, e)}
          onContextMenu={(e) => handleNodeContextMenu(feedNode, e)}
          style={{
            padding: '4px 8px',
            borderRadius: '4px',
            cursor: 'pointer',
            backgroundColor: isSelected
              ? 'var(--mantine-color-blue-light)'
              : 'transparent',
            transition: 'background-color 0.15s ease',
            userSelect: 'none',
            ...elementProps.style,
          }}
        >
          {/* 展开/折叠图标 */}
          <Box
            style={{
              flexShrink: 0,
              width: '16px',
              height: '16px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              visibility: hasChildren ? 'visible' : 'hidden',
            }}
            onClick={(e) => {
              e.stopPropagation();
              if (hasChildren) {
                handleToggleExpand(feedNode.value);
              }
            }}
          >
            <IconChevronRight
              size={14}
              stroke={1.5}
              style={{
                transform: expanded ? 'rotate(90deg)' : 'rotate(0deg)',
                transition: 'transform 0.15s ease',
              }}
            />
          </Box>

          {/* 分类/订阅源图标 */}
          <Box style={{ flexShrink: 0 }}>
            {isCategory ? (
              <IconFolder
                size={18}
                stroke={1.5}
                color="var(--mantine-color-gray-6)"
              />
            ) : (
              feedNode.faviconUrl ? (
                <img
                  src={feedNode.faviconUrl}
                  alt=""
                  style={{
                    width: 18,
                    height: 18,
                    borderRadius: '2px',
                    objectFit: 'contain',
                  }}
                  onError={(e) => {
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
            {!isCategory && !feedNode.faviconUrl && (
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
            {feedNode.label}
          </Text>

          {/* 未读计数 */}
          {hasUnread && (
            <Tooltip label={`${feedNode.unreadCount} 条未读`} withArrow>
              <Badge
                size="sm"
                color="blue"
                variant="light"
                style={{
                  flexShrink: 0,
                  minWidth: 'auto',
                  padding: '0 6px',
                }}
              >
                {feedNode.unreadCount}
              </Badge>
            </Tooltip>
          )}
        </Group>
      );
    },
    [selectedId, handleNodeClick, handleNodeContextMenu, handleToggleExpand]
  );

  // 没有数据时的空状态
  if (!data || data.length === 0) {
    return (
      <ScrollArea style={{ height: '100%' }}>
        <Box
          style={{
            padding: '24px',
            textAlign: 'center',
            color: 'var(--mantine-color-gray-5)',
          }}
        >
          暂无订阅源
        </Box>
      </ScrollArea>
    );
  }

  return (
    <ScrollArea style={{ height: '100%' }}>
      <Tree
        data={data as unknown as import('@mantine/core').TreeNodeData[]}
        tree={tree}
        levelOffset={23}
        expandOnClick={false}
        selectOnClick={false}
        renderNode={renderTreeNode}
      />
    </ScrollArea>
  );
}
