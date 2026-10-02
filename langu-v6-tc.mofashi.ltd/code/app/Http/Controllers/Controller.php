<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Exceptions\UploadException;
use App\Http\Result;
use App\Models\Group;
use App\Models\Strategy;
use App\Models\User;
use App\Services\ImageService;
use App\Utils;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\Console\Output\BufferedOutput;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, Result;

    public function install(Request $request): View|Response
    {
        if (file_exists(base_path('installed.lock'))) {
            if ($request->expectsJson()) {
                return $this->fail('Already installed. if you want to reinstall, please remove installed.lock file.');
            }
            abort(404);
        }

        $extensions = collect([
            ['name' => 'BCMath', 'intro' => '数学精度处理拓展'],
            ['name' => 'Ctype', 'intro' => '字符类型检测拓展'],
            ['name' => 'DOM', 'intro' => 'DOM 对象模型，用于处理文档元素'],
            ['name' => 'Fileinfo', 'intro' => '获取文件信息拓展'],
            ['name' => 'JSON', 'intro' => 'JavaScript 对象符号（JSON）'],
            ['name' => 'Mbstring', 'intro' => '多字节字符串处理拓展'],
            ['name' => 'OpenSSL', 'intro' => '加密拓展'],
            ['name' => 'PCRE', 'intro' => '正则表达式拓展'],
            ['name' => 'PDO', 'intro' => '数据库拓展'],
            ['name' => 'Tokenizer', 'intro' => '令牌处理拓展'],
            ['name' => 'XML', 'intro' => 'Xml 解析器'],
            ['name' => 'Imagick', 'intro' => '高性能图片处理拓展'],
        ])->transform(function ($item) {
            $item['result'] = extension_loaded(strtolower($item['name']));
            return $item;
        })->push([
            'name' => 'readlink、symlink 函数',
            'intro' => '读取、创建符号链接函数',
            'result' => function_exists('readlink') && function_exists('symlink'),
        ])->push([
            'name' => 'putenv、getenv 函数',
            'intro' => '设置和获取环境变量函数',
            'result' => function_exists('putenv') && function_exists('getenv'),
        ])->push([
            'name' => 'exec、shell_exec 函数',
            'intro' => '执行外部命令',
            'result' => function_exists('exec') && function_exists('shell_exec'),
        ])->push([
            'name' => 'chmod、chown、fileperms 函数',
            'intro' => '设置和获取文件、文件夹权限函数',
            'result' => function_exists('chmod') && function_exists('chown') && function_exists('fileperms'),
        ])->push([
            'name' => 'PHP >= 8.0.2',
            'intro' => '最低要求 PHP 8.0.2 版本',
            'result' => phpversion() >= 8,
        ]);

        $status = ! $extensions->where('result', false)->isNotEmpty();

        if ($request->method() === 'POST') {
            try {
                $request->validate([
                    'account.email' => 'required|email',
                    'account.password' => 'required|between:6,32'
                ], [], [
                    'account.email' => '管理员账号邮箱',
                    'account.password' => '管理员账号密码'
                ]);

                $data = collect($request->only([
                    'connection', 'host', 'port', 'database', 'username', 'password',
                ]))->transform(fn($item, $key) => ['--'.$key => $item])->collapse();
                $output = new BufferedOutput();
                $exitCode = Artisan::call('lsky:install', $data->toArray(), $output);
                if ($exitCode) {
                    throw new \Exception(str_replace(PHP_EOL, '<br/>', $output->fetch()));
                }
                $user = new User([
                    'name' => '超级管理员',
                    'email' => $request->input('account.email'),
                    'password' => Hash::make($request->input('account.password')),
                    'registered_ip' => $request->ip(),
                ]);
                $user->group_id = Group::query()->first()['id'];
                $user->is_adminer = true;
                $user->status = UserStatus::Normal;
                $user->email_verified_at = date('Y-m-d H:i:s');
                // 设置默认策略组的 url 为当前请求 url
                Strategy::query()->update(['configs->url' => $request->getSchemeAndHttpHost().'/i']);
                $user->save();
            } catch (\Throwable $e) {
                @unlink(base_path('installed.lock'));
                Utils::e($e, '执行安装程序时出现异常');
                return $this->fail($e->getMessage());
            }
            return $this->success();
        }

        return view('install', compact('extensions', 'status'));
    }

    /**
     * 访客首页。
     *
     * 原先在 routes/web.php 里写成闭包路由 fn () => view('welcome')，
     * 闭包无法被序列化，会导致 php artisan route:cache 直接报错，
     * 故改为控制器方法。
     */
    public function welcome(): View
    {
        return view('welcome');
    }

    /**
     * 上传页（登录后）。
     *
     * 同上：原为闭包路由 fn () => view('user.upload')，为支持 route:cache 改为控制器方法。
     */
    public function uploadPage(): View
    {
        return view('user.upload');
    }

    public function upload(Request $request, ImageService $service): Response
    {
        try {
            $image = $service->store($request);
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Web 上传文件时发生异常');
            if (config('app.debug')) {
                return $this->fail($e->getMessage());
            }
            return $this->fail('服务异常，请稍后再试');
        }
        return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
            'id', 'pathname', 'origin_name', 'size', 'mimetype', 'md5', 'sha1', 'links'
        ));
    }

    public function presign(Request $request, ImageService $service): Response
    {
        try {
            return $this->success('获取直传地址成功', $service->presign($request));
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, '获取直传地址时发生异常');
            return $this->fail('获取直传地址失败，请稍后再试');
        }
    }

    public function confirm(Request $request, ImageService $service): Response
    {
        try {
            $image = $service->confirm($request);
            return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
                'id', 'pathname', 'origin_name', 'size', 'mimetype', 'md5', 'sha1', 'links'
            ));
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, '直传确认时发生异常');
            return $this->fail('服务异常，请稍后再试');
        }
    }
}
