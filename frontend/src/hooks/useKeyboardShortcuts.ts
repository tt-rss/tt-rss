import { useEffect, useCallback } from 'react';

/**
 * 快捷键配置接口
 */
export interface ShortcutConfig {
  /** 快捷键组合，例如 'Ctrl+K', 'Shift+/', 'g f' */
  key: string;
  /** 回调函数 */
  callback: () => void;
  /** 描述信息 */
  description?: string;
  /** 是否在输入框中生效 (默认 false) */
  allowInInput?: boolean;
}

/**
 * 快捷键 Hook
 * 用于注册和管理全局键盘快捷键
 * 
 * @param shortcuts 快捷键配置数组
 * 
 * @example
 * ```tsx
 * useKeyboardShortcuts([
 *   { key: 'Ctrl+K', callback: () => openSearch(), description: '打开搜索' },
 *   { key: 'Shift+/', callback: () => openHelp(), description: '打开帮助' },
 * ]);
 * ```
 */
export function useKeyboardShortcuts(shortcuts: ShortcutConfig[]) {
  // 规范化快捷键字符串
  const normalizeKey = (key: string): string => {
    return key.toLowerCase().trim();
  };

  // 检查是否应该忽略快捷键（在输入框中时）
  const shouldIgnoreShortcut = useCallback((shortcut: ShortcutConfig): boolean => {
    if (shortcut.allowInInput) {
      return false;
    }
    const activeElement = document.activeElement;
    if (!activeElement) {
      return false;
    }
    const tagName = activeElement.tagName.toLowerCase();
    const isInput = tagName === 'input' || tagName === 'textarea' || 
      (activeElement as HTMLElement).isContentEditable;
    return isInput;
  }, []);

  // 检查快捷键是否匹配
  const isShortcutMatch = useCallback((event: KeyboardEvent, shortcutKey: string): boolean => {
    const normalizedShortcut = normalizeKey(shortcutKey);
    
    // 处理组合键
    const parts = normalizedShortcut.split('+').map(p => p.trim());
    const mainKey = parts[parts.length - 1];
    
    const needCtrl = parts.includes('ctrl') || parts.includes('control');
    const needShift = parts.includes('shift');
    const needAlt = parts.includes('alt');
    const needMeta = parts.includes('meta') || parts.includes('cmd') || parts.includes('command');

    // 检查修饰键
    if (needCtrl !== event.ctrlKey) return false;
    if (needShift !== event.shiftKey) return false;
    if (needAlt !== event.altKey) return false;
    if (needMeta !== event.metaKey) return false;

    // 检查主键
    const eventKey = event.key.toLowerCase();
    if (eventKey !== mainKey) return false;

    return true;
  }, []);

  // 处理键盘事件
  const handleKeyDown = useCallback((event: KeyboardEvent) => {
    for (const shortcut of shortcuts) {
      if (shouldIgnoreShortcut(shortcut)) {
        continue;
      }
      
      if (isShortcutMatch(event, shortcut.key)) {
        event.preventDefault();
        event.stopPropagation();
        shortcut.callback();
        break;
      }
    }
  }, [shortcuts, shouldIgnoreShortcut, isShortcutMatch]);

  // 注册和移除事件监听
  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [handleKeyDown]);
}

/**
 * 预定义的常用快捷键
 */
export const commonShortcuts = {
  /** 打开搜索 (Ctrl+K 或 Cmd+K) */
  search: 'Ctrl+K',
  /** 打开帮助 (Shift+/ 或 ?) */
  help: 'Shift+/',
  /** 刷新 (r) */
  refresh: 'r',
  /** 上一条目 (k 或 ArrowUp) */
  prevItem: 'k',
  /** 下一条目 (j 或 ArrowDown) */
  nextItem: 'j',
  /** 标记为已读 (m) */
  markRead: 'm',
  /** 收藏/取消收藏 (s) */
  star: 's',
  /** 打开原文链接 (o 或 Enter) */
  open: 'o',
  /** 返回首页 (g h) */
  goHome: 'g h',
  /** 刷新当前 Feed (g r) */
  goRefresh: 'g r',
} as const;

/**
 * 获取快捷键的描述信息
 */
export function getShortcutDescription(key: string): string {
  const descriptions: Record<string, string> = {
    'Ctrl+K': '打开搜索',
    'Shift+/': '打开帮助',
    'r': '刷新',
    'k': '上一条目',
    'j': '下一条目',
    'm': '标记为已读',
    's': '收藏/取消收藏',
    'o': '打开原文链接',
    'Enter': '打开原文链接',
    'g h': '返回首页',
    'g r': '刷新当前 Feed',
  };
  return descriptions[key] || '';
}
