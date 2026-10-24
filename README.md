> ⚠️ **重要声明：本项目大量代码由 DEEPSEEK AI 辅助生成。**
> 这是一个旨在学习和探索的开源项目，请在部署和使用前充分测试，并根据自身需求进行调整。

# 🏫 CampusWall · 极简校园墙

**一个专为校园场景打造的轻量级、无需复杂配置的 PHP 校园墙/树洞系统。**

[![Latest Release](https://img.shields.io/github/v/release/BA4QZP/CampusWall?style=flat-square&color=blue)](https://github.com/BA4QZP/CampusWall/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-8892bf.svg)](https://php.net)
[![AI Assisted](https://img.shields.io/badge/AI%20Assisted-DEEPSEEK-yellow.svg)](#)

**无需 Composer，无需复杂环境，只要有 PHP 和 MySQL，扔上去就能跑。**

[⬇️ 一键下载最新版](https://github.com/BA4QZP/CampusWall/releases/latest) · [🌐 在线演示](https://ba4qzp.github.io/CampusWall/) · [🐛 提交 Issue](https://github.com/BA4QZP/CampusWall/issues)

---

## ✨ 核心特性

- 🚀 **傻瓜式安装**：自带可视化安装向导，自动建表、写入配置，一键跑通。
- 🎭 **纯粹匿名**：支持完全匿名发帖，保护学生隐私。
- 🛡️ **极简权限**：只有超管（站长）、管理员和普通学生，拒绝繁琐的版主等级。
- 🛒 **积分集市**：独创的积分交易系统，所有用户都能上架闲置/悬赏，系统自动扣款结算。
- 🤖 **AI 助手**：支持接入大语言模型（兼容 OpenAI 格式）。
- 💬 **实时私聊**：内置实时轮询机制，支持引用与撤回功能。
- 📝 **Markdown 支持**：发帖、评论、AI 对话完美支持 Markdown 语法。

---

## 📁 目录结构

```text
├── src/            # 核心代码 (初始化、公共函数、页面布局)
├── assets/         # 静态资源 (CSS, JS, Markdown库)
├── uploads/        # 用户上传的文件 (图片/附件)
├── install.php     # 一键安装向导 (安装后删除)
├── index.php       # 首页/发帖/评论/点赞路由
├── board.php       # 帖子列表渲染
├── index1.php      # 私聊界面
├── market.php      # 积分集市
├── profile.php     # 个人主页
├── ai.php          # AI 助手
├── admin.php       # 管理后台
└── .env            # 环境配置 (安装后自动生成，切勿上传)
```

---

## 🚀 极速部署（以 InfinityFree 为例）

1. **下载源码**：去 [Releases](https://github.com/BA4QZP/CampusWall/releases/latest) 页面下载最新的 `.zip` 压缩包并解压。
2. **上传代码**：将所有文件上传到你的服务器（或 `htdocs` 目录）。
3. **创建数据库**：在你的主机控制面板创建一个 MySQL 数据库（记下账号、密码、库名）。
4. **运行安装**：浏览器访问 `你的域名/install.php`，填入数据库信息，勾选需要的功能开关，点击安装。
5. **删除安装文件**：安装成功后，**务必删除 `install.php`**。
6. **成为超管**：访问首页，注册第一个账号，系统会自动将你设为 **超级管理员**。

---

## 🤖 关于 AI 助手的配置

本站默认**不包含**任何 AI API Key（防止被盗刷）。如果不配置，导航栏将不会显示 AI 入口。

- **免费方案**：建议去 [Agnes AI](https://agnes-ai.com) 或 [智谱开放平台](https://open.bigmodel.cn) 注册，他们有免费的 Flash 模型。
- **付费方案**：你可以接入任意 OpenAI 兼容格式的接口（如 DeepSeek、Kimi 等）。

配置方法：打开根目录的 `.env` 文件，修改以下三行：

```env
AI_BASE_URL="https://api.agnes-ai.cn/v1"
AI_API_KEY="你申请的key"
AI_MODEL="agnes-3.0-flash"
```
---

## ⚠️ 安全与运维建议

1. 确保 `uploads/` 目录不可执行 PHP 文件（如果你使用 Nginx/Apache，可以配置禁止解析）。
2. 务必在 `.gitignore` 中忽略 `.env` 文件，防止数据库密码泄露。
3. 建议在安装时开启强制人工审核，防止外部闲杂人员进入。
4. 建议定期清理 `ip_logs` 表，避免数据库体积膨胀。

---

## 📄 开源协议

本项目基于 [MIT License](https://opensource.org/licenses/MIT) 开源。你可以自由地修改、分发，甚至用于商业用途。
