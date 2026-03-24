import { ActionIcon, useMantineColorScheme } from '@mantine/core';
import { IconSun, IconMoon } from '@tabler/icons-react';
import { useAppStore } from '../../stores/appStore';

export interface ThemeToggleProps {
  /** 按钮尺寸 */
  size?: 'sm' | 'md' | 'lg';
}

/**
 * 主题切换按钮组件
 * 用于在亮色模式和暗色模式之间切换
 */
export function ThemeToggle({ size = 'md' }: ThemeToggleProps) {
  const { theme, toggleTheme } = useAppStore();
  const { setColorScheme } = useMantineColorScheme();

  const handleToggle = () => {
    toggleTheme();
    setColorScheme(theme === 'light' ? 'dark' : 'light');
  };

  return (
    <ActionIcon
      onClick={handleToggle}
      size={size}
      variant="subtle"
      title={theme === 'light' ? '切换到暗色模式' : '切换到亮色模式'}
    >
      {theme === 'light' ? <IconMoon size={20} /> : <IconSun size={20} />}
    </ActionIcon>
  );
}
