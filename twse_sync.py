#
import requests # 用於發送 HTTP 請求（從證交所 API 抓資料）
import pandas as pd # 用於數據處理（DataFrame 格式、日期運算）
from datetime import datetime
from sqlalchemy import create_engine, text  # SQL 工具庫，用於操作資料庫
from sqlalchemy import types as sa_types 
import pymysql # MySQL 的 Python 驅動程式
import time # 用於暫停執行（防止爬太快被鎖 IP）
import sys
import argparse # 用於讀取命令行參數（如 --start, --end）


# --- 定義資料庫連線配置 (請確認密碼是否正確) ---
DB_HOST = 'localhost'      
DB_USER = 'root' 
DB_PASSWORD = ''  
DB_NAME = 'stock_system'   
DB_TABLE = 'trade_statistics' 

# --- API 設定 ---
TWSE_API_URL = "https://www.twse.com.tw/exchangeReport/MI_INDEX"
EXCLUDE_TYPES = ["證券合計(1+6+14+15)", "總計(1~15)"] #定義要排除的行數

def fetch_and_process_twse_data(query_date_str):
    #抓取與清洗數據
    #定義一個名為 fetch_and_process_twse_data 的函式，
    #它接收一個參數 query_date_str
    #（格式通常為 20250901 這樣的 8 位數字字串）。
    """ 獲取單一日期的 TWSE MI_INDEX 資料 """
    #在控制台（黑視窗）印出目前的進度，方便開發者知道程式現在正在抓哪一天的資料。
    print(f"\n-> 嘗試獲取 {query_date_str} 的 MI_INDEX 資料...")

    #解釋：設定要傳給證交所 API 的參數。response: json 代表要求對方回傳 JSON 格式（這比網頁 HTML 好解析）；date 就是要查詢的日期。
    params = {'response': 'json', 'date': query_date_str} 
    
    #解釋：使用 requests 套件發送 GET 請求。timeout=15 代表如果證交所主機 15 秒內沒回應就斷開，避免程式無限期卡死。
    try: 
        response = requests.get(TWSE_API_URL, params=params, timeout=15)
        #解釋：raise_for_status() 會檢查 HTTP 狀態碼，如果發生 404（找不到頁面）或 500（伺服器錯誤）會直接跳到錯誤處理。如果正常，則將回傳的內容轉換成 Python 的字典（Dictionary）格式存入 data。
        response.raise_for_status()
        data = response.json()
    
    #解釋：如果上述過程發生任何意外（例如網路斷線），
    #會捕捉錯誤並印出，然後回傳 None 給主程式，告訴主程式這一天抓不到。
    except Exception as e:
        print(f"⚠️ 網路請求失敗: {e}")
        return None

    #解釋：證交所的 API 就算當天沒開盤（週六日）也會回傳資料，但內容會寫「查無資料」。這行程式是在檢查：
    #如果回傳的表格數量少於 7 個，通常代表當天沒交易，直接放棄處理。
    if 'tables' not in data or len(data['tables']) < 7:
        print(f"❌ {query_date_str}：API 回傳結構不符或當日無交易(週末/假日)。")
        return None
    
    #解釋：在證交所的 MI_INDEX API 中，第 7 張表（索引值為 6）
    #通常就是「成交統計」。這行程式嘗試取出這張表裡面的純數據（data 部分）。
    raw_data_list = data['tables'][6].get('data')
    if not raw_data_list:
        return None
    
    #數據轉換與清洗 (Pandas)
    #解釋：定義一個映射字典因為 API 回傳的資料沒有標題（只有 0, 1, 2, 3 欄），
    #我們主動幫它們對應到資料庫的欄位名稱（類型、金額、股數、筆數）。
    columns_map = {0: 'trade_type_zh', 1: 'trade_money_nt', 2: 'trade_volume_shares', 3: 'transaction_count'}
    
    #pd.DataFrame(raw_data_list)：將原始列表轉為 Pandas 的資料表格格式。
    #[[0, 1, 2, 3]]：只選取前四欄（後面的欄位可能不需要）。
    #.rename(columns=columns_map)：將原本 0, 1, 2, 3 的標題改成我們定義好的英文名稱，方便後續直接存入資料庫。
    df = pd.DataFrame(raw_data_list)[[0, 1, 2, 3]].rename(columns=columns_map)

    # 資料處理
    trade_date = datetime.strptime(query_date_str, '%Y%m%d')
    df.insert(0, 'trade_date', trade_date)
    
    for col in ['trade_money_nt', 'trade_volume_shares', 'transaction_count']:
        df[col] = df[col].astype(str).str.replace(',', '').replace('', '0')
        df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0).astype('int64')

    df = df[~df['trade_type_zh'].isin(EXCLUDE_TYPES)].reset_index(drop=True)
    print(f"✅ {query_date_str} 資料整理成功，取得 {len(df)} 筆數據。")
    return df


#資料庫寫入邏輯
def import_data_to_mysql(df):
    """ 使用 UPSERT 語法同步資料到 MySQL """
    if df.empty: return False # 如果傳入的表格是空的，直接回傳失敗
    
    # 建立 SQLAlchemy 連線字串 (格式：數據庫類型+驅動://帳號:密碼@位置/資料庫名)
    db_connection_str = f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}?charset=utf8mb4"
    
    try:
        # 建立資料庫引擎
        engine = create_engine(db_connection_str)
        # 將 Pandas 的日期格式轉為資料庫能識別的 'YYYY-MM-DD' 字串格式
        df['trade_date'] = df['trade_date'].dt.strftime('%Y-%m-%d')


        # 使用 engine.begin() 開啟事務(Transaction)，確保這一批資料要麼全成功，要麼全失敗
        with engine.begin() as conn:
            # 逐行遍歷 DataFrame 內的每一筆數據
            for _, row in df.iterrows():
                # 使用 ON DUPLICATE KEY UPDATE ，如果 (日期, 類型) 已經存在於資料庫，不重複插入，而是更新
                upsert_sql = text(f"""
                    INSERT INTO {DB_TABLE} 
                    (trade_date, trade_type_zh, trade_money_nt, trade_volume_shares, transaction_count)
                    VALUES (:date, :type, :money, :shares, :count)
                    ON DUPLICATE KEY UPDATE
                    trade_money_nt = VALUES(trade_money_nt),
                    trade_volume_shares = VALUES(trade_volume_shares),
                    transaction_count = VALUES(transaction_count)
                """)
                # 執行 SQL，並將 row 裡面的數值填入對應的變數 (:date, :type 等)
                conn.execute(upsert_sql, {
                    'date': row['trade_date'],
                    'type': row['trade_type_zh'],
                    'money': row['trade_money_nt'],
                    'shares': row['trade_volume_shares'],
                    'count': row['transaction_count']
                })
        return True
    except Exception as e:
        print(f"❌ 資料庫同步失敗: {e}")
        return False

# --- 在 (PC19)TWSE_autotest.py 內部 ---
#這是腳本執行的起點，負責處理參數、時間循環以及呼叫上述功能。
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--start", help="YYYY-MM-DD")
    parser.add_argument("--end", help="YYYY-MM-DD")
    args, unknown = parser.parse_known_args()

    ## 如果使用者有提供開始與結束日期
    ## 如果使用者有提供開始與結束日期
    if args.start and args.end:
        # 這裡從字串轉換，不會報 SyntaxError
        start_dt = datetime.strptime(args.start, '%Y-%m-%d')
        end_dt = datetime.strptime(args.end, '%Y-%m-%d')
    else:
        # ❌ 錯誤寫法: datetime(2025, 09, 01)
        # ✅ 正確寫法: 去掉數字前的 0
        start_dt = datetime(2025, 9, 1) 
        end_dt = datetime(2025, 9, 30)
#//***********
    # 產生這段時間內「每一天」的日期序列
    date_range = pd.date_range(start=start_dt, end=end_dt, freq='D')
    # 重要過濾：只保留週一到週五 (dayofweek < 5)，因為週末證交所沒開盤，不需浪費請求
    trade_dates = [d for d in date_range if d.dayofweek < 5]

    print(f"--- 啟動同步任務: {start_dt.date()} ~ {end_dt.date()} ---")
    
    total_inserted = 0 # 用來統計總共存入了幾筆資料

    # 開始按日期循環抓取
    for date_obj in trade_dates:
        # 將日期轉成 API 需要的格式 (例如 20250901)
        query_date_str = date_obj.strftime('%Y%m%d')
        # 呼叫抓取函式 (fetch_and_process_twse_data) 取得清洗後的資料
        df_daily = fetch_and_process_twse_data(query_date_str)
        
        # 如果當天有抓到資料 (不是 None)
        if df_daily is not None:
            # 呼叫匯入函式將資料存入 MySQL
            if import_data_to_mysql(df_daily):
                total_inserted += len(df_daily) # 累加成功筆數
                print(f"    ➡️ 已存入/更新至資料庫。")
        # 強制休息 3 秒，避免頻繁請求被證交所黑名單 (爬蟲禮儀)
        time.sleep(3) 

    # --- 關鍵：一定要印出這行，PHP 才能判斷成功 ---
    print("\n================== 批次匯入完成 ==================")
    print(f"任務結束。總共處理 {total_inserted} 筆數據。")


# 確保這個檔案是被直接執行時才啟動 main()
if __name__ == "__main__":
    main()