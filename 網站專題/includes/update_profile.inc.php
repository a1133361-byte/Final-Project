<?php
session_start();
if (isset($_POST["submit_profile"]) && isset($_SESSION["user_id"])) {
    require_once "dbh.inc.php";
    $bio = $_POST["bio"];
    $uid = $_SESSION["user_id"];

    try {
        $sql = "UPDATE users SET bio = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bio, $uid]);

        header("Location: ../profile.php?id=" . $uid . "&update=success");
        exit();
    } catch (PDOException $e) {
        die("更新失敗: " . $e->getMessage());
    }
}