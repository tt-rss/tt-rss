import { useState } from 'react';
import { Container, Title, Text, Center, Group, ActionIcon, Tooltip, Box } from '@mantine/core';
import { IconFileImport, IconFileExport } from '@tabler/icons-react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router';
import { useAuthStore } from './stores/authStore';
import { OpmlDialog } from './components/opml/OpmlDialog';
import { SearchBox } from './components/search/SearchBox';
import LoginPage from './pages/LoginPage';
import SearchPage from './pages/SearchPage';
import AuthCallback from './components/auth/AuthCallback';
import type { OpmlImportResult } from './types';

function HomePage() {
  // OPML 对话框状态
  const [opmlDialogOpened, setOpmlDialogOpened] = useState(false);
  const [opmlDialogMode, setOpmlDialogMode] = useState<'import' | 'export'>('import');

  // 打开导入对话框
  const handleOpenImport = () => {
    setOpmlDialogMode('import');
    setOpmlDialogOpened(true);
  };

  // 打开导出对话框
  const handleOpenExport = () => {
    setOpmlDialogMode('export');
    setOpmlDialogOpened(true);
  };

  // 导入成功回调
  const handleImportSuccess = (result: OpmlImportResult) => {
    console.log('OPML 导入成功:', result);
  };

  // 导出成功回调
  const handleExportSuccess = () => {
    console.log('OPML 导出成功');
  };

  return (
    <Container size="md" py="xl">
      <Center>
        <Title order={1}>RSS Reader</Title>
      </Center>
      <Text c="dimmed" ta="center" mt="md">
        欢迎使用 RSS 阅读器 - 基于 React 19 + Mantine 7 构建
      </Text>

      {/* 搜索框 */}
      <Box mt="xl" mx="auto" style={{ maxWidth: 600 }}>
        <SearchBox />
      </Box>

      {/* OPML 操作按钮 */}
      <Group justify="center" mt="xl">
        <Tooltip label="导入 OPML" withArrow>
          <ActionIcon
            variant="light"
            color="blue"
            size="lg"
            onClick={handleOpenImport}
          >
            <IconFileImport size={24} />
          </ActionIcon>
        </Tooltip>
        <Tooltip label="导出 OPML" withArrow>
          <ActionIcon
            variant="light"
            color="green"
            size="lg"
            onClick={handleOpenExport}
          >
            <IconFileExport size={24} />
          </ActionIcon>
        </Tooltip>
      </Group>

      {/* OPML 对话框 */}
      <OpmlDialog
        opened={opmlDialogOpened}
        mode={opmlDialogMode}
        onClose={() => setOpmlDialogOpened(false)}
        onImportSuccess={handleImportSuccess}
        onExportSuccess={handleExportSuccess}
      />
    </Container>
  );
}

/**
 * 认证守卫组件
 * 未登录时重定向到登录页
 */
function AuthGuard({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthStore();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/auth/callback" element={<AuthCallback />} />
        <Route path="/search" element={<SearchPage />} />
        <Route
          path="/"
          element={
            <AuthGuard>
              <HomePage />
            </AuthGuard>
          }
        />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
