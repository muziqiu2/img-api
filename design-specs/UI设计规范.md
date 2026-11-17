# 魔法师随机图片API — UI 设计规范（v2 · Clean Studio）

> 依据 ui-ux-pro-max-skill SOP 产出。v2 为**整套重新设计**：用户要求浅色、不要「AI 味」渐变玻璃风，视觉规范对标 BoardUI / shadcn 类源码级设计系统的浅色主题（BoardUI 为 React/TSX 源码库，无法在本纯 PHP 项目直接调用，故按其视觉语言手写同款令牌落地）。

---

## 一、需求解析

| 字段名 | 内容 |
|--------|------|
| `project_type` | 开发者工具（随机图片 API 服务 + 管理后台） |
| `target_platform` | Web 响应式 |
| `user_persona` | ① 接入 API 的开发者 ② 站长本人 |
| `core_tasks` | ① 复制 API 链接 ② 查看调用量/趋势 ③ 管理图片链接 |
| `brand_tone` | 浅色、克制、组件库质感（参考 BoardUI / shadcn 浅色主题）；明确拒绝暗色与渐变玻璃「AI 味」 |
| `constraints` | 纯 PHP + 本地静态资源（Bootstrap 5.3 + FA6）；保持功能类名/ID 不变以兼容既有 JS |

---

## 二、设计令牌表（Design Tokens）

### 颜色（前后台同一套 `--app-*`）

| 令牌 | 值 | 用途 |
|------|-----|------|
| `--app-bg` | `#f7f8fa` | 页面背景（叠加 24px 点阵纹理 `#e7e9ec`） |
| `--app-surface` | `#ffffff` | 卡片/导航/侧边栏 |
| `--app-surface-2` | `#f4f5f7` | 内嵌灰底（代码行、hover、次级底） |
| `--app-border` / `--app-border-strong` | `#e6e8eb` / `#d5d9de` | 细边 / 强边 |
| `--app-text` / `-2` / `-3` | `#18181b` / `#52525b` / `#9ca3af` | 主文 / 次文 / 弱文（zinc 系） |
| `--app-brand` / `strong` / `soft` | `#2563eb` / `#1d4ed8` / `#eff4ff` | 品牌蓝：链接、主按钮、激活态 |
| `--app-violet` / soft | `#7c3aed` / `#f3efff` | 辅助点缀（移动端、代理模式） |
| `--app-success` / soft | `#059669` / `#ecfdf5` | GET 徽标、成功态 |
| `--app-amber` / soft | `#d97706` / `#fffbeb` | 警告 |
| 危险 | Bootstrap danger `#dc2626` 系 | 删除 |

推导依据：中性 zinc 灰阶 + 单一品牌蓝点缀；黑白主按钮/激活态（`#18181b`）承担「重」色，蓝色只做强调——这是 shadcn 系的典型配色策略。全部文本对比度 ≥ 4.5:1。

### 字体 / 圆角 / 阴影

| 令牌 | 值 |
|------|-----|
| 正文 | `-apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", system-ui` |
| 等宽 | `ui-monospace, SFMono-Regular, Menlo, Consolas`（接口地址、GET 徽标、版本号） |
| 标题 | 650~700 字重、`letter-spacing: -0.02~-0.025em`（组件库惯例的紧排版） |
| `--app-radius` | lg 16px（卡）/ 12px（条目）/ sm 8px（输入、按钮）/ 999px（胶囊徽标） |
| `--shadow-card` | `0 1px 2px rgba(24,24,27,.04), 0 1px 3px rgba(24,24,27,.03)`（近无感） |
| `--shadow-pop` | `0 4px 16px rgba(24,24,27,.08)`（hover 提升、弹层） |

---

## 三、组件规格卡（核心组件）

### Topnav（顶部导航）
sticky + `rgba(255,255,255,.82)` 毛玻璃 + 细下边线；左侧方形黑底 Logo chip + 站名，右侧锚点链接 + 「管理后台」胶囊按钮。移动端隐藏锚点只留按钮。

### Hero
居中；白底细边胶囊徽标（图标品牌蓝）→ 大标题（clamp 1.9~2.75rem）→ 副文案 → 双 CTA（主：品牌蓝实底 42px 圆角 10；次：白底细边）。

### StatCard
白卡 lg 圆角；38px 图标 chip（语义 tinted：灰/蓝/紫）；label .8rem 次文；数值 1.75rem/650 `tabular-nums`；图片数为虚线下划线链接。

### EndpointCard（接口卡，前台新增）
三列网格；头部 = 绿底 `GET` 等宽徽标 + 接口名；中部 = 灰底等宽地址行 + 右浮复制按钮；底部 = 次文说明 + 今日调用次数。

### ApiLink / CopyButton
地址行 `padding: .58rem 74px .58rem .8rem`（右侧为按钮预留）；等宽 .82rem ellipsis；复制按钮 hover 反色为黑底白字（shadcn 式 hover 反转）。

### Accordion / 代码块 / 表格
- 手风琴：独立圆角 12 条目、间距 8px，展开态品牌浅底；`:focus-visible` 焦点环。
- 代码块 `.code-block`：终端风 `#18181b` 底 `#e4e4e7` 文本（文档站惯例）。
- 参数表：表头灰底圆角、行 hover、参数名 `.param-code` 等宽 chip。

### 后台组件
- **small-box 统计卡**：白卡 + 42px 语义色图标 chip（info 蓝/success 绿/primary 紫/warning 琥珀），保留原 `bg-*` 类名零 HTML 改动。
- **侧边栏**：白底；激活项 = 灰底 + 3px 品牌左指示条 + 黑字加粗。
- **分页**：激活页黑底白字（中性重色，避免满屏蓝）。
- **Alert 四态**：全部换为浅色 tinted 底 + 深色文字。
- **Toast**：语义色实底白字，右上角堆叠。

---

## 四、页面布局蓝图

### 前台首页
```
Topnav（sticky 毛玻璃）
Hero（徽标 + 大标题 + 副文案 + 双 CTA）
统计三卡（grid 3col）+ 调用趋势图（白卡）
API 接口三卡（grid 3col，GET 徽标 + 复制）
参数说明 → 调用示例（手风琴×5）→ 访问模式（2col）
Footer（版权左 / GitHub 版本胶囊右）
```
断点：`<900px` 接口卡单列；`<768px` 统计/模式单列、隐藏导航锚点。

### 后台
布局不变（232px 侧边栏 + sticky 顶栏 + 内容区 + 移动端抽屉），仅视觉令牌全量切换。

关键路径：复制接口 = 接口卡复制按钮 → 底部居中 Toast；删图 = 单行/勾选 → danger 确认弹窗 → Toast。

---

## 五、前端实现指引

1. **令牌契约**：所有颜色/圆角/阴影走 `:root --app-*`，前台 `home.css` 与后台 `admin.css` 同名同值；禁止硬编码偏离色。
2. **去 AI 味守则**：不用渐变横幅/玻璃拟态/发光球体/紫色渐变文字；hover 用底色/边框变化而非位移跳动；重色用黑而非彩色。
3. **无障碍（WCAG 2.1 AA）**：全局 `:focus-visible` 3px 蓝环；纯图标按钮带 `aria-label`；动态提示 `role="status" aria-live="polite"`；外链 `rel="noopener"`；装饰图标 `aria-hidden`。
4. **缓存**：静态资源 `?v=APP_VERSION`，改 CSS/JS/图标必须 bump 版本（当前 3.2.3.7）。
5. **JS 健壮性**：定时器 clearTimeout 防堆叠；视口监听挂 `window`；XSS 统一转义。

---

## 六、校验清单（v2 执行结果）

- [x] 前后台令牌一致（同一套 `--app-*`，zinc 灰阶 + 品牌蓝）
- [x] BoardUI 为 React/TSX 源码库不可直接调用 → 按其视觉语言手写落地（已向用户说明）
- [x] 无渐变横幅/玻璃卡/发光装饰（仅 topnav 毛玻璃与浅点阵底纹，均为组件库常规手法）
- [x] 键盘焦点可见、aria 齐全、对比度达标
- [x] `php -l`（index/config）与 `node --check`（home.js）远程通过
- [x] APP_VERSION 3.2.3.7，favicon 品牌色同步 `#2563eb`
