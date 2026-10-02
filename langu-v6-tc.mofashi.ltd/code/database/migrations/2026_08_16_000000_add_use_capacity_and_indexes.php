<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * users 增加 use_capacity 已用容量列，并用现有图片数据回填，避免每次上传实时 SUM 全表扫描。
     * images 增加 md5/sha1 与 created_at 索引，加速去重查询与限流/排序。
     *
     * @return void
     */
    public function up()
    {
        // users 增加已用容量列
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('use_capacity', 20, 2)->default(0)->after('capacity')->comment('已使用容量(kb)');
        });

        // 回填已有用户的已用容量
        DB::statement('UPDATE users u
            LEFT JOIN (SELECT user_id, COALESCE(SUM(size), 0) AS total FROM images GROUP BY user_id) t
            ON t.user_id = u.id
            SET u.use_capacity = COALESCE(t.total, 0)');

        // images 增加索引（md5 单列索引，兼容 MySQL5.7 索引长度限制；去重查询 md5+sha1 命中 md5 索引后过滤即可）
        Schema::table('images', function (Blueprint $table) {
            $table->index('md5', 'idx_images_md5');
            $table->index('created_at', 'idx_images_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_images_md5');
            $table->dropIndex('idx_images_created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('use_capacity');
        });
    }
};