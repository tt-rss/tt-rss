import { useState, useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import {
  Modal,
  Box,
  Title,
  Notification,
  ScrollArea,
  TextInput,
  Button,
  Stack,
  Group,
  Text,
  Loader,
  ActionIcon,
  Tooltip,
  Table,
  Badge,
  rem,
} from '@mantine/core';
import {
  IconCheck,
  IconAlertCircle,
  IconTag,
  IconEdit,
  IconTrash,
  IconPlus,
  IconPalette,
} from '@tabler/icons-react';
import type { Label, LabelFormData } from '../../types';

/**
 * LabelManager 模式类型
 */
export type LabelManagerMode = 'create' | 'edit' | 'manage';

/**
 * LabelManager 组件属性
 */
export interface LabelManagerProps {
  /** 对话框是否打开 */
  opened: boolean;
  /** 对话框模式：create | edit | manage */
  mode?: LabelManagerMode;
  /** 编辑模式时的初始数据 */
  initialData?: Label | null;
  /** 所有标签列表（管理模式下使用） */
  labels?: Label[];
  /** 是否正在加载标签列表 */
  isLoadingLabels?: boolean;
  /** 关闭对话框回调 */
  onClose: () => void;
  /** 创建标签提交处理 */
  onCreate?: (data: LabelFormData) => void | Promise<void>;
  /** 更新标签提交处理 */
  onUpdate?: (id: number, data: LabelFormData) => void | Promise<void>;
  /** 删除标签处理 */
  onDelete?: (id: number) => void | Promise<void>;
  /** 是否正在提交 */
  isSubmitting?: boolean;
  /** 提交错误信息 */
  submitError?: string | null;
  /** 对话框标题（可选，默认根据模式生成） */
  title?: string;
}

/**
 * LabelManager - 标签管理组件
 *
 * 功能：
 * - 创建标签模式
 * - 编辑标签模式
 * - 管理标签模式（列表展示、编辑、删除）
 * - 成功/错误提示
 * - 使用 Mantine Modal 组件
 */
export function LabelManager({
  opened,
  mode = 'create',
  initialData = null,
  labels = [],
  isLoadingLabels = false,
  onClose,
  onCreate,
  onUpdate,
  onDelete,
  isSubmitting = false,
  submitError = null,
  title,
}: LabelManagerProps) {
  // 内部状态：提交成功提示、当前编辑的标签
  const [showSuccess, setShowSuccess] = useState(false);
  const [editingLabel, setEditingLabel] = useState<Label | null>(null);
  const [isManageMode, setIsManageMode] = useState(mode === 'manage');

  // 表单状态
  const {
    control,
    handleSubmit,
    formState: { errors },
    reset,
    setValue,
  } = useForm<LabelFormData>({
    defaultValues: {
      caption: '',
      fgColor: '',
      bgColor: '',
    },
    mode: 'onChange',
  });

  // 对话框打开时重置状态
  useEffect(() => {
    if (opened) {
      setShowSuccess(false);
      if (mode === 'edit' && initialData) {
        setEditingLabel(initialData);
        setValue('caption', initialData.caption);
        setValue('fgColor', initialData.fgColor || '');
        setValue('bgColor', initialData.bgColor || '');
      } else if (mode === 'create') {
        setEditingLabel(null);
        reset({ caption: '', fgColor: '', bgColor: '' });
      }
      setIsManageMode(mode === 'manage');
    }
  }, [opened, mode, initialData, reset, setValue]);

  // 对话框关闭时重置状态
  useEffect(() => {
    if (!opened) {
      setShowSuccess(false);
      setEditingLabel(null);
    }
  }, [opened]);

  // 处理表单提交
  const handleFormSubmit = async (data: LabelFormData) => {
    try {
      if (isManageMode && editingLabel) {
        await onUpdate?.(editingLabel.id, data);
      } else if (mode === 'edit' && initialData) {
        await onUpdate?.(initialData.id, data);
      } else {
        await onCreate?.(data);
      }
      setShowSuccess(true);
      // 1.5 秒后关闭对话框
      setTimeout(() => {
        setShowSuccess(false);
        if (!isManageMode) {
          onClose();
        }
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

  // 处理编辑标签
  const handleEdit = (label: Label) => {
    setEditingLabel(label);
    setValue('caption', label.caption);
    setValue('fgColor', label.fgColor || '');
    setValue('bgColor', label.bgColor || '');
  };

  // 处理取消编辑
  const handleCancelEdit = () => {
    setEditingLabel(null);
    reset({ caption: '', fgColor: '', bgColor: '' });
  };

  // 处理删除标签
  const handleDelete = async (label: Label) => {
    if (window.confirm(`确定要删除标签"${label.caption}"吗？`)) {
      try {
        await onDelete?.(label.id);
        setShowSuccess(true);
        setTimeout(() => setShowSuccess(false), 1500);
      } catch (error) {
        console.error('删除失败:', error);
      }
    }
  };

  // 默认标题
  const getDefaultTitle = () => {
    if (isManageMode) return '管理标签';
    if (mode === 'edit') return '编辑标签';
    return '创建标签';
  };

  // 渲染颜色预览
  const renderColorPreview = (fgColor?: string, bgColor?: string) => {
    const fg = fgColor || '#000000';
    const bg = bgColor || '#ffffff';
    return (
      <Box
        style={{
          width: rem(20),
          height: rem(20),
          backgroundColor: bg,
          border: `1px solid ${fg}`,
          color: fg,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: rem(10),
          fontWeight: 'bold',
        }}
      >
        A
      </Box>
    );
  };

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={
        <Box style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <IconTag size={20} color="var(--mantine-color-blue-7)" />
          <Title order={4}>{title ?? getDefaultTitle()}</Title>
        </Box>
      }
      size={isManageMode ? 'lg' : 'md'}
      centered
      closeOnClickOutside={!isSubmitting}
      closeOnEscape={!isSubmitting}
      withCloseButton={!isSubmitting}
    >
      <ScrollArea.Autosize mah={isManageMode ? 500 : 400} type="scroll">
        <Box p="sm">
          {/* 成功提示 */}
          {showSuccess && (
            <Notification
              icon={<IconCheck size={18} />}
              color="teal"
              withCloseButton={false}
              mb="md"
            >
              {isManageMode ? '操作成功' : mode === 'create' ? '标签已创建' : '标签已更新'}
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

          {isManageMode ? (
            // 管理模式：标签列表
            <>
              <Stack gap="md">
                {/* 创建新标签表单 */}
                {!editingLabel && (
                  <Box>
                    <Title order={5} mb="sm">创建新标签</Title>
                    <form onSubmit={handleSubmit(handleFormSubmit)}>
                      <Stack gap="md">
                        <Controller
                          name="caption"
                          control={control}
                          rules={{
                            required: '标签名称不能为空',
                            minLength: {
                              value: 1,
                              message: '标签名称至少需要 1 个字符',
                            },
                            maxLength: {
                              value: 250,
                              message: '标签名称不能超过 250 个字符',
                            },
                          }}
                          render={({ field }) => (
                            <TextInput
                              {...field}
                              label="标签名称"
                              placeholder="输入标签名称"
                              leftSection={<IconTag size={18} />}
                              error={errors.caption?.message}
                              disabled={isSubmitting}
                              withAsterisk
                            />
                          )}
                        />

                        <Group grow gap="sm">
                          <Controller
                            name="fgColor"
                            control={control}
                            render={({ field }) => (
                              <TextInput
                                {...field}
                                label="前景颜色"
                                placeholder="#000000"
                                leftSection={<IconPalette size={18} />}
                                error={errors.fgColor?.message}
                                disabled={isSubmitting}
                              />
                            )}
                          />
                          <Controller
                            name="bgColor"
                            control={control}
                            render={({ field }) => (
                              <TextInput
                                {...field}
                                label="背景颜色"
                                placeholder="#ffffff"
                                leftSection={<IconPalette size={18} />}
                                error={errors.bgColor?.message}
                                disabled={isSubmitting}
                              />
                            )}
                          />
                        </Group>

                        <Group justify="flex-end" gap="sm">
                          <Button
                            type="submit"
                            leftSection={<IconPlus size={18} />}
                            loading={isSubmitting}
                            loaderProps={{ children: <Loader size={16} /> }}
                          >
                            创建标签
                          </Button>
                        </Group>
                      </Stack>
                    </form>
                  </Box>
                )}

                {/* 编辑标签表单 */}
                {editingLabel && (
                  <Box>
                    <Title order={5} mb="sm">编辑标签</Title>
                    <form onSubmit={handleSubmit(handleFormSubmit)}>
                      <Stack gap="md">
                        <Controller
                          name="caption"
                          control={control}
                          rules={{
                            required: '标签名称不能为空',
                            minLength: {
                              value: 1,
                              message: '标签名称至少需要 1 个字符',
                            },
                            maxLength: {
                              value: 250,
                              message: '标签名称不能超过 250 个字符',
                            },
                          }}
                          render={({ field }) => (
                            <TextInput
                              {...field}
                              label="标签名称"
                              placeholder="输入标签名称"
                              leftSection={<IconTag size={18} />}
                              error={errors.caption?.message}
                              disabled={isSubmitting}
                              withAsterisk
                            />
                          )}
                        />

                        <Group grow gap="sm">
                          <Controller
                            name="fgColor"
                            control={control}
                            render={({ field }) => (
                              <TextInput
                                {...field}
                                label="前景颜色"
                                placeholder="#000000"
                                leftSection={<IconPalette size={18} />}
                                error={errors.fgColor?.message}
                                disabled={isSubmitting}
                              />
                            )}
                          />
                          <Controller
                            name="bgColor"
                            control={control}
                            render={({ field }) => (
                              <TextInput
                                {...field}
                                label="背景颜色"
                                placeholder="#ffffff"
                                leftSection={<IconPalette size={18} />}
                                error={errors.bgColor?.message}
                                disabled={isSubmitting}
                              />
                            )}
                          />
                        </Group>

                        <Group justify="flex-end" gap="sm">
                          <Button
                            variant="outline"
                            onClick={handleCancelEdit}
                            disabled={isSubmitting}
                          >
                            取消
                          </Button>
                          <Button
                            type="submit"
                            leftSection={<IconCheck size={18} />}
                            loading={isSubmitting}
                            loaderProps={{ children: <Loader size={16} /> }}
                          >
                            保存
                          </Button>
                        </Group>
                      </Stack>
                    </form>
                  </Box>
                )}

                {/* 标签列表 */}
                <Box>
                  <Title order={5} mb="sm">我的标签</Title>
                  {isLoadingLabels ? (
                    <Box style={{ textAlign: 'center', padding: '20px' }}>
                      <Loader />
                    </Box>
                  ) : labels.length === 0 ? (
                    <Text c="dimmed" size="sm" ta="center">
                      暂无标签，请创建一个新标签
                    </Text>
                  ) : (
                    <Table striped highlightOnHover>
                      <Table.Thead>
                        <Table.Tr>
                          <Table.Th>预览</Table.Th>
                          <Table.Th>名称</Table.Th>
                          <Table.Th>操作</Table.Th>
                        </Table.Tr>
                      </Table.Thead>
                      <Table.Tbody>
                        {labels.map((label) => (
                          <Table.Tr key={label.id}>
                            <Table.Td>
                              {renderColorPreview(label.fgColor, label.bgColor)}
                            </Table.Td>
                            <Table.Td>
                              <Badge
                                variant="light"
                                color={label.bgColor ? undefined : 'blue'}
                                style={{
                                  color: label.fgColor,
                                  backgroundColor: label.bgColor,
                                }}
                              >
                                {label.caption}
                              </Badge>
                            </Table.Td>
                            <Table.Td>
                              <Group gap="xs">
                                <Tooltip label="编辑">
                                  <ActionIcon
                                    variant="subtle"
                                    color="blue"
                                    onClick={() => handleEdit(label)}
                                  >
                                    <IconEdit size={18} />
                                  </ActionIcon>
                                </Tooltip>
                                <Tooltip label="删除">
                                  <ActionIcon
                                    variant="subtle"
                                    color="red"
                                    onClick={() => handleDelete(label)}
                                  >
                                    <IconTrash size={18} />
                                  </ActionIcon>
                                </Tooltip>
                              </Group>
                            </Table.Td>
                          </Table.Tr>
                        ))}
                      </Table.Tbody>
                    </Table>
                  )}
                </Box>
              </Stack>
            </>
          ) : (
            // 创建/编辑模式：简单表单
            <form onSubmit={handleSubmit(handleFormSubmit)}>
              <Stack gap="md">
                <Controller
                  name="caption"
                  control={control}
                  rules={{
                    required: '标签名称不能为空',
                    minLength: {
                      value: 1,
                      message: '标签名称至少需要 1 个字符',
                    },
                    maxLength: {
                      value: 250,
                      message: '标签名称不能超过 250 个字符',
                    },
                  }}
                  render={({ field }) => (
                    <TextInput
                      {...field}
                      label="标签名称"
                      placeholder="输入标签名称"
                      leftSection={<IconTag size={18} />}
                      error={errors.caption?.message}
                      disabled={isSubmitting}
                      withAsterisk
                    />
                  )}
                />

                <Group grow gap="sm">
                  <Controller
                    name="fgColor"
                    control={control}
                    render={({ field }) => (
                      <TextInput
                        {...field}
                        label="前景颜色（可选）"
                        placeholder="#000000"
                        leftSection={<IconPalette size={18} />}
                        error={errors.fgColor?.message}
                        disabled={isSubmitting}
                      />
                    )}
                  />
                  <Controller
                    name="bgColor"
                    control={control}
                    render={({ field }) => (
                      <TextInput
                        {...field}
                        label="背景颜色（可选）"
                        placeholder="#ffffff"
                        leftSection={<IconPalette size={18} />}
                        error={errors.bgColor?.message}
                        disabled={isSubmitting}
                      />
                    )}
                  />
                </Group>

                {/* 错误提示 */}
                {submitError && (
                  <Text size="sm" c="red">
                    {submitError}
                  </Text>
                )}

                {/* 操作按钮 */}
                <Group justify="flex-end" gap="sm" mt="md">
                  <Button
                    variant="outline"
                    onClick={handleClose}
                    disabled={isSubmitting}
                  >
                    取消
                  </Button>
                  <Button
                    type="submit"
                    loading={isSubmitting}
                    loaderProps={{ children: <Loader size={16} /> }}
                  >
                    {mode === 'edit' ? '更新' : '创建'}
                  </Button>
                </Group>
              </Stack>
            </form>
          )}
        </Box>
      </ScrollArea.Autosize>
    </Modal>
  );
}
