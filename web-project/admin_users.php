<?php
session_start();
require_once "includes/dbh.inc.php";

// 驗證管理員身份 (1 = 管理員)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

$current_admin_id = $_SESSION['user_id'] ?? null; // 記錄當前登入的管理員 ID
$success_msg = "";
$error_msg = "";

// ── 處理 POST 動作 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_role' && isset($_POST['user_id']) && isset($_POST['new_role'])) {
        $target_user_id = (int)$_POST['user_id'];
        $new_role = (int)$_POST['new_role'];
        
        if ($target_user_id === (int)$current_admin_id) {
            $error_msg = "安全保護：您無法變更自己的權限，以防失去管理員身份！";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $target_user_id]);
                $success_msg = "用戶權限已成功更新！";
            } catch (PDOException $e) {
                $error_msg = "更新失敗：" . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_user' && isset($_POST['user_id'])) {
        $target_user_id = (int)$_POST['user_id'];
        
        if ($target_user_id === (int)$current_admin_id) {
            $error_msg = "安全保護：您無法刪除您目前正在登入的管理員帳號！";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target_user_id]);
                $success_msg = "用戶帳號已成功刪除！";
            } catch (PDOException $e) {
                $error_msg = "刪除失敗：該用戶可能有關聯的資料尚未清除（" . $e->getMessage() . "）";
            }
        }
    }
}

// ── 搜尋與分頁邏輯 ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(username LIKE :search OR email LIKE :search OR id = :search_id)";
    $params['search'] = "%$search%";
    $params['search_id'] = is_numeric($search) ? (int)$search : -1;
}

if ($role_filter !== '') {
    $where_clauses[] = "role = :role";
    $params['role'] = (int)$role_filter;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// 計算總使用者數 (分頁用)
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_sql");
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    die("資料查詢失敗：" . $e->getMessage());
}

$limit = 10; // 每頁顯示數量
$total_pages = ceil($total_users / $limit);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $limit;

// 取得使用者列表
try {
    $sql = "SELECT id, username, email, role, profile_img, created_at FROM users $where_sql ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    // 綁定搜尋參數
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("使用者資料讀取失敗：" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>成員管理 - 管理後台</title>
    <style>
        :root {
            --bg: #f0f4ff;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #6366f1;
            --accent-soft: rgba(99,102,241,.10);
            --gold: #f59e0b;
            --gold-soft: rgba(245,158,11,.10);
            --green: #10b981;
            --green-soft: rgba(16,185,129,.10);
            --red: #ef4444;
            --red-soft: rgba(239,68,68,.10);
            --header-h: 60px;
        }
        [data-theme="dark"] {
            --bg: #0d1526;
            --card: #1a253a;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --border: #2d3f5a;
            --accent-soft: rgba(99,102,241,.18);
            --gold-soft: rgba(245,158,11,.15);
            --green-soft: rgba(16,185,129,.15);
            --red-soft: rgba(239,68,68,.15);
        }

        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: background .3s, color .3s;
        }

        /* ── Header ── */
        .adm-header {
            height: var(--header-h);
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 200;
        }
        .adm-header .logo {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; font-size: 1.1rem;
        }
        .adm-header .logo span { font-size: 1.4rem; }
        .header-actions { display: flex; align-items: center; gap: 12px; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 10px;
            font-size: .85rem; font-weight: 700;
            cursor: pointer; border: none; transition: .2s;
            text-decoration: none;
        }
        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
        .btn-icon {
            background: none; border: none; cursor: pointer;
            font-size: 1.2rem; padding: 6px; border-radius: 8px;
            color: var(--muted); transition: .2s;
        }
        .btn-icon:hover { background: var(--accent-soft); color: var(--accent); }
        
        .btn-submit {
            background: var(--accent);
            color: #fff;
        }
        .btn-submit:hover { filter: brightness(1.1); }

        .btn-danger {
            background: var(--red-soft);
            color: var(--red);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        .btn-danger:hover {
            background: var(--red);
            color: #fff;
        }

        /* ── Layout ── */
        .page { max-width: 1280px; margin: 0 auto; padding: 28px 24px 60px; }
        .page-title { font-size: 1.5rem; font-weight: 800; margin: 0 0 6px; }
        .page-sub { color: var(--muted); font-size: .9rem; margin: 0 0 28px; }

        /* ── Alert Messages ── */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid rgba(16,185,129,.2);
        }
        .alert-danger {
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid rgba(239,68,68,.2);
        }

        /* ── Search Card ── */
        .search-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
        }
        .input-control {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color .2s;
        }
        .input-control:focus {
            border-color: var(--accent);
        }
        .search-actions {
            display: flex;
            gap: 10px;
            align-self: flex-end;
            margin-top: auto;
        }

        /* ── Table Card ── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 24px;
            overflow: hidden;
        }
        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .card-title {
            font-size: 1rem; font-weight: 800;
            margin: 0;
            display: flex; align-items: center; gap: 8px;
        }
        .total-badge {
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
        }

        /* ── Responsive Table ── */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
        }
        .htable { width: 100%; border-collapse: collapse; font-size: .88rem; min-width: 800px; }
        .htable th {
            text-align: left; padding: 12px 16px;
            font-size: .72rem; font-weight: 800;
            color: var(--muted); text-transform: uppercase;
            border-bottom: 2px solid var(--border);
        }
        .htable td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .htable tr:last-child td { border-bottom: none; }
        .htable tr:hover td { background: var(--accent-soft); }
        
        .u-avatar-wrap { display: flex; align-items: center; gap: 12px; }
        .u-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--accent-soft);
            flex-shrink: 0;
        }
        .u-username { font-weight: 700; font-size: .9rem; color: var(--text); text-decoration: none; }
        .u-username:hover { color: var(--accent); }
        .u-email { font-size: 0.8rem; color: var(--muted); }

        /* ── Select Role Dropdown ── */
        .role-select {
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            outline: none;
        }
        .role-select:focus {
            border-color: var(--accent);
        }

        /* ── Badges ── */
        .role-badge {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .role-admin {
            background: var(--gold-soft);
            color: var(--gold);
        }
        .role-user {
            background: var(--accent-soft);
            color: var(--accent);
        }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
        }
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            transition: .2s;
        }
        .page-link:hover:not(.disabled) {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
        }
        .page-link.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 48px 0;
            color: var(--muted);
        }
        .empty-icon { font-size: 3rem; margin-bottom: 12px; }

        /* ── Custom Dialog Modal ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }
        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
        .modal {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            max-width: 400px;
            width: 90%;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }
        .modal-overlay.open .modal {
            transform: scale(1);
        }
        .modal-title {
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--red);
        }
        .modal-body {
            font-size: 0.9rem;
            color: var(--text);
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
    </style>
</head>
<body data-theme="light">

<header class="adm-header">
    <div class="logo">
        <span>👥</span> 後台成員管理
    </div>
    <div class="header-actions">
        <a href="index.php" class="btn btn-ghost">⬅️ 返回論壇</a>
        <button class="btn-icon" id="themeBtn" title="切換主題">🌓</button>
    </div>
</header>

<div class="page">
    <h1 class="page-title">論壇成員管理</h1>
    <p class="page-sub">搜尋、編輯用戶權限以及安全移除用戶帳號</p>

    <!-- 訊息提示 -->
    <?php if ($success_msg !== ""): ?>
        <div class="alert alert-success">
            <span>✅</span> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg !== ""): ?>
        <div class="alert alert-danger">
            <span>⚠️</span> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- 搜尋卡片 -->
    <div class="search-card">
        <form class="search-form" method="GET" action="admin_users.php">
            <div class="form-group">
                <label for="search">關鍵字搜尋</label>
                <input type="text" id="search" name="search" class="input-control" placeholder="輸入用戶ID、名稱或信箱..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="form-group">
                <label for="role">角色篩選</label>
                <select id="role" name="role" class="input-control">
                    <option value="">所有權限</option>
                    <option value="0" <?= $role_filter === '0' ? 'selected' : '' ?>>一般用戶 (0)</option>
                    <option value="1" <?= $role_filter === '1' ? 'selected' : '' ?>>管理員 (1)</option>
                </select>
            </div>

            <div class="search-actions">
                <button type="submit" class="btn btn-submit">🔍 開始搜尋</button>
                <?php if ($search !== '' || $role_filter !== ''): ?>
                    <a href="admin_users.php" class="btn btn-ghost">🧹 重設</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- 用戶列表卡片 -->
    <div class="card">
        <div class="card-header-flex">
            <h2 class="card-title">👥 成員名單 <span class="total-badge">共 <?= $total_users ?> 筆</span></h2>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h3>找不到符合條件的成員</h3>
                <p>請嘗試使用不同的關鍵字或重設篩選條件。</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="htable">
                    <thead>
                        <tr>
                            <th style="width: 80px;">UID</th>
                            <th>成員資訊</th>
                            <th>電子郵件</th>
                            <th style="width: 150px;">當前角色</th>
                            <th style="width: 180px;">權限調整</th>
                            <th>加入日期</th>
                            <th style="width: 100px; text-align: center;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--muted);">#<?= $u['id'] ?></td>
                            <td>
                                <div class="u-avatar-wrap">
                                    <img src="<?= !empty($u['profile_img']) ? "uploads/users_profile_img/".$u['profile_img'] : "uploads/default_avatar.png" ?>" class="u-avatar">
                                    <div>
                                        <a href="profile.php?id=<?= $u['id'] ?>" class="u-username" target="_blank">
                                            <?= htmlspecialchars($u['username']) ?>
                                        </a>
                                        <?php if ((int)$u['id'] === (int)$current_admin_id): ?>
                                            <span class="role-badge role-admin" style="font-size: 0.65rem; padding: 2px 6px; margin-left: 4px;">你自己</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="u-email"><?= htmlspecialchars($u['email'] ?? '未提供') ?></span></td>
                            <td>
                                <?php if ($u['role'] == 1): ?>
                                    <span class="role-badge role-admin">🛡️ 管理員</span>
                                <?php else: ?>
                                    <span class="role-badge role-user">👤 一般用戶</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="admin_users.php" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="new_role" class="role-select" onchange="this.form.submit()" <?= (int)$u['id'] === (int)$current_admin_id ? 'disabled' : '' ?>>
                                        <option value="0" <?= $u['role'] == 0 ? 'selected' : '' ?>>設為 一般用戶</option>
                                        <option value="1" <?= $u['role'] == 1 ? 'selected' : '' ?>>設為 管理員</option>
                                    </select>
                                </form>
                            </td>
                            <td style="color:var(--muted); font-size:.8rem;"><?= date('Y-m-d H:i', strtotime($u['created_at'])) ?></td>
                            <td style="text-align: center;">
                                <?php if ((int)$u['id'] !== (int)$current_admin_id): ?>
                                    <button class="btn btn-danger" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                                        ❌ 刪除
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--muted); font-style: italic;">保護中</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 分頁導覽 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <!-- 上一頁 -->
                    <a href="?search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&page=<?= $page - 1 ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">
                        上頁
                    </a>
                    
                    <!-- 頁碼 -->
                    <?php 
                    $start_range = max(1, $page - 2);
                    $end_range = min($total_pages, $page + 2);
                    for ($i = $start_range; $i <= $end_range; $i++): 
                    ?>
                        <a href="?search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&page=<?= $i ?>" class="page-link <?= $page === $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- 下一頁 -->
                    <a href="?search=<?= urlencode($search) ?>&role=<?= urlencode($role_filter) ?>&page=<?= $page + 1 ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        下頁
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 刪除確認的自訂 Modal (無 alert() & confirm()) -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3 class="modal-title">⚠️ 安全確認</h3>
        <p class="modal-body">
            您確定要永久刪除使用者「<strong id="modalUsername" style="color: var(--accent);"></strong>」嗎？<br>
            此操作為不可逆動作，該用戶將立即失去登入與論壇發文權限。
        </p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal()">取消</button>
            <form id="deleteForm" method="POST" action="admin_users.php" style="margin: 0;">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" id="modalUserId" name="user_id" value="">
                <button type="submit" class="btn" style="background: var(--red); color: white;">確認刪除</button>
            </form>
        </div>
    </div>
</div>

<script>
// ── 主題切換 ──
const themeBtn = document.getElementById('themeBtn');
const saved = localStorage.getItem('theme') || 'light';
document.body.setAttribute('data-theme', saved);
themeBtn.onclick = () => {
    const t = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.body.setAttribute('data-theme', t);
    localStorage.setItem('theme', t);
};

// ── 自訂刪除彈窗邏輯 ──
const deleteModal = document.getElementById('deleteModal');
const modalUsername = document.getElementById('modalUsername');
const modalUserId = document.getElementById('modalUserId');

function confirmDelete(userId, username) {
    modalUsername.textContent = username;
    modalUserId.value = userId;
    deleteModal.classList.add('open');
}

function closeModal() {
    deleteModal.classList.remove('open');
}

// 點擊彈窗背景可關閉彈窗
deleteModal.onclick = (e) => {
    if (e.target === deleteModal) {
        closeModal();
    }
};
</script>
</body>
</html>