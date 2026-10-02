<?php

use Illuminate\Support\Facades\DB;
use App\Database\Configs\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 树状评论：每条评论记录其「根评论 id」。
     * - 主评论（parent_id NULL）：root_id 始终为 NULL；
     * - 任意层级回复：root_id = 顶层主评论 id（回复「回复」时也归一到同一线程）。
     * parent_id 已有 onDelete cascade，主评论删除时整棵子树由数据库级联删除。
     */
    public function up(): void
    {
        Schema::table(Table::COMMENTS, function (Blueprint $table) {
            $table->unsignedBigInteger('root_id')->nullable()->after('parent_id')->index('comments_root_id_index');
        });

        // 历史数据回填：递归 CTE 沿 parent_id 链求出每条回复的根评论 id（MySQL 8+）。
        DB::statement(
            'WITH RECURSIVE comment_tree (id, root_id) AS ('
            . ' SELECT id, id FROM ' . Table::COMMENTS . ' WHERE parent_id IS NULL'
            . ' UNION ALL'
            . ' SELECT c.id, ct.root_id FROM ' . Table::COMMENTS . ' c INNER JOIN comment_tree ct ON c.parent_id = ct.id'
            . ')'
            . ' UPDATE ' . Table::COMMENTS . ' c'
            . ' INNER JOIN comment_tree ct ON c.id = ct.id'
            . ' SET c.root_id = ct.root_id'
            . ' WHERE c.parent_id IS NOT NULL AND c.root_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table(Table::COMMENTS, function (Blueprint $table) {
            $table->dropIndex('comments_root_id_index');
            $table->dropColumn('root_id');
        });
    }
};
