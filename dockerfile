FROM php:8.2-apache

# 啟用 Apache 的 rewrite 模組
RUN a2enmod rewrite

# 💡 關鍵：安裝並啟用 PHP 的 MySQL 驅動程式（包含 mysqli 和 pdo_mysql）
RUN docker-php-ext-install mysqli pdo_mysql

# 萬用搜尋：自動將你的專案檔案撈出來放進網頁根目錄
COPY . /tmp/project
RUN find /tmp/project -name "index.php" -exec dirname {} \; | head -n 1 > /tmp/app_path && \
    cp -r $(cat /tmp/app_path)/* /var/www/html/

RUN chown -R www-data:www-data /var/www/html
