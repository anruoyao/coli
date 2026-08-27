<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\StoryFrame;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * 将窗口期内的聚合批次组装为 Digest 邮件所需的分组数据。
 *
 * 分组规则：
 * - post/comment/story 实体 → 帖子区块（title 取内容摘要）
 * - follow 实体 → 「新粉丝」区块
 * 每区块内互动/评论/提及独立列出；超出配置上限的部分折叠为「以及其他 N 人」，
 * 区块总数超出 entities_per_mail 的部分折叠为「以及其他 N 个帖子/内容」。
 */
class DigestPayloadBuilder
{
    private bool $isZh;

    private int $entityLimit;
    private int $actorLimit;
    private int $commentLimit;

    /** @var array<int, User> 操作者缓存，避免 N+1 */
    private array $actorCache = [];

    public function __construct(private User $user, private Collection $batches)
    {
        $this->isZh = app()->getLocale() === 'zh' || $this->user->language === 'zh';
        $this->entityLimit = (int) config('notifications.digest.entities_per_mail', 5);
        $this->actorLimit = (int) config('notifications.digest.actors_per_entity', 6);
        $this->commentLimit = (int) config('notifications.digest.comments_per_entity', 3);
    }

    public function build(): array
    {
        $sections = [];
        $followers = [];
        $extraEntities = 0;
        $extraFollowers = 0;

        $grouped = $this->batches->groupBy(fn ($b) => $b->entity_type . ':' . $b->entity_id);

        foreach ($grouped as $key => $group) {
            [$entityType, $entityId] = explode(':', $key);

            if ($entityType === 'follow') {
                $actors = $this->actorsOf($group);

                foreach ($actors->take($this->actorLimit) as $actor) {
                    $followers[] = ['actor_name' => $actor->name, 'actor_id' => $actor->id];
                }

                $extraFollowers += max(0, $actors->count() - $this->actorLimit);

                continue;
            }

            $section = $this->buildEntitySection($entityType, (int) $entityId, $group);

            if ($section === null) {
                continue;
            }

            if (count($sections) < $this->entityLimit) {
                $sections[] = $section;
            } else {
                $extraEntities++;
            }
        }

        return [
            'is_zh' => $this->isZh,
            'subject' => $this->buildSubject(),
            'sections' => $sections,
            'followers' => $followers,
            'extra_followers' => $extraFollowers,
            'extra_entities' => $extraEntities,
            'total_actors' => $this->batches->pluck('actor_id')->unique()->count(),
        ];
    }

    private function buildEntitySection(string $entityType, int $entityId, Collection $group): ?array
    {
        $title = $this->resolveEntityTitle($entityType, $entityId);

        $section = [
            'entity_type' => $entityType,
            'title' => $title,
            'reactions' => [],
            'comments' => [],
            'mentions' => [],
            'extra_reactions' => 0,
            'extra_comments' => 0,
        ];

        foreach ($group as $batch) {
            switch ($batch->type) {
                case 'post.reacted':
                case 'comment.reacted':
                    $meta = $batch->meta ?: [];
                    $unifiedId = $meta['reaction'] ?? null;

                    $row = [
                        'actor_name' => $this->actorName($batch->actor_id),
                        'actor_id' => $batch->actor_id,
                    ];

                    if ($unifiedId) {
                        $row['reaction'] = $unifiedId;
                        $row['reaction_img'] = reaction_image_url($unifiedId);
                    }

                    $section['reactions'][] = $row;
                    break;

                case 'post.commented':
                case 'comment.mentioned':
                    $section['comments'][] = [
                        'actor_name' => $this->actorName($batch->actor_id),
                        'actor_id' => $batch->actor_id,
                        'content' => Str::limit((string) ($batch->meta['content'] ?? ''), 80),
                    ];
                    break;

                case 'post.mentioned':
                case 'story.mentioned':
                    $section['mentions'][] = [
                        'actor_name' => $this->actorName($batch->actor_id),
                        'actor_id' => $batch->actor_id,
                    ];
                    break;
            }
        }

        $reactionCount = count($section['reactions']);
        if ($reactionCount > $this->actorLimit) {
            $section['extra_reactions'] = $reactionCount - $this->actorLimit;
            $section['reactions'] = array_slice($section['reactions'], 0, $this->actorLimit);
        }

        $commentCount = count($section['comments']);
        if ($commentCount > $this->commentLimit) {
            $section['extra_comments'] = $commentCount - $this->commentLimit;
            $section['comments'] = array_slice($section['comments'], 0, $this->commentLimit);
        }

        if (empty($section['reactions']) && empty($section['comments']) && empty($section['mentions'])) {
            return null;
        }

        return $section;
    }

    private function resolveEntityTitle(string $entityType, int $entityId): string
    {
        switch ($entityType) {
            case 'post':
                $post = Post::withTrashed()->find($entityId);
                return $post && ! empty($post->content)
                    ? Str::limit(html_entity_decode($post->content), 42)
                    : $this->fallbackTitle('post');

            case 'comment':
                $comment = Comment::withTrashed()->find($entityId);
                return $comment
                    ? ($this->isZh ? '对评论的互动' : 'Interaction on a comment') . '「' . Str::limit(html_entity_decode($comment->content), 30) . '」'
                    : $this->fallbackTitle('comment');

            case 'story':
                $story = StoryFrame::withTrashed()->find($entityId);
                return $story ? Str::limit(html_entity_decode($story->caption ?? ''), 42) : $this->fallbackTitle('story');

            default:
                return $this->fallbackTitle($entityType);
        }
    }

    private function fallbackTitle(string $type): string
    {
        return $this->isZh
            ? match ($type) {
                'post' => '你的帖子',
                'comment' => '你的评论',
                'story' => '你的故事',
                default => '你的内容',
            }
            : match ($type) {
                'post' => 'Your post',
                'comment' => 'Your comment',
                'story' => 'Your story',
                default => 'Your content',
            };
    }

    private function buildSubject(): string
    {
        $n = $this->batches->pluck('actor_id')->unique()->count();

        return $this->isZh
            ? "过去1小时内，{$n}位用户与你的内容进行了互动"
            : "{$n} users interacted with your content in the past hour";
    }

    private function actorName(int $actorId): string
    {
        return $this->actorOf($actorId)?->name ?? ($this->isZh ? '用户' : 'User');
    }

    private function actorOf(int $actorId): ?User
    {
        if (! array_key_exists($actorId, $this->actorCache)) {
            $this->actorCache[$actorId] = User::without(['emailNotificationSettings', 'pushNotificationSettings'])->find($actorId);
        }

        return $this->actorCache[$actorId];
    }

    private function actorsOf(Collection $group): Collection
    {
        $ids = $group->pluck('actor_id')->unique()->values()->all();

        $users = $ids ? User::whereIn('id', $ids)->get()->keyBy('id') : collect();

        return $users->values();
    }
}