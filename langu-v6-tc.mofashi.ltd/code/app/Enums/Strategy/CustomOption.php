<?php

namespace App\Enums\Strategy;

final class CustomOption
{
    /** @var string 访问地址 */
    const Url = 'url';

    /** @var string OpenList S3 API 连接地址（与访问地址不同，用于直传/上传） */
    const Endpoint = 'endpoint';

    /** @var string 用户名 */
    const Username = 'username';

    /** @var string 密码 */
    const Password = 'password';

    /** @var string 储存名称 */
    const Bucket = 'bucket';

    /** @var string 桶内根目录前缀（可选），不填直接存桶根路径 */
    const Prefix = 'prefix';
}
