# 魔法师随机图片API

一个简单易用的随机图片API服务，支持PC/移动端自适应，提供管理后台和统计功能。

## 功能特性

- 🌐 自动识别设备类型（PC/移动端）「v1.0」
- 🖼️ 图片访问模式后台可切换（302 跳转 / 代理隐藏真实图片链接）「v3.1.7」
- 🎛️ 灵活的缓存控制「v1.0」
- 📊 完整的调用统计「v1.0」
- 🔐 安全的管理后台「v1.0」
- 📝 操作日志记录「v1.0」
- 🗄️ SQLite 数据库存储，单文件便于部署和迁移，内置索引查询更快「v3.0」
- 🔄 一键自动更新系统（基于 GitHub Releases）「v3.1.0」
- 📦 备份与一键回滚，保证更新安全「v3.1.0」
- ⚙️ 应用设置管理（GitHub Token 配置）「v3.1.0」
- 🎨 网站设置：后台可自定义网页标题、网站名称、版权信息与备案号「v3.1.3」
- 🔁 JSON 格式输出：`?format=json` 返回图片地址 JSON，后台开关控制「v3.1.8」
- 💬 后台交互优化：自定义确认弹窗与 Toast 通知，替代原生 confirm/alert「v3.1.8」
- ⏱️ 统计自动落库：调用统计按可配置间隔自动持久化，后台可调「v3.1.9」
- 🔍 后台环境检测：一键检测运行环境、依赖扩展与关键目录可写性，系统更新页同步展示「v3.2.0」
- ⚡ 性能优化：限流改用 APCu 内存计数（可选，降低 SQLite 写压力）+ 随机取图缓存 id 列表均匀选取（修复 id 空洞分布不均）「v3.2.1.1」
- 📱 后台移动端优化：长文本表格自动换行、横向滚动兜底，避免内容溢出界面「v3.2.0」

## 快速开始

### 环境要求

- PHP 7.4 或更高版本
- PHP PDO SQLite 扩展（通常默认开启）
- Apache/Nginx Web服务器
- 开启 curl 扩展（推荐，用于图片 SSRF 防护与自动更新）「v2.0 / v3.1.0」
- 开启 zip 扩展（自动更新功能需要）「v3.1.0」

### 安装部署

1. 将项目文件上传到Web服务器目录
2. 确保以下目录可写：
   - `data/`
   - `admin/logs/`
   - `data/cache/`
   - `data/backups/`「v3.1.0」
   - `data/update_cache/`「v3.1.0」
3. 访问项目首页即可使用

### Nginx 部署（重要）

项目自带的 `.htaccess` 防护**仅对 Apache 生效**。若使用 Nginx，必须参考根目录的 `nginx.conf.example` 配置规则，至少确保以下路径不可被 Web 访问（否则 `data/app.db` 数据库与 `data/backups/*.zip` 备份包可被公网直接下载，造成源码与数据泄露）：

```nginx
# 敏感目录：data/ 与 admin/logs/
location ~ ^/(data|admin\/logs)/ {
    deny all;
    return 403;
}
# 压缩包与隐藏文件兜底
location ~* \.zip$ { deny all; return 403; }
location ~ /\.     { deny all; return 403; }
```

部署完成后可访问 `admin/` → 系统更新 → 环境检查，查看是否有目录暴露相关警告。

### 默认账号

- 用户名：`admin`
- 密码：`123456`

⚠️ **重要**：首次登录后请立即修改默认密码！

## API使用说明

### 基础接口

#### 自动识别设备
```
https://your-domain.com/api.php
```

#### PC端专用
```
https://your-domain.com/pc.php
```

#### 移动端专用
```
https://your-domain.com/pe.php
```

### 请求参数

| 参数 | 可选值 | 说明 |
|------|--------|------|
| `cache` | 数字(秒) | 缓存时间，默认0秒（不缓存） |

> 图片访问模式由后台「网站设置 → 图片访问模式」统一控制，调用方传参不再影响返回方式。

### 图片访问模式

#### 1. 302 跳转模式（默认）
API 直接302重定向到随机图片URL，适合`<img>`标签直接使用。

#### 2. 代理模式
服务器代为下载图片并转发给用户，用户无法看到真实图片URL，可隐藏图片链接。适合不希望暴露真实图片来源的场景。

### 调用示例

```html
<!-- 直接显示图片 -->
<img src="https://your-domain.com/api.php" alt="随机图片">

<!-- 启用1小时缓存 -->
<img src="https://your-domain.com/pe.php?cache=3600" alt="随机图片">
```

## 管理后台

访问 `https://your-domain.com/admin/` 进入管理后台。

### 功能模块

1. **图片管理**
   - 添加/删除图片链接
   - 批量导入图片
   - PC/移动端分类管理

2. **操作日志**
   - 查看管理员操作记录
   - 包含操作时间、用户、IP地址

3. **用户设置**
   - 修改管理员用户名
   - 修改登录密码

4. **一键更新**「v3.1.0」
   - 检查 GitHub 最新版本
   - 一键更新到最新版本
   - 备份管理与一键回滚
   - 更新历史日志查看
   - GitHub Token 配置（提升 API 速率限制，私有仓库必需）

## 安全建议

1. **修改默认密码**：首次使用务必修改默认账号密码
2. **目录保护**：确保`data/`和`admin/logs/`目录无法通过web访问
3. **HTTPS**：生产环境建议使用HTTPS
4. **定期备份**：定期备份`data/`目录下的数据文件

## 安全特性

- 🔒 SSRF防护：禁止访问内网IP，验证DNS解析结果、验证图片MIME类型与文件签名（魔数）「v2.0 / v3.0」
- 🔒 登录锁定：5次失败后锁定5分钟，修复 CSRF 检查顺序绕过漏洞「v3.0」
- 🔒 CSRF Token：所有POST操作验证「v3.0」
- 🔒 频率限制：API每分钟100次，管理后台每分钟10次「v3.0」
- 🔒 XSS防护：所有用户输入输出均经过转义「v3.0」
- 🔒 目录保护：敏感目录禁止web访问
- 🔒 会话管理：设置 Cookie SameSite、HttpOnly、超时自动登出「v3.0」
- 🔒 代理头可信任配置：可选择是否信任 X-Forwarded-For 等代理头「v3.0」

## 项目结构

```
随机图片api/
├── api.php              # 自动识别设备API
├── pc.php               # PC端专用API
├── pe.php               # 移动端专用API
├── index.php            # 项目首页
├── config.php           # 配置文件和核心函数
├── nginx.conf.example   # Nginx 部署安全配置示例「v3.1.2」
├── admin/
│   ├── index.php        # 登录页面
│   ├── dashboard.php    # 管理后台
│   ├── logout.php       # 退出登录
│   ├── update.php       # 一键更新AJAX接口「v3.1.0」
│   └── logs/            # 操作日志目录
├── update/              # 更新系统目录「v3.1.0」
│   ├── updater.php      # 核心更新类
│   └── migrations.php   # 数据迁移脚本
├── public/             # 静态资源目录
└── data/
    ├── app.db           # SQLite 数据库「v3.0」
    ├── app_version.txt  # 版本号备份文件「v3.1.0」
    ├── cache/           # 缓存目录
    ├── backups/         # 更新备份目录「v3.1.0」
    └── update_cache/    # 更新临时下载目录「v3.1.0」
```

## 技术栈

- 后端：PHP + SQLite「v3.0」
- 前端：Bootstrap 5, jQuery, Chart.js
- 数据存储：SQLite 数据库（替代原 JSON/TXT 文件）「v3.0」
- 自动更新：GitHub Releases API「v3.1.0」

## 当前版本

v3.2.1.2

## 许可证

本项目采用 [MIT License](LICENSE) 开源协议。

允许自由使用、修改、商用与再分发，仅需保留版权声明。详见 [LICENSE](LICENSE) 文件。
