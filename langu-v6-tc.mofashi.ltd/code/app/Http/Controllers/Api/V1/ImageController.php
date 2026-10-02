<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UploadException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use App\Services\UserService;
use App\Utils;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    /**
     * 解析 API 鉴权身份：根据 Bearer Token 正确切换 guard，
     * 使后续 $request->user() 能解析出 API（token）用户，而非默认 web guard。
     * @throws AuthenticationException
     */
    protected function resolveAuthUser(Request $request)
    {
        if (! $request->hasHeader('Authorization')) {
            return null;
        }

        $guards = array_keys(config('auth.guards'));

        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);
                return Auth::user();
            }
        }

        throw new AuthenticationException('Authentication failed.');
    }

    /**
     * @throws AuthenticationException
     */
    public function upload(Request $request, ImageService $service): Response
    {
        $this->resolveAuthUser($request);

        try {
            $image = $service->store($request);
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Api 上传文件时发生异常');
            if (config('app.debug')) {
                return $this->fail($e->getMessage());
            }
            return $this->fail('服务异常，请稍后再试');
        }
        return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
            'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
        ));
    }

    /**
     * 获取直传上传地址（仅 OpenList S3 策略；其余策略保留 store 中转）
     * @throws AuthenticationException
     */
    public function presign(Request $request, ImageService $service): Response
    {
        $this->resolveAuthUser($request);

        try {
            return $this->success('获取直传地址成功', $service->presign($request));
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Api 获取直传地址时发生异常');
            return $this->fail('获取直传地址失败，请稍后再试');
        }
    }

    /**
     * 确认直传上传并落库（仅 OpenList S3 策略）
     * @throws AuthenticationException
     */
    public function confirm(Request $request, ImageService $service): Response
    {
        $this->resolveAuthUser($request);

        try {
            $image = $service->confirm($request);
            return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
                'id', 'pathname', 'origin_name', 'size', 'mimetype', 'md5', 'sha1', 'links'
            ));
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Api 直传确认时发生异常');
            return $this->fail('服务异常，请稍后再试');
        }
    }

    public function images(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $images = $user->images()->filter($request)->paginate(40)->withQueryString();
        $images->getCollection()->each(function (Image $image) {
            $image->human_date = $image->created_at->diffForHumans();
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        });
        return $this->success('success', $images);
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        (new UserService())->deleteImages([$request->route('key')], $user, 'key');
        return $this->success('删除成功');
    }
}
