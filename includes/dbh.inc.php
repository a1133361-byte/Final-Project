<?php

// 1. 優先讀取 Render 後台設定的 PostgreSQL 連線網址
$dbUrl = getenv('DATABASE_URL');

if ($dbUrl) {
    // 自動解析 postgres://user:pass@host:port/dbname 格式
    $url = parse_url($dbUrl);
    
    $host = $url["host"];
    $port = $url["port"] ?? 5432; // PostgreSQL 預設 port 是 5432
    $dbusername = $url["user"];
    $dbpassword = $url["pass"];
    $dbname = ltrim($url["path"], '/');
    
    // 💡 組合為 PostgreSQL (pgsql) 的 DSN 格式
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
} else {
    // 2. 如果沒有環境變數（例如你在本機電腦 XAMPP 測試時的備用方案）
    $host = 'localhost';
    $dbname = 'forum'; 
    $dbusername = 'postgres'; // PostgreSQL 在本機的預設帳號通常是 postgres
    $dbpassword = '';         // 請填寫你本機 PostgreSQL 的密碼
    
    $dsn = "pgsql:host=$host;dbname=$dbname;";
}

try {
    // 建立 PostgreSQL 的 PDO 連線
    $pdo = new PDO($dsn, $dbusername, $dbpassword);

    // 保持你原本設定的報錯與抓取模式
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}
