<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=please_login");
    exit();
}

require_once "includes/dbh.inc.php";

try {
    $sql = "SELECT * FROM categories ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("資料庫錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>發表新文章 - PHP Forum</title>
    <style>
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

        body { font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif; background-color: var(--bg-color); margin: 0; color: var(--text-color); transition: background-color 0.3s, color 0.3s; }
        header { background: var(--header-gradient); color: white; padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        header h1 { margin: 0; font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }
        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; }
        .user-link { display: flex; align-items: center; gap: 10px; padding: 5px 15px; background: rgba(255, 255, 255, 0.1); border-radius: 50px; }
        .nav-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        .main-container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .post-form-card { background: var(--card-bg); padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        select, input[type="text"], textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; background-color: var(--input-bg); color: var(--text-color); outline: none; transition: 0.3s; }
        textarea { min-height: 250px; line-height: 1.6; }
        
        /* 圖片上傳區優化 */
        .img-upload-wrapper { margin-top: 15px; padding: 15px; border: 2px dashed var(--border-color); border-radius: 10px; background: var(--input-bg); }
        .btn-upload-trigger { display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
        
        .preview-container { display: flex; gap: 12px; margin-top: 15px; overflow-x: auto; padding-bottom: 10px; }
        .preview-item { flex: 0 0 100px; position: relative; }
        .preview-item img { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); }
        .preview-tag { position: absolute; bottom: 5px; left: 5px; background: rgba(0,0,0,0.6); color: white; font-size: 10px; padding: 2px 5px; border-radius: 4px; }
        
        /* 個別移除按鈕 */
        .remove-btn { position: absolute; top: -8px; right: -8px; background: var(--danger-color); color: white; border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }

        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        .btn-submit { flex: 2; background: linear-gradient(to right, #667eea, #764ba2); color: white; border: none; padding: 14px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-cancel { flex: 1; background-color: var(--bg-color); color: var(--text-muted); text-align: center; text-decoration: none; padding: 14px; border-radius: 8px; border: 1px solid var(--border-color); }
        
        .theme-toggle { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 8px 12px; border-radius: 20px; cursor: pointer; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">🚀 PHP Forum</a></h1>
    <div class="nav-links">
        <button class="theme-toggle" id="themeBtn">🌙 切換模式</button>
        <a href="index.php">返回首頁</a>
        <?php if (isset($_SESSION["user_id"])): ?>
            <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="user-link">
                <?php $nav_avatar = !empty($_SESSION['profile_img']) ? "uploads/users_profile_img/".$_SESSION['profile_img'] : "uploads/default_avatar.png"; ?>
                <img src="<?= $nav_avatar ?>" class="nav-avatar-img">
                <span><?= htmlspecialchars($_SESSION["username"]) ?></span>
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="main-container">
    <div class="post-form-card">
        <h2>✍️ 分享你的想法</h2>
        
        <form id="postForm" action="includes/post.inc.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>選擇看板</label>
                <select name="category_id" required>
                    <option value="" disabled selected>-- 請選擇一個看板 --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>文章標題</label>
                <input type="text" name="title" placeholder="標題內容..." required>
            </div>

            <div class="form-group">
                <label>內容 (可將 [img1] 等標籤剪下貼至段落之間)</label>
                <textarea id="postContent" name="content" placeholder="支援分段插入圖片，例如：文字...[img1]...文字..." required></textarea>
                
                <div class="img-upload-wrapper">
                    <label for="postImg" class="btn-upload-trigger">📷 選擇照片 (可多次選取)</label>
                    <input type="file" id="postImg" accept="image/*" multiple style="display:none;">
                    <div id="previewStatus" style="font-size:12px; color:var(--text-muted); margin-top:8px;">提示：點擊照片右上角可移除該圖。</div>
                    <div class="preview-container" id="previewContainer"></div>
                </div>
            </div>

            <!-- 隱藏的真正 input，用於送出檔案 -->
            <input type="file" name="post_imgs[]" id="hiddenFiles" multiple style="display:none;">

            <div class="button-group">
                <a href="index.php" class="btn-cancel">取消</a>
                <button type="submit" name="submit_post" class="btn-submit">發布文章</button>
            </div>
        </form>
    </div>
</div>

<script>
    let fileList = []; // 用來存放真正的 File 物件
    const postImg = document.getElementById('postImg');
    const previewContainer = document.getElementById('previewContainer');
    const postContent = document.getElementById('postContent');
    const postForm = document.getElementById('postForm');
    const hiddenFiles = document.getElementById('hiddenFiles');

    // 監聽圖片選取
    postImg.addEventListener('change', function() {
        const files = Array.from(this.files);
        
        files.forEach(file => {
            fileList.push(file); // 加入全域陣列，不覆蓋舊的
            renderPreviews();
            
            // 自動在內容最尾端補上新標籤，方便使用者剪下
            const imgIndex = fileList.length;
            postContent.value += `\n[img${imgIndex}]\n`;
        });
        
        this.value = ''; // 重置 input 以利下次觸發 change
    });

    // 渲染預覽圖
    function renderPreviews() {
        previewContainer.innerHTML = '';
        fileList.forEach((file, index) => {
            const reader = new FileReader();
            const imgIndex = index + 1;
            
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <span class="preview-tag">[img${imgIndex}]</span>
                    <button type="button" class="remove-btn" onclick="removeImage(${index})">✕</button>
                `;
                previewContainer.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }

    // 移除單張圖片
    window.removeImage = function(index) {
        // 從檔案陣列移除
        fileList.splice(index, 1);
        
        // 移除內容中對應的標籤（選用：如果想讓使用者手動管理標籤可刪除這行）
        const tagToRemove = `[img${index + 1}]`;
        postContent.value = postContent.value.replace(tagToRemove, '');
        
        renderPreviews();
    };

    // 表單送出前的最後處理：將 fileList 轉回 input
    postForm.addEventListener('submit', function(e) {
        const dataTransfer = new DataTransfer();
        fileList.forEach(file => dataTransfer.items.add(file));
        hiddenFiles.files = dataTransfer.files; 
    });

    // --- 深色模式 (維持原樣) ---
    const themeBtn = document.getElementById('themeBtn');
    if (localStorage.getItem('theme') === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
        themeBtn.textContent = '☀️ 淺色模式';
    }
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