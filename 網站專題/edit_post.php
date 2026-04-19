<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "includes/dbh.inc.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$post_id = $_GET["id"];
$user_id = $_SESSION["user_id"];

try {
    $sql = "SELECT * FROM posts WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        die("找不到這篇文章！");
    }

    if ($post['user_id'] != $user_id) {
        header("Location: view_post.php?id=$post_id&error=unauthorized");
        exit();
    }

    $cat_sql = "SELECT * FROM categories";
    $categories = $pdo->query($cat_sql)->fetchAll();

    // 抓取現有圖片
    $img_sql = "SELECT * FROM post_images WHERE post_id = ? ORDER BY id ASC";
    $img_stmt = $pdo->prepare($img_sql);
    $img_stmt->execute([$post_id]);
    $existing_images = $img_stmt->fetchAll();

} catch (PDOException $e) {
    die("讀取失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯文章 - PHP Forum</title>
    <style>
        /* --- 保持與原版完全一致的變數與導覽列樣式 --- */
        :root {
            --bg-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-color: #333333;
            --text-muted: #636e72;
            --header-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --border-color: #dddddd;
            --input-bg: #fafafa;
            --input-focus-bg: #ffffff;
            --danger-color: #ff7675;
            --success-color: #55efc4;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a2e;
            --card-bg: #16213e;
            --text-color: #e9ecef;
            --text-muted: #b2bec3;
            --header-gradient: linear-gradient(135deg, #1f4068 0%, #16213e 100%);
            --border-color: #444444;
            --input-bg: #0f3460;
            --input-focus-bg: #1f4068;
        }

        body { font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif; background-color: var(--bg-color); margin: 0; color: var(--text-color); transition: background-color 0.3s, color 0.3s; padding-bottom: 50px; }

        header { background: var(--header-gradient); color: white; padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        header h1 { margin: 0; font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }
        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; }
        .theme-toggle { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 8px 12px; border-radius: 20px; cursor: pointer; font-size: 14px; }
        .user-link { display: flex; align-items: center; gap: 10px; padding: 5px 15px; background: rgba(255, 255, 255, 0.1); border-radius: 50px; }
        .nav-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        .form-container { max-width: 700px; margin: 40px auto; background: var(--card-bg); padding: 35px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h2 { color: #764ba2; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-bottom: 25px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        
        input[type="text"], select, textarea {
            width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; 
            box-sizing: border-box; font-size: 16px; background-color: var(--input-bg); color: var(--text-color);
            transition: all 0.3s; outline: none;
        }
        textarea { resize: vertical; min-height: 200px; line-height: 1.6; }

        /* --- 圖片管理區增強樣式 --- */
        .img-edit-section { margin-top: 15px; padding: 15px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--input-bg); }
        .preview-grid { display: flex; gap: 15px; overflow-x: auto; padding: 10px 5px; }
        
        .preview-item { flex: 0 0 110px; position: relative; transition: 0.3s; }
        .preview-item img { width: 110px; height: 110px; object-fit: cover; border-radius: 8px; border: 2px solid transparent; }
        
        .preview-tag { position: absolute; bottom: 5px; left: 5px; background: rgba(0,0,0,0.6); color: white; font-size: 10px; padding: 2px 5px; border-radius: 4px; pointer-events: none; }
        
        .remove-btn { position: absolute; top: -8px; right: -8px; background: var(--danger-color); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 5; }
        
        /* 待刪除狀態視覺效果 */
        .preview-item.to-delete img { opacity: 0.2; filter: grayscale(1); border: 2px dashed var(--danger-color); }
        .preview-item.to-delete::after { content: "待刪除"; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: var(--danger-color); font-weight: bold; font-size: 14px; pointer-events: none; }
        .preview-item.to-delete .remove-btn { background: var(--success-color); transform: rotate(45deg); }

        .btn-upload-trigger { display: inline-block; margin-top: 10px; padding: 8px 16px; background: #764ba2; color: white; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; transition: 0.3s; }

        .btn-group { display: flex; gap: 15px; margin-top: 30px; }
        .btn-save { background: linear-gradient(to right, #667eea, #764ba2); color: white; border: none; padding: 14px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; flex: 2; box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3); }
        .btn-cancel { background: var(--bg-color); color: var(--text-muted); text-decoration: none; padding: 14px 25px; border-radius: 8px; text-align: center; flex: 1; border: 1px solid var(--border-color); }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">🚀 PHP Forum</a></h1>
    <div class="nav-links">
        <button class="theme-toggle" id="themeBtn">🌙 切換模式</button>
        <a href="index.php">首頁</a>
        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="user-link">
            <?php $nav_avatar = !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png"; ?>
            <img src="<?= $nav_avatar ?>" class="nav-avatar-img">
            <span><?= htmlspecialchars($_SESSION["username"]) ?></span>
        </a>
    </div>
</header>

<div class="form-container">
    <h2>📝 編輯您的文章</h2>
    
    <form id="editForm" action="includes/edit_post.inc.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">

        <div class="form-group">
            <label>文章分類</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $post['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>標題</label>
            <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
        </div>

        <div class="form-group">
            <label>內容 (維持 [imgX] 標籤可顯示圖片)</label>
            <textarea id="postContent" name="content" required><?= htmlspecialchars($post['content']) ?></textarea>
        </div>

        <div class="form-group">
            <label>圖片管理</label>
            <div class="img-edit-section">
                <small style="color:var(--text-muted)">現有圖片 (點擊 ✕ 標記刪除)：</small>
                <div class="preview-grid" id="existingPhotosPreview">
                    <?php foreach ($existing_images as $index => $img): ?>
                        <div class="preview-item" id="old-img-container-<?= $img['id'] ?>">
                            <img src="uploads/post_imgs/<?= $img['image_path'] ?>">
                            <span class="preview-tag">[img<?= $index+1 ?>]</span>
                            <!-- 點擊此按鈕會觸發隱藏的 checkbox -->
                            <button type="button" class="remove-btn" onclick="toggleDeleteOldImage(<?= $img['id'] ?>)">✕</button>
                            <!-- 存放要刪除的 ID -->
                            <input type="checkbox" name="delete_imgs[]" value="<?= $img['id'] ?>" id="delete-check-<?= $img['id'] ?>" style="display:none;">
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr style="border:0; border-top:1px solid var(--border-color); margin:15px 0;">
                
                <small style="color:var(--text-muted)">追加新圖片：</small>
                <label for="newImgInput" class="btn-upload-trigger">📷 選擇照片</label>
                <input type="file" id="newImgInput" accept="image/*" multiple style="display:none;">
                <div class="preview-grid" id="newPhotosPreview"></div>
            </div>
        </div>

        <!-- 存放新選取檔案的隱藏欄位 -->
        <input type="file" name="new_post_imgs[]" id="hiddenNewFiles" multiple style="display:none;">

        <div class="btn-group">
            <a href="view_post.php?id=<?= $post_id ?>" class="btn-cancel">取消修改</a>
            <button type="submit" name="submit_edit" class="btn-save">儲存並更新</button>
        </div>
    </form>
</div>

<script>
    let newFilesArray = [];
    const newImgInput = document.getElementById('newImgInput');
    const newPhotosPreview = document.getElementById('newPhotosPreview');
    const hiddenNewFiles = document.getElementById('hiddenNewFiles');
    const postContent = document.getElementById('postContent');
    const existingCount = <?= count($existing_images) ?>;

    // --- 1. 處理舊圖片的刪除標記 ---
    function toggleDeleteOldImage(imgId) {
        const container = document.getElementById(`old-img-container-${imgId}`);
        const checkbox = document.getElementById(`delete-check-${imgId}`);
        
        if (!checkbox.checked) {
            checkbox.checked = true;
            container.classList.add('to-delete');
        } else {
            checkbox.checked = false;
            container.classList.remove('to-delete');
        }
    }

    // --- 2. 處理追加新圖片 (分次選取邏輯) ---
    newImgInput.addEventListener('change', function() {
        const files = Array.from(this.files);
        files.forEach(file => {
            newFilesArray.push(file);
            const newTagIndex = existingCount + newFilesArray.length;
            postContent.value += `\n[img${newTagIndex}]\n`;
        });
        renderNewPreviews();
        this.value = ''; 
    });

    function renderNewPreviews() {
        newPhotosPreview.innerHTML = '';
        newFilesArray.forEach((file, index) => {
            const reader = new FileReader();
            const tagIndex = existingCount + index + 1;
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <span class="preview-tag">[img${tagIndex}]</span>
                    <button type="button" class="remove-btn" onclick="removeNewImage(${index})">✕</button>
                `;
                newPhotosPreview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }

    window.removeNewImage = function(index) {
        newFilesArray.splice(index, 1);
        renderNewPreviews();
    };

    // --- 3. 表單送出前同步 DataTransfer ---
    document.getElementById('editForm').addEventListener('submit', function() {
        const dt = new DataTransfer();
        newFilesArray.forEach(file => dt.items.add(file));
        hiddenNewFiles.files = dt.files;
    });

    // --- 4. 深色模式切換 (維持原邏輯) ---
    const themeBtn = document.getElementById('themeBtn');
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme === 'dark') { document.body.setAttribute('data-theme', 'dark'); themeBtn.textContent = '☀️ 淺色模式'; }
    themeBtn.addEventListener('click', () => {
        if (document.body.getAttribute('data-theme') !== 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            themeBtn.textContent = '☀️ 淺色模式';
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.removeAttribute('data-theme');
            themeBtn.textContent = '🌙 深色模式';
            localStorage.setItem('theme', 'light');
        }
    });
</script>

</body>
</html>