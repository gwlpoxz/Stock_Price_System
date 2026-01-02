<?php
// 1. 延長執行時間
set_time_limit(600); 

require 'db.php';

// 檢查是否登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = null;
$error = null;
$crawler_output = ""; 

// === 日誌記錄 ===
function log_action($pdo, $user_id, $type, $detail) {
    try {
        $stmt = $pdo->prepare("INSERT INTO trade_stats_history (user_id, action_type, detail) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $type, $detail]);
    } catch (PDOException $e) {}
}

// --- 預設日期與變數設定 ---
$today = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '2025-09-01';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '2025-09-30';

$query_results = [];
$query_title = "查詢結果";
$show_results = false; 
$current_export_url = '';

$trade_types = [
    '1.一般股票', '2.台灣存託憑證', '3.受益憑證', '4.ETF', '5.受益證券', 
    '6.變更交易股票', '7.認購(售)權證', '8.轉換公司債', '9.附認股權特別股', 
    '10.附認股權公司債', '11.認股權憑證', '12.公司債', '13.ETN', 
    '14.創新板股票', '15.創新板-變更交易方法股票'
];

$value_fields = [
    'trade_money_nt' => '成交金額(元)',
    'trade_volume_shares' => '成交股數(股)',
    'transaction_count' => '成交筆數'
];

$filter_operators = ['>' => '大於', '<' => '小於', '=' => '等於', '>=' => '大於等於', '<=' => '小於等於'];

#把中文類型前面的「數字編號」切出來，當成數字來排
#切文字：如果類型名稱是 "1.外資"，它會把點（.）左邊的 "1" 抓出來。
#變數字：把抓出來的文字 
"1" 真正變成可以計算的「數字 1」。
#排序 (ASC)：按照 1, 2, 3... 的順序排好。
$order_sql = " ORDER BY trade_date ASC, CAST(SUBSTRING_INDEX(trade_type_zh, '.', 1) AS UNSIGNED) ASC";

// === 處理爬蟲連動test===
// === 處理爬蟲連動 (優化版) ===
if (isset($_POST['action']) && $_POST['action'] == 'run_crawler') {
    $c_start = $_POST['crawl_start'];
    $c_end = $_POST['crawl_end'];

    
	$python_path = "C:\\Users\\Gwen\\anaconda3\\envs\\AI\\python.exe";

	$cmd = "cmd /c \"\"$python_path\" \"$script_path\" --start $arg_start --end $arg_end\" 2>&1";

    // 2. 使用 escapeshellarg 確保參數中若含空白或特殊字元不會造成錯誤或攻擊
    $cmd = sprintf(
        "%s %s --start %s --end %s 2>&1",
        escapeshellarg($python_path),
        escapeshellarg($script_path),
        escapeshellarg($c_start),
        escapeshellarg($c_end)
    );

    // 執行
    $crawler_output = shell_exec($cmd);
    

    if (strpos($crawler_output, '批次匯入完成') !== false) {
        $message = "✅ 數據同步成功！已更新 $c_start 至 $c_end 的資料。";
        log_action($pdo, $user_id, "遠端同步", "$c_start ~ $c_end");
    } else {
        $error = "⚠️ 同步過程可能發生異常，請檢查下方日誌。";
    }
}

// ==========================================
// 邏輯處理核心 (調整優先權)
// ==========================================
// --- A. 處理新增 (POST) ---
if (isset($_POST['action']) && $_POST['action'] == 'add') {
    $op_date = $_POST['op_date'];
    $type = $_POST['new_type'];
    $money = filter_var($_POST['new_money'], FILTER_VALIDATE_INT);
    $shares = filter_var($_POST['new_shares'], FILTER_VALIDATE_INT);
    $count = filter_var($_POST['new_count'], FILTER_VALIDATE_INT);

    if ($money !== false && $type && $op_date) {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM trade_statistics WHERE trade_date = ? AND trade_type_zh = ?");
            $check->execute([$op_date, $type]);
            if ($check->fetchColumn() > 0) { #如果有筆數
                $error = "💡 提示：資料已存在，已為您顯示最新數據。";
            } else {
                $stmt = $pdo->prepare("INSERT INTO trade_statistics (trade_date, trade_type_zh, trade_money_nt, trade_volume_shares, transaction_count) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$op_date, $type, $money, $shares, $count]);
                $message = "✅ 成功新增資料。";
				#這是「稽核日誌」。除了把資料存進去，系統還會額外記錄：「誰（user_id）在什麼時候做了一個『新增』動作，內容是哪一天的哪種交易。」
                log_action($pdo, $user_id, "新增", "日期:{$op_date}, 類型:{$type}");
            }
            // 強制覆蓋：只查詢這一筆
            $show_results = true;
            $query_title = "新增/檢視目標：{$op_date} ({$type})";
			
			#找 trade_statistics 表格裡，日期和類型符合條件的所有詳細資料。
            $stmt_single = $pdo->prepare("SELECT * FROM trade_statistics WHERE trade_date = ? AND trade_type_zh = ?");
			#找 trade_statistics 表格裡，日期和類型符合條件的所有詳細資料。
            $stmt_single->execute([$op_date, $type]);
			#把結果全部打包收好。 把資料庫找到的所有內容（fetchAll）抓回來，並整理成一個像「清單（關聯數組）」一樣的格式，存進 $query_results 變數裡，方便等一下在網頁上印出來。
            $query_results = $stmt_single->fetchAll(PDO::FETCH_ASSOC);
			
        // 復原：單筆匯出連結
            $current_export_url = "export_handler.php?export_type=single&date={$op_date}&type=" . urlencode($type);
        } catch (PDOException $e) { $error = "錯誤: " . $e->getMessage(); }
    }
}

// --- B. 處理修改 (POST) ---
elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
    $op_date = $_POST['op_date'];
    $target_type = $_POST['target_type'];
    $target_field = $_POST['target_field'];
    $new_value = filter_var($_POST['new_value'], FILTER_VALIDATE_INT);

    if ($new_value !== false && $op_date) {
        try {
            $stmt = $pdo->prepare("UPDATE trade_statistics SET {$target_field} = ? WHERE trade_type_zh = ? AND trade_date = ?");
            $stmt->execute([$new_value, $target_type, $op_date]);
            $message = ($stmt->rowCount() > 0) ? "✅ 成功修改資料。" : "ℹ️ 資料未變動。";
            
            // 強制覆蓋：只查詢這一筆
            $show_results = true;
            $query_title = "修改結果：{$op_date} ({$target_type})";
            $stmt_single = $pdo->prepare("SELECT * FROM trade_statistics WHERE trade_date = ? AND trade_type_zh = ?");
            $stmt_single->execute([$op_date, $target_type]);
            $query_results = $stmt_single->fetchAll(PDO::FETCH_ASSOC);
			
            // 復原：單筆匯出連結
            $current_export_url = "export_handler.php?export_type=single&date={$op_date}&type=" . urlencode($target_type);
            log_action($pdo, $user_id, "修改", "日期:{$op_date}, 類型:{$target_type}");
			} catch (PDOException $e) { $error = "錯誤: " . $e->getMessage(); }
    }
}

// --- C. 處理條件篩選 (GET but explicit query) ---
elseif (isset($_GET['query']) && $_GET['query'] == '1') {
    $q_val = filter_var($_GET['query_value'], FILTER_VALIDATE_INT);
    if ($q_val !== false) {
        $sql = "SELECT * FROM trade_statistics WHERE (trade_date BETWEEN ? AND ?) AND {$_GET['query_field']} {$_GET['query_op']} ?" . $order_sql;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['start_date'], $_GET['end_date'], $q_val]);
        $query_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $show_results = true;
        $query_title = "條件篩選結果 (共 " . count($query_results) . " 筆)";
		// 復原：篩選結果匯出連結 (帶入所有 GET 參數)
        $current_export_url = 'export_handler.php?export_type=query_result&' . http_build_query($_GET);
    }
}

// --- D. 最後才是處理區間刷新 (GET refresh) ---
#檢查網址列有沒有 refresh=1 這個參數。有的話，代表使用者點了「重新整理」或是想要「查看區間結果」。
elseif (isset($_GET['refresh']) && $_GET['refresh'] == '1') { 
	#準備撈取一段時間內的資料。 告訴資料庫：「我要找 trade_statistics 表，日期介於 ? 到 ? 之間的資料。」後面的 $order_sql 則是負責控制資料要怎麼排（例如：從新到舊）。
    $stmt = $pdo->prepare("SELECT * FROM trade_statistics WHERE trade_date BETWEEN ? AND ?" . $order_sql);
	#丟入開始日期、結束日期
    $stmt->execute([$start_date, $end_date]);
	#把搜尋到的結果（可能有很多筆）通通抓回來，轉換成 PHP 看得懂的清單格式。
    $query_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
	#打開顯示開關。 確定現在要把搜尋結果的表格呈現給使用者
    $show_results = true;
    $query_title = "區間資料：{$start_date} ~ {$end_date}";
	
	// ：區間匯出連結
    $current_export_url = "export_handler.php?export_type=range&start_date={$start_date}&end_date={$end_date}";
}

// 歷史紀錄
#告訴資料庫：「去trade_stats_history（歷史紀錄表）找資料。我只要動作類型、詳細內容跟時間這三個欄位。記得只要找『目前這位使用者（user_id）』的，而且要按時間『從新到舊（DESC）』排好，最後只給我『前 10 筆（LIMIT 10）』就好。」
$hist_stmt = $pdo->prepare("SELECT action_type, detail, search_time FROM trade_stats_history WHERE user_id = ? ORDER BY search_time DESC LIMIT 10");
$hist_stmt->execute([$user_id]);
$history_list = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>成交統計數據管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { margin-bottom: 20px; border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-bg { background: linear-gradient(45deg, #007bff, #0056b3); color: white; border-radius: 10px 10px 0 0; }
        pre { background: #212529; color: #39ff14; padding: 15px; border-radius: 5px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <header class="mb-4 p-3 bg-white shadow-sm rounded d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0 text-primary fw-bold">股價查詢系統</h1>
            <span>歡迎, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong> | <a href="logout.php" class="text-danger text-decoration-none">登出</a></span>
        </header>

        <?php if ($message): ?> <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
        <?php if ($error): ?> <div class="alert alert-info alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

        <div class="card border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">自動數據同步</h5>
                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#crawlerPanel">展開/收起</button>
            </div>
            <div id="crawlerPanel" class="collapse <?php echo $crawler_output ? 'show' : ''; ?>">
                <div class="card-body">
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="run_crawler">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">同步起點</label>
                            <input type="date" name="crawl_start" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">同步終點</label>
                            <input type="date" name="crawl_end" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">啟動遠端 Python 同步</button>
                        </div>
                    </form>
                    <?php if ($crawler_output): ?>
                    <div class="mt-3">
                        <pre style="max-height: 200px; overflow-y: auto;"><code><?php echo htmlspecialchars($crawler_output); ?></code></pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-warning text-dark fw-bold">🔍檢視區間設定</div>
            <div class="card-body">
                <form method="get" class="row align-items-end">
                    <input type="hidden" name="refresh" value="1">
                    <div class="col-md-5"><label class="form-label">起始日期</label><input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>"></div>
                    <div class="col-md-5"><label class="form-label">結束日期</label><input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-dark w-100">刷新顯示區</button></div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-9">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header header-bg">新增數據</div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="add">
                                    <div class="mb-2"><label class="small">日期:</label><input type="date" name="op_date" class="form-control form-control-sm" value="<?php echo $today; ?>"></div>
                                    <div class="mb-2"><label class="small">類型:</label><select name="new_type" class="form-select form-select-sm"><?php foreach($trade_types as $t) echo "<option value='$t'>$t</option>"; ?></select></div>
                                    <div class="mb-2"><label class="small">金額:</label><input type="number" name="new_money" class="form-control form-control-sm" required></div>
                                    <div class="mb-2"><label class="small">股數:</label><input type="number" name="new_shares" class="form-control form-control-sm" required></div>
                                    <div class="mb-2"><label class="small">筆數:</label><input type="number" name="new_count" class="form-control form-control-sm" required></div>
                                    <button type="submit" class="btn btn-primary btn-sm w-100">存入並查看結果</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">修正數據</div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="edit">
                                    <div class="mb-2"><label class="small">日期:</label><input type="date" name="op_date" class="form-control form-control-sm" value="<?php echo $today; ?>"></div>
                                    <div class="mb-2"><label class="small">目標類型:</label><select name="target_type" class="form-select form-select-sm"><?php foreach($trade_types as $t) echo "<option value='$t'>$t</option>"; ?></select></div>
                                    <div class="mb-2"><label class="small">修改欄位:</label><select name="target_field" class="form-select form-select-sm"><?php foreach($value_fields as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                                    <div class="mb-2"><label class="small">新值:</label><input type="number" name="new_value" class="form-control form-control-sm" required></div>
                                    <button type="submit" class="btn btn-info btn-sm w-100 text-white">確認修改並查看</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">條件篩選</div>
                            <div class="card-body">
                                <form method="get">
                                    <input type="hidden" name="query" value="1">
                                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                                    <div class="mb-2"><label class="small">篩選欄位:</label><select name="query_field" class="form-select form-select-sm"><?php foreach($value_fields as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                                    <div class="mb-2"><label class="small">條件:</label><select name="query_op" class="form-select form-select-sm"><?php foreach($filter_operators as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                                    <div class="mb-2"><label class="small">數值:</label><input type="number" name="query_value" class="form-control form-control-sm" required></div>
                                    <button type="submit" class="btn btn-success btn-sm w-100">執行篩選</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($show_results): ?>
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo $query_title; ?> (<?php echo count($query_results); ?> 筆)</h5>
                        <?php if($current_export_url): ?>
                            <a href="<?php echo $current_export_url; ?>" class="btn btn-sm btn-success fw-bold">💾 匯出結果 (Excel)</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>日期</th><th>類型</th><th class="text-end">金額</th><th class="text-end">股數</th><th class="text-end">筆數</th></tr>
                                </thead>
                                <tbody>
                                    <?php if(count($query_results) > 0): ?>
                                        <?php foreach($query_results as $row): ?>
                                        <tr>
                                            <td><?php echo $row['trade_date']; ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['trade_type_zh']); ?></span></td>
                                            <td class="text-end fw-bold"><?php echo number_format($row['trade_money_nt']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['trade_volume_shares']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['transaction_count']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center p-4 text-muted">查無相關資料。</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header bg-secondary text-white">最近操作</div>
                    <div class="card-body p-2" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach($history_list as $h): ?>
                        <div class="border-bottom mb-2 pb-2">
                            <small class="text-muted d-block"><?php echo $h['search_time']; ?></small>
                            <span class="badge bg-info text-dark"><?php echo $h['action_type']; ?></span>
                            <p class="small mb-0 mt-1"><?php echo htmlspecialchars($h['detail']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

