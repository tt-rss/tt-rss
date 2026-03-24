import { useState } from 'react';
import { useForm } from 'react-hook-form';
import {
  Box,
  Button,
  Container,
  Paper,
  PasswordInput,
  Text,
  TextInput,
  Title,
  Checkbox,
  Notification,
} from '@mantine/core';
import { IconCheck, IconX } from '@tabler/icons-react';
import { useNavigate } from 'react-router';
import { useAuth } from '../hooks/useAuth';

interface LoginForm {
  username: string;
  password: string;
  remember?: boolean;
}

export default function LoginPage() {
  const navigate = useNavigate();
  const { login: authLogin } = useAuth();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>({
    defaultValues: {
      username: '',
      password: '',
      remember: false,
    },
  });

  const onSubmit = async (data: LoginForm) => {
    setIsLoading(true);
    setError(null);

    try {
      // 调用 useAuth hook 进行登录
      // 注意：authApi.login 内部会将 username 映射为后端期望的 login 字段
      await authLogin({
        username: data.username,
        password: data.password,
      });

      // 记住用户名（如果需要）
      if (data.remember) {
        localStorage.setItem('rememberedUsername', data.username);
      }

      // 跳转到主页
      navigate('/');
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : '登录失败，请检查用户名和密码';
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Container size="sm" py="xl">
      <Paper shadow="md" p="xl" withBorder>
        <Title order={2} ta="center" mb="md">
          登录
        </Title>
        <Text c="dimmed" ta="center" mb="xl">
          欢迎回来，请登录您的账户
        </Text>

        {error && (
          <Notification
            icon={<IconX size={18} />}
            color="red"
            withCloseButton
            onClose={() => setError(null)}
            mb="md"
          >
            {error}
          </Notification>
        )}

        <Box component="form" onSubmit={handleSubmit(onSubmit)}>
          <TextInput
            label="用户名"
            placeholder="请输入用户名"
            required
            minLength={3}
            error={errors.username?.message}
            {...register('username', {
              required: '用户名不能为空',
              minLength: {
                value: 3,
                message: '用户名至少需要 3 个字符',
              },
            })}
            mb="md"
          />

          <PasswordInput
            label="密码"
            placeholder="请输入密码"
            required
            minLength={6}
            error={errors.password?.message}
            {...register('password', {
              required: '密码不能为空',
              minLength: {
                value: 6,
                message: '密码至少需要 6 个字符',
              },
            })}
            mb="md"
          />

          <Checkbox
            label="记住我"
            {...register('remember')}
            mb="xl"
          />

          <Button
            type="submit"
            fullWidth
            loading={isLoading}
            leftSection={isLoading ? null : <IconCheck size={18} />}
          >
            {isLoading ? '登录中...' : '登录'}
          </Button>
        </Box>
      </Paper>
    </Container>
  );
}
