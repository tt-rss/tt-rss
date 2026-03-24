// 用户类型定义
export interface User {
  id: number;
  username: string;
  email: string;
}

// 登录相关类型
export interface LoginCredentials {
  username: string;
  password: string;
  remember?: boolean;
}

export interface LoginResponse {
  accessToken: string;
  refreshToken: string;
  userId: number;
  username: string;
  email: string;
}

export interface RefreshTokenResponse {
  accessToken: string;
  refreshToken: string;
  userId: number;
  username: string;
  email: string;
}

export interface AuthState {
  isAuthenticated: boolean;
  user: User | null;
  token: string | null;
}

// API 错误响应类型
export interface ApiError {
  timestamp: number;
  status: number;
  error: string;
  message: string;
  path: string;
}

export interface Feed {
  id: string;
  title: string;
  url: string;
  description?: string;
  createdAt: Date;
  updatedAt: Date;
}

export interface FeedItem {
  id: string;
  feedId: string;
  title: string;
  link: string;
  description?: string;
  content?: string;
  publishedAt: Date;
  isRead: boolean;
}

// 分类类型
export interface Category {
  id: string;
  title: string;
  parentId?: string | null;
  orderIndex?: number;
  unreadCount?: number;
  children?: Category[] | Feed[];
}

// 订阅源类型（扩展）
export interface FeedWithUnread extends Feed {
  unreadCount?: number;
  faviconUrl?: string;
  categoryId?: string | null;
}

// FeedTree 节点类型（兼容 Mantine TreeNodeData）
export interface FeedTreeNodeData {
  value: string;  // Mantine Tree 需要的唯一标识
  label: string;
  type: 'category' | 'feed';
  id?: string;  // 向后兼容
  unreadCount?: number;
  faviconUrl?: string;
  feedId?: string;
  categoryId?: string;
  children?: FeedTreeNodeData[];
}

// 右键菜单项类型
export interface ContextMenuItem {
  label: string;
  icon?: React.ReactNode;
  onClick: () => void;
  disabled?: boolean;
  danger?: boolean;
}

// FeedTree 组件属性
export interface FeedTreeProps {
  data: FeedTreeNodeData[];
  selectedId?: string | null;
  onSelect?: (id: string, type: 'category' | 'feed') => void;
  onContextMenu?: (event: React.MouseEvent, node: FeedTreeNodeData) => void;
  expandedIds?: string[];
  onExpandChange?: (ids: string[]) => void;
}

// FeedTreeNode 组件属性
export interface FeedTreeNodeProps {
  node: FeedTreeNodeData;
  selectedId?: string | null;
  onSelect?: (id: string, type: 'category' | 'feed') => void;
  onContextMenu?: (event: React.MouseEvent, node: FeedTreeNodeData) => void;
}

// ==================== 文章相关类型 ====================

/**
 * 文章（Feed Item）基础类型
 */
export interface Article {
  id: string;
  feedId: string;
  feedTitle?: string;
  feedFaviconUrl?: string;
  title: string;
  link: string;
  content?: string;
  excerpt?: string;
  imageUrl?: string;
  author?: string;
  publishedAt: string; // ISO 8601 日期字符串
  updatedAt?: string;
  isRead: boolean;
  isStarred: boolean;
  tags?: string[];
}

/**
 * 文章列表查询参数
 */
export interface ArticleListParams {
  page?: number;
  pageSize?: number;
  feedId?: string;
  categoryId?: string;
  isRead?: boolean;
  isStarred?: boolean;
  search?: string;
  orderBy?: 'publishedAt' | 'updatedAt' | 'title';
  orderDirection?: 'asc' | 'desc';
}

/**
 * 搜索查询参数
 */
export interface SearchParams {
  query?: string;
  feedId?: string;
  categoryId?: string;
  page?: number;
  pageSize?: number;
}

/**
 * 搜索结果项（包含高亮信息）
 */
export interface SearchResult extends Article {
  // 高亮字段（可选，由后端返回）
  highlightedTitle?: string;
  highlightedContent?: string;
  highlightedExcerpt?: string;
}

/**
 * 搜索响应
 */
export interface SearchResponse {
  results: SearchResult[];
  total: number;
  page: number;
  pageSize: number;
  hasNextPage: boolean;
  query: string;
}

/**
 * 文章列表响应
 */
export interface ArticleListResponse {
  articles: Article[];
  total: number;
  page: number;
  pageSize: number;
  hasNextPage: boolean;
}

/**
 * 批量操作请求参数
 */
export interface BatchOperationParams {
  ids: string[];
  read?: boolean;
  starred?: boolean;
}

/**
 * 文章操作结果
 */
export interface ArticleOperationResult {
  success: boolean;
  affectedIds?: string[];
  failedIds?: string[];
  message?: string;
}

// ==================== OPML 相关类型 ====================

/**
 * OPML 导入结果
 */
export interface OpmlImportResult {
  success: boolean;
  importedCount: number;
  skippedCount: number;
  message?: string;
}

/**
 * OPML 导入进度
 */
export interface OpmlImportProgress {
  current: number;
  total: number;
  percentage: number;
  message?: string;
}

// ==================== 标签相关类型 ====================

/**
 * 标签
 */
export interface Label {
  id: number;
  caption: string;
  fgColor?: string;
  bgColor?: string;
}

/**
 * 标签表单数据
 */
export interface LabelFormData {
  caption: string;
  fgColor?: string;
  bgColor?: string;
}

/**
 * 标签选择器属性
 */
export interface LabelPickerProps {
  /** 已选中的标签 ID 列表 */
  value?: number[];
  /** 所有可用标签 */
  labels?: Label[];
  /** 是否禁用 */
  disabled?: boolean;
  /** 选择变化回调 */
  onChange?: (labelIds: number[]) => void;
  /** 占位文本 */
  placeholder?: string;
  /** 是否可创建新标签 */
  allowCreate?: boolean;
  /** 创建新标签回调 */
  onCreateLabel?: (caption: string) => Promise<Label>;
}

/**
 * 标签管理器属性
 */
export interface LabelManagerProps {
  /** 是否打开 */
  opened: boolean;
  /** 关闭回调 */
  onClose: () => void;
  /** 提交回调 */
  onSubmit?: (data: LabelFormData) => void | Promise<void>;
  /** 编辑模式时的初始数据 */
  initialData?: Label | null;
  /** 是否正在提交 */
  isSubmitting?: boolean;
}
