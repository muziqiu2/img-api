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
# php:8.2-fpm-bookworm 基础镜像已通过 --with-pdo-sqlite/--with-sqlite3/--with-curl/
# --enable-mbstring 内置 pdo_sqlite、sqlite3、curl、mbstring，PHP 8 亦默认内置
# fileinfo，均无需重复安装。
# 注意：sqlite3 扩展源码使用 config0.m4（phpize 只认 config.m4），
# 即使未内置也无法通过 docker-php-ext-install 编译，切勿加入安装列表。
# 唯一需要额外安装的是 zip（更新系统解压用）。
RUN docker-php-ext-install zip

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
