<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Strategy;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class StrategyController extends Controller
{
    public function index(): Response
    {
        /** @var Group $group */
        $group = Auth::check() ? Auth::user()->group : Group::query()->where('is_guest', true)->first();

        // 对外返回字符串 key 便于客户端识别存储类型（如 OpenList S3 返回 'custom'，支持直传上传）
        $strategies = $group->strategies()->get()->map(fn (Strategy $strategy) => [
            'id'   => $strategy->id,
            'name' => $strategy->name,
            'key'  => Strategy::keyName($strategy->key),
        ]);

        return $this->success('success', compact('strategies'));
    }
}
