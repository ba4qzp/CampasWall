# PHPCampus

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![License](https://img.shields.io/badge/License-MIT-green)
![Dependencies](https://img.shields.io/badge/Dependencies-Zero-brightgreen)

PHPCampus 是一个基于原生 PHP 开发的极简校园墙 / 树洞系统。**无需 Composer，零外部依赖**，专为 InfinityFree 等免费虚拟主机设计，上传即可完成一键建站。

### 🚀 快速下载
- [**⬇️ 下载最新版 (.zip)**](https://github.com/BA4QZP/PHPCampus/releases/latest) （前往 Releases 页面）
- [**直接下载主分支源码**](https://github.com/BA4QZP/PHPCampus/archive/refs/heads/main.zip)

### 🌐 在线演示
[https://phpcampus.gt.tc](https://phpcampus.gt.tc)

## ✨ 核心特性

- **🚀 极速部署**：纯 PHP 环境，无需配置复杂的运行环境，自带一键安装向导。
- **🛡️ 内置基础安全防线**：
  - 安装/初始化时自动为 `uploads/` 目录生成 `.htaccess`，**强制阻断任何脚本执行权限**，防止恶意文件上传攻击。
  - 内置日志自动维护机制，定期清理过期的 IP 日志与无用通知，防止数据库膨胀。
- **🎭 隐私与互动**：支持匿名发帖、Markdown 渲染、密码帖、关闭评论区、匿名评论及私聊撤回。
- **🛒 积分集市**：轻量级的站内交易系统，支持带图发布、积分扣款、售出通知等完整闭环。
- **🤖 AI 助手扩展**：预留 OpenAI 兼容接口，在 `.env` 中填入 API Key 即可开启站内 AI 对话。

## 📦 部署指南

1. 点击上方链接下载最新版本的 `.zip` 压缩包。
2. 解压并将所有文件上传至你的网站根目录。
3. 在浏览器中访问 `http://你的域名/install.php`，按提示填写数据库信息和超管账号。
4. **安装完成后，请务必删除 `install.php` 文件**，并在后台绑定你的域名。

### 🛡️ 生产环境安全建议

虽然系统已做基础加固，但部署至公网前，建议额外完成以下操作：
1. **强制 HTTPS**：在服务器或 CDN 层配置 SSL 证书，防止数据明文传输。
2. **修改目录权限**：确保 `uploads/` 目录仅有读写权限，无执行权限（系统已自动尝试通过 `.htaccess` 限制）。
3. **敏感配置隔离**：确保 `.env` 文件不会被直接访问下载。

## 💻 关于本项目

- **开发背景**：本项目核心逻辑由 DeepSeek AI 辅助生成，旨在提供一个极致轻量、无需框架即可运行的校园交流平台原型。
- **适用场景**：非常适合 PHP 初学者阅读源码、学习 Web 开发，或用于班级、社团的内部非核心交流。如需用于大型高并发或处理极其敏感的数据，建议由专业团队进行二次代码审计。
- **开源协议**：[MIT License](https://opensource.org/licenses/MIT)。你可以自由使用、修改和分发。

## 🤝 反馈与贡献

如果你在部署或使用中遇到 Bug，欢迎提交 [Issue](https://github.com/BA4QZP/PHPCampus/issues)。如果是安全相关的漏洞，请直接联系作者。
