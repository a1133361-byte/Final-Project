<?php
session_start();
require_once "includes/dbh.inc.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: index.php");
    exit();
}

try {
    $stats = [];
    $stats['users']       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['posts']       = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $stats['comments']    = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    $stats['today_posts'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // 看板文章分佈
    $cat_dist = $pdo->query("
        SELECT c.name, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY post_count DESC
    ")->fetchAll();

    // 最近 7 天每日新增文章
    $daily_posts = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as count
        FROM posts
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ")->fetchAll();

    // 最近 7 天每日新增使用者
    $daily_users = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as count
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ")->fetchAll();

    // 最新加入成員
    $recent_users = $pdo->query("
        SELECT id, username, profile_img, created_at 
        FROM users 
        ORDER BY id DESC 
        LIMIT 8
    ")->fetchAll();

    // 最熱門文章（按讚數）
    $hot_posts = $pdo->query("
        SELECT p.id, p.title, p.created_at, u.username, c.name as cat_name,
               COUNT(l.id) as like_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN likes l ON l.post_id = p.id
        GROUP BY p.id
        ORDER BY like_count DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    die("數據讀取失敗：" . $e->getMessage());
}

// 準備 Google Charts 所需的 JSON 資料
$cat_chart_data = [['看板', '文章數']];
foreach ($cat_dist as $cat) {
    $cat_chart_data[] = [$cat['name'], (int)$cat['post_count']];
}

// 過去 7 天完整日期陣列
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-$i days"));
}
$daily_posts_map  = array_column($daily_posts, 'count', 'day');
$daily_users_map  = array_column($daily_users, 'count', 'day');

$trend_chart_data = [['日期', '新文章', '新用戶']];
foreach ($days as $d) {
    $trend_chart_data[] = [
        date('m/d', strtotime($d)),
        (int)($daily_posts_map[$d] ?? 0),
        (int)($daily_users_map[$d] ?? 0),
    ];
}

// 供 JS 匯出 Excel 用的完整資料
$export_cat    = $cat_dist;
$export_users  = $recent_users;
$export_hot    = $hot_posts;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>數據分析 - 管理後台</title>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
            --red: #ef4444;
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
        .btn-export {
            background: var(--green);
            color: #fff;
        }
        .btn-export:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-icon {
            background: none; border: none; cursor: pointer;
            font-size: 1.2rem; padding: 6px; border-radius: 8px;
            color: var(--muted); transition: .2s;
        }
        .btn-icon:hover { background: var(--accent-soft); color: var(--accent); }

        /* ── Layout ── */
        .page { max-width: 1280px; margin: 0 auto; padding: 28px 24px 60px; }

        .page-title {
            font-size: 1.5rem; font-weight: 800;
            margin: 0 0 6px;
        }
        .page-sub { color: var(--muted); font-size: .9rem; margin: 0 0 28px; }

        /* ── KPI cards ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        @media (max-width: 900px)  { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 520px)  { .kpi-grid { grid-template-columns: 1fr; } }

        .kpi-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: transform .2s, box-shadow .2s;
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.07); }
        .kpi-card.highlight { border-color: var(--gold); background: var(--gold-soft); }
        .kpi-icon { font-size: 1.6rem; }
        .kpi-label { font-size: .8rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
        .kpi-value { font-size: 2.2rem; font-weight: 900; line-height: 1; color: var(--accent); }
        .kpi-card.highlight .kpi-value { color: var(--gold); }
        .kpi-note { font-size: .78rem; color: var(--muted); }

        /* ── Chart grid ── */
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }
        .chart-grid.wide { grid-template-columns: 3fr 2fr; }
        @media (max-width: 860px) { .chart-grid, .chart-grid.wide { grid-template-columns: 1fr; } }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 24px;
            overflow: hidden;
        }
        .card-title {
            font-size: 1rem; font-weight: 800;
            margin: 0 0 18px;
            display: flex; align-items: center; gap: 8px;
        }
        .chart-wrap { width: 100%; }

        /* ── User list ── */
        .user-list { display: flex; flex-direction: column; gap: 0; }
        .user-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid var(--border);
        }
        .user-row:last-child { border-bottom: none; }
        .u-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--accent-soft);
            flex-shrink: 0;
        }
        .u-name { font-weight: 700; font-size: .9rem; }
        .u-date { font-size: .75rem; color: var(--muted); }
        .u-link { text-decoration: none; color: inherit; flex: 1; display: flex; align-items: center; gap: 12px; }
        .u-link:hover .u-name { color: var(--accent); }

        /* ── Hot posts table ── */
        .htable { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .htable th {
            text-align: left; padding: 8px 12px;
            font-size: .72rem; font-weight: 800;
            color: var(--muted); text-transform: uppercase;
            border-bottom: 2px solid var(--border);
        }
        .htable td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .htable tr:last-child td { border-bottom: none; }
        .htable tr:hover td { background: var(--accent-soft); }
        .htable a { color: var(--text); text-decoration: none; font-weight: 600; }
        .htable a:hover { color: var(--accent); }
        .like-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(239,68,68,.1); color: var(--red);
            font-weight: 800; font-size: .78rem;
            padding: 3px 10px; border-radius: 20px;
        }
        .cat-tag {
            background: var(--accent-soft); color: var(--accent);
            font-size: .72rem; font-weight: 700;
            padding: 2px 8px; border-radius: 20px;
        }
    </style>
</head>
<body data-theme="light">

<header class="adm-header">
    <div class="logo">
        <span>📊</span> 管理後台數據中心
    </div>
    <div class="header-actions">
        <button class="btn btn-export" onclick="exportToExcel()">
            ⬇️ 匯出 Excel 報表
        </button>
        <a href="index.php" class="btn btn-ghost">⬅️ 返回論壇</a>
        <button class="btn-icon" id="themeBtn" title="切換主題">🌓</button>
    </div>
</header>

<div class="page">
    <h1 class="page-title">數據總覽</h1>
    <p class="page-sub">即時監控論壇各項核心指標</p>

    <!-- KPI -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">👤</div>
            <div class="kpi-label">總註冊用戶</div>
            <div class="kpi-value"><?= number_format($stats['users']) ?></div>
            <div class="kpi-note">持續增長中</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📝</div>
            <div class="kpi-label">總文章數</div>
            <div class="kpi-value"><?= number_format($stats['posts']) ?></div>
            <div class="kpi-note">社群活力來源</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">💬</div>
            <div class="kpi-label">累計留言</div>
            <div class="kpi-value"><?= number_format($stats['comments']) ?></div>
            <div class="kpi-note">高黏著度表現</div>
        </div>
        <div class="kpi-card highlight">
            <div class="kpi-icon">🔥</div>
            <div class="kpi-label">今日新增文章</div>
            <div class="kpi-value"><?= $stats['today_posts'] ?></div>
            <div class="kpi-note">即時熱度指標</div>
        </div>
    </div>

    <!-- Row 1：折線圖 + 圓餅圖 -->
    <div class="chart-grid wide">
        <div class="card">
            <div class="card-title">📈 過去 7 天活動趨勢</div>
            <div class="chart-wrap" id="trendChart" style="height:280px;"></div>
        </div>
        <div class="card">
            <div class="card-title">🍕 整體數據佔比</div>
            <div class="chart-wrap" id="pieChart" style="height:280px;"></div>
        </div>
    </div>

    <!-- Row 2：長條圖 + 最新成員 -->
    <div class="chart-grid">
        <div class="card">
            <div class="card-title">📊 看板熱度排行</div>
            <div class="chart-wrap" id="barChart" style="height:300px;"></div>
        </div>
        <div class="card">
            <div class="card-title">✨ 最新成員</div>
            <div class="user-list">
                <?php foreach ($recent_users as $u): ?>
                <div class="user-row">
                    <a href="profile.php?id=<?= $u['id'] ?>" class="u-link">
                        <img src="<?= !empty($u['profile_img']) ? "uploads/users_profile_img/".$u['profile_img'] : "uploads/default_avatar.png" ?>" class="u-avatar">
                        <div>
                            <div class="u-name"><?= htmlspecialchars($u['username']) ?></div>
                            <div class="u-date"><?= date('Y-m-d', strtotime($u['created_at'])) ?> 加入</div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
                <a href="admin_users.php" style="display:block;text-align:center;margin-top:14px;font-size:.8rem;color:var(--accent);text-decoration:none;font-weight:700;">查看完整列表 →</a>
            </div>
        </div>
    </div>

    <!-- Row 3：熱門文章表格 -->
    <div class="card">
        <div class="card-title">🏆 熱門文章 TOP 5</div>
        <table class="htable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>標題</th>
                    <th>看板</th>
                    <th>作者</th>
                    <th>讚數</th>
                    <th>發布日期</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hot_posts as $i => $p): ?>
                <tr>
                    <td style="font-weight:800;color:var(--muted);"><?= $i+1 ?></td>
                    <td><a href="view_post.php?id=<?= $p['id'] ?>"><?= htmlspecialchars(mb_substr($p['title'], 0, 35)) ?><?= mb_strlen($p['title']) > 35 ? '…' : '' ?></a></td>
                    <td><span class="cat-tag"><?= htmlspecialchars($p['cat_name']) ?></span></td>
                    <td><?= htmlspecialchars($p['username']) ?></td>
                    <td><span class="like-badge">❤️ <?= $p['like_count'] ?></span></td>
                    <td style="color:var(--muted);font-size:.8rem;"><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── PHP 資料傳入 JS ──
const catData    = <?= json_encode($cat_chart_data, JSON_UNESCAPED_UNICODE) ?>;
const trendData  = <?= json_encode($trend_chart_data, JSON_UNESCAPED_UNICODE) ?>;
const kpiStats   = {
    users:    <?= (int)$stats['users'] ?>,
    posts:    <?= (int)$stats['posts'] ?>,
    comments: <?= (int)$stats['comments'] ?>
};
const exportCat   = <?= json_encode($export_cat,   JSON_UNESCAPED_UNICODE) ?>;
const exportUsers = <?= json_encode($export_users, JSON_UNESCAPED_UNICODE) ?>;
const exportHot   = <?= json_encode($export_hot,   JSON_UNESCAPED_UNICODE) ?>;

// ── 主題切換 ──
const themeBtn = document.getElementById('themeBtn');
const saved = localStorage.getItem('theme') || 'light';
document.body.setAttribute('data-theme', saved);
themeBtn.onclick = () => {
    const t = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.body.setAttribute('data-theme', t);
    localStorage.setItem('theme', t);
    drawCharts(); // 重繪圖表以配合主題
};

// ── Google Charts ──
google.charts.load('current', { packages: ['corechart', 'bar'] });
google.charts.setOnLoadCallback(drawCharts);

function isDark() { return document.body.getAttribute('data-theme') === 'dark'; }
function chartBg()   { return isDark() ? '#1a253a' : '#ffffff'; }
function textColor() { return isDark() ? '#f1f5f9' : '#0f172a'; }
function mutedColor(){ return isDark() ? '#94a3b8' : '#64748b'; }
function gridColor() { return isDark() ? '#2d3f5a' : '#e2e8f0'; }

function drawCharts() {
    drawTrend();
    drawPie();
    drawBar();
}

// 折線圖：過去 7 天趨勢
function drawTrend() {
    const data = google.visualization.arrayToDataTable(trendData);
    const opts = {
        backgroundColor: chartBg(),
        legend: { position: 'top', textStyle: { color: textColor(), fontSize: 12 } },
        colors: ['#6366f1', '#10b981'],
        chartArea: { left: 45, right: 20, top: 40, bottom: 40, width: '100%', height: '100%' },
        hAxis: { textStyle: { color: mutedColor(), fontSize: 11 }, gridlines: { color: 'transparent' } },
        vAxis: { textStyle: { color: mutedColor(), fontSize: 11 }, gridlines: { color: gridColor() }, minValue: 0, format: '0' },
        lineWidth: 3,
        pointSize: 6,
        pointShape: 'circle',
        curveType: 'function',
        tooltip: { textStyle: { color: '#0f172a' } },
    };
    const chart = new google.visualization.LineChart(document.getElementById('trendChart'));
    chart.draw(data, opts);
}

// 圓餅圖：整體數據佔比
function drawPie() {
    const data = google.visualization.arrayToDataTable([
        ['類型', '數量'],
        ['用戶', kpiStats.users],
        ['文章', kpiStats.posts],
        ['留言', kpiStats.comments],
    ]);
    const opts = {
        backgroundColor: chartBg(),
        legend: { textStyle: { color: textColor(), fontSize: 12 } },
        colors: ['#6366f1', '#f59e0b', '#10b981'],
        chartArea: { left: 10, right: 10, top: 10, bottom: 10, width: '100%', height: '82%' },
        pieHole: 0.42,         // donut 樣式
        pieSliceBorderColor: chartBg(),
        tooltip: { textStyle: { color: '#0f172a' } },
    };
    const chart = new google.visualization.PieChart(document.getElementById('pieChart'));
    chart.draw(data, opts);
}

// 橫向長條圖：看板熱度
function drawBar() {
    const data = google.visualization.arrayToDataTable(catData);
    const opts = {
        backgroundColor: chartBg(),
        legend: { position: 'none' },
        colors: ['#6366f1'],
        chartArea: { left: 110, right: 30, top: 10, bottom: 30, width: '100%', height: '85%' },
        hAxis: { textStyle: { color: mutedColor(), fontSize: 11 }, gridlines: { color: gridColor() }, minValue: 0, format: '0' },
        vAxis: { textStyle: { color: textColor(), fontSize: 11 } },
        bar: { groupWidth: '62%' },
        tooltip: { textStyle: { color: '#0f172a' } },
    };
    const chart = new google.visualization.BarChart(document.getElementById('barChart'));
    chart.draw(data, opts);
}

// ── 匯出 Excel ──
function exportToExcel() {
    const wb = XLSX.utils.book_new();

    // Sheet 1：KPI 總覽
    const kpiRows = [
        ['指標', '數值'],
        ['總註冊用戶', kpiStats.users],
        ['總文章數',   kpiStats.posts],
        ['累計留言',   kpiStats.comments],
        ['今日新增文章', <?= (int)$stats['today_posts'] ?>],
    ];
    const ws1 = XLSX.utils.aoa_to_sheet(kpiRows);
    ws1['!cols'] = [{ wch: 20 }, { wch: 12 }];
    styleHeader(ws1, 'A1:B1');
    XLSX.utils.book_append_sheet(wb, ws1, 'KPI 總覽');

    // Sheet 2：看板熱度
    const catRows = [['看板名稱', '文章數']];
    exportCat.forEach(c => catRows.push([c.name, parseInt(c.post_count)]));
    const ws2 = XLSX.utils.aoa_to_sheet(catRows);
    ws2['!cols'] = [{ wch: 20 }, { wch: 10 }];
    styleHeader(ws2, 'A1:B1');
    XLSX.utils.book_append_sheet(wb, ws2, '看板熱度');

    // Sheet 3：最新成員
    const userRows = [['用戶名', '加入日期']];
    exportUsers.forEach(u => userRows.push([u.username, u.created_at ? u.created_at.split(' ')[0] : '']));
    const ws3 = XLSX.utils.aoa_to_sheet(userRows);
    ws3['!cols'] = [{ wch: 20 }, { wch: 14 }];
    styleHeader(ws3, 'A1:B1');
    XLSX.utils.book_append_sheet(wb, ws3, '最新成員');

    // Sheet 4：熱門文章
    const hotRows = [['排名', '標題', '看板', '作者', '讚數', '發布日期']];
    exportHot.forEach((p, i) => hotRows.push([i+1, p.title, p.cat_name, p.username, parseInt(p.like_count), p.created_at ? p.created_at.split(' ')[0] : '']));
    const ws4 = XLSX.utils.aoa_to_sheet(hotRows);
    ws4['!cols'] = [{ wch: 6 }, { wch: 40 }, { wch: 14 }, { wch: 16 }, { wch: 8 }, { wch: 14 }];
    styleHeader(ws4, 'A1:F1');
    XLSX.utils.book_append_sheet(wb, ws4, '熱門文章');

    // 加入過去 7 天趨勢（跳過標題列，已在 trendData 裡）
    const trendRows = trendData.map(row => row);
    const ws5 = XLSX.utils.aoa_to_sheet(trendRows);
    ws5['!cols'] = [{ wch: 10 }, { wch: 10 }, { wch: 10 }];
    styleHeader(ws5, 'A1:C1');
    XLSX.utils.book_append_sheet(wb, ws5, '7天趨勢');

    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `forum_report_${today}.xlsx`);
}

// 簡單標頭粗體樣式
function styleHeader(ws, range) {
    const ref = XLSX.utils.decode_range(range);
    for (let C = ref.s.c; C <= ref.e.c; C++) {
        const addr = XLSX.utils.encode_cell({ r: ref.s.r, c: C });
        if (!ws[addr]) continue;
        ws[addr].s = {
            font: { bold: true, color: { rgb: 'FFFFFF' } },
            fill: { fgColor: { rgb: '6366F1' } },
            alignment: { horizontal: 'center' }
        };
    }
}

// 視窗 resize 時重繪圖表
window.addEventListener('resize', () => {
    clearTimeout(window._resizeTimer);
    window._resizeTimer = setTimeout(drawCharts, 200);
});
</script>
</body>
</html>