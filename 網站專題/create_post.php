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
            --bg-color: #f4f7f6; --card-bg: #ffffff; --text-color: #333333;
            --text-muted: #636e72; --header-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --border-color: #dddddd; --input-bg: #fafafa; --input-focus-bg: #ffffff;
            --danger-color: #ff7675; --accent-color: #6c5ce7;
        }
        [data-theme="dark"] {
            --bg-color: #1a1a2e; --card-bg: #16213e; --text-color: #e9ecef;
            --text-muted: #b2bec3; --header-gradient: linear-gradient(135deg, #1f4068 0%, #16213e 100%);
            --border-color: #444444; --input-bg: #0f3460; --input-focus-bg: #1f4068;
        }

        body { font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif; background-color: var(--bg-color); margin: 0; color: var(--text-color); transition: background-color 0.3s, color 0.3s; }
        header { background: var(--header-gradient); color: white; padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        header h1 { margin: 0; font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }
        .main-container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .post-form-card { background: var(--card-bg); padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        select, input[type="text"], textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--input-bg); color: var(--text-color); outline: none; box-sizing: border-box; }
        textarea { min-height: 200px; }
        
        .attachment-controls { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .btn-control { padding: 8px 15px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--card-bg); cursor: pointer; color: var(--text-color); font-size: 13px; transition: 0.2s; }
        .btn-control:hover { background: var(--accent-color); color: white; border-color: var(--accent-color); }
        
        .preview-container { display: flex; gap: 12px; margin-top: 10px; overflow-x: auto; padding-bottom: 10px; min-height: 100px; align-items: center; border-top: 1px solid var(--border-color); pt-2 }
        .preview-item { flex: 0 0 100px; position: relative; }
        .preview-item img, .preview-item video { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); }
        .remove-btn { position: absolute; top: -8px; right: -8px; background: var(--danger-color); color: white; border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; }
        
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        .btn-submit { flex: 2; background: linear-gradient(to right, #667eea, #764ba2); color: white; border: none; padding: 14px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-cancel { flex: 1; background-color: var(--bg-color); color: var(--text-muted); text-align: center; text-decoration: none; padding: 14px; border-radius: 8px; border: 1px solid var(--border-color); }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">🚀 PHP Forum</a></h1>
    <div class="nav-links">
        <button class="btn-control" id="themeBtn">🌙 模式</button>
    </div>
</header>

<div class="main-container">
    <div class="post-form-card">
        <h2>✍️ 分享你的想法</h2>
        <form id="postForm" action="includes/post.inc.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>選擇看板</label>
                <select name="category_id" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>文章標題</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>內容</label>
                <textarea id="postContent" name="content" required placeholder="支援語法：[img1], [vid1], [embed]連結[/embed]"></textarea>
                
                <div style="margin-top: 15px;">
                    <div class="attachment-controls">
                        <button type="button" class="btn-control" onclick="document.getElementById('imgInput').click()">📷 圖片</button>
                        <button type="button" class="btn-control" onclick="document.getElementById('vidInput').click()">🎬 影片</button>
                        <button type="button" class="btn-control" onclick="insertEmbedLink()">🔗 嵌入網址</button>
                    </div>
                    
                    <input type="file" id="imgInput" accept="image/*" multiple style="display:none;">
                    <input type="file" id="vidInput" accept="video/mp4,video/webm,video/ogg" multiple style="display:none;">
                    
                    <div class="preview-container" id="previewContainer"></div>
                </div>
            </div>

            <input type="file" name="post_imgs[]" id="hiddenFiles" multiple style="display:none;">
            <input type="file" name="post_vids[]" id="hiddenVids" multiple style="display:none;">

            <div class="button-group">
                <a href="index.php" class="btn-cancel">取消</a>
                <button type="submit" name="submit_post" class="btn-submit">發布文章</button>
            </div>
        </form>
    </div>
</div>

<script>
    let imgList = [];
    let vidList = [];

    // 處理圖片
    document.getElementById('imgInput').addEventListener('change', function() {
        Array.from(this.files).forEach(file => {
            imgList.push(file);
            document.getElementById('postContent').value += `\n[img${imgList.length}]\n`;
        });
        renderPreviews();
        this.value = '';
    });

    // 處理影片
    document.getElementById('vidInput').addEventListener('change', function() {
        Array.from(this.files).forEach(file => {
            vidList.push(file);
            document.getElementById('postContent').value += `\n[vid${vidList.length}]\n`;
        });
        renderPreviews();
        this.value = '';
    });

    // 嵌入網址助手
    function insertEmbedLink() {
        const url = prompt("請輸入連結 (YouTube/Vimeo等):");
        if (url) {
            document.getElementById('postContent').value += `\n[embed]${url}[/embed]\n`;
        }
    }

    function renderPreviews() {
        const container = document.getElementById('previewContainer');
        container.innerHTML = '';
        
        // 渲染圖片
        imgList.forEach((file, index) => {
            createPreviewElement(file, 'image', index, container);
        });
        // 渲染影片
        vidList.forEach((file, index) => {
            createPreviewElement(file, 'video', index, container);
        });
    }

    function createPreviewElement(file, type, index, container) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = type === 'image' 
                ? `<img src="${e.target.result}"><button type="button" class="remove-btn" onclick="removeItem('img', ${index})">✕</button>`
                : `<video src="${e.target.result}"></video><button type="button" class="remove-btn" onclick="removeItem('vid', ${index})">✕</button>`;
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    window.removeItem = function(type, index) {
        if(type === 'img') imgList.splice(index, 1);
        else vidList.splice(index, 1);
        renderPreviews();
    };

    document.getElementById('postForm').addEventListener('submit', function() {
        const dataTransferImg = new DataTransfer();
        imgList.forEach(f => dataTransferImg.items.add(f));
        document.getElementById('hiddenFiles').files = dataTransferImg.files;

        const dataTransferVid = new DataTransfer();
        vidList.forEach(f => dataTransferVid.items.add(f));
        document.getElementById('hiddenVids').files = dataTransferVid.files;
    });

    const themeBtn = document.getElementById('themeBtn');
    themeBtn.addEventListener('click', () => {
        const isDark = document.body.getAttribute('data-theme') === 'dark';
        document.body.setAttribute('data-theme', isDark ? '' : 'dark');
        themeBtn.textContent = isDark ? '🌙 模式' : '☀️ 模式';
    });
</script>

</body>
</html>