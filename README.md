# 魔法师随机图片API

一个简单易用的随机图片API服务，支持PC/移动端自适应，提供管理后台和统计功能。

## 功能特性

- 🌐 自动识别设备类型，PC / 移动端返回不同图库
- 🖼️ 多种输出方式：302 跳转、代理隐藏真实图片链接、JSON 地址输出
- 🎛️ 灵活的缓存控制
- 📊 调用统计与可视化报表，支持定时自动落库
- 🖥️ 功能完善的管理后台：图片管理、操作日志、网站信息自定义
- 🔍 后台环境检测：运行环境、依赖扩展与目录权限一键自检
- 🔄 一键在线更新：基于 GitHub Releases，更新前自动备份、失败自动回滚
- 🔐 多层安全防护：SSRF 防护、CSRF Token、登录锁定、频率限制、XSS 过滤
- 🗄️ SQLite 单文件存储，部署与迁移便捷
- ⚡ 可选 APCu 内存加速，从容应对高并发

## 快速开始

### 环境要求

- PHP 7.4 或更高版本
- PHP PDO SQLite 扩展（通常默认开启）
- Apache/Nginx Web服务器
- 开启 curl 扩展（推荐，用于图片 SSRF 防护与自动更新）
- 开启 zip 扩展（自动更新功能需要）

### 安装部署

1. 将项目文件上传到Web服务器目录
2. 确保以下目录可写：
   - `data/`
   - `admin/logs/`
   - `data/cache/`
   - `data/backups/`
   - `data/update_cache/`
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

4. **一键更新**
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

- 🔒 SSRF防护：禁止访问内网IP，验证DNS解析结果、验证图片MIME类型与文件签名（魔数）
- 🔒 登录锁定：5次失败后锁定5分钟
- 🔒 CSRF Token：所有POST操作验证
- 🔒 频率限制：API每分钟100次，管理后台每分钟10次
- 🔒 XSS防护：所有用户输入输出均经过转义
- 🔒 目录保护：敏感目录禁止web访问
- 🔒 会话管理：设置 Cookie SameSite、HttpOnly、超时自动登出
- 🔒 代理头可信任配置：可选择是否信任 X-Forwarded-For 等代理头

## 项目结构

```
随机图片api/
├── api.php              # 自动识别设备API
├── pc.php               # PC端专用API
├── pe.php               # 移动端专用API
├── index.php            # 项目首页
├── config.php           # 配置入口：核心常量、会话引导与 lib 模块装配
├── nginx.conf.example   # Nginx 部署安全配置示例
├── lib/                 # 核心函数模块（按职责拆分，由 config.php 统一 require）
│   ├── db.php           # 数据库连接与初始化
│   ├── auth.php         # 认证/登录锁定/CSRF
│   ├── images.php       # 图片管理
│   ├── network.php      # SSRF 防护、远程抓取与设备识别
│   ├── stats.php        # 调用统计与自动落库
│   ├── update.php       # 自动更新与目录防护
│   └── ...              # 其余模块（cache/settings/ratelimit/api/log/version/environment）
├── admin/
│   ├── index.php        # 登录页面
│   ├── dashboard.php    # 管理后台（路由与共享布局）
│   ├── views/           # 后台各功能区块视图（按 section 拆分）
│   ├── logout.php       # 退出登录
│   ├── update.php       # 一键更新AJAX接口
│   └── logs/            # 操作日志目录
├── update/              # 更新系统目录
│   ├── updater.php      # 核心更新类
│   └── migrations.php   # 数据迁移脚本
├── public/             # 静态资源目录
└── data/                # 数据目录（SQLite、缓存、备份等）
    ├── app.db           # SQLite 数据库
    ├── app_version.txt  # 版本号备份文件
    ├── cache/           # 缓存目录
    ├── backups/         # 更新备份目录
    └── update_cache/    # 更新临时下载目录
```

> 自 v3.2.2 起，`config.php` 已收敛为配置入口，业务函数按职责拆分为 `lib/` 下的独立模块；管理后台各功能区块（图片管理、操作日志、用户设置、网站设置、环境检测、系统更新）拆分为 `admin/views/` 下的子视图。业务调用方无需感知这些拆分。

## 技术栈

- 后端：PHP + SQLite
- 前端：Bootstrap 5, jQuery, Chart.js
- 数据存储：SQLite 数据库
- 自动更新：GitHub Releases API

## 当前版本

v3.2.3.2

## 许可证

本项目采用 [MIT License](LICENSE) 开源协议。

允许自由使用、修改、商用与再分发，仅需保留版权声明。详见 [LICENSE](LICENSE) 文件。
