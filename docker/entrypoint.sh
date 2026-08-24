#!/bin/sh
# ============================================================
# 魔法师随机图片API - 容器启动脚本
# 首次启动（挂载卷为空）时自动从 GitHub Releases 拉取最新源码；
# 后续更新由内置 updater.php 完成（代码在挂载卷中，可持久化）。
# ============================================================
set -e

WEBROOT="/var/www/html"
REPO="muziqiu2/img-api"

mkdir -p "$WEBROOT"

# ---- 首次启动：挂载卷中没有业务代码时自动拉取最新 Release ----
if [ ! -f "$WEBROOT/index.php" ]; then
    echo "[entrypoint] 首次启动：从 GitHub Releases 拉取最新源码..."

    TMP="$(mktemp -d)"
    ZIP_URL=""

    # 优先取最新 release 的 zipball（api.github.com 有速率限制，失败则退回 main 分支）
    ZIP_URL="$(curl -fsSL --connect-timeout 10 "https://api.github.com/repos/${REPO}/releases/latest" 2>/dev/null \
        | sed -n 's/.*"zipball_url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n1)"
    if [ -z "$ZIP_URL" ]; then
        echo "[entrypoint] release 接口失败，退回拉取 main 分支"
        ZIP_URL="https://codeload.github.com/${REPO}/zip/refs/heads/main"
    fi

    echo "[entrypoint] 下载: ${ZIP_URL}"
    if ! curl -fSL --connect-timeout 10 --max-time 300 -o "${TMP}/src.zip" "$ZIP_URL"; then
        echo "[entrypoint] 源码下载失败，请检查网络后重启容器"
        rm -rf "$TMP"
        exit 1
    fi

    mkdir -p "${TMP}/src"
    if ! unzip -q "${TMP}/src.zip" -d "${TMP}/src"; then
        echo "[entrypoint] 解压失败"
        rm -rf "$TMP"
        exit 1
    fi

    # 剥离 GitHub 源码包的顶层目录（owner-repo-hash 格式）
    INNER="$(find "${TMP}/src" -mindepth 1 -maxdepth 1 -type d | head -n1)"
    if [ -n "$INNER" ]; then
        cp -a "$INNER/." "$WEBROOT/"
    else
        cp -a "${TMP}/src/." "$WEBROOT/"
    fi

    rm -rf "$TMP"

    # 交给 www-data 运行（宿主挂载目录权限不一致时容错跳过）
    chown -R www-data:www-data "$WEBROOT" 2>/dev/null || true

    echo "[entrypoint] 源码拉取完成"
fi

echo "[entrypoint] 启动 Nginx + PHP-FPM"
exec "$@"
