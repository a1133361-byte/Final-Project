# 1. 幫我下載一個官方已經幫忙裝好「PHP 8.2 和 Apache 網頁伺服器」的作業系統環境
FROM php:8.2-apache

# 2. 幫我把 Apache 的 Rewrite 模組打開（這樣你原本寫的 .htaccess 才會生效）
RUN a2enmod rewrite

# 3. 把我 GitHub 上所有的專案檔案（你的論壇程式碼），通通複製到這台虛擬伺服器的網頁根目錄
COPY . /var/www/html/

# 4. 把檔案的讀寫權限設定好，讓網頁伺服器可以順利執行你的 PHP 檔案
RUN chown -R www-data:www-data /var/www/html

#5
