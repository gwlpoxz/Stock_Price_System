<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("未授權存取");
}

$export_type = $_GET['export_type'] ?? '';
$results = [];

// 統一排序語句：日期小到大 -> 類型小到大
$order_sql = " ORDER BY trade_date ASC, CAST(SUBSTRING_INDEX(trade_type_zh, '.', 1) AS UNSIGNED) ASC";

try {
    if ($export_type === 'single') {
        $date = $_GET['date'] ?? '';
        $type = $_GET['type'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM trade_statistics WHERE trade_date = ? AND trade_type_zh = ?");
        $stmt->execute([$date, $type]);
    } elseif ($export_type === 'query_result') {
        $q_field = $_GET['query_field'];
        $q_op = $_GET['query_op'];
        $q_val = $_GET['query_value'];
        $s_date = $_GET['start_date'];
        $e_date = $_GET['end_date'];
        $stmt = $pdo->prepare("SELECT * FROM trade_statistics WHERE (trade_date BETWEEN ? AND ?) AND {$q_field} {$q_op} ?" . $order_sql);
        $stmt->execute([$s_date, $e_date, $q_val]);
    } elseif ($export_type === 'range') {
        $s_date = $_GET['start_date'];
        $e_date = $_GET['end_date'];
        $stmt = $pdo->prepare("SELECT * FROM trade_statistics WHERE trade_date BETWEEN ? AND ?" . $order_sql);
        $stmt->execute([$s_date, $e_date]);
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("資料庫錯誤");
}

// 檔名設定
$filename = "成交統計匯出_" . date('Ymd_His') . ".xls";

// 設定瀏覽器下載 Header
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

// 輸出 Excel
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
    <style>
        /* 樣式美化：微軟正黑體、邊框、自動欄寬適應 */
        table {
            border-collapse: collapse;
            font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
        }
        th, td {
            border: 0.5pt solid #000000; /* 加入表格線 */
            padding: 5px;
            white-space: nowrap; /* 自動調整內容不換行 (可視性) */
        }
        th {
            background-color: #4472C4; /* 比照範例 Excel 的藍色表頭 */
            color: #FFFFFF;
            font-weight: bold;
            text-align: center;
        }
        .num-format {
            vnd.ms-excel.numberformat: #,##0; /* Excel 數值千分位格式 */
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>日期</th>
                <th>成交統計類型</th>
                <th>成交金額(元)</th>
                <th>成交股數(股)</th>
                <th>成交筆數</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
            <tr>
                <td class="text-center"><?php echo $row['trade_date']; ?></td>
                <td><?php echo htmlspecialchars($row['trade_type_zh']); ?></td>
                <td class="num-format"><?php echo $row['trade_money_nt']; ?></td>
                <td class="num-format"><?php echo $row['trade_volume_shares']; ?></td>
                <td class="num-format"><?php echo $row['transaction_count']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>