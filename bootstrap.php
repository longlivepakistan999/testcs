<?php
/**
 * 应用引导文件
 * Application Bootstrap
 */

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 加载配置
$config = require __DIR__ . '/config/config.php';

// 设置时区
date_default_timezone_set($config['system']['timezone']);

// 自动加载
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 初始化数据库
\App\Database::init($config['database']);

// 返回配置供其他文件使用
return $config;
