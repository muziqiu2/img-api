<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    // 注意：App\Providers\RouteServiceProvider 已降级为纯常量类（HOME），
    // 不再是 ServiceProvider，不可注册在此处。
];
