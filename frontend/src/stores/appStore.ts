import { create } from 'zustand';

export type ThemeMode = 'light' | 'dark';

interface AppState {
  // 示例状态
  isLoading: boolean;
  setLoading: (loading: boolean) => void;

  // 主题状态
  theme: ThemeMode;
  setTheme: (theme: ThemeMode) => void;
  toggleTheme: () => void;

  // Feed 选择状态
  selectedFeedId: string | null;
  selectedFeedType: 'category' | 'feed' | null;
  setSelectedFeed: (id: string | null, type?: 'category' | 'feed') => void;
  clearSelectedFeed: () => void;

  // FeedTree 展开状态
  expandedFeedIds: string[];
  setExpandedFeedIds: (ids: string[]) => void;
  toggleExpandedFeed: (id: string) => void;
}

export const useAppStore = create<AppState>((set) => ({
  isLoading: false,
  setLoading: (loading) => set({ isLoading: loading }),

  // 主题状态
  theme: 'light' as ThemeMode,
  setTheme: (theme) => set({ theme }),
  toggleTheme: () => set((state) => ({ theme: state.theme === 'light' ? 'dark' : 'light' })),

  // Feed 选择状态
  selectedFeedId: null,
  selectedFeedType: null,
  setSelectedFeed: (id, type = 'feed') => set({ selectedFeedId: id, selectedFeedType: type }),
  clearSelectedFeed: () => set({ selectedFeedId: null, selectedFeedType: null }),

  // FeedTree 展开状态
  expandedFeedIds: [],
  setExpandedFeedIds: (ids) => set({ expandedFeedIds: ids }),
  toggleExpandedFeed: (id) =>
    set((state) => ({
      expandedFeedIds: state.expandedFeedIds.includes(id)
        ? state.expandedFeedIds.filter((fid) => fid !== id)
        : [...state.expandedFeedIds, id],
    })),
}));
