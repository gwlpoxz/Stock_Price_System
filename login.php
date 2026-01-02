<?php
// ... session_start(), 檢查登入狀態, 登入處理） ...
require 'db.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 實際應使用預處理語句防止 SQL 注入
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = "使用者名稱或密碼錯誤。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 成交統計系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* 將步驟一的 CSS 內容貼在這裡，或使用 <link href="styles.css" rel="stylesheet"> */
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
                <h2 class="text-center">系統登入</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">使用者名稱</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">密碼</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">登入</button>
                </form>
                
                <div class="text-center small mt-3">
                    <p class="mb-1"><a href="forgot.php" class="text-link">忘記密碼？</a></p>
                    <p>還沒有帳號？ <a href="register.php" class="text-link">立即註冊</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>