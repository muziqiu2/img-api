# 魔法师随机图片API

一个简单易用的随机图片API服务，支持PC/移动端自适应，提供管理后台和统计功能。

## 功能特性

- 🌐 自动识别设备类型（PC/移动端）
- 📱 支持多种返回格式（重定向、JSON、图片流）
- 🎛️ 灵活的缓存控制
- 📊 完整的调用统计
- 🔐 安全的管理后台
- 📝 操作日志记录

## 快速开始

### 环境要求

- PHP 7.0 或更高版本
- Apache/Nginx Web服务器
- 开启curl扩展（推荐）

### 安装部署

1. 将项目文件上传到Web服务器目录
2. 确保以下目录可写：
   - `data/`
   - `admin/logs/`
   - `data/cache/`
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
  "width": 1920,
  "height": 1080,
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

## 安全建议

1. **修改默认密码**：首次使用务必修改默认账号密码
2. **目录保护**：确保`data/`和`admin/logs/`目录无法通过web访问
3. **HTTPS**：生产环境建议使用HTTPS
4. **定期备份**：定期备份`data/`目录下的数据文件
5. **删除重置脚本**：使用完 `reset_config.php` 后请立即删除

## 安全特性

- 🔒 SSRF防护：禁止访问内网IP，验证图片MIME类型
- 🔒 登录锁定：5次失败后锁定5分钟
- 🔒 CSRF Token：所有POST操作验证
- 🔒 频率限制：API每分钟100次，管理后台每分钟10次
- 🔒 XSS防护：所有用户输入输出均经过转义
- 🔒 目录保护：敏感目录禁止web访问

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
│   └── logs/            # 操作日志目录
└── data/
    ├── user_config.json # 用户配置
    ├── api_call_count.json # 调用统计
    ├── rate_limit.json  # 频率限制数据
    ├── pc.txt           # PC端图片链接
    ├── pe.txt           # 移动端图片链接
    └── cache/           # 缓存目录
```

## 技术栈

- 后端：PHP
- 前端：Bootstrap 5, jQuery, Chart.js
- 数据存储：JSON文件 + 文本文件

## 许可证

本项目仅供学习和个人使用。

## 更新日志

### v2.1 (2026-06-14)
- 🔒 **安全修复**：增强SSRF防护，验证DNS解析结果、禁止内网IP段、验证MIME类型
- 🔒 **安全修复**：修复登录锁定绕过漏洞（CSRF检查顺序）
- 🔒 **安全修复**：修复XSS漏洞（操作日志、图片链接列表）
- 🔒 **安全修复**：添加API频率限制（每分钟100次请求）
- 🔒 **安全修复**：添加管理后台频率限制（每分钟10次请求）
- 🐛 **Bug修复**：修复登录失败计数显示错误
- 🐛 **Bug修复**：修复分页计算bug（总数为0时显示错误）
- 🐛 **Bug修复**：修复统计更新竞态条件（添加重试机制）
- ⚡ **优化**：优化URL重复检查效率（从O(n)到O(1)）
- ⚡ **优化**：JSON响应添加charset=utf-8
- 🔧 **改进**：使用__DIR__替代相对路径，提高兼容性
- 📝 **新增**：添加reset_config.php重置脚本

### v2.0 (2025-04-18)
- 🔒 增强安全性：添加SSRF防护
- 🔒 添加文件锁，防止并发写入问题
- ⚡ 实现图片URL缓存机制，提升性能
- 🧹 代码优化：提取重复逻辑为独立函数
- 📝 添加输入验证
- 📦 创建.gitignore保护敏感数据

### v1.0
- 初始版本发布
