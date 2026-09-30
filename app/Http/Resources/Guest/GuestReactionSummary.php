<?php

namespace App\Http\Resources\Guest;

use App\Models\Reaction;

/**
 * 访客版反应（点赞）摘要。
 *
 * 复用 Reaction 聚合行（每种 unified_id 一行），只输出公开计数；
 * has_reacted 恒为 false（访客无身份动作）。
 */
class GuestReactionSummary
{
    /**
     * @param  \Illuminate\Support\Collection<int, Reaction>  $reactions
     * @return array<int, array<string, mixed>>
     */
    public static function map($reactions): array
    {
        return $reactions->map(function (Reaction $item) {
            return [
                'unified_id' => $item->unified_id,
                'image_url' => reaction_image_url($item->unified_id),
                'native_symbol' => null,
                'total' => $item->reactions_count,
                'has_reacted' => false,
            ];
        })->values()->toArray();
    }
}
