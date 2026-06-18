# 魔法师随机图片API

一个简单易用的随机图片API服务，支持PC/移动端自适应，提供管理后台和统计功能。

## 功能特性

- 🌐 自动识别设备类型（PC/移动端）「v1.0」
- 📱 支持多种返回格式（重定向、JSON、图片流）「v1.0」
- 🎛️ 灵活的缓存控制「v1.0」
- 📊 完整的调用统计「v1.0」
- 🔐 安全的管理后台「v1.0」
- 📝 操作日志记录「v1.0」
- 🗄️ SQLite 数据库存储，单文件便于部署和迁移，内置索引查询更快「v3.0」
- 🔄 一键自动更新系统（基于 GitHub Releases）「v3.1.0」
- 📦 备份与一键回滚，保证更新安全「v3.1.0」
- ⚙️ 应用设置管理（GitHub Token 配置）「v3.1.0」

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
| `return` | `redirect`/`json`/`img` | 返回格式，默认重定向 |
| `cache` | 数字(秒) | 缓存时间，默认0秒（不缓存） |

### 返回格式

#### 1. 重定向（默认）
直接302重定向到随机图片URL，适合`<img>`标签直接使用。

#### 2. JSON格式
```json
{
  "success": true,
  "url": "https://example.com/image.jpg",
  "type": "pc",
  "timestamp": 1622505600
}
```

#### 3. 图片流
直接输出图片二进制数据，适合需要隐藏真实URL的场景。

### 调用示例

```html
<!-- 直接显示图片 -->
<img src="https://your-domain.com/api.php" alt="随机图片">

<!-- 获取JSON数据 -->
<script>
fetch('https://your-domain.com/pc.php?return=json')
  .then(res => res.json())
  .then(data => console.log(data));
</script>

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
5. **删除重置脚本**：使用完 `reset_config.php` 后请立即删除「v3.0」

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
├── reset_config.php     # 重置用户配置脚本（使用后删除）
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

v3.1.0

## 许可证

本项目仅供学习和个人使用。
