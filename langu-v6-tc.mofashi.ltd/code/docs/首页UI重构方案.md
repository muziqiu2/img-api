# 首页 UI 重构方案

> 撰写日期：2026-09-17　目标文件：`public/index.html`
> 定位：**生产环境首页**（nginx 直接服务的静态文件，站位 `/`，实测 200 / 21,229 B）
> 关联文件：`public/static/css/main.css`、`public/static/js/alpine.js`、`public/static/images/*`
> 所有结论均来自本次实测（含生产服务器核对），非推测；依据见附录 A。

---

## 0. 结论摘要

1. **最大的障碍不是设计，而是样式体系。** `public/static/css/main.css` 是一份**不可重建的 Tailwind v3 JIT 子集**（仅 232 条规则）。`block`、`rounded-full`、`shadow-lg`、`hover:bg-white`、`sm:grid-cols-3`、`group-hover:*`、`peer-checked:*` 全都不存在。**重构时写任何新 Tailwind 类都会静默失效、不报错。** 这是第一优先级要解决的事。
2. **当前页面已存在 15 处可复现缺陷**，其中 3 处影响真实观感（2 个装饰光斑完全不可见、3 张插图不缩放导致裁切、第 4 张插图垂直被裁 20px），1 处影响可用性（**首页没有任何登录入口**），1 处是结构性错误（**页脚被塞在最后一个内容区块内部，且不是 `<footer>`**）。
3. **首屏 1.70 MB，其中 67% 是纯浪费**：未使用的 `alpine.js` 77 KB、22 倍像素冗余的 logo 562 KB、61 倍像素冗余的 favicon 533 KB。这三项不涉及任何设计决策，可独立先修。
4. **建议把首页样式从「Tailwind 子集」改为「手写组件化 CSS + 设计令牌」**，并获得一条额外收益：能与已是成品的 `upload/index.html` 共用同一套令牌，解决两页视觉不一致的问题（`upload/index.html` 的 `--paper:#fbfaf7` 与已有风格稿 `style_d_japanese` 的 `--bg` 完全相同，方向本就在收敛）。

---

## 1. 现状盘点

### 1.1 页面骨架与体量

| 项 | 值 |
|---|---|
| 文件 | `public/index.html`，281 行 / 21,229 B |
| 样式 | 1 个外链（`static/css/main.css`）+ 1 段内联 `<style>`（58 行） |
| 脚本 | 1 个外链（`static/js/alpine.js`）+ 1 段内联脚本（运行时长） |
| 图片 | 6 个本地（`static/images/`）+ 2 个外链（首屏背景，桌面/移动各一） |
| 与其他页共享 | **无**。`main.css` 全项目仅被 `index.html` 与 `index-副本.html` 引用 |

区块流（自上而下）：

```
[全屏固定背景层 site-bg]
[header]  logo  ────────────  赞助药丸          ← 没有 <header> 标签，只有一个 div
[hero]    渐变标题 + 副标题 + CTA「上传图片」→ /index.php      ← h-screen 锁死高度
[features] 「探索更多的可能。」+ 4 张功能卡（SVG + 标题 + 描述）
[showcase] 「好评如潮的图床程序」        图文（图右）
[showcase] 「难以想象的全球加速网络」    图文（图左）
[showcase] 「永不宕机的运行架构」        图文（图右）
[showcase] 「土豆熟了没一目了然」        图文（图左）
           └─ 备案 ×2 + 运行时长  ← ★ 页脚被写在最后一个 showcase 内部，共用一个 div
```

四个 showcase 的外层 class 字符串**逐字相同**（`relative bg-white text-gray-900 overflow-hidden py-16 md:pt-28 dark:bg-slate-900`），仅第 2、4 个把 `py-16` 换成 `pt-12 pb-16`。——这是最明显的组件抽取点。

### 1.2 样式体系：一个不可重建的 Tailwind 子集 ← 核心约束

```
public/static/css/main.css
├─ 头部注释：tailwindcss v3.0.23（非压缩、带注释的正式构建产物）
├─ 1,387 行 / 23,202 B
├─ 232 条选择器规则
└─ 残留着「上一版首页」才用的类
   bj.webp 全屏照片版 → bg-fixed / backdrop-brightness-90 / col-span-12 / break-inside-avoid-column
```

**为什么不可重建：**

| 检查项 | 结果 |
|---|---|
| `tailwind.config.js` 的 `content` 扫描范围 | 只有 `./resources/views/**/*.blade.php`、pagination、`storage/framework/views/*.php` — **不含 `public/*.html`** |
| 本地 `node_modules/` | **不存在** |
| 本地是否安装过 tailwindcss | **未安装** |
| `webpack.mix.js` 的输出目标 | `public/css/app.css` — **不是** `public/static/css/main.css` |

也就是说：`public/static/` 是一套**独立于 Laravel Mix 构建管线的手工维护静态站点包**，`main.css` 是当时用某个外部 Tailwind 构建器扫出来的产物，配置和输入端都已不在仓库里。

**已确认失效的常用类**（抽查 28 个）：

| 类 | main.css | 类 | main.css |
|---|---|---|---|
| `flex` / `grid` / `hidden` | ✔ | `block` | ★ 无 |
| `items-center` / `justify-between` | ✔ | `rounded-full` / `rounded-2xl` | ★ 无 |
| `text-center` / `transition-colors` | ✔ | `shadow-lg` / `shadow-2xl` | ★ 无 |
| `dark:text-slate-400` | ✔ | `hover:bg-white` / `sm:grid-cols-3` | ★ 无 |
| `h-[300px]` / `w-[640px]` / `opacity-[.15]` | ✔ | `peer-checked:*` | ★ 无（0 条） |

变体族的实际覆盖（本次逐个数过）：`lg:` 20 条、`sm:` 9 条、`md:` 8 条、`dark:` 7 条、`xl:` 5 条，
而 `group-hover:` 只有 2 条、`hover:` 1 条、`focus:` 1 条，`focus-visible:` / `active:` / `2xl:` / `peer-*:` **全为 0 条**。
也就是说：变体**不是全缺，而是残缺**——恰好只够现有页面用。任何新加的 `hover:`/`focus-visible:` 反馈几乎必然落空。

> 注意：任意值类（`h-[300px]` / `w-[640px]` / `opacity-[.15]` / `left-[-20%]`）以及变体任意值类
> （`sm:max-w-[480px]`、`lg:inline-flex`）**都是存在的**。不要凭"看起来像新语法"就断定它一定缺——
> 必须逐个按转义写法实查（判定方法见附录 A，这里我第一遍就查错过）。
> 真正缺的自定义/工具类只有 5 个：`bg-radial-gradient`、`bg-top-left`、`icon`、`inline-block`、`text-md`。

**好消息**：`main.css` 不被任何 Laravel 页面引用，所以**改造它不会波及博客/图床应用本体**。

### 1.3 已确认的现存缺陷（15 项）

证据等级：**实测**＝已在本地/生产核对；全部缺陷均可复现。

| # | 缺陷 | 证据 | 影响 |
|---|---|---|---|
| 1 | **两个装饰光斑完全不可见** | `bg-radial-gradient` 在 CSS 中**无任何规则**（反查确认 CSS 里不含 `radial` 字样）→ 元素在 ≥1024px 确实渲染为 `inline-flex`，但没有任何背景，视觉上不可见 | 设计意图整体丢失，白留 2 个 DOM 节点 |
| 2 | **3 张插图不缩放** | `0755dbda7d4f1.png`(600×300)、`c265158cb1abe.png`(558×280)、`d0ae251bc8917.png`(609×270) 所在盒子的 `style` 里**只有 `background-image`，没有 `background-size`**，容器却是 `w-full` | 视口变窄时图片右侧被裁；第 4 张 270px 高放进 `h-[250px]` 盒子，**垂直裁掉 20px**。目前"看起来正常"是原图宽 600px ≈ 容器宽的巧合 |
| 3 | 正文 `text-md` 类不存在 | Tailwind 只有 `text-base`，无 `text-md` | 移动端字号未按设计生效（回退到继承值） |
| 4 | 4 个功能卡 SVG 的 `class="icon"` 无定义 | `.icon` 既不在 `main.css` 也不在内联 `<style>` | 死类（靠 `width/height` 属性兜住，目前不显形） |
| 5 | 赞助药丸 SVG 的 `inline-block` 无定义 | 同上 | 死类 |
| 6 | `bg-top-left` 不是 Tailwind 类 | 正确写法 `bg-left-top` | 恰好默认值就是左上，无害但是错的（会误导后续维护） |
| 7 | **页脚不是 `<footer>`，且写在最后一个内容区块内部** | 备案 ×2 + 运行时长三块位于最后一个 `<div class="relative bg-white …">`（234–279 行）内 | 语义缺失；改版时极易误删；屏幕阅读器没有地标 |
| 8 | 整页零语义地标 | 无 `<main>`、无 `<nav>`；"header" 是个 `<div class="relative z-50">`；无 `<footer>` | a11y / SEO 均受损 |
| 9 | **首页没有任何登录入口** | 全页链接仅 `/`、`/index.php`、`afd.mofashi.ltd`、两个备案站 | 老用户无法从首页登录，只能手输 `/login` |
| 10 | `h-screen` 锁死首屏高度 | `<section class="… h-screen">` 内配 `pt-48` | 桌面端首屏底部大片空白；移动端 `100vh` ≠ 可视高度——背景层已用 `100dvh` 修正，容器没跟上 |
| 11 | 运行时长脚本重复实现，且存在孤立文件 | 内联脚本（259–277 行）与 `static/js/custom.js` 逻辑相同；`custom.js` **全项目无人引用** | 双份实现；`custom.js` 是死文件 |
| 12 | 使用废弃 HTML | `<center>` 元素、`<font>`（由 JS 注入）、`<script language="javascript">` | 已废弃标签，Stylelint/HTML 校验不过 |
| 13 | 无 `prefers-reduced-motion` 降级 | 页面有 `transition-transform` / `group-hover:-translate-y-1` 等动效 | 对晕动症用户不友好 |
| 14 | 硬编码 URL 散布 | `tc.mofashi.ltd` ×4、首屏背景图 URL ×4（preload + og + CSS 桌面/移动） | 换域名或换图要改 4 处以上 |
| 15 | 内联样式 12 处 / 1,085 字符（占全文 5.6%） | 最大一处 377 字符（赞助药丸） | 无法复用、无法做状态变体 |

### 1.4 性能基线（首次访问）

| 资源 | 体积 | 评价 |
|---|---|---|
| `index.html` | 21,229 B（gzip **7,028 B**） | ✔ 服务端 gzip 已生效 |
| `static/css/main.css` | 23,202 B | 冻结子集，仅 232 条规则 |
| `static/js/alpine.js` | **77,000 B** | ★ 首页 **0 个** `x-*` / `@` / `:` 绑定 → 纯浪费 |
| `static/images/logo-horizontal.png` | **562,241 B** | ★ 原图 **3370×1123**，显示宽 150px → **22 倍像素冗余** |
| `static/images/logo-icon.png`（favicon） | **532,887 B** | ★ 原图 **1946×1946**，显示 32px → **61 倍像素冗余** |
| 首屏背景 webp（外链） | 544,082 B（桌面）/ 499,918 B（移动） | 即 LCP 元素，已有按端分图 + preload |
| **合计** | **≈ 1.70 MB** | |

> **1.145 MB（67%）为纯浪费**：`alpine.js` + 两个 logo。这三项不需要任何设计决策即可独立优化，建议最先做（见 §5.1 P0-1）。

---

## 2. 整体布局与结构的调整思路

### 2.1 目标信息架构

```
<body>
 ├─ .site-bg                      固定背景层（保留现有实现，含 100dvh 修正）
 ├─ a.skip-link                   跳到主要内容（新增）
 ├─ <header class="site-header">  语义化 header
 │    ├─ logo（<img> 带 width/height，防布局抖动）
 │    ├─ <nav>  上传 · 我的图片 · 登录/控制台        ★ 新增，补上缺失的登录入口
 │    └─ 赞助药丸（抽出为 .pill--ghost 组件）
 ├─ <main id="main">
 │    ├─ <section class="hero">        内容驱动高度，替换 h-screen
 │    ├─ <section class="features">    4 张功能卡
 │    └─ <section class="showcase"> ×4 同一组件，靠 --reverse 镜像
 └─ <footer class="site-footer">   从内容区块里拆出来
      ├─ 备案 ×2（一个 nav 内的链接组）
      └─ 运行时长（外链 JS，删除内联脚本）
```

**结构层面的四个决策：**

1. **页脚必须独立。** 现在它与第 4 个 showcase 共用外层 div，等于「改最后一段文案有可能顺手删掉备案」。这是合规信息，风险不可接受。
2. **Hero 去掉 `h-screen`。** 改成 `min-height: min(100svh, 780px)`（`svh` 而非 `vh`，与背景层已做的 `dvh` 修正保持同一思路），并让内容自然撑开。理由：当前 `h-screen` + 内部 `pt-48` 的组合在 1080p 桌面端会在首屏底部留出约 200px 空白，而在小屏手机上 CTA 按钮有被推出可视区的风险。
3. **四个 showcase 合并为一个组件**，用 `data-reverse` 或 `.showcase--reverse` 控制图文左右。当前是靠 `lg:order-last` 硬编码在 HTML 结构里，导致"想换一张图的位置"要动 DOM 顺序——换成 CSS 控制后，DOM 顺序与视觉顺序解耦，也顺带修正了「视觉顺序与源码顺序不一致」这个 a11y 隐患（屏幕阅读器现在读到的顺序与视觉不一致）。
4. **补齐导航。** 首页目前**没有任何登录入口**，这是可用性缺陷而非风格问题。建议至少在 header 放一个「登录 / 控制台」链接（可用 `Auth::check()` 的思路——但注意这是静态文件，需用 JS 探测或直接两个链接都放、由服务端 302 兜住）。

### 2.2 逐区块调整清单

| 区块 | 保留 | 调整 |
|---|---|---|
| 背景层 `.site-bg` | 现有实现（`fixed` + `100dvh` + 按端分图）很好，**不动** | 仅把图片 URL 提到 CSS 自定义属性，便于换图 |
| Header | logo + 赞助药丸的布局关系 | 加 `<header>`/`<nav>` 语义；补登录入口；logo 换小体积格式并加 `width`/`height`/`decoding="async"` |
| Hero | 文案、CTA 目标 `/index.php` | 去掉 `h-screen`；`text-md` → `text-base`；装饰光斑二选一（实现或删除）；渐变标题补 `-webkit-background-clip` 兜底并检查浅色图上的对比度 |
| Features | 「探索更多的可能。」+ 4 卡的文案 | 4 张卡抽成循环/组件；SVG 统一为同一套图标尺寸与描边风格（现在 4 个 SVG 的 viewBox 各不相同：1316×1024 / 1024×1024 / 1152×1024 / 1152×1024，视觉重心不齐） |
| Showcase ×4 | 文案与配图 | 合并为单组件；补 `background-size: cover` 或改 `<img>`；`h-[300px]` 等硬高度改为 `aspect-ratio`；DOM 顺序与视觉顺序解耦 |
| Footer | 备案信息、运行时长 | 拆为独立 `<footer>`；`<center>`→`text-align:center`；`<font>`→`<span>`；内联脚本改为引用 `static/js/uptime.js`；`<script language>` 属性删除 |

### 2.3 语义化与可访问性

| 项 | 现状 | 目标 |
|---|---|---|
| 地标 | 0 个 | `<header>` / `<nav>` / `<main>` / `<footer>` 齐全 |
| 跳转链接 | 无 | `.skip-link`（聚焦时可见） |
| 标题层级 | h1 → h2 → h3 ✔ | 保持 |
| 装饰元素 | 光斑无 `aria-hidden`（背景层有 ✔） | 全部 `aria-hidden="true"` |
| 图片 | logo 有 `alt` ✔，但无 `width`/`height` | 补尺寸属性，消除 CLS |
| 动效 | 无降级 | `@media (prefers-reduced-motion: reduce)` 关闭位移动画与背景 `fixed` |
| 颜色对比 | 白字压在高亮照片上（hero），有风险 | 给 hero 加半透明遮罩层，保证正文对比度 ≥ 4.5:1 |
| 键盘可达 | CTA 是 `<a>` ✔；赞助药丸是 `<a>` ✔ | 保持；新增开关/导航需可 Tab 聚焦 |

---

## 3. 主要视觉样式的优化方向

### 3.1 建立设计令牌层（token）

当前所有颜色、间距、圆角、阴影都是零散的 Tailwind 类名和 12 处内联样式，没有一处是"变量"。建议第一步就抽出令牌层：

```css
:root {
  /* 色彩：语义命名，不按外观命名 */
  --paper:      #fbfaf7;   /* 页面底色（与 upload/index.html 完全一致，见 §3.2） */
  --surface:    #ffffff;
  --ink:        #232018;   /* 主文字 */
  --ink-2:      #585854;   /* 次要文字 */
  --ink-3:      #9a9993;   /* 弱化文字 */
  --line:       rgba(22,22,22,.09);
  --accent:     #1f6f5d;   /* 强调色（与 upload/index.html 一致） */
  --accent-soft:rgba(31,111,93,.10);
  --danger:     #c8443a;

  /* 排版 */
  --font-sans:  "Instrument Sans", -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif;
  --font-serif: "Fraunces", Georgia, "Songti SC", serif;
  --fs-hero:    clamp(2rem, 5vw, 3.75rem);
  --fs-h2:      clamp(1.5rem, 3vw, 2.25rem);

  /* 尺度 */
  --container:  1120px;
  --gutter:     clamp(20px, 5vw, 40px);
  --gap:        clamp(24px, 4vw, 56px);
  --radius:     18px;
  --radius-sm:  11px;
  --shadow:     0 1px 2px rgba(22,22,22,.04), 0 18px 40px -24px rgba(22,22,22,.18);

  /* 动效 */
  --ease:       cubic-bezier(.22,.8,.3,1);
  --dur:        .22s;
}
```

好处：换肤（含暗色）只需改变量；`upload/index.html` 可直接复用同一份 `:root`。

### 3.2 风格基线的选择

项目里现在有 **5 套并存的视觉语言**，必须先收敛，否则重构只是把混乱换个地方：

| 来源 | 底色 | 强调色 | 特征 |
|---|---|---|---|
| 当前首页 `public/index.html` | 白 + 全屏照片 | sky-500 蓝 | Tailwind 模板血统，重照片 |
| `preview/style_a_corporate` | — | — | 企业商务 |
| `preview/style_b_minimal` | `#fafafa` | `#111` 黑白 | 直角、无阴影、细线 |
| `preview/style_c_bento` | `#f6f5f2` | `#2f6f4f` 绿 | 便当盒网格 |
| `preview/style_d_japanese` | **`#fbfaf7`** | `#4f7ea0` 蓝灰 | 大留白、圆润、浅色 |
| `upload/index.html`（已成品） | **`#fbfaf7`** | **`#1f6f5d` 绿** | Fraunces 衬线标题 + IBM Plex Mono 数字 |

**建议：以 `upload/index.html` 的设计语言为唯一基线。**

理由：
- 它已经是**完成品且经过你确认**（上一轮的压缩开关样式就是从它复刻的），把首页统一过去是"向既有标准收敛"，而不是再造一套；
- 它的 `--paper: #fbfaf7` 与 `style_d_japanese` 的 `--bg` **完全相同**，`--accent: #1f6f5d` 与 `style_c_bento` 的 `#2f6f4f` 同属绿色系——说明你当时已经在朝这个方向收敛；
- 一次收敛同时拿到三个收益：首页与上传页视觉一致、设计令牌可以跨页共用、将来若要合并两页成本最低。

**唯一需要单独决策的是首屏背景。** 当前首页靠一张 544 KB 的全屏照片撑起第一印象，而上传页是纯 `--paper` 底。两个方向：
- **保守（推荐先做）**：保留照片，但加半透明遮罩 + 缩小体积，作为首页与内页的差异化表达；
- **激进**：放弃照片，改用 `--paper` 底 + 排版张力（更接近上传页，也与 style_b/d 的方向一致，且能砍掉 544 KB LCP）。

### 3.3 具体视觉优化点

| 位置 | 现状 | 建议 |
|---|---|---|
| 首屏 Logo | `filter: drop-shadow(0 1px 3px rgba(255,255,255,.95)) drop-shadow(0 0 12px rgba(255,255,255,.55))` —— 双层白描边滤镜硬压照片 | 改用统一遮罩保证对比度；滤镜方案在浅色照片上会糊成白块 |
| 首屏标题 | `bg-clip-text text-transparent` 渐变文字（`from-aka-default`→`to-aka-dark`） | 渐变文字在照片上对比度不可控；建议改为纯色 + 遮罩，或仅在遮罩足够的区域使用渐变 |
| 装饰光斑 | 2 个 span 当前**完全不可见**（成因单一：`bg-radial-gradient` 无规则） | 要么实现 `.glow` 组件（径向渐变 + 模糊），要么删除——不要保留"看起来在起作用的死元素" |
| 插图盒子 | 3/4 张无 `background-size`，靠原图尺寸恰好贴合 | 统一改 `aspect-ratio` + `object-fit: cover` 的 `<img>`（比 `background-image` 更利于 LCP、可加 `alt`、可懒加载） |
| 功能卡图标 | 4 个 SVG 的 viewBox 不一致，颜色全为硬编码 `#1296db` | 统一为同一套线性图标（24×24 网格、`currentColor` 描边），颜色交给 `--accent` |
| 圆角/阴影 | 卡片用 `rounded-lg`，赞助药丸用 `999px`，插图用 `rounded-lg` | 收敛到 `--radius` / `--radius-sm` 两档 |
| 暗色模式 | `@media (prefers-color-scheme: dark)` + 每个元素写 `dark:` 类 | 改为令牌层覆盖（`@media … { :root { --paper: … } }`），一处生效全域，避免漏写 |
| 正文与首屏衔接 | 首屏（照片）→ 正文（纯白）是硬切 | 首屏底部加渐变到 `--paper` 的过渡，或让正文底色直接用 `--paper` 与上传页对齐 |

### 3.4 动效

- 统一时长与缓动到 `--dur` / `--ease`（现在是各写各的：`.2s`、`.25s`、`.15s`）。
- 保留 CTA 的 `translateY(-1px)` 悬停反馈，但补 `:focus-visible` 同款反馈（现在只有 `group-focus`，键盘用户拿不到等价的视觉反馈）。
- 新增 `@media (prefers-reduced-motion: reduce)`：关闭位移与固定背景（`position: static`），仅保留颜色变化。

---

## 4. 组件的拆分与复用方式

### 4.1 三层结构

```
第 1 层  令牌（Token）    :root 变量           ── 跨页复用（首页 + 上传页）
第 2 层  组件（Component） .btn / .card / .pill / .field / .badge  ── 页面内复用，也可跨页
第 3 层  区块（Section）   .hero / .features / .showcase / .site-footer ── 首页专用
```

**关键原则：组件边界画在 CSS 上，而不是画在 HTML 里。**

这是本方案与"用 JS 动态渲染 HTML"路线的核心分歧。对一个 281 行的 SEO 关键型静态落地页，把区块做成客户端 JS 渲染会：让首屏依赖 JS（拖慢 LCP）、破坏无 JS 场景、损害 SEO。因此：

- **重复度低（≤4 次）的区块**：在 HTML 里写全，但用统一的 class 命名 + 注释锚点标记，靠 CSS 组件控制外观；
- **需要跨页复用的部分**（header / footer / 令牌）：用「共享 CSS 文件」复用，HTML 各写一份；
- **只有确实增长到维护不动时**，才引入 partial 拼装（见 §4.3 方案乙）。

### 4.2 组件清单

| 组件 | class | 使用次数 | 现状 | 抽取动作 |
|---|---|---|---|---|
| 按钮-实心 | `.btn .btn--solid` | 1（CTA） | 4 层嵌套 span 实现"位移"效果 | 简化为 1 个元素 + `box-shadow` 模拟偏移，或保留但收敛 |
| 按钮-幽灵 | `.pill--ghost` | 1（赞助药丸） | **377 字符内联样式** | 提到 CSS，纯 class |
| 面板/卡片 | `.card` | 4（功能卡）+ 4（插图盒） | 只有 Tailwind 类，无组件概念 | 建立 `.card` 基础类 + `.card--feature` / `.card--media` 变体 |
| 区块标题 | `.section-title` | 5（h2） | 每次都重写 `text-4xl font-bold md:text-5xl dark:text-white` | 抽为 1 个类 |
| 区块容器 | `.container` | 8 | 反复写 `max-w-screen-xl w-full mx-auto px-5` | 抽为 1 个类，宽度交给 `--container` |
| 图文区块 | `.showcase` | 4 | 外层 class 逐字相同，靠 `lg:order-last` 镜像 | 抽为 1 个组件 + `--reverse` 修饰符 |
| 装饰光斑 | `.glow` | 2 | **完全不可见** | 实现或删除 |
| 备案链接 | `.beian-link` | 2 | 内联 `display:inline-flex; align-items:center` | 抽为 1 个类 |
| 表单/输入 | `.field` | 0（首页无表单） | — | 预留，将来若把登录框放在首页可复用上传页的 `.field` |

### 4.3 复用机制选型

三种可选做法，按"引入的复杂度"排序：

| | 方案甲：纯手写 CSS + 命名约定 | 方案乙：partial 拼装 + 本地小构建 | 方案丙：重建 Tailwind |
|---|---|---|---|
| **做法** | 单文件 HTML + 手写组件化 CSS，重复区块手写 | 拆 `partials/{header,footer,showcase}.html`，用 ~40 行的本地 Node/Python 脚本拼成 `index.html` 后部署 | 补 `tailwind.config.js` 的 content 源、安装 tailwind、重新构建 `main.css` |
| **改动量** | 中（重写 CSS，HTML 结构调整） | 中 + 新增构建步骤 | 小到大（依赖能否装成功） |
| **日常维护** | 改一处即上线，零工具 | **必须先跑构建再部署**，否则源与产物不一致 | 每次改 class 都要重新构建 |
| **踩坑风险** | 无（不会再有静默失效的类） | 有人直接改产物 → 下次构建覆盖，改动丢失 | 需装 ~300–500 MB `node_modules`；构建器版本漂移；仍可能漏扫 |
| **产物体积** | CSS 约 3–6 KB（可压到 <2 KB gzip） | 同甲 | 视扫描范围，可能比现在大得多 |
| **适用前提** | 页面数量少、手写可维护 | 出现 ≥6 个重复区块，或第 2 个页面要共享同一套 header/footer | 团队本来就熟悉 Tailwind 且打算长期用工具类 |

**推荐：方案甲。** 理由：页面只有 1 个、重复单元只有 4+4 个、部署方式是"传一个文件"（生产方式已验证过），引入构建步骤的收益小于风险。方案甲还顺带解决了本方案最大的隐患——不再存在"写了不生效的类"。

**若将来要做方案乙**，需同时满足：首页与 `upload/index.html` 都上线并需要共享 header/footer，且有 ≥6 个重复区块。那时再引入不迟。

### 4.4 与 `upload/index.html` 的跨页复用

这是本方案额外的收益点。两页目前的重叠与差异：

| 项 | 首页 | upload 页 | 可复用 |
|---|---|---|---|
| 底色 | `#ffffff`（正文）+ 照片 | `#fbfaf7` | ✔ 统一到 `--paper` |
| 强调色 | `sky-500` `#0ea5e9` | `#1f6f5d` | ✔ 统一到 `--accent` |
| 字体 | `font-sans`（Nunito） | Instrument Sans + Fraunces + IBM Plex Mono | ✔ 统一字体栈 |
| 圆角 | `rounded-lg` 8px | 18px / 11px | ✔ 统一到 `--radius` |
| 按钮 | 1 个（CTA） | `.btn` / `.start` | ✔ 抽出共用 `.btn` |
| 卡片 | `.card` | `.item` | 各自保留，但共用圆角/描边/阴影令牌 |
| 开关/表单 | 无 | `.comp` / `.field` | 若首页加登录框可直接复用 |
| header | logo + 赞助药丸 | 品牌区 + 模式药丸 | ✘ 形态不同，只共用令牌 |

落地方式：新建 `public/static/css/tokens.css`（只有 `:root`），首页与上传页各引一次；各自的页面样式文件引用令牌。这样"统一视觉"是**修改一个文件**的事，而不是两处对齐。

---

## 5. 改动优先级与实施步骤

### 5.1 优先级矩阵

| 优先级 | 项 | 收益 | 风险 | 依赖 |
|---|---|---|---|---|
| **P0-1** | 删除未使用的 `alpine.js` 引用（77 KB）；logo 转 WebP 在显示尺寸 2× 导出（562→约 15 KB）；favicon 换成 32×32（533→约 2 KB） | 首屏 **1.70 MB → 约 0.57 MB（−67%）** | 极低（不涉及布局） | 无 |
| **P0-2** | 修 3 张插图的 `background-size`（或改 `<img>` + `aspect-ratio`） | 消除裁切，观感立即正确 | 低 | 无 |
| **P0-3** | 首屏去掉 `h-screen`；`text-md`→`text-base` | 修掉移动端 CTA 出屏风险与桌面大空白 | 低 | 无 |
| **P0-4** | 页脚拆出独立 `<footer>` | 消除"改文案误删备案"的结构性风险 | 低 | 无 |
| **P1-1** | 首页补登录入口 | 修可用性缺陷 | 低（纯新增） | 无 |
| **P1-2** | 2 个装饰光斑：实现或删除 | 消除死元素 | 低 | 无 |
| **P1-3** | 建立令牌层 + 替换 `main.css` 为手写组件化 CSS（方案甲） | 根除"类静默失效"；CSS 23 KB→约 4 KB；与上传页统一 | **中**（样式全量重写） | P0-1（图片先定型） |
| **P1-4** | 4 个 showcase 合并为单组件 + DOM 顺序解耦 | 可维护性 + a11y | 中 | P1-3 |
| **P1-5** | 语义化：`<header>`/`<nav>`/`<main>`/`<footer>` + skip link | a11y / SEO | 低 | P0-4 |
| **P2-1** | 暗色模式改令牌驱动 | 消除漏写 `dark:` 类 | 低 | P1-3 |
| **P2-2** | `prefers-reduced-motion` 降级 | a11y | 低 | P1-3 |
| **P2-3** | 删除孤立的 `custom.js`，运行时长脚本外链化；清 `<center>`/`<font>` | 清理 | 低 | 无 |
| **P2-4** | 硬编码 URL 提到 CSS 变量 / 数据属性 | 换域名成本 | 低 | P1-3 |
| **P3** | 首屏背景图减体积（AVIF / 降质 / 换静态底） | 再省 300–500 KB LCP | 中（影响首屏观感，需你定方向） | 需先定 §3.2 的方向 |
| **P3** | 4 个功能卡 SVG 统一图标网格与描边风格 | 观感一致性 | 低（但需设计取舍） | 无 |

### 5.2 分阶段实施

**Stage 0 · 准备（5 分钟）**
- 记录基线：`index.html` md5 = `7030df910b2bdd5a7061b8b2d26806f6`，`main.css` md5 = `ac85f3c0f61145441a1b8da15d91f08b`（两者已与生产逐字节核对一致）
- 生产端备份：`cp public/index.html{,.bak_$(date +%Y%m%d_%H%M%S)}`
- 建本地预览：起一个静态服务器，改完先在本地看效果（见下方"本地预览"）

**Stage 1 · 零风险修复（P0-1 ~ P0-4）**
只动资源、不动视觉体系。可一次上线，随时回滚。
- 导出 logo：`logo-horizontal` → 300px 宽 WebP（2× 于 150px 显示）；favicon → 32×32 PNG 或 SVG
- 删除 `alpine.js` 的 `<script>` 标签
- 3 张插图盒补 `background-size: cover; background-position: center`（这一步先做最小改动，改 `<img>` 留到 Stage 2）
- hero 的 `h-screen` → `min-height: min(100svh, 780px)`；`text-md` → `text-base`
- 页脚三块移出最后一个 section，包进 `<footer>`
- **上线后必查**：首页返回 200 且字节数变化符合预期；备案文字与运行时长仍正常；移动端首屏 CTA 在可视区内

**Stage 2 · 结构重组（P1-1 ~ P1-2、P1-5）**
- 补 `<header>` / `<nav>` / `<main>` / `<footer>` / skip link
- 补登录入口
- 光斑二选一
- `custom.js` 清理、`<center>`/`<font>` 替换
- **此阶段不动 CSS 文件**，靠现有类完成，确保"结构和样式"两类改动不互相干扰

**Stage 3 · 样式体系换血（P1-3、P1-4）**
这是最大的一步，建议：
1. 先落 `tokens.css`（只有 `:root`），页面暂时仍用 `main.css`——此步零视觉变化，用于验证令牌值正确
2. 逐区块把手写 CSS 写上，每完成一个区块就在本地预览比对一次（hero → features → showcase → footer）
3. 全站切换后删除 `main.css` 引用
4. 4 个 showcase 合并为单组件
- **上线前必做**：本地逐断点（375 / 768 / 1024 / 1440 / 1920）截图比对；暗色模式单独看一遍

**Stage 4 · 收尾（P2、P3）**
- 暗色令牌、`prefers-reduced-motion`、URL 变量化、图标统一
- 首屏背景图优化（需先定方向）

### 5.3 发布与回滚

**发布方式**（已验证可用）：文件在本地改好 → `scp` 到服务器临时目录 → 校验 md5 → 备份原文件 → 替换 → 清缓存。本机已具备免密通道：

```bash
unset HTTP_PROXY HTTPS_PROXY ALL_PROXY http_proxy https_proxy all_proxy
scp -o BatchMode=yes public/index.html langu-v6:/tmp/mofashi_deploy/
ssh -o BatchMode=yes langu-v6 '...'   # 校验 md5 + 备份 + 替换
```

**两个必须注意的缓存问题（实测）**：

| 资源 | 响应头 | 后果 |
|---|---|---|
| `index.html` | **无 `Cache-Control`、无 `Expires`**，只有 `ETag` / `Last-Modified` | 浏览器每次会发条件请求，改动**立即生效** ✔ |
| `static/css/main.css` | `Cache-Control: max-age=43200`（**12 小时**） | ★ **改 CSS 后老访客最长 12 小时看不到变化** |

→ 因此改 CSS 时必须做**缓存失效**：把引用改成 `main.css?v=20260917`（或改名 `main.20260917.css`）。**不要**只依赖刷新——`max-age` 未过期时浏览器根本不会发请求。

**回滚**：还原 `index.html`（或还原 CSS）即可，无需重启任何服务。这是静态页面的最大优势——发布链路里没有 PHP、没有 artisan、没有缓存重建。

### 5.4 验收清单

- [ ] 首页 `GET /` 返回 200，`Content-Length` 与预期一致
- [ ] 首屏照片、logo、4 张插图全部正常显示，无裁切、无留白
- [ ] 375 / 768 / 1024 / 1440 / 1920 五个宽度下无横向滚动条、无元素重叠
- [ ] 移动端首屏 CTA 按钮在初始可视区内
- [ ] 暗色模式（`prefers-color-scheme: dark`）下文字可读、无白底黑字残留
- [ ] `prefers-reduced-motion: reduce` 下动效关闭且布局不错乱
- [ ] 备案号、备案链接、运行时长正常显示且数值在走动
- [ ] 键盘 Tab 可依次到达 logo / 导航 / CTA / 赞助 / 备案链接，焦点可见
- [ ] HTML 校验：无 `<center>` / `<font>` / `language=` 属性
- [ ] 首屏传输体积对比基线（目标 ≤ 0.6 MB）
- [ ] 生产 `index.html` md5 与本地一致；`main.css` 引用已带版本号
- [ ] 备份文件在位，回滚命令已验证

---

## 6. 风险与注意事项

1. **最大的隐性风险是"写了不生效的类"。** 在 Stage 3 完成前，你写的任何新 Tailwind 类（尤其 `block`、`rounded-full`、`shadow-*`、`hover:*`、`sm:/md:/lg:` 变体）**都不会有样式，且不报错**。改之前先查 `main.css` 里有没有，或者直接用内联 CSS 变量。
2. **不要试图从 `tailwind.config.js` 重新构建。** 它的 `content` 不含 `public/*.html`，本地也没有 `node_modules`——按现有配置构建出来的 `main.css` 会丢掉首页**全部**样式。真要重建必须先把 `./public/*.html` 加进 content 源。
3. **改 CSS 务必做版本号。** `max-age=43200` 意味着老访客最长 12 小时看不到新样式，且 F5 也可能无效（`max-age` 未过期时不发请求）。这是"改了但没生效"类问题的头号来源。
4. **生产上 `index-副本.html` 仍在 `public/` 目录里**（本地与生产都有该引用关系）。它同样引用 `main.css`——若 Stage 3 删除 `main.css`，这个副本页会彻底失去样式。它虽然已被 `.gitignore` 排除，但**文件仍在服务器上**，处理方式需明确：要么一起删，要么一起改。
5. **首屏背景图是外链**（`free-img.mofashi.ltd`，544 KB）。它不在本项目仓库里，改不动源码；优化需在该图床侧替换图片，或把图片纳入 `static/` 自托管。另外它是 LCP 元素且带 `max-age=86400`，换图后同样有 24 小时缓存延迟。
6. **别把登录表单直接放进首页。** 首页是静态文件，没有 CSRF token、没有 session 上下文。要做登录框必须让 nginx 把 `/` 交给 PHP（即删除 `index.html` 或调整 nginx `index` 顺序）——这会牺牲"PHP 挂掉首页仍可访问"的韧性，**不建议**。用链接跳转 `/login` 是正确做法。
7. **图片格式兼容性**：用 WebP 替换 logo 前，确认目标浏览器范围（WebP 已全平台支持，风险很低）；favicon 若改 SVG，需保留一个 PNG 兜底（旧 Safari 不支持 SVG favicon）。
8. **Stage 3 是"大爆炸式"改动**，一次性重写全部样式。若希望更稳，可把它拆成两次上线：先 hero + features，观察一两天再改 showcase + footer。

---

## 附录 A：本次核查用的证据与命令

**已核对的生产事实**

```
生产路径 : /www/wwwroot/tc.mofashi.ltd/public/index.html
本地 md5 : 7030df910b2bdd5a7061b8b2d26806f6
生产 md5 : 7030df910b2bdd5a7061b8b2d26806f6   ✔ 逐字节一致
main.css : ac85f3c0f61145441a1b8da15d91f08b   ✔ 本地与生产一致

GET /                 → 200, Content-Length: 21229, 无 Cache-Control（仅 ETag）
GET /static/css/main.css → 200, 23202 B, Cache-Control: max-age=43200
Accept-Encoding: gzip → 首页 6,? 实测 7028 B
```

**关键类覆盖核查**

判定方法（**必须这样判，否则会得出错误结论**）：Tailwind 会把特殊字符转义后写进选择器 ——
`h-[300px]` 在 CSS 里是 `.h-\[300px\]`，`lg:inline-flex` 是 `.lg\:inline-flex`。
所以 `grep "h-\[300px\]"` **找不到任何东西**，会误报为"类不存在"。
正确做法是逐字符转义后再做字面子串匹配，并用 `class="..."` 的实际出现位置验证。

```
首页使用 class 共 130 个，分为三类：

【A】由页面内联 <style> 定义（正常，4 个）
     footer-text   footer-time   site-bg   sponsor-pill

【B】外链 CSS 与内联均无规则 —— 真正的死类（5 个）
     bg-radial-gradient   bg-top-left   icon   inline-block   text-md

【C】其余 121 个在 main.css 中有对应规则（含全部任意值类与断点变体）

确认存在的（曾被我误判为缺失）：lg:inline-flex、sm:max-w-[480px]、
     h-[300px]  h-[280px]  h-[250px]  w-[640px]  left-[-20%]  opacity-[.15]
     group-hover:-translate-y-1  group-focus:-translate-y-1  dark:bg-slate-900
确认缺失的常用类（首页当前未使用）：block  rounded-full  shadow-lg
     hover:bg-white  sm:grid-cols-3  peer-checked:*  animate-pulse  aspect-video
```

> 复核脚本已沉淀为技能：`~/.workbuddy/skills/static-page-css-audit/scripts/audit.py`。
> 用法：`python audit.py <页面.html> <样式.css>`，一条命令输出样式缺口、变体族覆盖、
> 双重失效候选、未使用脚本、图片像素冗余五类结果。

**图片实测**

```
logo-horizontal.png  3370 x 1123   562,241 B   显示宽 150px → 22 倍像素冗余
logo-icon.png        1946 x 1946   532,887 B   显示 32px   → 61 倍像素冗余
lankong.webp         2026 x 1310   140,854 B   （有 background-size:cover ✔）
0755dbda7d4f1.png     600 x 300     19,353 B   无 background-size
c265158cb1abe.png     558 x 280     26,893 B   无 background-size
d0ae251bc8917.png     609 x 270     11,294 B   无 background-size，盒子高 250px → 裁 20px
首屏背景(桌面)        webp          544,082 B   外链 free-img.mofashi.ltd，LCP
首屏背景(移动)        webp          499,918 B
```

**其他**

```
alpine.js 引用 : <script src="static/js/alpine.js" defer>
x-*/@/: 绑定数 : 0            → 77,000 B 完全未被使用
main.css 引用方 : 仅 public/index.html、public/index-副本.html（全项目）
custom.js      : 存在于 public/static/js/，全项目无人引用（孤立文件）
生产 static/images : 7 个文件 / 1.4 MB（本地多 3 个未部署的重名副本 PNG，约 2 MB）
```

## 附录 B：样式方案选型对比（详细）

见 §4.3。补充两点量化依据：

- 当前 `main.css` 23,202 B 中，**preflight（CSS Reset）占了绝大部分**（1,387 行里约 1,000 行是 Tailwind 的 base reset 与注释），真正的工具类只有 232 条里的一小部分。手写方案下，一份只含本页所需规则 + 令牌的样式表预计 **3–6 KB**，gzip 后 **< 2 KB**。
- 若走方案丙（重建 Tailwind），仅 `npm install` 的依赖体积就在 300–500 MB 量级，且需要把 `./public/*.html` 加入 `content` 源，否则构建产物会丢掉首页全部样式（这是最容易踩的坑）。

---

*本方案基于 2026-09-17 对本地仓库与生产服务器 `dxipv6243`（tc.mofashi.ltd）的实测。所有"现状缺陷"条目均可复现；如需逐条演示，可执行附录 A 中的核查脚本。*
