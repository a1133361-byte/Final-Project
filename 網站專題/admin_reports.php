<?php
session_start();
require_once "includes/dbh.inc.php";

// 權限檢查：只有管理員能進來
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

// 處理管理行為 (刪除或忽略)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $report_id = $_POST['report_id'];
    $post_id = $_POST['post_id'];
    
    try {
        if ($_POST['action'] === 'delete_post') {
            // 1. 刪除文章 (連動刪除可能需要資料庫設定 ON DELETE CASCADE)
            $del_stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
            $del_stmt->execute([$post_id]);
            
            // 2. 標記檢舉已處理
            $upd_stmt = $pdo->prepare("UPDATE reports SET status = 1 WHERE id = ?");
            $upd_stmt->execute([$report_id]);
            
            $msg = "文章已成功刪除並關閉案件。";
        } elseif ($_POST['action'] === 'ignore_report') {
            // 標記檢舉已處理 (但不刪文章)
            $upd_stmt = $pdo->prepare("UPDATE reports SET status = 1 WHERE id = ?");
            $upd_stmt->execute([$report_id]);
            $msg = "檢舉已忽略。";
        }
    } catch (PDOException $e) {
        $error = "操作失敗: " . $e->getMessage();
    }
}

// 讀取未處理檢舉
try {
    $sql = "SELECT r.*, p.title as post_title, p.content as post_content, u.username as reporter 
            FROM reports r 
            JOIN posts p ON r.post_id = p.id 
            JOIN users u ON r.user_id = u.id 
            WHERE r.status = 0 
            ORDER BY r.created_at DESC";
    $reports = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    die("讀取檢舉失敗");
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>檢舉審核中心 - Admin</title>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; background: #f1f5f9; padding: 20px; color: #1e293b; line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* 標題區塊 */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
        .back-link { text-decoration: none; color: #6366f1; font-weight: 700; transition: color 0.2s; }
        .back-link:hover { color: #4f46e5; }

        /* 提示訊息 */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* 檢舉卡片 */
        .report-card { background: white; border-radius: 15px; padding: 0; margin-bottom: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0; }
        
        .report-meta { padding: 15px 25px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 0.9rem; color: #64748b; }
        
        .report-body { padding: 25px; }

        /* 檢舉理由區塊 */
        .reason-section { background: #fff1f2; border: 1px solid #fecaca; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .reason-label { display: block; color: #e11d48; font-weight: 800; font-size: 0.85rem; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.05em; }
        .reason-text { color: #9f1239; font-size: 1.05rem; word-break: break-all; white-space: pre-wrap; }

        /* 文章預覽區塊 */
        .post-preview { background: #ffffff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 10px; margin-bottom: 20px; position: relative; }
        .post-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .post-title { font-weight: 800; font-size: 1.1rem; color: #1e293b; display: block; flex: 1; }
        .view-post-link { 
            text-decoration: none; 
            color: #6366f1; 
            font-size: 0.85rem; 
            padding: 4px 10px; 
            border: 1px solid #6366f1; 
            border-radius: 6px; 
            white-space: nowrap; 
            margin-left: 10px;
            transition: all 0.2s;
        }
        .view-post-link:hover { background: #6366f1; color: white; }
        
        .post-content { color: #475569; font-size: 0.95rem; border-top: 1px dashed #cbd5e1; margin-top: 10px; padding-top: 10px; }

        /* 按鈕區 */
        .actions { display: flex; gap: 12px; }
        .btn { flex: 1; padding: 12px; border-radius: 8px; cursor: pointer; border: none; font-weight: 700; transition: all 0.2s; text-align: center; font-size: 1rem; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }

        @media (max-width: 600px) {
            .actions { flex-direction: column; }
            .report-meta { flex-direction: column; gap: 5px; }
            .post-header { flex-direction: column; gap: 8px; }
            .view-post-link { margin-left: 0; align-self: flex-start; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🚩 檢舉審核中心</h1>
        <a href="index.php" class="back-link">← 返回首頁</a>
    </div>

    <?php if(isset($msg)): ?>
        <div class="alert alert-success"><?= $msg ?></div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <?php if(count($reports) == 0): ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 15px; color: #64748b;">
            <p style="font-size: 3rem; margin-bottom: 10px;">🎉</p>
            <p>目前沒有待處理的檢舉案件。</p>
        </div>
    <?php endif; ?>

    <?php foreach ($reports as $r): ?>
        <div class="report-card">
            <!-- 頂部資訊條 -->
            <div class="report-meta">
                <span><strong>檢舉人：</strong><?= htmlspecialchars($r['reporter']) ?></span>
                <span><strong>時間：</strong><?= $r['created_at'] ?></span>
            </div>
            
            <div class="report-body">
                <!-- 檢舉理由 -->
                <div class="reason-section">
                    <span class="reason-label">檢舉理由 (詳細說明)</span>
                    <div class="reason-text"><?= nl2br(htmlspecialchars($r['reason'])) ?></div>
                </div>

                <!-- 被檢舉的文章內容 -->
                <div class="post-preview">
                    <div class="post-header">
                        <span class="post-title">文章標題：<?= htmlspecialchars($r['post_title']) ?></span>
                        <!-- 新增：前往文章頁面的連結 -->
                        <a href="view_post.php?id=<?= $r['post_id'] ?>" target="_blank" class="view-post-link">🔗 查看完整文章</a>
                    </div>
                    <div class="post-content">
                        <strong>內容全文：</strong><br>
                        <?= nl2br(htmlspecialchars($r['post_content'])) ?>
                    </div>
                </div>

                <!-- 審核操作 -->
                <form method="POST" onsubmit="return confirm('確定執行此操作嗎？');">
                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="post_id" value="<?= $r['post_id'] ?>">
                    <div class="actions">
                        <button type="submit" name="action" value="delete_post" class="btn btn-danger">刪除違規文章</button>
                        <button type="submit" name="action" value="ignore_report" class="btn btn-secondary">忽略檢舉 (保留文章)</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>