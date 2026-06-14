<?php
require_once 'config.php';

// 确定设备类型
$deviceType = isMobileDevice() ? 'pe' : 'pc';

handleImageApiRequest($deviceType, 'api');
