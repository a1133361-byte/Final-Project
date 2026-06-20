<?php
session_start();
require_once "dbh.inc.php";

header('Content-Type: application/json');

// 檢查使用者是否登入
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "請先登入"]);
    exit();
}

// 檢查必要的 POST 參數
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["post_id"]) && isset($_POST["reason"])) {
    $post_id = $_POST["post_id"];
    $user_id = $_SESSION["user_id"];
    $reason = trim($_POST["reason"]);

    // 檢查內容是否為空
    if (empty($reason)) {
        echo json_encode(["status" => "error", "message" => "理由不能為空"]);
        exit();
    }

    try {
        // 插入檢舉資料到資料庫
        $sql = "INSERT INTO reports (post_id, user_id, reason) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$post_id, $user_id, $reason]);

        echo json_encode(["status" => "success", "message" => "檢舉已送出"]);
    } catch (PDOException $e) {
        // 錯誤處理
        echo json_encode(["status" => "error", "message" => "系統錯誤，請稍後再試"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "非法請求"]);
}