<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 访客版投票：只读数据。
 *
 * 返回选项文本与公开票数/占比、总票数；不返回投票用户头像，
 * has_voted 恒 false；访客投票端点不存在（405 由只读策略保证）。
 */
class GuestPollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $votes = is_array($this->votes) ? $this->votes : [];
        $totalVotes = max(count($votes), 0);

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'has_voted' => false,
            'is_expired' => ! empty($this->expires_at),
            'is_anonymous' => $this->is_anonymous,
            'votes' => $totalVotes,
            'choices' => collect($this->choices)->map(function ($item) use ($votes, $totalVotes) {
                $choiceId = $item['id'] ?? null;
                $choiceVotes = collect($votes)->where('choice_id', $choiceId)->count();
                $choiceText = $item['choice_text'] ?? ($item['text'] ?? null);

                return [
                    'id' => $choiceId,
                    'choice_text' => $choiceText,
                    'text' => $choiceText,
                    'has_voted_choice' => false,
                    'votes' => $choiceVotes,
                    'percentage' => $totalVotes > 0 ? (int) round($choiceVotes / $totalVotes * 100) : 0,
                ];
            })->values()->toArray(),
        ];
    }
}
