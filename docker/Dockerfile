# ============================================================
# 魔法师随机图片API - Docker 镜像
# 单容器：PHP-FPM + Nginx + Supervisor
# 代码不打包进镜像，由 entrypoint.sh 首次启动时从 GitHub Releases
# 自动拉取最新源码到挂载卷，内置 updater.php 更新系统照常可用。
# ============================================================

FROM php:8.2-fpm-bookworm

# ---------- 系统依赖 ----------
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        unzip \
        curl \
        ca-certificates \
        libzip-dev \
        libcurl4-openssl-dev \
        libsqlite3-dev \
        libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ---------- PHP 扩展（本项目必需）----------
# pdo_sqlite/sqlite3: SQLite 存储；zip: 更新系统解压；curl: 更新下载+SSRF 防护；
# fileinfo: 图片魔数校验；mbstring: 字符串处理
RUN docker-php-ext-install pdo_sqlite sqlite3 zip curl fileinfo mbstring

# ---------- PHP 运行时配置 ----------
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-img-api.ini

# ---------- Nginx 配置 ----------
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

# ---------- Supervisor 配置 ----------
COPY docker/supervisord.conf /etc/supervisor/conf.d/img-api.conf

# ---------- 启动脚本 ----------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ---------- 挂载点 ----------
# /var/www/html 挂载代码与数据（首次启动自动拉取，后续由 updater.php 更新）
VOLUME ["/var/www/html"]

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/img-api.conf", "-n"]
