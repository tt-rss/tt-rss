/**
 * 搜索结果页面
 *
 * 显示搜索结果列表，支持按订阅源/分类过滤和分页
 */

import { useEffect, useState, useMemo, useCallback } from 'react';
import { useSearchParams } from 'react-router';
import {
  Container,
  Title,
  Text,
  Group,
  Stack,
  Card,
  Image,
  Badge,
  Pagination,
  Select,
  Loader,
  Center,
  ActionIcon,
  Tooltip,
  Divider,
} from '@mantine/core';
import { IconExternalLink, IconRss, IconCategory } from '@tabler/icons-react';
import { useQuery } from '@tanstack/react-query';
import { feedApi } from '../services/feedApi';
import { useSearch, highlightKeyword } from '../hooks/useSearch';
import { SearchBox } from '../components/search/SearchBox';
import type { Feed } from '../types';

/**
 * 搜索结果项组件
 */
function SearchResultItem({
  result,
  query,
}: {
  result: typeof useSearch extends (...args: any[]) => { results: infer R } ? R extends Array<infer T> ? T : never : never;
  query: string;
}) {
  const resultTyped = result as any;

  // 高亮标题
  const highlightedTitle = useMemo(() => {
    return highlightKeyword(resultTyped.title, query);
  }, [resultTyped.title, query]);

  // 高亮摘要
  const highlightedExcerpt = useMemo(() => {
    const excerpt = resultTyped.excerpt || resultTyped.content?.substring(0, 200) || '';
    return highlightKeyword(excerpt, query);
  }, [resultTyped, query]);

  // 格式化日期
  const formatDate = useCallback((dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('zh-CN', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }, []);

  return (
    <Card
      padding="lg"
      radius="md"
      withBorder
      style={{ cursor: 'pointer' }}
      onClick={() => window.open(resultTyped.link, '_blank')}
    >
      <Stack gap="sm">
        {/* 标题 */}
        <Group justify="space-between" align="flex-start" wrap="nowrap">
          <Title
            order={4}
            style={{
              fontSize: '1.125rem',
              fontWeight: 600,
              cursor: 'pointer',
            }}
            dangerouslySetInnerHTML={{ __html: highlightedTitle }}
          />
          <Tooltip label="在新窗口打开">
            <ActionIcon
              variant="subtle"
              color="gray"
              size="sm"
              onClick={(e) => {
                e.stopPropagation();
                window.open(resultTyped.link, '_blank');
              }}
            >
              <IconExternalLink size={16} />
            </ActionIcon>
          </Tooltip>
        </Group>

        {/* 元信息 */}
        <Group gap="xs" wrap="wrap">
          {resultTyped.feedFaviconUrl && (
            <Image
              src={resultTyped.feedFaviconUrl}
              alt=""
              w={16}
              h={16}
              style={{ borderRadius: 2 }}
            />
          )}
          {resultTyped.feedTitle && (
            <Badge
              variant="light"
              color="blue"
              size="sm"
              leftSection={<IconRss size={12} />}
            >
              {resultTyped.feedTitle}
            </Badge>
          )}
          <Text size="xs" c="dimmed">
            {formatDate(resultTyped.publishedAt)}
          </Text>
          {resultTyped.author && (
            <Text size="xs" c="dimmed">
              · {resultTyped.author}
            </Text>
          )}
        </Group>

        {/* 摘要 */}
        {highlightedExcerpt && (
          <>
            <Divider my="xs" />
            <Text
              size="sm"
              c="dimmed"
              lineClamp={3}
              dangerouslySetInnerHTML={{ __html: highlightedExcerpt }}
            />
          </>
        )}
      </Stack>
    </Card>
  );
}

/**
 * 搜索页面主组件
 */
export default function SearchPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  // 从 URL 获取参数
  const query = searchParams.get('q') || '';
  const feedId = searchParams.get('feedId') || undefined;
  const categoryId = searchParams.get('categoryId') || undefined;
  const page = parseInt(searchParams.get('page') || '1', 10);

  // 本地状态
  const [searchQuery, setSearchQuery] = useState(query);

  // 获取订阅源和分类列表（用于过滤选择器）
  const { data: feedsData } = useQuery({
    queryKey: ['feeds'],
    queryFn: () => feedApi.getFeeds(),
    staleTime: 1000 * 60 * 10,
  });

  // 使用搜索 Hook
  const {
    results,
    isLoading,
    isFetching,
    total,
    currentPage,
  } = useSearch(
    {
      query: query || undefined,
      feedId,
      categoryId,
      page,
    },
    20,
    true
  );

  // 搜索词变化时更新 URL
  useEffect(() => {
    if (searchQuery !== query) {
      const newParams = new URLSearchParams(searchParams);
      if (searchQuery.trim()) {
        newParams.set('q', searchQuery.trim());
      } else {
        newParams.delete('q');
      }
      newParams.set('page', '1'); // 重置页码
      setSearchParams(newParams);
    }
  }, [searchQuery, query, searchParams, setSearchParams]);

  // 处理搜索变化
  const handleSearchChange = useCallback((newQuery: string) => {
    setSearchQuery(newQuery);
  }, []);

  // 处理订阅源过滤
  const handleFeedChange = useCallback((value: string | null) => {
    const newParams = new URLSearchParams(searchParams);
    if (value) {
      newParams.set('feedId', value);
      newParams.delete('categoryId'); // 清除分类过滤
    } else {
      newParams.delete('feedId');
    }
    newParams.set('page', '1');
    setSearchParams(newParams);
  }, [searchParams, setSearchParams]);

  // 处理分类过滤
  const handleCategoryChange = useCallback((value: string | null) => {
    const newParams = new URLSearchParams(searchParams);
    if (value) {
      newParams.set('categoryId', value);
      newParams.delete('feedId'); // 清除订阅源过滤
    } else {
      newParams.delete('categoryId');
    }
    newParams.set('page', '1');
    setSearchParams(newParams);
  }, [searchParams, setSearchParams]);

  // 处理分页变化
  const handlePageChange = useCallback((newPage: number) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set('page', String(newPage));
    setSearchParams(newParams);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [searchParams, setSearchParams]);

  // 订阅源选择器数据
  const feedOptions = useMemo(() => {
    if (!feedsData) return [];
    return (feedsData as Feed[]).map((feed) => ({
      value: feed.id,
      label: feed.title,
    }));
  }, [feedsData]);

  // 分类选择器数据（假设有分类数据）
  const categoryOptions = useMemo(() => {
    // 这里可以根据实际 API 返回分类数据
    return [] as { value: string; label: string }[];
  }, []);

  return (
    <Container size="lg" py="xl">
      <Stack gap="xl">
        {/* 页面标题 */}
        <Group justify="space-between">
          <Title order={2}>
            {query ? `搜索结果：${query}` : '搜索'}
          </Title>
          {total > 0 && (
            <Text c="dimmed">
              共 {total} 条结果
            </Text>
          )}
        </Group>

        {/* 搜索框 */}
        <SearchBox
          initialValue={query}
          onSearchChange={handleSearchChange}
          size="lg"
          radius="md"
        />

        {/* 过滤条件 */}
        <Group gap="md" wrap="wrap">
          <Select
            placeholder="按订阅源过滤"
            data={feedOptions}
            value={feedId || null}
            onChange={handleFeedChange}
            clearable
            w={200}
            leftSection={<IconRss size={16} />}
          />
          <Select
            placeholder="按分类过滤"
            data={categoryOptions}
            value={categoryId || null}
            onChange={handleCategoryChange}
            clearable
            w={200}
            leftSection={<IconCategory size={16} />}
            disabled={categoryOptions.length === 0}
          />
        </Group>

        {/* 搜索结果列表 */}
        {isLoading ? (
          <Center py="xl">
            <Loader size="lg" />
          </Center>
        ) : results.length === 0 ? (
          <Center py="xl">
            <Stack align="center" gap="sm">
              <Text c="dimmed" size="lg">
                {query ? `未找到与"${query}"相关的文章` : '请输入搜索关键词'}
              </Text>
            </Stack>
          </Center>
        ) : (
          <>
            <Stack gap="md">
              {results.map((result) => (
                <SearchResultItem key={result.id} result={result} query={query} />
              ))}
            </Stack>

            {/* 分页 */}
            {total > 20 && (
              <Center mt="xl">
                <Pagination
                  value={currentPage}
                  total={Math.ceil(total / 20)}
                  onChange={handlePageChange}
                  size="md"
                  boundaries={2}
                  siblings={2}
                />
              </Center>
            )}

            {/* 加载更多提示 */}
            {isFetching && (
              <Center py="md">
                <Loader size="sm" />
              </Center>
            )}
          </>
        )}
      </Stack>
    </Container>
  );
}
