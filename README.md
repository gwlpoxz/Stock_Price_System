# Stock_Price_System
使用爬蟲抓取台灣公開股價頁資料存入資料庫後，
撰寫php顯示在網頁中


/stock_analysis_system (專案根目錄)
│
├── config/                 # 系統配置與環境設定
│   └── db.php                # 資料庫連線與 Session 初始化
│
├── public/                 # Web 進入點與前端視圖 (UI)
│   ├── index.php             # 系統主控儀表板 (Dashboard)
│   ├── login.php             # 使用者登入頁面
│   ├── register.php          # 新使用者註冊頁面
│   ├── forgot.php            # 密碼重設引導頁
│   └── logout.php            # 登出與 Session 銷毀
│
├── api/                    # 處理特定請求的後端服務
│   └── export_handler.php    # 負責 Excel 報表生成與下載流
│
├── core_scripts/           # 數據處理核心 (ETL)
│   ├── twse_sync.py          # Python 爬蟲與數據同步引擎
│   └── run_stock_crawler.bat # Windows 環境啟動批次檔
│
├── docs/                   # 專案文檔
│   ├── ARCHITECTURE.md       # 高階設計文檔
│   └── README.md             # 專案快速入門手冊
│
└── logs/                   # (建議增加) 存放執行日誌
    └── crawler_error.log     # 記錄 Python 執行失敗的 Traceback
