import { createTheme, MantineColorsTuple, MantineThemeOverride } from '@mantine/core';

// 自定义主色调 (蓝色系)
const primaryColor: MantineColorsTuple = [
  '#e5f4ff',
  '#cce4ff',
  '#99c4ff',
  '#66a3ff',
  '#408aff',
  '#297aff',
  '#1a70ff',
  '#1261e6',
  '#0e56cc',
  '#0a4cb3',
];

// 亮色主题
export const lightTheme: MantineThemeOverride = createTheme({
  primaryColor: 'brand',
  colors: {
    brand: primaryColor,
  },
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif',
  defaultRadius: 'md',
  cursorType: 'pointer',
  components: {
    Button: {
      defaultProps: {
        size: 'md',
      },
    },
    Input: {
      defaultProps: {
        size: 'md',
      },
    },
  },
});

// 暗色主题
export const darkTheme: MantineThemeOverride = createTheme({
  primaryColor: 'brand',
  colors: {
    brand: primaryColor,
  },
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif',
  defaultRadius: 'md',
  cursorType: 'pointer',
  components: {
    Button: {
      defaultProps: {
        size: 'md',
      },
    },
    Input: {
      defaultProps: {
        size: 'md',
      },
    },
  },
});

// 默认导出（亮色主题）
export const theme = lightTheme;
