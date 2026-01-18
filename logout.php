<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登出 - 成交統計系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="3;url=login.php"> <style>
        /* ... styles.css 內容 ... */
        body { background: linear-gradient(135deg, #001f3f 0%, #0073e6 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; }
        .auth-container { max-width: 420px; width: 90%; }
        .auth-card { background-color: rgba(255, 255, 255, 0.95); border-radius: 1rem; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); }
        .auth-card h2 { color: #0073e6; margin-bottom: 25px; font-weight: 600; }
        .text-link { color: #0073e6 !important; text-decoration: none; font-weight: 500; }
        .text-link:hover { text-decoration: underline; }
        .progress-bar { transition: width 3s linear; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="card auth-card text-center">
            <div class="card-body">
                <h2 class="text-center text-success">👋 登出成功</h2>
                <p class="mb-4">您已安全登出系統。</p>
                
                <div class="progress mb-4" style="height: 5px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <p class="small text-muted">
                    將在 3 秒後自動跳轉至 <a href="login.php" class="text-link">登入頁面</a>...
                </p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 啟動進度條動畫
        document.addEventListener("DOMContentLoaded", function() {
            const progressBar = document.querySelector('.progress-bar');
            setTimeout(() => {
                progressBar.style.width = '0%';
            }, 100); 
        });
    </script>
</body>
</html>