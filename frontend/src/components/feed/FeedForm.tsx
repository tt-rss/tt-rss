import { useForm, Controller } from 'react-hook-form';
import {
  TextInput,
  Select,
  Button,
  Stack,
  Group,
  Text,
  Loader,
} from '@mantine/core';
import { IconLink, IconRss, IconFolder } from '@tabler/icons-react';

/**
 * 订阅源表单数据类型
 */
export interface FeedFormData {
  title: string;
  feedUrl: string;
  siteUrl?: string;
  categoryId?: string | null;
}

/**
 * 分类选项类型
 */
export interface CategoryOption {
  value: string;
  label: string;
}

/**
 * FeedForm 组件属性
 */
export interface FeedFormProps {
  /** 初始数据（编辑模式时提供） */
  initialData?: FeedFormData | null;
  /** 分类列表（用于选择） */
  categories?: CategoryOption[];
  /** 是否允许创建新分类 */
  allowCreateCategory?: boolean;
  /** 提交处理 */
  onSubmit: (data: FeedFormData) => void | Promise<void>;
  /** 取消处理 */
  onCancel?: () => void;
  /** 是否正在提交 */
  isSubmitting?: boolean;
  /** 提交错误信息 */
  submitError?: string | null;
}

/**
 * FeedForm - 订阅源表单组件
 *
 * 功能：
 * - 标题（必填）
 * - 订阅源 URL（必填，URL 格式验证）
 * - 网站 URL（可选，URL 格式验证）
 * - 分类选择（可选，支持创建新分类）
 *
 * 使用 React Hook Form 进行表单验证
 */
export function FeedForm({
  initialData,
  categories = [],
  allowCreateCategory = false,
  onSubmit,
  onCancel,
  isSubmitting = false,
  submitError = null,
}: FeedFormProps) {
  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FeedFormData>({
    defaultValues: {
      title: initialData?.title ?? '',
      feedUrl: initialData?.feedUrl ?? '',
      siteUrl: initialData?.siteUrl ?? '',
      categoryId: initialData?.categoryId ?? null,
    },
    mode: 'onChange',
  });

  // 分类选项（添加"无分类"选项）
  const categoryOptions: CategoryOption[] = [
    { value: '', label: '无分类' },
    ...categories,
  ];

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="md">
        {/* 标题字段 */}
        <Controller
          name="title"
          control={control}
          rules={{
            required: '标题不能为空',
            minLength: {
              value: 2,
              message: '标题至少需要 2 个字符',
            },
            maxLength: {
              value: 200,
              message: '标题不能超过 200 个字符',
            },
          }}
          render={({ field }) => (
            <TextInput
              {...field}
              label="标题"
              placeholder="输入订阅源标题"
              leftSection={<IconRss size={18} />}
              error={errors.title?.message}
              disabled={isSubmitting}
              withAsterisk
            />
          )}
        />

        {/* 订阅源 URL 字段 */}
        <Controller
          name="feedUrl"
          control={control}
          rules={{
            required: '订阅源 URL 不能为空',
            pattern: {
              value: /^https?:\/\/.+/i,
              message: '请输入有效的 URL（以 http:// 或 https:// 开头）',
            },
          }}
          render={({ field }) => (
            <TextInput
              {...field}
              label="订阅源 URL"
              placeholder="https://example.com/feed.xml"
              leftSection={<IconLink size={18} />}
              error={errors.feedUrl?.message}
              disabled={isSubmitting}
              withAsterisk
            />
          )}
        />

        {/* 网站 URL 字段 */}
        <Controller
          name="siteUrl"
          control={control}
          rules={{
            pattern: {
              value: /^https?:\/\/.*$/i,
              message: '请输入有效的 URL（以 http:// 或 https:// 开头）',
            },
          }}
          render={({ field }) => (
            <TextInput
              {...field}
              label="网站 URL（可选）"
              placeholder="https://example.com"
              leftSection={<IconLink size={18} />}
              error={errors.siteUrl?.message}
              disabled={isSubmitting}
            />
          )}
        />

        {/* 分类选择字段 */}
        <Controller
          name="categoryId"
          control={control}
          render={({ field }) => (
            <Select
              {...field}
              label="分类"
              placeholder="选择分类"
              leftSection={<IconFolder size={18} />}
              data={categoryOptions}
              disabled={isSubmitting || categories.length === 0}
              clearable
              searchable={allowCreateCategory}
              nothingFoundMessage="没有找到分类"
            />
          )}
        />

        {/* 错误提示 */}
        {submitError && (
          <Text size="sm" c="red">
            {submitError}
          </Text>
        )}

        {/* 操作按钮 */}
        <Group justify="flex-end" gap="sm" mt="md">
          {onCancel && (
            <Button
              variant="outline"
              onClick={onCancel}
              disabled={isSubmitting}
            >
              取消
            </Button>
          )}
          <Button type="submit" loading={isSubmitting} loaderProps={{ children: <Loader size={16} /> }}>
            {initialData ? '更新' : '创建'}
          </Button>
        </Group>
      </Stack>
    </form>
  );
}
