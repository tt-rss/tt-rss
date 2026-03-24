import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Center, Loader, Text, Container, Title, Alert } from '@mantine/core';
import { IconCheck, IconX } from '@tabler/icons-react';
import { useAuthStore } from '../../stores/authStore';
import type { User } from '../../types';

/**
 * AuthCallback 组件
 * 处理登录后的回调，包括 OAuth 回调等场景
 * 负责 Token 存储和状态更新
 */
export default function AuthCallback() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { login, setTokens } = useAuthStore();
  const [status, setStatus] = useState<'loading' | 'success' | 'error'>('loading');
  const [errorMessage, setErrorMessage] = useState<string>('');

  useEffect(() => {
    const handleCallback = async () => {
      try {
        // 从 URL 参数中获取 Token 和用户信息
        const accessToken = searchParams.get('access_token');
        const refreshToken = searchParams.get('refresh_token');
        const userJson = searchParams.get('user');

        if (!accessToken || !refreshToken) {
          throw new Error('缺少 Token 参数');
        }

        let user: User | null = null;
        if (userJson) {
          try {
            user = JSON.parse(decodeURIComponent(userJson));
          } catch {
            // 如果解析失败，尝试从其他参数获取用户信息
            user = {
              id: parseInt(searchParams.get('user_id') || '0', 10),
              username: searchParams.get('username') || searchParams.get('name') || 'User',
              email: searchParams.get('email') || '',
            };
          }
        }

        if (!user) {
          throw new Error('缺少用户信息');
        }

        // 存储 Token 和更新认证状态
        login(user, accessToken, refreshToken);

        setStatus('success');

        // 延迟跳转到首页，让用户看到成功提示
        setTimeout(() => {
          navigate('/');
        }, 1500);
      } catch (error) {
        console.error('认证回调处理失败:', error);
        setErrorMessage(error instanceof Error ? error.message : '认证失败');
        setStatus('error');

        // 错误情况下延迟跳转到登录页
        setTimeout(() => {
          navigate('/login');
        }, 2000);
      }
    };

    handleCallback();
  }, [searchParams, login, setTokens, navigate]);

  return (
    <Container size="sm" py="xl">
      <Center>
        {status === 'loading' && (
          <>
            <Loader size="lg" />
            <Text ml="md">处理登录中...</Text>
          </>
        )}

        {status === 'success' && (
          <Alert
            icon={<IconCheck />}
            title="登录成功"
            color="green"
            variant="light"
          >
            正在跳转到首页...
          </Alert>
        )}

        {status === 'error' && (
          <Container size="xs">
            <Title order={4} c="red" ta="center" mb="md">
              <IconX />
            </Title>
            <Alert
              icon={<IconX />}
              title="登录失败"
              color="red"
              variant="light"
            >
              {errorMessage}
            </Alert>
            <Text c="dimmed" ta="center" mt="md" size="sm">
              正在返回登录页...
            </Text>
          </Container>
        )}
      </Center>
    </Container>
  );
}
