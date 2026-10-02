<?php

namespace App\Providers;

/**
 * Laravel 11+ 起路由注册迁至 bootstrap/app.php 的 withRouting()，
 * 限流器由 $middleware->throttleApi() 默认提供（与原 configureRateLimiting 等价）。
 * 本类仅保留 HOME 常量，供 Auth 控制器与测试引用。
 */
final class RouteServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';
}
