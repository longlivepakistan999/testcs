# CLAUDE.md - 旁站探测系统 (Side Site Detection System)

## 项目概述

这是一个基于 PHP 的旁站探测系统，用于市场调研分析，帮助判断域名使用的是共享空间还是独立服务器。支持大规模批量处理（10万+域名）。

### 主要功能

1. **域名解析**: 将域名解析为IP地址
2. **Cloudflare检测**: 检测IP是否属于Cloudflare CDN（使用Cloudflare官方公开的IP范围）
3. **旁站探测**: 使用ViewDNS.info API进行反向IP查询，获取同IP下的其他域名
4. **主机类型判断**: 根据旁站数量判断是共享空间还是独立服务器
5. **IP一致性检测**: 自动检测旁站当前IP与原始IP是否一致
6. **全局搜索**: 搜索某个URL是主域名资产还是某个资产的旁站
7. **数据导出**: 支持CSV和JSON格式导出，支持流式导出大数据量
8. **批量处理**: CLI工具支持大规模域名批量导入、后台处理、批量导出
9. **文件导入**: Web界面支持TXT/CSV文件上传导入域名

## 项目结构

```
testcs/
├── CLAUDE.md              # 本文件 - AI助手指南
├── bootstrap.php          # 应用引导文件（自动加载、配置初始化）
├── config/
│   ├── config.php         # 配置文件（API密钥、数据库连接等）
│   └── config.example.php # 配置文件示例
├── database/
│   └── schema.sql         # 数据库结构SQL文件
├── src/
│   ├── Database.php       # 数据库连接类（PDO单例模式）
│   ├── CloudflareDetector.php  # Cloudflare IP检测类
│   └── SideSiteDetector.php    # 旁站探测核心类
├── public/
│   ├── index.php          # 前端页面（Web界面，含全局搜索）
│   ├── detail.php         # 旁站详情页面（分页显示）
│   └── api.php            # RESTful API接口
├── cli/
│   ├── import.php         # 批量导入域名脚本
│   ├── process.php        # 后台队列处理脚本
│   └── export.php         # 批量导出脚本
├── cache/                 # 缓存目录（Cloudflare IP缓存）
└── logs/                  # 日志目录
```

## 技术栈

- **后端**: PHP 8.0+
- **数据库**: MySQL 5.7+ / MariaDB 10.3+
- **前端**: 原生HTML/CSS/JavaScript（无框架依赖）
- **外部API**: ViewDNS.info Reverse IP API

## CLI命令行工具（大规模批量处理）

### 1. 批量导入域名

```bash
# 从TXT文件导入（每行一个域名）
php cli/import.php domains.txt

# 从CSV文件导入（第一列为域名）
php cli/import.php domains.csv

# 跳过重复域名
php cli/import.php domains.txt --skip-duplicates

# 查看帮助
php cli/import.php --help
```

### 2. 后台队列处理

```bash
# 开始处理队列
php cli/process.php

# 自定义参数
php cli/process.php --batch=50 --delay=1000

# 限制处理数量
php cli/process.php --limit=1000

# 查看当前状态
php cli/process.php --status

# 重试失败的域名
php cli/process.php --retry

# 后台运行（推荐）
nohup php cli/process.php --batch=100 > logs/process.log 2>&1 &

# 或使用screen
screen -S detector
php cli/process.php --batch=100
# Ctrl+A+D 分离
```

**参数说明：**
- `--batch=N`: 每批处理数量（默认100）
- `--delay=N`: API调用间隔毫秒（默认500）
- `--limit=N`: 总处理数量限制
- `--status`: 显示当前处理状态
- `--retry`: 重试所有失败的域名

### 3. 批量导出

```bash
# 导出到CSV文件
php cli/export.php -o result.csv

# 导出为JSON格式
php cli/export.php -o result.json -f json

# 只导出共享空间
php cli/export.php --type=shared -o shared.csv

# 快速导出（不检测当前IP）
php cli/export.php --no-ip-check -o quick.csv

# 查看帮助
php cli/export.php --help
```

### 处理10万+域名的推荐流程

```bash
# 1. 导入域名
php cli/import.php domains.txt

# 2. 查看状态
php cli/process.php --status

# 3. 后台处理（使用screen）
screen -S detector
php cli/process.php --batch=100 --delay=500

# 4. 查看进度（新终端）
php cli/process.php --status

# 5. 导出结果
php cli/export.php -o result.csv --no-ip-check
```

**预估时间：**
- 10万域名，500ms延迟 ≈ 14小时
- 10万域名，1000ms延迟 ≈ 28小时

## 开发规范

### PHP代码规范

1. **命名空间**: 所有类使用 `App` 命名空间
2. **类型声明**: 使用严格类型声明（参数类型、返回类型）
3. **错误处理**: 使用异常处理，避免静默失败
4. **数据库**: 使用PDO预处理语句，防止SQL注入

### 数据库表结构

- `domains`: 主域名表，存储待检测域名及检测结果
- `side_sites`: 旁站表，存储每个域名的旁站信息（含当前IP、IP一致性）
- `cloudflare_ips`: Cloudflare IP缓存表
- `ip_cache`: IP解析缓存表（避免重复DNS查询）

### 搜索功能

系统支持全局搜索，可以查询某个URL是主域名资产还是某个资产的旁站：

```
GET api.php?action=search&q=example.com
```

返回结果包含：
- `domains`: 匹配的主域名列表
- `side_sites`: 匹配的旁站列表（含所属主域名信息）

### API接口

| 接口 | 方法 | 描述 |
|------|------|------|
| `?action=list` | GET | 获取域名列表（支持分页、状态筛选） |
| `?action=add` | POST | 添加域名（支持批量） |
| `?action=detect` | POST | 检测单个域名 |
| `?action=process` | POST | 处理检测队列 |
| `?action=side_sites` | GET | 获取旁站列表（支持分页，从数据库读取） |
| `?action=refresh_ip` | POST | 刷新旁站IP（重新检测并更新数据库） |
| `?action=statistics` | GET | 获取统计数据 |
| `?action=delete` | POST/DELETE | 删除域名 |
| `?action=retry` | POST | 重新检测失败的域名 |
| `?action=export_single` | GET | 导出单个域名的旁站（支持IP筛选） |
| `?action=export_all` | GET | 导出所有域名的旁站（支持流式导出） |
| `?action=export_stats` | GET | 获取导出统计（预估大小） |
| `?action=import` | POST | 文件上传导入域名（TXT/CSV） |
| `?action=export_domains` | GET | 导出域名列表（不含旁站） |
| `?action=batch_retry` | POST | 批量重试选中的域名 |
| `?action=retry_all_failed` | POST | 重试所有失败的域名 |
| `?action=batch_export` | GET | 批量导出选中的域名 |
| `?action=batch_delete` | POST | 批量删除选中的域名 |
| `?action=search` | GET | 搜索域名和旁站（查询某URL是主域名还是旁站） |

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
2. **配置参数**: 复制 `config/config.example.php` 为 `config/config.php`，填入：
   - ViewDNS.info API密钥
   - MySQL数据库连接信息
3. **设置目录权限**: `cache/` 和 `logs/` 目录需要写入权限
4. **配置Web服务器**: 将 `public/` 目录设为网站根目录

## 主机类型判断逻辑

- **旁站数量 > 5**: 判断为**共享空间**
- **旁站数量 <= 5**: 判断为**独立服务器**

阈值可在 `SideSiteDetector.php` 中的 `SHARED_HOSTING_THRESHOLD` 常量修改。

## 注意事项

1. **API限制**: ViewDNS.info API有调用频率限制，批量检测时会自动添加延迟
2. **Cloudflare域名**: 使用Cloudflare CDN的域名无法获取真实IP，会自动跳过旁站探测
3. **IP变化**: 旁站的IP可能会变化，系统支持实时检测当前IP与原始IP的一致性
4. **大规模处理**: 使用CLI工具处理大量域名，支持断点续传和后台运行

## 常见问题

### Q: 为什么某些域名显示"已跳过"？
A: 这些域名使用了Cloudflare CDN，解析出的是Cloudflare的边缘节点IP，无法获取真实服务器IP。

### Q: 如何获取ViewDNS.info API密钥？
A: 访问 https://viewdns.info/api/ 注册账号并购买API访问权限。

### Q: 导出的CSV文件乱码？
A: 导出文件使用UTF-8编码并添加了BOM，如果仍有问题，请使用记事本或Excel的"数据导入"功能指定UTF-8编码打开。

### Q: 处理中断了怎么办？
A: 直接重新运行 `php cli/process.php`，系统会自动从上次中断的地方继续处理。

### Q: 如何重试失败的域名？
A: 运行 `php cli/process.php --retry`，会将所有失败的域名重置为待处理状态。

## 给AI助手的提示

1. **修改配置**: 配置文件在 `config/config.php`
2. **添加功能**: 核心逻辑在 `src/SideSiteDetector.php`
3. **修改界面**: 前端代码在 `public/index.php`
4. **API扩展**: API接口在 `public/api.php`
5. **CLI工具**: 命令行脚本在 `cli/` 目录
6. **数据库变更**: 先修改 `database/schema.sql`，再执行迁移
