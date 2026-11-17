/** Tailwind 构建配置 — 魔法师随机图片API 前台
 *  构建方式（在测试服务器上，生产无需 Node）：
 *  ./tailwindcss-linux-x64 -c tailwind.config.js -i public/css/tailwind.src.css -o public/css/tw.css --minify
 *  产物 tw.css 随仓库提交；改动页面标记后需重新构建并 bump APP_VERSION。
 */
module.exports = {
  content: ['./index.php'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', '"PingFang SC"', '"Microsoft YaHei"', 'system-ui', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', '"SF Mono"', 'Menlo', 'Consolas', '"Liberation Mono"', 'monospace'],
      },
      colors: {
        brand: { DEFAULT: '#2563eb', dark: '#1d4ed8', soft: '#eff4ff' },
      },
      boxShadow: {
        card: '0 1px 2px rgba(24,24,27,.04), 0 1px 3px rgba(24,24,27,.03)',
        pop: '0 4px 16px rgba(24,24,27,.08)',
      },
    },
  },
  plugins: [],
};
