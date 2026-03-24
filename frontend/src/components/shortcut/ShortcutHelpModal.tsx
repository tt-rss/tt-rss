import { Modal, Table, Badge, Text, useMantineTheme } from '@mantine/core';
import { useHotkeys } from '@mantine/hooks';

export interface ShortcutItem {
  /** 快捷键组合 */
  keys: string[];
  /** 功能描述 */
  description: string;
  /** 分类 */
  category?: string;
}

export interface ShortcutHelpModalProps {
  /** 是否打开模态框 */
  opened: boolean;
  /** 关闭模态框的回调 */
  onClose: () => void;
  /** 自定义快捷键列表 */
  shortcuts?: ShortcutItem[];
}

/**
 * 默认快捷键列表
 */
const defaultShortcuts: ShortcutItem[] = [
  {
    category: '导航',
    keys: ['g h'],
    description: '返回首页',
  },
  {
    category: '导航',
    keys: ['g r'],
    description: '刷新当前 Feed',
  },
  {
    category: '文章操作',
    keys: ['j', '↓'],
    description: '下一篇文章',
  },
  {
    category: '文章操作',
    keys: ['k', '↑'],
    description: '上一篇文章',
  },
  {
    category: '文章操作',
    keys: ['o', 'Enter'],
    description: '打开原文链接',
  },
  {
    category: '文章操作',
    keys: ['m'],
    description: '标记为已读/未读',
  },
  {
    category: '文章操作',
    keys: ['s'],
    description: '收藏/取消收藏',
  },
  {
    category: '全局',
    keys: ['Ctrl+K', 'Cmd+K'],
    description: '打开搜索',
  },
  {
    category: '全局',
    keys: ['Shift+/', '?'],
    description: '打开快捷键帮助',
  },
  {
    category: '全局',
    keys: ['r'],
    description: '刷新',
  },
];

/**
 * 快捷键帮助面板组件
 * 显示所有可用的键盘快捷键
 */
export function ShortcutHelpModal({ opened, onClose, shortcuts }: ShortcutHelpModalProps) {
  const theme = useMantineTheme();
  const shortcutList = shortcuts || defaultShortcuts;

  // 按分类分组
  const groupedShortcuts = shortcutList.reduce((acc, item) => {
    const category = item.category || '其他';
    if (!acc[category]) {
      acc[category] = [];
    }
    acc[category].push(item);
    return acc;
  }, {} as Record<string, ShortcutItem[]>);

  // 使用 Mantine 的 Hotkeys 来关闭模态框
  useHotkeys([
    ['Escape', onClose],
  ]);

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="键盘快捷键"
      size="lg"
      centered
      yOffset={100}
    >
      <Text c="dimmed" size="sm" mb="md">
        使用以下快捷键快速操作
      </Text>

      {Object.entries(groupedShortcuts).map(([category, items]) => (
        <div key={category} style={{ marginBottom: theme.spacing.lg }}>
          <Text fw={600} mb="sm" size="sm">
            {category}
          </Text>
          <Table striped highlightOnHover withTableBorder withColumnBorders>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>快捷键</Table.Th>
                <Table.Th>功能</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {items.map((item, index) => (
                <Table.Tr key={index}>
                  <Table.Td>
                    <div style={{ display: 'flex', gap: theme.spacing.xs, flexWrap: 'wrap' }}>
                      {item.keys.map((key, keyIndex) => (
                        <Badge key={keyIndex} variant="outline" size="sm">
                          {key}
                        </Badge>
                      ))}
                    </div>
                  </Table.Td>
                  <Table.Td>{item.description}</Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </div>
      ))}

      <Text c="dimmed" size="xs" mt="lg">
        提示：在输入框中时，大部分快捷键将被禁用
      </Text>
    </Modal>
  );
}
