<?php
require 'db.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 基本驗證
    if (strlen($password) < 6) {
        $error = "密碼長度必須至少為6個字元。";
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $error = "使用者名稱只能包含英文字母和數字。";
    } else {
        // 檢查使用者名稱是否重複
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "此使用者名稱已被註冊。";
        } else {
            // 註冊
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashed_password]);
            
            $message = "註冊成功！請 <a href='login.php' class='text-link'>點擊這裡登入</a>。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 成交統計系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ... styles.css 內容 ... */
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
                <h2 class="text-center">新使用者註冊</h2>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-custom"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">使用者名稱</label>
                        <input type="text" name="username" id="username" class="form-control" required pattern="[a-zA-Z0-9]+" title="只能包含英文字母和數字">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">密碼 (至少6個字元)</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">註冊帳號</button>
                </form>
                
                <div class="text-center small mt-3">
                    <a href="login.php" class="text-link">返回登入頁</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>