FROM php:8.2-apache

# 1. 啟用 Apache 的 rewrite 模組
RUN a2enmod rewrite

# 2. 💡 關鍵：安裝 Linux 的 libpq-dev，並啟用 PHP 的 pdo_pgsql 驅動
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql

# 3. 萬用搜尋：自動將你的專案檔案撈出來放進網頁根目錄
COPY . /tmp/project
RUN find /tmp/project -name "index.php" -exec dirname {} \; | head -n 1 > /tmp/app_path && \
    cp -r $(cat /tmp/app_path)/* /var/www/html/

RUN chown -R www-data:www-data /var/www/html
