import { useCallback, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { opmlApi } from '../services/opmlApi';
import type { OpmlImportProgress } from '../types';

/**
 * OPML Hook
 *
 * 封装 OPML 导入和导出操作
 */
export function useOpml() {
  // 内部状态：导入进度
  const [importProgress, setImportProgress] = useState<OpmlImportProgress | null>(null);

  /**
   * 导入 OPML Mutation
   */
  const importMutation = useMutation({
    mutationFn: opmlApi.importOpml,
    onMutate: () => {
      // 重置进度
      setImportProgress({
        current: 0,
        total: 0,
        percentage: 0,
        message: '准备导入...',
      });
    },
    onSuccess: (result) => {
      // 更新进度为完成
      setImportProgress({
        current: result.importedCount + result.skippedCount,
        total: result.importedCount + result.skippedCount,
        percentage: 100,
        message: '导入完成',
      });
    },
    onError: () => {
      // 出错时清除进度
      setImportProgress(null);
    },
  });

  /**
   * 导出 OPML
   * 直接下载文件
   */
  const exportOpml = useCallback(async () => {
    try {
      const blob = await opmlApi.exportOpml();
      
      // 创建下载链接
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `tt-rss-backup-${new Date().toISOString().split('T')[0]}.opml`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      return { success: true };
    } catch (error) {
      console.error('导出失败:', error);
      throw error;
    }
  }, []);

  /**
   * 导入 OPML
   */
  const importOpml = useCallback(
    async (file: File) => {
      return importMutation.mutateAsync(file);
    },
    [importMutation]
  );

  /**
   * 清除导入进度
   */
  const clearImportProgress = useCallback(() => {
    setImportProgress(null);
  }, []);

  return {
    // 状态
    importProgress,
    
    // 方法
    importOpml,
    exportOpml,
    clearImportProgress,
    
    // Mutation 状态
    isImporting: importMutation.isPending,
    isExporting: false, // 导出是同步的，不需要 loading 状态
    importError: importMutation.error,
    importResult: importMutation.data,
  };
}
