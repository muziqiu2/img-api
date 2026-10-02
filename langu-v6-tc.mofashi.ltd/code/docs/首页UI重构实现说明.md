# 首页 UI 重构 · 实现说明

> 实施日期：2026-09-17
> **部署状态：已上线生产**（2026-09-17 23:32，见 §8）
> 目标文件：`public/index.html`（生产环境首页，nginx 直接服务的静态文件，站位 `/`）
> 新增资源：`public/static/images/` 下 6 个文件（见 §6）
> 前置方案：`docs/首页UI重构方案.md`（问题诊断与优先级矩阵，本文是其落地记录）
> 本文所有数字均为本次实测值，不含推测。

---

## 0. 结论摘要

1. **改用了全项目统一的设计语言，而不是另造一套。** 首页从「全屏照片 + sky-500 蓝 + Tailwind 模板血统」改为项目主程序既有的 **Corporate Clean（企业简洁）** 体系：浅灰底 `#f7f8fa` + 白卡片 + 1px 细描边 `#e4e7ec` + 品牌蓝 `#2563eb` + 8/10/12px 圆角。令牌名沿用主程序 `resources/css/common.less` 里已有的 `--cc-*` 命名，将来可直接共用。
2. **首页原有的最大问题是「看不见」而非「不好看」。** 实测截图显示：h1 用的渐变文字（`#cfd9df` → `#878787`）压在明亮插画上几乎不可读，白色正文同样糊在花朵上；`h-screen` 让首屏在任何视口都占满整屏高度，1440×900 下正文被推到 y=805 之后（整屏都是背景图）。这两点在旧版是可复现的硬缺陷。
3. **首屏传输体积 1.68 MB → 118 KB（−93.3%）**，且不再有外链 CSS/JS。移动端首屏 83 KB。这与视觉改造互不冲突：省下的是 `alpine.js`（77 KB，0 个绑定）与两个像素冗余的 logo/favicon。
4. **结构补上了 4 项可用性/合规缺口**：页脚从最后一个内容区块里拆出来成为独立 `<footer>`；补齐 `<header>/<nav>/<main>/<footer>` 地标与跳转链接；**补上首页此前完全不存在的登录入口**；清除 `<center>`/`<font>`/`language=` 等废弃标记。
5. **与前置方案文档的一处重要差异**：方案文档 §3.2 建议「以 `upload/index.html` 的设计语言（`#fbfaf7` 暖白 + `#1f6f5d` 绿）为唯一基线」。本次按用户后续明确要求**排除该页**（该页暂不启用），改以**主程序本体**为基准。因此底色是 `#f7f8fa` 而非 `#fbfaf7`，强调色是品牌蓝而非绿色。

---

## 1. 设计决策

### 1.1 基准来源：主程序，而非风格稿

用户要求「设计风格要向整个项目进行参考，不然会太过于违和」。项目里实际并存多套视觉语言（Tailwind 模板血统的旧首页、`upload/index.html`、`public/preview/` 下的 4 份风格稿）。经比对，**唯一具有「全站强制力」的是主程序的 `resources/css/common.less`**：

- 它用 `!important` 把 Tailwind 的 `indigo/sky/violet/fuchsia/purple/cyan/teal` 一律压成 `#2563eb`；
- 它把 `bg-gray-100` 强制为 `#f7f8fa`、`shadow-custom` 强制为细边框卡片；
- 它已经写好了 `prefers-reduced-motion` 降级与 `:root` 令牌。

也就是说：**admin / 登录 / 上传等所有真实页面都长成 Corporate Clean，只有首页不是。** 首页才是那个「违和」的例外。因此基准取它，方向明确。

### 1.2 首屏照片的处理

旧首页靠一张 544 KB 全屏照片撑第一印象，设计上是有道理的——它本身是图床，用自己托管的图当门面是合理的表达。但「全屏铺底 + 文字压在上面」与 Corporate Clean 的无渐变/无发光原则冲突，且必然产生对比度问题。

处理方式：**保留这张图，但从「背景」改为「内容」**——放进首屏右侧一张产品卡片里，并配上它真实的元数据（格式 WEBP、文件名、4300×2500、CDN 直链、复制按钮）。这样一次解决三件事：照片仍是门面、文字对比度不再依赖照片明暗、卡片本身成为「上传后拿到什么」的直观演示。

### 1.3 明确未做的事

| 项 | 决定 | 理由 |
|---|---|---|
| 暗色模式 | **不做**，恒为浅色 | 主程序用 `!important` 强制浅色。首页若跟随系统变暗、点进内页却变亮，恰是用户要避免的违和；且 logo 是纯黑图形（97% 暗像素），暗色下需再出一套白色变体。如需暗色，建议与主程序一起改，而不是只改首页。 |
| 装饰光斑 | **删除**，不实现 | 旧版 2 个光斑因 `bg-radial-gradient` 无定义而完全不可见。Corporate Clean 明确「无渐变无发光」，实现它等于逆着体系走；留着则是死元素。 |
| `upload/index.html` | 不参与 | 用户明确要求排除。 |
| `main.css` / `alpine.js` | **保留文件，仅解除引用** | `public/index-副本.html` 仍在引用这两者（实测）。删除会让该副本页彻底失去样式，故文件留在磁盘上。 |

---

## 2. 设计令牌

沿用主程序 `--cc-*` 命名，值与其逐字对齐，新增几个首页才需要的：

```css
:root {
    /* 与 resources/css/common.less 完全一致 */
    --cc-bg: #f7f8fa;          --cc-surface: #ffffff;
    --cc-line: #e4e7ec;        --cc-line-strong: #cbd2dc;
    --cc-ink: #1f2430;         --cc-sub: #5f6b7a;      --cc-muted: #98a1b0;
    --cc-accent: #2563eb;      --cc-accent-soft: #eff4ff;

    /* 首页新增 */
    --cc-accent-hover: #1d4ed8;
    --cc-accent-line: #dbe6ff;          /* 浅色描边，用于 hover 提色 */
    --cc-r-sm: 8px;  --cc-r: 10px;  --cc-r-lg: 12px;   /* 与 common.less 的按钮/拖拽区/卡片一致 */
    --cc-wrap: 1120px;
    --cc-gutter: clamp(20px, 5vw, 40px);
    --cc-shadow-card: 0 1px 2px rgba(31,36,48,.03), 0 4px 16px -8px rgba(31,36,48,.06);
    --cc-shadow-lift: 0 8px 18px -8px rgba(37,99,235,.42), 0 2px 6px rgba(31,36,48,.06);
    --cc-ring: 0 0 0 3px rgba(37,99,235,.12);
    --cc-ease: cubic-bezier(.22,.8,.3,1);   --cc-dur: .18s;
}
```

字体栈与主程序 `tailwind.config.js` 的 `sans` **逐字一致**（`Nunito, ui-sans-serif, system-ui, …`）。

> 实测确认：**Nunito 全项目只是被声明，从未真正加载**——仓库内没有 Google Fonts 链接、没有本地字体文件。所以主程序实际渲染用的是系统 UI 字体。这里同样只声明不加依赖：既不多一次请求，将来若主程序真加载了 Nunito，首页自动跟上。

---

## 3. 组件

三层结构，与方案文档 §4.1 一致：令牌 → 组件 → 区块。

| 组件 | class | 次数 | 说明 |
|---|---|---|---|
| 按钮 | `.btn` + `.btn--primary` / `.btn--ghost` / `.btn--sm` | 3 | 旧版 CTA 用 4 层嵌套 `span` 模拟位移，现为单元素 + `transform`；hover 上浮 1px，`:active` 回弹 `scale(.98)`，与 `common.less` 的手感定义一致 |
| 徽标 | `.eyebrow` | 1 | 品牌蓝浅底药丸 |
| 卡片 | `.feat` / `.demo` | 5 | 1px 细描边 + 双层微阴影，hover 上浮 2px 并转蓝调描边 |
| 图文区块 | `.show` + `.show--rev` | 4 | 镜像靠 CSS `order`，DOM 顺序恒为「图 → 文」 |
| 直链字段 | `.field` / `.field__url` / `.field__btn` | 1 | 等宽字体；`user-select: all` 便于手动复制 |
| 备案链接 | `.beian` | 2 | 抽为 flex 行 |
| 区块容器 | `.wrap` | 9 | 替掉 8 处重复的 `max-w-screen-xl w-full mx-auto px-5` |

**hand-written、零构建**：全部规则写在页面 `<style>` 内，共 52 条选择器。页面**不含任何内联 `style=` 属性**（旧版 12 处 / 1,085 字符）。因为不再引用 `main.css`，也不存在「写了不生效的 Tailwind 类」这个旧版最大的隐患。

---

## 4. 结构

```
<body>
 ├─ a.skip                        跳到主要内容（新增）
 ├─ <header class="hdr">          position:sticky，白底 + 下边框 + 浅影
 │   ├─ logo（<picture> WebP，带 width/height 防 CLS）
 │   └─ <nav>  上传图片 · 我的图片 · 登录 · [赞助我们]     ← 登录入口为新增
 ├─ <main id="main">
 │   ├─ <section class="hero">    内容驱动高度（实测 545px，非视口 805px）
 │   │   ├─ 左：徽标 + h1 + 导语 + 2 个 CTA + 3 项信任点
 │   │   └─ 右：<figure class="demo"> 真实图片卡片 + 元数据 + 复制直链
 │   ├─ <section> 4 张功能卡
 │   └─ <section> ×4 图文区块（图右 / 图左 / 图右 / 图左）
 └─ <footer class="ftr">          独立页脚：备案 ×2 + 运行时长
```

**导航链接取值已核实**（`routes/web.php`）：
- 上传图片 → `/index.php`（游客可上传，实测 200）
- 我的图片 → `/images`（`Route::get('images', …)->name('images')`，在 `auth` 组内，未登录由中间件 302 到 `/login`）
- 登录 → `/login`

> 注意：`/user/images` 是 `ImageController@images` 的 **JSON 接口**，不是页面。导航若误用它只会得到一串 JSON。

---

## 5. 修掉的问题（对照方案文档 §1.3 的 15 项）

| # | 原缺陷 | 处理 |
|---|---|---|
| 1 | 2 个装饰光斑完全不可见 | **删除**（Corporate Clean 无渐变无发光，不实现） |
| 2 | 3 张插图不缩放、第 4 张垂直裁 20px | 改为 `<img width/height>` + `width:100%; height:auto`，比例自然保持，**零裁切**，且带尺寸属性无 CLS |
| 3 | `text-md` 死类 | 未使用（用具体字号） |
| 4 | 4 个 SVG `class="icon"` 死类 | 4 个图标按 24×24 网格重绘，`stroke="currentColor"`，颜色交给 `--cc-accent`；旧版 viewBox 各异（1316×1024 / 1024×1024 / 1152×1024）导致的视觉重心不齐一并解决 |
| 5 | `inline-block` 死类 | 未使用 |
| 6 | `bg-top-left` 拼错 | 未使用 |
| 7 | 页脚不是 `<footer>` 且在内容区块内 | 拆为独立 `<footer class="ftr">` |
| 8 | 整页零语义地标 | `<header>/<nav>/<main>/<footer>` 齐全 + `.skip` 跳转链接 + 4 处 `aria-label` + 10 处 `aria-hidden` |
| 9 | **首页没有登录入口** | 导航新增「登录」「我的图片」，hero 增「登录 / 注册」次按钮 |
| 10 | `h-screen` 锁死首屏 | 改内容驱动。实测 hero 高 **545px**，白区起始 y=**610**（旧版 805），首屏内即可看到正文开头 |
| 11 | 运行时长脚本重复 + `custom.js` 孤立 | 内联脚本重写：4 个固定 `<b>` 只改 `textContent`（旧版每秒重建 `innerHTML`）。`custom.js` 仍未被任何 HTML 引用，**未删除**（见 §8） |
| 12 | `<center>` / `<font>` / `language=` | 全部清除（实测计数 0）。`<font>` 由 CSS 着色替代 |
| 13 | 无 `prefers-reduced-motion` | 已加，并额外关闭 hover/active 的位移动画 |
| 14 | 硬编码 URL 散布 | 首屏图已本地化；其余为主站/备案域名，静态页保留 |
| 15 | 12 处内联样式 | **0 处**（实测 `style="` 计数为 0） |

另外修掉了方案文档未列出的两项：
- **h1 折行丑陋**：`简单、好用、永久免费` 9 字原与品牌名同字号，1440px 下被迫折成「永久免 / 费」。改为副标题独立成行、字号降一档（`.7em`），并加 `text-wrap: balance`。
- **`theme-color` 不一致**：`#0ea5e9`（sky-500）→ `#2563eb`（品牌蓝），与 `common.less` 的收敛方向一致。

---

## 6. 性能

### 新增/替换的图片资源

| 文件 | 尺寸 | 体积 | 来源 |
|---|---|---|---|
| `logo-horiz-540.webp` | 540×200 | 28,286 B | 原 `logo-horizontal.png`（3370×1123 / 562,241 B）裁掉 40% 透明留白后按 3× 显示尺寸导出 |
| `logo-horiz-540.png` | 540×200 | 60,485 B | 同上，WebP 兜底 |
| `hero-1100.webp` | 1100×640 | 75,116 B | 原外链首屏图（4300×2500 / 544,082 B）重编码 |
| `hero-720.webp` | 720×419 | 40,240 B | 同上，供窄屏与 `srcset` |
| `favicon-64.png` | 64×64 | 5,965 B | 原 `logo-icon.png`（1946×1946 / 532,887 B）|
| `apple-touch-icon.png` | 180×180 | 24,354 B | 同上 |
| `logo-mark-192.png` | 192×192 | 26,393 B | 纯图案，预留（**无引用，未部署**）|

原文件**全部保留未覆盖**，回滚只需改回引用。

### 首屏传输对比

| | 旧版 | 新版 |
|---|---|---|
| HTML | 21,229 B（gzip 7,028） | 34,451 B（gzip **9,007**） |
| 外链 CSS | 23,202 B | **0**（内联进 HTML） |
| 外链 JS | 77,000 B（`alpine.js`，页面 0 个绑定） | **0** |
| logo | 562,241 B | 28,286 B |
| favicon | 532,887 B | 5,965 B |
| 首屏主图 | 544,082 B（外链） | 75,116 B（1100w，本地） |
| **合计** | **1,760,641 B ≈ 1.68 MB** | **118,374 B ≈ 0.11 MB** |

**降幅 93.3%。** 移动端（用 720w 主图）首屏 83,498 B。

> 图像重编码用 Pillow（`LANCZOS` 缩放、WebP `quality=76 method=6`）。首屏主图从 4300×2500 降到 1100×640 是因为它在卡片里最大只显示约 500 CSS px（2× DPR → 1000 px）。
> 一处需说明的不一致：卡片里展示的**预览图**是本地 1100px 重编码版，而卡片上显示的**直链**指向 4300px 原图。卡片元数据（WEBP / 4300×2500 / 该 URL）描述的是链接所指的真实文件，预览只是缩略渲染。该 URL 已实测返回 200 / 544,082 B。

---

## 7. 验证记录

### 断点（iframe 法，绕开 headless Chrome 最小窗口宽 500px 的限制）

| 断点 | 文档高 | 滚动宽 / 视口宽 | 横向溢出 |
|---|---|---|---|
| 375 | 3,687 | 375 / 375 | 无 |
| 768 | 4,513 | 768 / 768 | 无 |
| 1024 | 2,880 | 1024 / 1024 | 无 |
| 1440 | 2,973 | 1440 / 1440 | 无 |
| 1920 | 2,973 | 1920 / 1920 | 无 |

逐断点扫描全部元素，唯一越界者是 `.skip` 跳转链接（`left:-9999px`，聚焦时才进入视口，属预期）。

### 功能

- 运行时长：实测输出 `1321 天 23 时 13 分 53 秒`，4 个 `<b>` **全部**为品牌蓝 `rgb(37, 99, 235)`；日期按 `2023-02-04T00:00:00+08:00` 显式带时区计算（旧版依赖浏览器本地时区解读 `02/04/2023 00:00:00`）。手算 2023-02-04 → 2026-09-17 = 1321 天，与实测一致。
- 复制直链：`navigator.clipboard` 可用时点击显示「已复制」；`file://` 下实测抛 `NotAllowedError` → 正确落到「请手动复制」分支；不支持 Clipboard API 时按钮直接移除（渐进增强）。全程无 JS 报错。
- 关键计算样式：header `rgb(255,255,255)` + `sticky`；主按钮 `rgb(37,99,235)` / 圆角 8px；功能卡圆角 12px、描边 `rgb(228,231,236)`；卡片阴影双层；h1 副标题 `rgb(37,99,235)`；body 字体栈以 `Nunito` 起头。**全部符合令牌定义。**

### 静态检查

- 页面使用的 46 个 class **全部**在页内 `<style>` 有定义（0 个未定义）。
- 引用的 11 个本地资源路径**全部存在**。
- `<center>` 0 / `<font>` 0 / `language=` 0 / `main.css` 引用 0 / `alpine.js` 引用 0 / `innerHTML` 0。
- 无 JS 环境：`<noscript>` 隐藏运行时长整行，避免出现「— 天 — 时」。

### 文件指纹

```
public/index.html   34,451 B   LF   md5 aee065cf9c9e1cd9f17b571e929cf489
                    (生产同 md5，见 §8 部署记录)
```

---

## 8. 部署记录（2026-09-17 23:32 已上线）

### 上传内容

| 文件 | 生产 md5 | 说明 |
|---|---|---|
| `public/index.html` | `aee065cf9c9e1cd9f17b571e929cf489` | 替换（旧版 md5 `7030df910b2bdd5a7061b8b2d26806f6` 已备份至 `/tmp/mofashi_deploy/home_20260917/backup_20260917_233241/`） |
| `static/images/hero-1100.webp` | `78139f9dab81f6bd934c9dcd32a03e11` | 新增 |
| `static/images/hero-720.webp` | `bb8841e3ac550aca12c41d9a4e2571b0` | 新增 |
| `static/images/logo-horiz-540.webp` | `c5e3bac56a7dd2c016e0e32b53c76d01` | 新增 |
| `static/images/logo-horiz-540.png` | `a05cd7ffc2bb5acd623dd357ea7f654c` | 新增（WebP 回退） |
| `static/images/favicon-64.png` | `27ba535860adf233f9f7e5086f42edb2` | 新增 |
| `static/images/apple-touch-icon.png` | `8c0a9f27756d00296099b77773895bee` | 新增 |

**顺序：先装 6 个图片，最后替换 HTML** —— 反过来会留下「页面已上线、图片 404」的窗口期。

未上传 `logo-mark-192.png`（192×192）：全项目**无任何引用**，属生成时的备用产物，留在本地不进生产。

### 验收

- 服务端端点：`/` 200(34,451B) · `/index.php` 200 · `/login` 200 · `/images` 302(未登录预期) · 6 个新资源全部 200 且字节数与本地一致。
- 经 CDN 逐字节复核 6 个新资源：`http=200`，字节数与本地**完全相同**。
- **最终验收**：headless Chrome 抓公网页面（带查询串绕缓存）截图，与本地产物 `md5` 比对 → `d0bc554012ef84c0044a843f1a0a2920` **两侧完全一致**，证明线上与本地逐像素相同、所有新资源在 CDN 上均解析成功。
- 旧标记残留 0（`h-screen` / `site-bg` / `bg-radial-gradient` / `alpine.js`）；`main.css` 仅剩注释里的一处文字提及。

### ⚠️ 一个必须知道的反直觉点：EdgeOne 会缓存裸 `/`

tc.mofashi.ltd 前置 **腾讯 EdgeOne**（从 `EO-Cache-Status` / `EO-LOG-UUID` 响应头可辨认）。实测：

| 请求 | 结果 |
|---|---|
| `/`（裸根） | `EO-Cache-Status: HIT` → **仍是旧 HTML**（md5 `7030df…`，21,229 B），源站已更新也照样发旧的 |
| `/index.html` | 另一个缓存键，`MISS` → 立即是新内容 |
| `/?任意查询串` | 另一个缓存键 → 立即是新内容（**验回源内容用这个**） |
| `-H 'Cache-Control: no-cache'` | **无效**，边缘仍回 HIT，不回源 |
| `-H 'If-None-Match: <新ETag>'` | **无效**，同上 |

**根因**：原先 §8 第 6/7 条假设「`index.html` 无 `Cache-Control` 所以改动立即生效」——
这只说对了**源站**，漏了**边缘**。裸 `/` 的缓存副本另有 TTL。

**处置（2026-09-17 已闭环，走 API 自动完成）**：
用腾讯云 EdgeOne 官方技能 `tencent-edgeone-skill` 调 `tccli teo CreatePurgeTask`：

```
站点 mofashi.ltd → ZoneId zone-3djutqrwjq2k
CreatePurgeTask --Type purge_url --Targets '["https://tc.mofashi.ltd/"]'
→ JobId 3v4cuv412ver，10 秒后 status=success
```

刷新后裸 `/` 首探 `MISS` 回源拿到新首页，再探转 `HIT`。
**验收**：headless Chrome 抓**裸 `/`** 截图 md5 = `d0bc554012ef84c0044a843f1a0a2920`，
与此前经查询串取到的源站产物**逐像素完全一致**。

> 更正：本文早前写的「服务器无任何腾讯云/EO 凭据、无法用 API 刷新」**已不成立** ——
> tccli 与凭据现已就位（`~/.tccli/default.credential`）。完整配方见技能 `langu-v6-ssh`「刷新边缘缓存」一节。
> ⚠️ 另注：**该套餐 `prefetch_url` 配额为 0，不支持预热**。

**踩坑提醒**：**探测请求本身就会把旧内容写进边缘缓存**（首次 `MISS` 回源取旧文件并缓存，随后全 `HIT`），
TTL 从那一刻重新计时。因此**部署前不要先去 curl 线上首页**。

**根治方案：已决策不做（2026-09-18）。** 曾考虑给首页加 `Cache-Control: no-cache` 让边缘每次回源校验，
但那要改 nginx vhost（`/www/server/panel/vhost/nginx/`）——**用户决定不改**，以后需要时直接用
`tccli teo CreatePurgeTask` 刷一下缓存即可（配方见技能 `langu-v6-ssh`「刷新边缘缓存」一节）。
理由：改动 vhost 的影响面大于收益（全站静态资源策略都会被动到），而刷新缓存只需一条命令。
**因此日常改首页的流程是：部署 → 立即刷一次根 URL → 验收。**

---

## 9. 未做 / 待确认

1. ~~**裸 `/` 的 EdgeOne 缓存待刷新**~~ → **已完成**（见 §8，JobId `3v4cuv412ver`，已验收）。
2. **暗色模式**：按 §1.3 的理由未做。若要，建议与主程序一起改（需同时产出 logo 白色变体）。
3. **`public/static/js/custom.js`（孤立文件）** 与 **`public/static/js/alpine.js`（首页已不引用，但 `index-副本.html` 仍引用）**：均未删除。删除属破坏性操作，需你确认。
4. **`public/index-副本.html`** 仍在 `public/` 目录（已被 `.gitignore` 排除，但文件在本地与生产都存在），且仍在引用 `main.css` + `alpine.js`。保留 `main.css` 就是为它兜底；若确定该副本不再需要，可连同 `main.css`、`alpine.js` 一起清理。
5. **两张深色配图**（`0755dbda7d4f1.png` 的 `</  Q  ?>`、`c265158cb1abe.png` 的「稳定、稳定还是™的稳定」）在浅色体系里是全站最跳的两块。原图未换（不擅自改内容），如需统一观感可考虑替换。
6. **回滚**：还原 `index.html` 即回到旧首页；新增的 6 个图片文件可留着（旧首页不引用它们，不冲突）。
7. **首页缓存 `Cache-Control` 根治**：**已决策不做**（见 §8 末）。需要时用 API 刷缓存即可，不改 nginx vhost。

---

## 10. 入库记录

2026-09-18 已提交并推送至 `muziqiu2/img-host`（main）：

| 提交 | 内容 |
|---|---|
| `7e268ab` | `fix(upload)` 前端压缩 JPEG 兜底 + 并发限流（`upload.blade.php`）|
| `8729b53` | `feat(home)` 首页 Corporate Clean 重构（`index.html` + 7 个新资源 + 2 份文档）|

已核验：入库 blob 与生产部署文件**逐字节一致**（`index.html` md5 `aee065cf…`、
`upload.blade.php` md5 `9f2a88e1…`，均 LF）。

