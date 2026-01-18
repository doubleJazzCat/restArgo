# restArgo
A PHP-based Web API debugging tool.

<div align="center">
  <img src="assets/argo.jpg" alt="restArgo Logo" width="120" style="border-radius: 20px;">
  <h1>restArgo</h1>
  <p><strong>v0.9.0 (Dev)</strong></p>
  <p>A lightweight, self-hosted Web API debugging tool built with PHP & Vue 3.</p>
  <p>
    <img src="https://img.shields.io/badge/PHP-7.4%2B-blue" alt="PHP">
    <img src="https://img.shields.io/badge/Vue.js-3.0-green" alt="Vue">
    <img src="https://img.shields.io/badge/Database-SQLite-lightgrey" alt="SQLite">
  </p>
</div>

---

## 📖 项目简介

**restArgo** 是一款私有化部署的 Web 端 API 调试工具。 

无需复杂的安装过程，无需 Node.js 或 Redis，只需 PHP 环境即可运行。所有数据默认存储在本地 SQLite 文件中，安全且易于迁移。

## ✨ 核心特性

- **极致轻量**: 纯 PHP + Vue 构建，单目录即插即用。
- **全功能请求**: 支持 GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS 等标准方法。
- **深度审计 (Inspect)**: 提供类似浏览器 F12 的视图，查看真实的请求头、响应头及 cURL 握手信息。
- **目录管理**: 支持无限层级文件夹，方便归档整理接口。
- **拖拽交互**: 基于 Sortable.js，支持文件夹和请求的自由拖拽排序。
- **超时控制**: 可为特定的慢速接口单独设置超时时间。
- **私有部署**: 内置简单的访问密码锁，适合内网或个人服务器使用。

## 📦 安装部署

### 1. 环境要求
* PHP 7.4 或更高版本
* PHP 扩展: `curl`, `pdo`, `pdo_sqlite` (默认) 或 `pdo_mysql`

### 2. 获取代码
直接克隆仓库到你的 Web 服务器目录：

```bash
git clone https://github.com/doubleJazzCat/restArgo.git
cd restArgo
```

### 3. 初始化配置
复制配置文件模版：

```bash
cp config-sample.php config.php
```

然后编辑 config.php 修改默认密码（推荐）。

### 4. 权限设置
确保 Web 服务器用户（如 `www-data` 或 `nginx`）对 `data` 目录拥有**写入权限**。

```bash
chmod -R 777 data
```

### 5. 目录结构
```text
/restArgo/
├── api.php
├── config-sample.php  # 配置文件模版
├── config.php         # 实际配置文件 (手动复制)
├── index.php
├── data/              # 数据存储目录 (程序自动生成)
└── assets/            # 前端静态资源
```

## 📂 静态资源来源 (Assets)

本项目为了实现内网/离线可用，已将核心依赖库内置于 `assets/` 目录。以下是各文件的原始来源说明：

| 本地文件名 | 库名称 / 说明 | 原始 CDN 来源参考 |
| :--- | :--- | :--- |
| `vue.js` | **Vue 3** (Global Build) | `cdnjs.cloudflare.com/ajax/libs/vue/3.2.47/vue.global.prod.min.js` |
| `tailwind.js` | **Tailwind CSS** (Play CDN) | `cdn.tailwindcss.com/3.3.0` |
| `sortable.js` | **SortableJS** (拖拽排序库) | `cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js` |
| `highlight.js` | **Highlight.js** (核心逻辑) | `cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js` |
| `highlight.css` | **Highlight.js** (GitHub样式) | `cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github.min.css` |
| `beautify.js` | **JS Beautify** (JS格式化) | `cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.7/beautify.min.js` |
| `beautify-html.js`| **JS Beautify** (HTML格式化) | `cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.7/beautify-html.min.js` |

## 📄 License

MIT License.
