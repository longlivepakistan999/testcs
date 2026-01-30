-- 旁站探测系统数据库结构
-- Side Site Detection System Database Schema

CREATE DATABASE IF NOT EXISTS `sidesite_detection` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `sidesite_detection`;

-- 域名表：存储待检测的域名
CREATE TABLE IF NOT EXISTS `domains` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `domain` VARCHAR(255) NOT NULL COMMENT '域名',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT '解析的IP地址',
    `is_cloudflare` TINYINT(1) DEFAULT 0 COMMENT '是否是Cloudflare IP: 0-否, 1-是',
    `side_site_count` INT UNSIGNED DEFAULT 0 COMMENT '旁站数量',
    `hosting_type` ENUM('unknown', 'shared', 'dedicated') DEFAULT 'unknown' COMMENT '主机类型: unknown-未知, shared-共享空间, dedicated-独立服务器',
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending' COMMENT '检测状态',
    `error_message` TEXT DEFAULT NULL COMMENT '错误信息',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `detected_at` TIMESTAMP NULL DEFAULT NULL COMMENT '检测完成时间',
    UNIQUE KEY `uk_domain` (`domain`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_is_cloudflare` (`is_cloudflare`),
    KEY `idx_hosting_type` (`hosting_type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='域名表';

-- 旁站表：存储每个域名的旁站信息
CREATE TABLE IF NOT EXISTS `side_sites` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `domain_id` INT UNSIGNED NOT NULL COMMENT '关联的域名ID',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
    `side_domain` VARCHAR(255) NOT NULL COMMENT '旁站域名',
    `last_resolved` DATE DEFAULT NULL COMMENT '最后解析日期(来自ViewDNS)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    KEY `idx_domain_id` (`domain_id`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_side_domain` (`side_domain`),
    CONSTRAINT `fk_side_sites_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='旁站表';

-- Cloudflare IP缓存表
CREATE TABLE IF NOT EXISTS `cloudflare_ips` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_range` VARCHAR(50) NOT NULL COMMENT 'IP范围(CIDR格式)',
    `ip_type` ENUM('ipv4', 'ipv6') NOT NULL COMMENT 'IP类型',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    UNIQUE KEY `uk_ip_range` (`ip_range`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cloudflare IP缓存表';

-- 统计视图：主机类型统计
CREATE OR REPLACE VIEW `v_hosting_statistics` AS
SELECT
    hosting_type,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM domains WHERE status = 'completed'), 2) as percentage
FROM domains
WHERE status = 'completed'
GROUP BY hosting_type;

-- 统计视图：Cloudflare使用统计
CREATE OR REPLACE VIEW `v_cloudflare_statistics` AS
SELECT
    CASE WHEN is_cloudflare = 1 THEN '使用Cloudflare' ELSE '未使用Cloudflare' END as cf_status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM domains), 2) as percentage
FROM domains
GROUP BY is_cloudflare;
