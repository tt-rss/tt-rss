/**
 * HTML 工具函数
 *
 * 提供 HTML 清理、安全渲染等功能
 */

/**
 * 允许使用的 HTML 标签白名单
 */
const ALLOWED_TAGS = new Set([
  // 基础结构
  'div', 'span', 'p', 'br', 'hr',
  // 标题
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
  // 文本格式
  'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del', 'ins', 'mark', 'small', 'sub', 'sup',
  // 列表
  'ul', 'ol', 'li', 'dl', 'dt', 'dd',
  // 引用
  'blockquote', 'q', 'cite', 'pre', 'code',
  // 表格
  'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'col', 'colgroup', 'caption',
  // 链接和图片
  'a', 'img', 'figure', 'figcaption',
  // 多媒体
  'audio', 'video', 'source', 'track',
  // 容器
  'article', 'section', 'header', 'footer', 'nav', 'aside', 'main',
  // 其他
  'abbr', 'address', 'time', 'details', 'summary', 'data',
]);

/**
 * 允许使用的 HTML 属性白名单
 */
const ALLOWED_ATTRIBUTES = new Set([
  // 通用属性
  'id', 'class', 'title', 'style', 'lang', 'dir',
  // 链接属性
  'href', 'target', 'rel', 'download',
  // 图片属性
  'src', 'srcset', 'alt', 'width', 'height', 'loading', 'decoding',
  // 表格属性
  'colspan', 'rowspan', 'headers', 'scope',
  // 多媒体属性
  'controls', 'autoplay', 'loop', 'muted', 'poster', 'preload', 'type',
  // 其他
  'datetime', 'open', 'name', 'role', 'aria-*', 'data-*',
]);

/**
 * 允许使用的 CSS 属性白名单
 */
const ALLOWED_CSS_PROPERTIES = new Set([
  'color', 'background-color', 'background',
  'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration',
  'text-align', 'text-indent', 'line-height', 'letter-spacing',
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
  'border-width', 'border-style', 'border-color', 'border-radius',
  'width', 'height', 'max-width', 'max-height', 'min-width', 'min-height',
  'display', 'position', 'top', 'right', 'bottom', 'left',
  'float', 'clear', 'overflow', 'overflow-x', 'overflow-y',
  'visibility', 'opacity', 'z-index',
  'flex', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items',
  'grid', 'grid-template-columns', 'grid-template-rows', 'gap',
  'list-style', 'list-style-type', 'list-style-position',
  'white-space', 'word-wrap', 'word-break',
  'cursor', 'pointer-events',
  'box-shadow', 'text-shadow',
  'transform', 'transition', 'animation',
]);

/**
 * 危险的 HTML 标签（必须移除）
 */
const DANGEROUS_TAGS = new Set([
  'script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select',
  'style', 'link', 'meta', 'base', 'applet', 'frame', 'frameset',
]);

/**
 * 解析 HTML 字符串为 DOM
 */
function parseHTML(html: string): DocumentFragment {
  const template = document.createElement('template');
  template.innerHTML = html.trim();
  return template.content;
}

/**
 * 检查标签是否在白名单中
 */
function isAllowedTag(tagName: string): boolean {
  return ALLOWED_TAGS.has(tagName.toLowerCase());
}

/**
 * 检查属性是否在白名单中
 */
function isAllowedAttribute(attrName: string): boolean {
  const name = attrName.toLowerCase();
  if (ALLOWED_ATTRIBUTES.has(name)) return true;
  // 支持 aria-* 和 data-* 属性
  if (name.startsWith('aria-') || name.startsWith('data-')) return true;
  return false;
}

/**
 * 清理 CSS 样式，只保留安全的属性
 */
function sanitizeStyle(styleValue: string): string {
  if (!styleValue) return '';

  const declarations = styleValue.split(';');
  const safeDeclarations: string[] = [];

  for (const declaration of declarations) {
    const trimmed = declaration.trim();
    if (!trimmed) continue;

    const colonIndex = trimmed.indexOf(':');
    if (colonIndex === -1) continue;

    const property = trimmed.substring(0, colonIndex).trim().toLowerCase();
    const value = trimmed.substring(colonIndex + 1).trim();

    // 检查是否是允许的属性
    if (ALLOWED_CSS_PROPERTIES.has(property)) {
      // 进一步检查值中是否包含危险内容
      if (!value.toLowerCase().includes('expression') &&
          !value.includes('javascript:') &&
          !value.includes('vbscript:') &&
          !value.includes('data:')) {
        safeDeclarations.push(`${property}: ${value}`);
      }
    }
  }

  return safeDeclarations.join('; ');
}

/**
 * 递归清理 DOM 节点
 */
function sanitizeNode(node: Node): Node | null {
  // 文本节点直接返回
  if (node.nodeType === Node.TEXT_NODE) {
    return node.cloneNode();
  }

  // 只处理元素节点
  if (node.nodeType !== Node.ELEMENT_NODE) {
    return null;
  }

  const element = node as Element;
  const tagName = element.tagName.toLowerCase();

  // 移除危险标签
  if (DANGEROUS_TAGS.has(tagName)) {
    return null;
  }

  // 检查标签白名单
  if (!isAllowedTag(tagName)) {
    // 对于不在白名单的标签，保留其子节点内容
    const fragment = document.createDocumentFragment();
    for (const child of Array.from(element.childNodes)) {
      const sanitizedChild = sanitizeNode(child);
      if (sanitizedChild) {
        fragment.appendChild(sanitizedChild);
      }
    }
    return fragment;
  }

  // 创建新的安全元素
  const safeElement = document.createElement(tagName);

  // 复制并过滤属性
  for (const attr of Array.from(element.attributes)) {
    if (isAllowedAttribute(attr.name)) {
      // 特殊处理 style 属性
      if (attr.name === 'style') {
        const sanitizedStyle = sanitizeStyle(attr.value);
        if (sanitizedStyle) {
          safeElement.setAttribute('style', sanitizedStyle);
        }
      }
      // 特殊处理 href 属性，防止 javascript: 协议
      else if (attr.name === 'href') {
        const hrefValue = attr.value.trim().toLowerCase();
        if (!hrefValue.startsWith('javascript:') &&
            !hrefValue.startsWith('vbscript:') &&
            !hrefValue.startsWith('data:')) {
          safeElement.setAttribute(attr.name, attr.value);
        }
      }
      // 特殊处理 target 属性，强制添加 rel="noopener noreferrer"
      else if (attr.name === 'target') {
        safeElement.setAttribute('target', '_blank');
        safeElement.setAttribute('rel', 'noopener noreferrer');
      }
      else {
        safeElement.setAttribute(attr.name, attr.value);
      }
    }
  }

  // 递归处理子节点
  for (const child of Array.from(element.childNodes)) {
    const sanitizedChild = sanitizeNode(child);
    if (sanitizedChild) {
      if (sanitizedChild.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
        safeElement.appendChild(sanitizedChild);
      } else {
        safeElement.appendChild(sanitizedChild);
      }
    }
  }

  return safeElement;
}

/**
 * 清理 HTML 字符串，防止 XSS 攻击
 *
 * 功能：
 * - 移除危险的 HTML 标签（script, iframe, form 等）
 * - 只保留白名单中的标签和属性
 * - 清理 CSS 样式中的危险内容
 * - 强制链接使用 target="_blank" 和 rel="noopener noreferrer"
 *
 * @param html - 需要清理的 HTML 字符串
 * @returns 清理后的安全 HTML 字符串
 *
 * @example
 * ```ts
 * const dirtyHTML = '<script>alert("xss")</script><p>Hello</p>';
 * const cleanHTML = sanitizeHTML(dirtyHTML);
 * // 输出: "<p>Hello</p>"
 * ```
 */
export function sanitizeHTML(html: string): string {
  if (!html || typeof html !== 'string') {
    return '';
  }

  try {
    const fragment = parseHTML(html);
    const sanitized = document.createDocumentFragment();

    for (const child of Array.from(fragment.childNodes)) {
      const sanitizedChild = sanitizeNode(child);
      if (sanitizedChild) {
        sanitized.appendChild(sanitizedChild);
      }
    }

    const container = document.createElement('div');
    container.appendChild(sanitized);
    return container.innerHTML;
  } catch (error) {
    console.error('HTML sanitization failed:', error);
    return '';
  }
}

/**
 * 创建安全的 HTML 渲染 props
 *
 * 用于 React 的 dangerouslySetInnerHTML
 *
 * @param html - 需要渲染的 HTML 字符串
 * @returns 可用于 dangerouslySetInnerHTML 的对象
 *
 * @example
 * ```tsx
 * <div {...createSafeHTMLProps(article.content)} />
 * ```
 */
export function createSafeHTMLProps(html: string): { __html: string } {
  return {
    __html: sanitizeHTML(html),
  };
}

/**
 * 提取 HTML 纯文本内容
 *
 * @param html - HTML 字符串
 * @returns 纯文本内容
 */
export function extractTextFromHTML(html: string): string {
  if (!html) return '';

  try {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.textContent?.trim() || '';
  } catch {
    return '';
  }
}
