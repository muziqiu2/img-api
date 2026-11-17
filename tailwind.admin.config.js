/** Tailwind 构建配置 — 魔法师随机图片API 管理后台
 *  构建命令（测试服务器项目根目录）：
 *  ./tailwindcss-linux-x64 -c tailwind.admin.config.js -i public/css/admin.tailwind.src.css -o public/css/admin.tw.css --minify
 *  content 扫描 admin 目录全部 PHP 与 admin.js（JS 动态拼接的类名必须被扫描到）
 */
module.exports = {
  content: ['./admin/**/*.php', './public/js/admin.js'],
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
  corePlugins: { preflight: true },
  plugins: [],
};
