<?php

/*
|--------------------------------------------------------------------------
| Create The Application (Laravel 11+ structure)
|--------------------------------------------------------------------------
|
| Migrated from app/Http/Kernel.php + app/Console/Kernel.php +
| app/Exceptions/Handler.php (legacy Laravel 9/10 skeleton).
|
*/

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateWithAdmin;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 全局中间件：L11 默认栈已含 TrustProxies / HandleCors /
        // PreventRequestsDuringMaintenance / ValidatePostSize / TrimStrings /
        // ConvertEmptyStringsToNull，行为与旧 App\Http\Kernel 一致。
        // 旧 App\Http\Middleware\TrustProxies 的自定义仅 proxies = '*'（headers 与框架默认相同）。
        $middleware->trustProxies(at: '*');

        // web 组：与旧 Kernel 完全一致（AuthenticateSession 原本就未启用），
        // 使用框架默认组，无需覆盖。

        // api 组：框架默认组 = [throttle:api, SubstituteBindings]；
        // statefulApi() 把 Sanctum 的 EnsureFrontendRequestsAreStateful
        // 插入到 api 组最前（与旧 Kernel 顺序一致）。
        $middleware->statefulApi();

        // throttle:api 默认即 Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())，
        // 与原 RouteServiceProvider::configureRateLimiting 完全一致。

        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.admin' => AuthenticateWithAdmin::class,
            'guest' => RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 原 Handler::register() 的 renderable
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()
                ->json(['status' => false, 'message' => $e->getMessage(), 'data' => new stdClass], 429);
        });

        // 原 Handler::unauthenticated()（L11 Exceptions 构建器无 unauthenticated()，
        // 用 render(AuthenticationException) 等价实现）
        // ⚠️ L11 起 AuthenticationException::redirectTo() 需要 $request 参数
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['status' => false, 'message' => $e->getMessage(), 'data' => new stdClass], 401)
                : redirect()->guest($e->redirectTo($request) ?? route('login'));
        });
    })
    ->create();
