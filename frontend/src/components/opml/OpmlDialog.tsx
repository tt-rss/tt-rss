import { useState, useCallback } from 'react';
import {
  Modal,
  Box,
  Title,
  Text,
  Button,
  Group,
  Progress,
  Notification,
  Stack,
  Center,
} from '@mantine/core';
import {
  IconUpload,
  IconCheck,
  IconAlertCircle,
  IconFileExport,
  IconFileImport,
} from '@tabler/icons-react';
import { useOpml } from '../../hooks/useOpml';
import type { OpmlImportResult } from '../../types';

/**
 * OpmlDialog 组件属性
 */
export interface OpmlDialogProps {
  /** 对话框是否打开 */
  opened: boolean;
  /** 模式：import | export */
  mode?: 'import' | 'export';
  /** 关闭对话框回调 */
  onClose: () => void;
  /** 导入成功回调 */
  onImportSuccess?: (result: OpmlImportResult) => void;
  /** 导出成功回调 */
  onExportSuccess?: () => void;
}

/**
 * OpmlDialog - OPML 导入/导出对话框组件
 *
 * 功能：
 * - OPML 文件导入（拖拽上传）
 * - OPML 文件导出（下载）
 * - 导入进度显示
 * - 结果提示
 */
export function OpmlDialog({
  opened,
  mode = 'import',
  onClose,
  onImportSuccess,
  onExportSuccess,
}: OpmlDialogProps) {
  const {
    importOpml,
    exportOpml,
    importProgress,
    clearImportProgress,
    isImporting,
    importError,
    importResult,
  } = useOpml();

  // 内部状态：拖拽状态
  const [isDragging, setIsDragging] = useState(false);

  // 对话框关闭时重置状态
  const handleClose = useCallback(() => {
    clearImportProgress();
    onClose();
  }, [clearImportProgress, onClose]);

  // 处理文件选择
  const handleFileSelect = useCallback(
    async (file: File) => {
      // 验证文件类型
      if (!file.name.toLowerCase().endsWith('.opml') && !file.name.toLowerCase().endsWith('.xml')) {
        alert('请上传 OPML 文件（.opml 或 .xml）');
        return;
      }

      try {
        const result = await importOpml(file);
        if (result.success) {
          onImportSuccess?.(result);
          // 延迟关闭，让用户看到成功提示
          setTimeout(() => {
            handleClose();
          }, 1500);
        }
      } catch (error) {
        console.error('导入失败:', error);
      }
    },
    [importOpml, onImportSuccess, handleClose]
  );

  // 处理拖拽进入
  const handleDragEnter = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  }, []);

  // 处理拖拽离开
  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  }, []);

  // 处理拖拽悬停
  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
  }, []);

  // 处理文件放置
  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      e.stopPropagation();
      setIsDragging(false);

      const files = e.dataTransfer.files;
      if (files.length > 0) {
        handleFileSelect(files[0]);
      }
    },
    [handleFileSelect]
  );

  // 处理文件输入变化
  const handleFileInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = e.target.files;
      if (files && files.length > 0) {
        handleFileSelect(files[0]);
      }
    },
    [handleFileSelect]
  );

  // 处理导出
  const handleExport = useCallback(async () => {
    try {
      await exportOpml();
      onExportSuccess?.();
      setTimeout(() => {
        handleClose();
      }, 1000);
    } catch (error) {
      console.error('导出失败:', error);
    }
  }, [exportOpml, onExportSuccess, handleClose]);

  // 导入模式
  if (mode === 'import') {
    return (
      <Modal
        opened={opened}
        onClose={handleClose}
        title={
          <Box style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <IconFileImport size={20} color="var(--mantine-color-blue-7)" />
            <Title order={4}>导入 OPML</Title>
          </Box>
        }
        size="md"
        centered
        closeOnClickOutside={!isImporting}
        closeOnEscape={!isImporting}
        withCloseButton={!isImporting}
      >
        <Stack gap="md">
          {/* 成功提示 */}
          {importResult?.success && (
            <Notification
              icon={<IconCheck size={18} />}
              color="teal"
              withCloseButton={false}
            >
              导入完成！成功导入 {importResult.importedCount} 个订阅源
              {importResult.skippedCount > 0 &&
                `，跳过 ${importResult.skippedCount} 个已存在的订阅源`}
            </Notification>
          )}

          {/* 错误提示 */}
          {importError && (
            <Notification
              icon={<IconAlertCircle size={18} />}
              color="red"
              withCloseButton={false}
            >
              {importError.message}
            </Notification>
          )}

          {/* 进度显示 */}
          {isImporting && importProgress && (
            <Box>
              <Group justify="space-between" mb="xs">
                <Text size="sm" c="dimmed">
                  {importProgress.message || '正在导入...'}
                </Text>
                <Text size="sm" fw={500}>
                  {importProgress.percentage}%
                </Text>
              </Group>
              <Progress
                value={importProgress.percentage}
                size="lg"
                radius="xl"
                striped
                animated
              />
              {importProgress.total > 0 && (
                <Text size="xs" c="dimmed" ta="center" mt="xs">
                  {importProgress.current} / {importProgress.total}
                </Text>
              )}
            </Box>
          )}

          {/* 上传区域 */}
          {!isImporting && (
            <Box
              onDragEnter={handleDragEnter}
              onDragLeave={handleDragLeave}
              onDragOver={handleDragOver}
              onDrop={handleDrop}
              style={{
                border: `2px dashed ${
                  isDragging
                    ? 'var(--mantine-color-blue-7)'
                    : 'var(--mantine-color-gray-4)'
                }`,
                borderRadius: 'var(--mantine-radius-md)',
                padding: 'var(--mantine-spacing-xl)',
                backgroundColor: isDragging
                  ? 'var(--mantine-color-blue-light)'
                  : 'var(--mantine-color-gray-0)',
                transition: 'all 0.2s ease',
                cursor: 'pointer',
              }}
            >
              <input
                type="file"
                accept=".opml,.xml"
                onChange={handleFileInputChange}
                style={{ display: 'none' }}
                id="opml-file-input"
                disabled={isImporting}
              />
              <label htmlFor="opml-file-input" style={{ cursor: 'pointer' }}>
                <Center>
                  <Stack align="center" gap="sm">
                    <IconUpload
                      size={48}
                      color="var(--mantine-color-gray-6)"
                    />
                    <Text fw={500}>
                      点击选择文件或拖拽文件到此处
                    </Text>
                    <Text size="sm" c="dimmed">
                      支持 .opml 或 .xml 格式
                    </Text>
                  </Stack>
                </Center>
              </label>
            </Box>
          )}

          {/* 操作按钮 */}
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose} disabled={isImporting}>
              取消
            </Button>
          </Group>
        </Stack>
      </Modal>
    );
  }

  // 导出模式
  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={
        <Box style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <IconFileExport size={20} color="var(--mantine-color-green-7)" />
          <Title order={4}>导出 OPML</Title>
        </Box>
      }
      size="sm"
      centered
    >
      <Stack gap="md" align="center" py="lg">
        <IconFileExport size={64} color="var(--mantine-color-green-7)" />
        
        <Text ta="center">
          导出所有订阅源为 OPML 文件，可用于备份或迁移到其他 RSS 阅读器。
        </Text>

        <Group mt="md">
          <Button variant="default" onClick={handleClose}>
            取消
          </Button>
          <Button
            leftSection={<IconFileExport size={18} />}
            color="green"
            onClick={handleExport}
          >
            导出 OPML
          </Button>
        </Group>
      </Stack>
    </Modal>
  );
}
