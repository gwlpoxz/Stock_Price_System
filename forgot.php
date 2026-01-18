<?php
require 'db.php'; 

$step = 1; // 1: 輸入使用者名稱, 2: 重設密碼
$error = null;
$message = null;
$target_username = null;

// === 處理步驟 1: 驗證使用者名稱並進入重設密碼階段 ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && !isset($_POST['new_password'])) {
    $target_username = trim($_POST['username']);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$target_username]);
    $user_exists = $stmt->fetch();

    if ($user_exists) {
        $step = 2; // 進入步驟 2: 允許重設密碼
        $message = "已找到帳號：**{$target_username}**。請輸入新的密碼。";
    } else {
        $error = "查無此帳號 **{$target_username}**。";
        $step = 1; // 停留在步驟 1
    }
}

// === 處理步驟 2: 執行密碼重設 ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password']) && isset($_POST['confirm_password']) && isset($_POST['target_username'])) {
    $target_username = trim($_POST['target_username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 再次驗證目標使用者是否存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$target_username]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "重設失敗：帳號不存在或已變更。";
        $step = 1; // 返回步驟 1
    } elseif (strlen($new_password) < 6) {
        $error = "密碼長度必須至少為6個字元。";
        $step = 2; // 停留在步驟 2
    } elseif ($new_password !== $confirm_password) {
        $error = "兩次輸入的密碼不一致，請重新輸入。";
        $step = 2; // 停留在步驟 2
    } else {
        // 執行密碼更新
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        $update_stmt->execute([$hashed_password, $target_username]);
        
        $message = "✅ 密碼已成功更新！";
        $step = 3; // 顯示成功訊息
    }
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘記密碼/密碼重設 - 成交統計系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* 從 index.php 統一的樣式，以維持風格 */
        body { background: linear-gradient(135deg, #001f3f 0%, #0073e6 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; }
        .auth-container { max-width: 420px; width: 90%; }
        .auth-card { background-color: rgba(255, 255, 255, 0.95); border-radius: 1rem; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); }
        .auth-card h2 { color: #0073e6; margin-bottom: 25px; font-weight: 600; }
        .form-control:focus { border-color: #0073e6; box-shadow: 0 0 0 0.25rem rgba(0, 115, 230, 0.25); }
        .btn-primary { background-color: #0073e6; border-color: #0073e6; transition: background-color 0.3s ease; }
        .btn-primary:hover { background-color: #005bb5; border-color: #005bb5; }
        .text-link { color: #0073e6 !important; text-decoration: none; font-weight: 500; }
        .text-link:hover { text-decoration: underline; }
        .alert-custom { border-radius: 0.5rem; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="card auth-card">
            <div class="card-body">
                <h2 class="text-center">🔑 密碼重設</h2>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-custom"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($step == 1): // 步驟 1: 輸入使用者名稱 ?>
                    <p class="text-center mb-4 small text-muted">請輸入您的使用者名稱以重設密碼。</p>

                    <form method="post">
                        <div class="mb-4">
                            <label for="username" class="form-label">使用者名稱</label>
                            <input type="text" name="username" id="username" class="form-control" required value="<?php echo htmlspecialchars($target_username ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">查詢帳號並重設</button>
                    </form>

                    <?php if ($error && strpos($error, '查無此帳號') !== false): // 如果找不到帳號，顯示註冊提示 ?>
                        <div class="text-center small mt-3">
                            <p>找不到您的帳號？</p>
                            <p>您是否需要 <a href="register.php" class="text-link">重新註冊一個使用者？</a></p>
                        </div>
                    <?php endif; ?>

                <?php elseif ($step == 2): // 步驟 2: 重設密碼 ?>
                    <p class="text-center mb-4 small text-muted">為帳號 **<?php echo htmlspecialchars($target_username); ?>** 設定新密碼。</p>
                    
                    <form method="post">
                        <input type="hidden" name="target_username" value="<?php echo htmlspecialchars($target_username); ?>">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">新密碼 (至少6個字元)</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">確認新密碼</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mb-3">確認重設密碼</button>
                    </form>

                <?php elseif ($step == 3): // 步驟 3: 重設成功 ?>
                    <div class="text-center">
                        <p class="mt-3">您的密碼已經更新完畢。</p>
                        <a href="login.php" class="btn btn-primary w-100 mt-3">返回登入頁面</a>
                    </div>
                <?php endif; ?>

                <?php if ($step != 3): ?>
                    <div class="text-center small mt-3">
                        <a href="login.php" class="text-link">返回登入頁</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>