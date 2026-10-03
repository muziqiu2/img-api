# 生产环境实测报告 · tc.mofashi.ltd

- 测试日期：2026-10-03
- 测试目标：`https://tc.mofashi.ltd`（前置腾讯 EdgeOne CDN），存储后端 `https://list-s3.mofashi.ltd`（OpenList S3），公网图床域名 `https://free-img.400040.xyz`（Cloudflare）
- 代码基线：`muziqiu2/img-host` 提交 `6333e42`（含 `7fd2998`、`9bb10ca`、`6333e42` 三次直传加固）
- 测试方式：**以游客身份**走真实业务流程（`/index.php` 欢迎页 → `/upload/presign` → PUT → `/upload/confirm`；`/upload` 服务端直传）
- 约束：**未修改任何生产代码**。仅发出读写业务请求，其中写操作产生了 4 条游客图片记录（见第六节清理清单）

---

## 一、总体结论

| 类别 | 结论 |
|---|---|
| 直传链路新版代码 | ✅ **已上线并在生产生效** |
| magic bytes 修复（最新提交 `6333e42`） | ✅ **已验证生效** |
| 字段规范化（R3） | ✅ **已验证生效** |
| 会话 Cookie / CORS / CSRF（P2） | ✅ **已验证生效** |
| 限流类防护（R1、R4.4 负向） | ⚠️ **受测试环境限制未能验证**，且发现其设计对 IP 轮换者无效 |
| 内容审核缺口（R2，浏览器直传路径） | ❌ 与代码分析一致：confirm 侧无任何内容校验 |

---

## 二、已验证生效 ✅

### 2.1 新版直传代码确已部署

`POST /upload/presign` 实际返回（游客身份）：

```
upload_url: https://list-s3.mofashi.ltd/nas/境外/4/2026/10/03/6ac0de9430b57.png
            ?X-Amz-Algorithm=AWS4-HMAC-SHA256
            &X-Amz-Credential=ihDg2JJK9dunKaJ9TYNF/20261003/us-east-1/s3/aws4_request
            &X-Amz-Date=20261003T105308Z
            &X-Amz-Expires=45                                  ← 新版 45 秒有效期
            &X-Amz-SignedHeaders=content-length;content-type;host  ← 新版手工 SigV4
pathname:     2026/10/03/6ac0de9430b57.png
existing:     null
content_type: image/png                                        ← 新版新增字段
```

三个新特征（45s / 手工 SigV4 头 / `content_type`）同时出现，证明生产已运行新版代码。

### 2.2 magic bytes 修复生效（本次最新提交的核心）

游客走 `POST /upload`（服务端直传，`directStore`）：

| 用例 | 结果 |
|---|---|
| 正常 8×8 PNG | `200 {"status":true,...}`，落库 `id=52424` |
| 非图片字节（`MOFASHI-NON-IMAGE-BYTES-PK-ZIP-EXE-TEST`）改名 `.png` | `200 {"status":false,"message":"文件不是有效的图片"}` ✅ **被拒** |

→ 非图片无法再借表单/API 路径落桶，与提交 `6333e42` 的改动一致。

### 2.3 字段规范化（R3）生效

`confirm` 提交恶意字段：

```
filename   = ../../<script>alert(1)</script>\evil.png
mimetype   = text/html          （越权指定）
dimensions = [999999999, -5]
```

实际落库返回：

```
origin_name: "evil.png"      ← basename 生效（\ 先归一为 /，再取末段）
mimetype:    "image/png"     ← 客户端提交的 text/html 被完全忽略，由扩展名推导
```

→ 客户端可控字段确实已被服务端规范化，不再污染落库数据。

### 2.4 会话 Cookie / CORS / CSRF（P2 三项）

| 项 | 实测 |
|---|---|
| 会话 Cookie | `mofashi_session` = `secure; httponly; samesite=lax`；`XSRF-TOKEN` = `secure; samesite=lax` ✅ |
| CORS | 带 `Origin: https://evil.example` 请求，仍返回 `access-control-allow-origin: https://tc.mofashi.ltd`，**不反射**恶意来源 ✅ |
| CSRF | 无令牌 POST `/upload/presign`、`/upload/confirm` → `419` ✅ |

### 2.5 去重与公网可读

- 同内容重复上传命中 `existing`，复用同一 pathname（游客池内）。
- 真实上传对象直链无需任何凭据即可读取，内容为上传的原始 75 字节 PNG：

```
GET https://free-img.400040.xyz/4/2026/10/03/6ac0dec49f099.png
→ 200  content-type: image/png  content-length: 75  sha1: c563356da2...  server: cloudflare
```

→ **印证前一报告 4.1 的待确认项：对象级直链为匿名公开可读。**

### 2.6 敏感面与后门扫描

`/.env`、`/.git/config`、`/composer.json`、`/phpunit.xml`、`/storage/logs/*.log`、`/bootstrap/cache/config.php`、`/server.php`、`/artisan`、`phpinfo.php`、`shell.php`、`adminer.php`、各类 `.bak`/`.zip`/`.sql` —— **全部 404**。
`/storage/`、`/vendor/`、`/public/`、`/uploads/` → 404；`/css/`、`/js/` → 403（禁止列目录）。
仅暴露 `server: nginx`，无 `X-Powered-By`。

---

## 三、受测试环境限制未能验证 ⚠️

### 3.1 沙箱出口 IP 每次请求都在变（关键环境事实）

对同一目标连续 3 次出口 IP：

```
115.190.92.241   →   115.191.57.248   →   101.126.17.35
```

由此产生两个直接后果：

1. **`throttle:30,1` 无法被触发**：35 次连续 `presign` 全部返回 `200`（0 次 429）。该限流按 IP 计数，而我的每个请求都是"新 IP"，永不累积。**这是环境artifact，不能据此判定中间件未生效**；但也直接暴露了下述设计缺陷（见 4.5）。
2. **`presign → confirm` 间歇性失败**：`confirm` 以 `i{请求IP}` 作为身份键，IP 一变即 `Cache::pull` 落空，返回「直传会话已过期或信息不一致」。

   实测 6 次连续尝试中 **5 次失败、1 次成功**（成功的 id=52427）。真实用户 IP 稳定，故不影响正常使用。

### 3.2 SigV4 的 Content-Length / Content-Type 绑定无法做负向验证

浏览器直传的 PUT 目标 `https://list-s3.mofashi.ltd/nas/...` **前置了 EdgeOne 人机校验**，我的自动化 PUT（含"报小传大"与"类型不符"两种负向用例）**全部被挑战页拦截**：

```
PUT（声明 75B，实传 1MiB）      → 200  content-type: text/html
body: <script>function a(a){function n(){for(var a={wQzOV:_0x649a("0x4")...
```

→ 未到达 S3，因此**看不到 S3 的 403 响应**，无法证明绑定在 S3 侧被强制。

正向路径已证：`/upload` 的**服务端 PUT 成功落桶**（id=52424 的对象可读），说明"签名 + CL/CT 绑定"在正常路径上被 S3 接受。

> 需你确认：该人机校验是否也会拦真实浏览器 `fetch()` 的 PUT。若会，游客直传会失效；若只在数据中心 IP/自动化特征下触发，则它反而是一层额外缓解。

---

## 四、本次新发现的问题（未修）

### 4.1 【高】`laravel-ignition` 路由在生产暴露

```
GET /_ignition/execute-solution  → 405   allow: POST
GET /_ignition/update-config     → 405   allow: POST
```

`405 + allow: POST` 是 **Laravel 路由表**的响应，说明这两个 Ignition 路由**已注册且可达** —— 这是 CVE-2021-3129 的攻击入口。

当前判定**尚不可直接利用**：
- 405 页为 Laravel 默认错误页（非 Ignition 调试页）；
- JSON 错误仅含 `message`，无 `exception`/`file`/`trace` → `APP_DEBUG=false`；
- `/_ignition/health-check`、`/_ignition/scripts/*` 被前置层 404。

建议：nginx 层 `location ^~ /_ignition/ { return 404; }`，并核对 ignition 版本。

> 透明说明：我曾发出 1 次**空 body** 的 POST 到 `execute-solution` 做可达性判定，无 payload，未尝试利用。

### 4.2 【中】`/analytics.html` 公网暴露明文 Bearer 令牌，且实测可用

- `https://tc.mofashi.ltd/analytics.html` 无鉴权，任何人可读取源码中的令牌；
- 实测该令牌可访问 `https://cdnpanel.mofashi.ltd/api/metrics/summary` 与 `/api/metrics/trend` → **200**（无令牌 401）；其他路径 404，权限边界限于**流量指标读取**；
- 影响：CDN 流量数据泄露；令牌随公网页面长期暴露（CDN 缓存 `last-modified` 2026-09-24）。

建议：轮换令牌 + 改为后端代理注入（令牌不出前端）+ 清理 CDN 缓存。

### 4.3 【中】全站缺安全响应头

静态页与 Laravel 响应均缺：`Strict-Transport-Security`、`X-Content-Type-Options`、`X-Frame-Options`、`Content-Security-Policy`、`Referrer-Policy`、`Permissions-Policy`。HTTP→HTTPS 有 302 跳转但无 HSTS 兜底。风险：SSL 剥离、MIME 嗅探、点击劫持。

### 4.4 【中】`confirm` 不校验对象是否存在 → 可制造"空记录"

实测：`presign` 后**完全不做 PUT**，直接 `confirm` → `200` 成功落库（`id=52427`，`pathname=2026/10/03/6ac0df299ee20.png`）。
该 pathname 实际无任何对象（GET 得到的是占位图，见 4.7）。

代码中该行为是**有意为之**（注释：OpenList headObject 写后读不一致，故不做存在性校验）。后果：可批量产生坏图记录、污染统计与配额计数。

### 4.5 【中】游客限流与会话绑定以 IP 为键 → 对 IP 轮换失效

这是本次测试最有价值的发现。当前设计对**游客**一律以 `$request->ip()` 作为身份：

- `presignLimiter` / `throttle:30,1`：按 IP 计数 → 攻击者轮换出口 IP 即可无限签发（我的沙箱每请求换 IP，35 次全部通过就是实证）；
- `presign:{i}{ip}:{md5}:{sha1}` 会话绑定：IP 变化即失败 → **正常用户若处于移动网络/CGNAT/IPv6 隐私地址等 IP 易变环境，会间歇性遇到"直传会话已过期"**。

建议：会话绑定改为 presign 返回一个服务端签发的**随机 nonce**，confirm 携带该 nonce 校验（与 IP 解耦）；限流补充基于 nonce/账号/全局的目标维度，而非仅 IP。

### 4.6 【低】新增说明：去重对游客是"全局池"

R5 声称"去重按用户隔离"，但游客分支为 `whereNull('user_id')` —— **所有游客共用一个去重命名空间**：

```php
->when(! is_null($user), fn ($q) => $q->where('user_id', $user->id),
                      fn ($q) => $q->whereNull('user_id'))
```

实测：我在 `/upload` 上传的 PNG，随后在独立会话的 `presign` 中被判为 `existing` 并复用 pathname。
后果：任一游客可通过提交已知文件的 md5/sha1，从 `existing` 字段**获取他人上传对象的路径**（对象本身已是公开可读，故危害有限）。已登录用户之间隔离正常。

### 4.7 【低】不存在路径返回 200 占位图，掩盖 404

```
GET /4/2026/10/03/6ac0dec49f099.png   → 200 image/png  75B      （真实对象）
GET /4/2026/10/03/zzz-not-exist-*.png → 200 image/webp 104372B  （占位图，任意不存在路径同一 sha1）
```

后果：无法用 HTTP 状态判断对象是否存在/已删除；坏图不会暴露为 404（与 4.4 叠加）。

### 4.8 【低】`/register` 公开注册开启

任何人可注册 → 获得上传能力。若无需公开注册，建议关闭或加邮箱验证。

### 4.9 【提示】R2 浏览器直传路径仍无内容校验

本次实测从侧面确认：`confirm` 只校验"会话一致性 + 扩展名 + 大小"，**不校验对象内容、不校验对象是否存在**。因此浏览器直传（`presign`+`confirm`）路径下的"任意字节托管"依然成立 —— 与前一报告结论一致，属**已接受的风险模型**。
（注：本次未能实际上传非图片对象，因 PUT 被人机校验拦截；该结论由 confirm 侧代码与实测行为共同得出。）

---

## 五、修复优先级建议

| 优先级 | 事项 |
|---|---|
| P0 | nginx 封禁 `/_ignition/*`；轮换并下线 `analytics.html` 明文令牌 |
| P1 | 会话绑定改为服务端 nonce（解耦 IP）；限流补 nonce/账号维度 |
| P1 | 补 HSTS / X-Content-Type-Options / X-Frame-Options / CSP |
| P2 | `confirm` 增加轻量对象存在性校验（异步或延迟复核，规避写后读不一致） |
| P2 | 游客去重改为按 IP 或 nonce 分池 |
| P3 | 占位图改为返回 404；按需关闭公开注册 |

---

## 六、待清理清单（本次测试产生的生产数据）

全部为游客记录（`user_id = NULL`），建议核对后删除：

| id | pathname | 说明 |
|---|---|---|
| 52424 | `2026/10/03/6ac0dec49f099.png` | 真实上传的 8×8 测试 PNG（75B），对象存在 |
| 52425 | `2026/10/03/6ac0dec49f099.png` | 去重命中的重复记录 |
| 52426 | `2026/10/03/6ac0dec49f099.png` | 字段规范化测试记录（`origin_name=evil.png`） |
| 52427 | `2026/10/03/6ac0df299ee20.png` | **空记录**（从未 PUT，指向不存在的对象） |

另有若干仅签发未确认的 presign 缓存键（TTL 90 秒，已自动过期），无残留。

**未产生**任何非图片对象（`/upload` 已拒绝；浏览器直传 PUT 被人机校验拦截）。

---

## 七、附：测试方法与免责

- 全部请求以游客身份发起，未使用、未尝试获取任何账号凭据；
- 未修改任何生产代码、配置或数据（除上述 4 条业务上传记录）；
- 唯一一次"异常"请求为空 body 的 POST 到 `_ignition/execute-solution`（可达性判定，无 payload）；
- 对第三方面板 `cdnpanel.mofashi.ltd` 仅发出 2 次只读 GET 以确认令牌影响面。

关联文档：
- [S3直传安全审查与整改方案-2026-10-03.md](S3直传安全审查与整改方案-2026-10-03.md)
- [S3直传-内容审核与任意字节托管风险复核-2026-10-03.md](S3直传-内容审核与任意字节托管风险复核-2026-10-03.md)