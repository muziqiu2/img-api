# 图床 API 接口文档

## 基本信息

- **接口基础地址**：`https://你的域名/api/v1`
- **验证方式**：采用「HTTP 基本验证」，获取 token 后通过请求头 `Authorization` 传递（Bearer Token）。
  ```
  Authorization: Bearer 1|1bJbwlqBfnggmOMEZqXT5XusaIwqiZjCDs7r1Ob5
  ```
- 若未设置 `Authorization` 请求头访问上传接口，将被视为**游客上传**。
- 文档中请求参数使用红色「`*`」标注的为**必传项**。

### 公共请求 Headers

| 字段 | 类型 | 说明 |
|------|------|------|
| Authorization | String | 授权 Token，例如 `Bearer 1|1bJbwlqBfnggmOMEZqXT5XusaIwqiZjCDs7r1Ob5` |
| `*`Accept | String | 必须设置为 `application/json` |

### 公共响应 Headers

| 字段 | 类型 | 说明 |
|------|------|------|
| X-RateLimit-Limit | Integer | 当前客户端一分钟内请求配额 |
| X-RateLimit-Remaining | Integer | 当前客户端剩余请求配额 |

### 响应状态码 HTTP Status Code

| 状态码 | 说明 |
|--------|------|
| 401 | 未登录或授权失败 |
| 403 | 管理员关闭了接口功能 |
| 429 | 超出请求配额，请求受限 |
| 500 | 服务端出现异常 |

---

## 一、授权相关

### 1. 生成 Token

- **接口**：`POST /tokens`

**请求参数 (Body)**

| 字段 | 类型 | 说明 |
|------|------|------|
| `*`email | String | 邮箱 |
| `*`password | String | 密码 |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;token | String | Token |

### 2. 清空 Token

- **接口**：`DELETE /tokens`

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |

### 3. 用户资料

- **接口**：`GET /profile`
- **鉴权**：需登录

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;name | String | 用户名 |
| &nbsp;&nbsp;&nbsp;&nbsp;avatar | String | 头像地址 |
| &nbsp;&nbsp;&nbsp;&nbsp;email | String | 邮箱地址 |
| &nbsp;&nbsp;&nbsp;&nbsp;capacity | Float | 总容量 |
| &nbsp;&nbsp;&nbsp;&nbsp;used_capacity | Float | 已使用容量 |
| &nbsp;&nbsp;&nbsp;&nbsp;url | String | 个人主页地址 |
| &nbsp;&nbsp;&nbsp;&nbsp;image_num | Integer | 图片数量 |
| &nbsp;&nbsp;&nbsp;&nbsp;album_num | Integer | 相册数量 |
| &nbsp;&nbsp;&nbsp;&nbsp;registered_ip | String | 注册 IP |

---

## 二、策略相关

### 1. 策略列表

- **接口**：`GET /strategies`

> 返回的策略包含 **key** 字段，当 key 为 **custom** 时表示 OpenList 自定义储存，支持**直传上传**（使用 `/upload/presign` 与 `/upload/confirm` 接口）。

**请求参数 (Query)**

| 字段 | 类型 | 说明 |
|------|------|------|
| keyword | String | 筛选关键字 |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;strategies | Object[] | 策略数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;id | Integer | 策略 ID |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;name | String | 策略名称 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;key | String | 策略标识，custom 表示 OpenList 自定义储存（支持直传） |

---

## 三、图片相关

### 1. 上传图片（中转上传）

- **接口**：`POST /upload`
- **说明**：图片经图床服务器中转后写入储存。当所选策略为 OpenList 自定义储存（key 为 custom）时，建议改用下方直传上传。

**Headers**

| 字段 | 类型 | 说明 |
|------|------|------|
| `*`Content-Type | String | 需要设置为 `multipart/form-data` |

**请求参数 (Body)**

| 字段 | 类型 | 说明 |
|------|------|------|
| `*`file | File | 图片文件 |
| strategy_id | Integer | 储存策略 ID |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;key | String | 图片唯一密钥 |
| &nbsp;&nbsp;&nbsp;&nbsp;name | String | 图片名称 |
| &nbsp;&nbsp;&nbsp;&nbsp;pathname | String | 图片路径名 |
| &nbsp;&nbsp;&nbsp;&nbsp;origin_name | String | 图片原始名 |
| &nbsp;&nbsp;&nbsp;&nbsp;size | Float | 图片大小，单位 KB |
| &nbsp;&nbsp;&nbsp;&nbsp;mimetype | String | 图片类型 |
| &nbsp;&nbsp;&nbsp;&nbsp;extension | String | 图片拓展名 |
| &nbsp;&nbsp;&nbsp;&nbsp;md5 | String | 图片 md5 值 |
| &nbsp;&nbsp;&nbsp;&nbsp;sha1 | String | 图片 sha1 值 |
| &nbsp;&nbsp;&nbsp;&nbsp;links | Object | 链接 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;url | String | 图片访问 url |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;html | String | - |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;bbcode | String | - |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;markdown | String | - |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;markdown_with_link | String | - |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;thumbnail_url | String | 缩略图 url |

### 2. 获取直传上传地址

- **接口**：`POST /upload/presign`
- **说明**：仅当所选储存策略为 OpenList 自定义储存（策略 key 为 **custom**）时支持直传上传；其余策略请使用上方 `/upload` 接口中转上传。

**请求参数 (Body)**

| 字段 | 类型 | 说明 |
|------|------|------|
| strategy_id | Integer | 储存策略 ID，不传则默认使用分组第一个可直传策略 |
| `*`extension | String | 图片拓展名，如 webp、jpg、png |
| `*`size | Integer | 图片文件大小，单位字节 |
| `*`md5 | String | 图片文件 md5 值 |
| `*`sha1 | String | 图片文件 sha1 值 |
| filename | String | 图片原始文件名，用于生成保存路径，默认 image.{extension} |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;upload_url | String | 直传上传地址，客户端直接对此地址发送 PUT 请求即可（有效期 15 分钟） |
| &nbsp;&nbsp;&nbsp;&nbsp;pathname | String | 图片路径名，后续确认时需要用到 |
| &nbsp;&nbsp;&nbsp;&nbsp;existing | String/null | 若存在相同图片，返回已存在图片的 pathname（此时无需再 PUT，直接走确认接口即可）；否则为 null |

### 3. 确认直传上传

- **接口**：`POST /upload/confirm`
- **说明**：图片已成功 PUT 至直传地址后，调用本接口完成落库。

**请求参数 (Body)**

| 字段 | 类型 | 说明 |
|------|------|------|
| strategy_id | Integer | 储存策略 ID，不传则默认使用分组第一个可直传策略 |
| `*`pathname | String | 图片路径名，获取直传地址接口返回的 pathname |
| `*`extension | String | 图片拓展名，如 webp、jpg、png |
| `*`size | Integer | 图片文件大小，单位字节 |
| `*`md5 | String | 图片文件 md5 值 |
| `*`sha1 | String | 图片文件 sha1 值 |
| filename | String | 图片原始文件名，默认取 pathname 文件名 |
| mimetype | String | 图片类型，如 image/webp |
| dimensions | Array | 图片尺寸，格式 [宽, 高]，客户端读取实际宽高后提交；不传默认 400\*400 |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;id | Integer | 图片 ID |
| &nbsp;&nbsp;&nbsp;&nbsp;pathname | String | 图片路径名 |
| &nbsp;&nbsp;&nbsp;&nbsp;origin_name | String | 图片原始名 |
| &nbsp;&nbsp;&nbsp;&nbsp;size | Float | 图片大小，单位 KB |
| &nbsp;&nbsp;&nbsp;&nbsp;mimetype | String | 图片类型 |
| &nbsp;&nbsp;&nbsp;&nbsp;md5 | String | 图片 md5 值 |
| &nbsp;&nbsp;&nbsp;&nbsp;sha1 | String | 图片 sha1 值 |
| &nbsp;&nbsp;&nbsp;&nbsp;links | Object | 链接，与上传接口返回参数中的 links 相同 |

### 4. 图片列表

- **接口**：`GET /images`
- **鉴权**：需登录

**请求参数 (Query)**

| 字段 | 类型 | 说明 |
|------|------|------|
| page | Integer | 页码 |
| order | String | 排序方式，newest=最新，earliest=最早，utmost=最大，least=最小 |
| permission | String | 权限，public=公开的，private=私有的 |
| album_id | Integer | 相册 ID |
| keyword | String | 筛选关键字 |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;current_page | Integer | 当前所在页页码 |
| &nbsp;&nbsp;&nbsp;&nbsp;last_page | Integer | 最后一页页码 |
| &nbsp;&nbsp;&nbsp;&nbsp;per_page | Integer | 每页展示数据数量 |
| &nbsp;&nbsp;&nbsp;&nbsp;total | Integer | 图片总数量 |
| &nbsp;&nbsp;&nbsp;&nbsp;data | Object[] | 图片列表 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;key | String | 图片唯一密钥 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;name | String | 图片名称 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;origin_name | String | 图片原始名称 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;pathname | String | 图片路径名 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;size | Float | 图片大小，单位 KB |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;width | Integer | 图片宽度 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;height | Integer | 图片高度 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;md5 | String | 图片 md5 值 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;sha1 | String | 图片 sha1 值 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;human_date | String | 上传时间（友好格式） |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;date | String | 上传日期 (yyyy-MM-dd HH:mm:ss) |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;links | Object | 链接，与上传接口返回参数中的 links 相同 |

### 5. 删除图片

- **接口**：`DELETE /images/:key`
- **鉴权**：需登录

**请求参数 (Params)**

| 字段 | 类型 | 说明 |
|------|------|------|
| `*`key | String | 图片密钥 |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |

---

## 四、相册相关

### 1. 相册列表

- **接口**：`GET /albums`
- **鉴权**：需登录

**请求参数 (Query)**

| 字段 | 类型 | 说明 |
|------|------|------|
| page | Integer | 页码 |
| order | String | 排序方式，newest=最新，earliest=最早，most=图片最多，least=图片最少 |
| keyword | String | 筛选关键字 |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |
| &nbsp;&nbsp;&nbsp;&nbsp;current_page | Integer | 当前所在页页码 |
| &nbsp;&nbsp;&nbsp;&nbsp;last_page | Integer | 最后一页页码 |
| &nbsp;&nbsp;&nbsp;&nbsp;per_page | Integer | 每页展示数据数量 |
| &nbsp;&nbsp;&nbsp;&nbsp;total | Integer | 图片总数量 |
| &nbsp;&nbsp;&nbsp;&nbsp;data | Object[] | 相册列表 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;id | Integer | 相册自增 ID |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;name | String | 相册名称 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;intro | String | 相册简介 |
| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;image_num | Integer | 相册图片数量 |

### 2. 删除相册

- **接口**：`DELETE /albums/:id`
- **鉴权**：需登录

**请求参数 (Params)**

| 字段 | 类型 | 说明 |
|------|------|------|
| `*`id | String | 相册自增 ID |

**返回参数**

| 字段 | 类型 | 说明 |
|------|------|------|
| status | Boolean | 状态，true 或 false |
| message | String | 描述信息 |
| data | Object | 数据 |

---

## 五、直传上传示例流程（OpenList 自定义储存）

1. `GET /strategies` 获取策略列表，找到 `key === 'custom'` 的策略，记录其 `id`。
2. `POST /upload/presign`，提交 `strategy_id`、`extension`、`size`、`md5`、`sha1` 等参数，获取 `upload_url` 与 `pathname`。
3. 若返回 `existing` 为空，则直接对 `upload_url` 发送 **PUT** 请求，PUT 体为图片文件字节。
4. `POST /upload/confirm`，提交 `strategy_id`、`pathname`、`extension`、`size`、`md5`、`sha1`、`dimensions` 等参数完成落库。