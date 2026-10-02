# 项目规则

## 静态资源与外部链接

- 所有项目如需使用外部 CDN 链接，一律使用自建反向代理站：`https://cdn-jsdelivr.mofashi.ltd`
- 该反代是 jsDelivr 镜像，路径须按 jsDelivr 标准格式书写：`https://cdn-jsdelivr.mofashi.ltd/npm/<包名>@<版本>/<文件路径>`
- 禁止直接引用其他公共 CDN（如 bytecdntp.com、cdn.jsdelivr.net、unpkg.com、cdnjs.cloudflare.com 等）。
- 引用前应先用 HEAD 请求实测确认资源路径有效再写入代码。
