# CLAUDE.md - 旁站探测系统 (Side Site Detection System)

## 项目概述

这是一个基于 PHP 的旁站探测系统，用于市场调研分析，帮助判断域名使用的是共享空间还是独立服务器。

### 主要功能

1. **域名解析**: 将域名解析为IP地址
2. **Cloudflare检测**: 检测IP是否属于Cloudflare CDN（使用Cloudflare官方公开的IP范围）
3. **旁站探测**: 使用ViewDNS.info API进行反向IP查询，获取同IP下的其他域名
4. **主机类型判断**: 根据旁站数量判断是共享空间还是独立服务器
5. **数据导出**: 支持CSV和JSON格式导出

## 项目结构

```
testcs/
├── CLAUDE.md              # 本文件 - AI助手指南
├── bootstrap.php          # 应用引导文件（自动加载、配置初始化）
├── config/
│   └── config.php         # 配置文件（API密钥、数据库连接等）
├── database/
│   └── schema.sql         # 数据库结构SQL文件
├── src/
│   ├── Database.php       # 数据库连接类（PDO单例模式）
│   ├── CloudflareDetector.php  # Cloudflare IP检测类
│   └── SideSiteDetector.php    # 旁站探测核心类
├── public/
│   ├── index.php          # 前端页面（Web界面）
│   └── api.php            # RESTful API接口
└── cache/                 # 缓存目录（Cloudflare IP缓存）
```

## 技术栈

- **后端**: PHP 8.0+
- **数据库**: MySQL 5.7+ / MariaDB 10.3+
- **前端**: 原生HTML/CSS/JavaScript（无框架依赖）
- **外部API**: ViewDNS.info Reverse IP API

## 开发规范

### PHP代码规范

1. **命名空间**: 所有类使用 `App` 命名空间
2. **类型声明**: 使用严格类型声明（参数类型、返回类型）
3. **错误处理**: 使用异常处理，避免静默失败
4. **数据库**: 使用PDO预处理语句，防止SQL注入

### 数据库表结构

- `domains`: 主域名表，存储待检测域名及检测结果
- `side_sites`: 旁站表，存储每个域名的旁站信息
- `cloudflare_ips`: Cloudflare IP缓存表

### API接口

| 接口 | 方法 | 描述 |
|------|------|------|
| `?action=list` | GET | 获取域名列表（支持分页、状态筛选） |
| `?action=add` | POST | 添加域名（支持批量） |
| `?action=detect` | POST | 检测单个域名 |
| `?action=process` | POST | 处理检测队列 |
| `?action=side_sites` | GET | 获取旁站列表（带IP实时检测） |
| `?action=statistics` | GET | 获取统计数据 |
| `?action=delete` | POST | 删除域名 |
| `?action=retry` | POST | 重新检测失败的域名 |
| `?action=export_single` | GET | 导出单个域名的旁站 |
| `?action=export_all` | GET | 导出所有域名的旁站 |

## 配置说明

### config/config.php

```php
return [
    'viewdns' => [
        'api_key' => 'YOUR_API_KEY',  // ViewDNS.info API密钥
        'api_url' => 'https://api.viewdns.info/reverseip/',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'sidesite_detection',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'cloudflare' => [
        'ipv4_url' => 'https://www.cloudflare.com/ips-v4',
        'ipv6_url' => 'https://www.cloudflare.com/ips-v6',
        'cache_file' => __DIR__ . '/../cache/cloudflare_ips.json',
        'cache_ttl' => 86400,  // 24小时
    ],
];
```

## 部署步骤

1. **导入数据库**: 执行 `database/schema.sql`
2. **配置参数**: 编辑 `config/config.php`，填入：
   - ViewDNS.info API密钥
   - MySQL数据库连接信息
3. **设置目录权限**: `cache/` 目录需要写入权限
4. **配置Web服务器**: 将 `public/` 目录设为网站根目录

## 主机类型判断逻辑

- **旁站数量 > 5**: 判断为**共享空间**
- **旁站数量 <= 5**: 判断为**独立服务器**

阈值可在 `SideSiteDetector.php` 中的 `SHARED_HOSTING_THRESHOLD` 常量修改。

## 注意事项

1. **API限制**: ViewDNS.info API有调用频率限制，批量检测时会自动添加延迟
2. **Cloudflare域名**: 使用Cloudflare CDN的域名无法获取真实IP，会自动跳过旁站探测
3. **IP变化**: 旁站的IP可能会变化，系统支持实时检测当前IP与原始IP的一致性

## 常见问题

### Q: 为什么某些域名显示"已跳过"？
A: 这些域名使用了Cloudflare CDN，解析出的是Cloudflare的边缘节点IP，无法获取真实服务器IP。

### Q: 如何获取ViewDNS.info API密钥？
A: 访问 https://viewdns.info/api/ 注册账号并购买API访问权限。

### Q: 导出的CSV文件乱码？
A: 导出文件使用UTF-8编码并添加了BOM，如果仍有问题，请使用记事本或Excel的"数据导入"功能指定UTF-8编码打开。

## 给AI助手的提示

1. **修改配置**: 配置文件在 `config/config.php`
2. **添加功能**: 核心逻辑在 `src/SideSiteDetector.php`
3. **修改界面**: 前端代码在 `public/index.php`
4. **API扩展**: API接口在 `public/api.php`
5. **数据库变更**: 先修改 `database/schema.sql`，再执行迁移
