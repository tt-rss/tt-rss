import { useState, useEffect } from 'react';
import { Modal, Box, Title, Notification, ScrollArea } from '@mantine/core';
import { IconCheck, IconAlertCircle, IconRss } from '@tabler/icons-react';
import { FeedForm, type FeedFormData, type CategoryOption } from './FeedForm';

/**
 * FeedDialog 模式类型
 */
export type FeedDialogMode = 'create' | 'edit';

/**
 * FeedDialog 组件属性
 */
export interface FeedDialogProps {
  /** 对话框是否打开 */
  opened: boolean;
  /** 对话框模式：create | edit */
  mode?: FeedDialogMode;
  /** 编辑模式时的初始数据 */
  initialData?: FeedFormData | null;
  /** 分类列表 */
  categories?: CategoryOption[];
  /** 是否允许创建新分类 */
  allowCreateCategory?: boolean;
  /** 关闭对话框回调 */
  onClose: () => void;
  /** 提交处理 */
  onSubmit: (data: FeedFormData) => void | Promise<void>;
  /** 是否正在提交 */
  isSubmitting?: boolean;
  /** 提交错误信息 */
  submitError?: string | null;
  /** 对话框标题（可选，默认根据模式生成） */
  title?: string;
}

/**
 * FeedDialog - 订阅源对话框组件
 *
 * 功能：
 * - 添加订阅源模式
 * - 编辑订阅源模式
 * - 成功/错误提示
 * - 使用 Mantine Modal 组件
 */
export function FeedDialog({
  opened,
  mode = 'create',
  initialData = null,
  categories = [],
  allowCreateCategory = false,
  onClose,
  onSubmit,
  isSubmitting = false,
  submitError = null,
  title,
}: FeedDialogProps) {
  // 内部状态：提交成功提示
  const [showSuccess, setShowSuccess] = useState(false);

  // 对话框关闭时重置状态
  useEffect(() => {
    if (!opened) {
      setShowSuccess(false);
    }
  }, [opened]);

  // 处理表单提交
  const handleSubmit = async (data: FeedFormData) => {
    try {
      await onSubmit(data);
      setShowSuccess(true);
      // 1.5 秒后关闭对话框
      setTimeout(() => {
        setShowSuccess(false);
        onClose();
      }, 1500);
    } catch (error) {
      // 错误由父组件通过 submitError 处理
      console.error('提交失败:', error);
    }
  };

  // 处理对话框关闭
  const handleClose = () => {
    if (!isSubmitting) {
      onClose();
    }
  };

  // 默认标题
  const defaultTitle = mode === 'create' ? '添加订阅源' : '编辑订阅源';

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={
        <Box style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <IconRss size={20} color="var(--mantine-color-orange-7)" />
          <Title order={4}>{title ?? defaultTitle}</Title>
        </Box>
      }
      size="md"
      centered
      closeOnClickOutside={!isSubmitting}
      closeOnEscape={!isSubmitting}
      withCloseButton={!isSubmitting}
    >
      <ScrollArea.Autosize mah={400} type="scroll">
        <Box p="sm">
          {/* 成功提示 */}
          {showSuccess && (
            <Notification
              icon={<IconCheck size={18} />}
              color="teal"
              withCloseButton={false}
              mb="md"
            >
              {mode === 'create' ? '订阅源已添加' : '订阅源已更新'}
            </Notification>
          )}

          {/* 错误提示 */}
          {submitError && (
            <Notification
              icon={<IconAlertCircle size={18} />}
              color="red"
              withCloseButton={false}
              mb="md"
            >
              {submitError}
            </Notification>
          )}

          {/* 表单 */}
          <FeedForm
            initialData={initialData}
            categories={categories}
            allowCreateCategory={allowCreateCategory}
            onSubmit={handleSubmit}
            onCancel={handleClose}
            isSubmitting={isSubmitting}
            submitError={null}
          />
        </Box>
      </ScrollArea.Autosize>
    </Modal>
  );
}
