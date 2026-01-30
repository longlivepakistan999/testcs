<?php
/**
 * 数据库连接类
 * Database Connection Class
 */

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * 初始化配置
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * 获取数据库连接实例
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = self::$config;

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException('数据库连接失败: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * 关闭连接
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
