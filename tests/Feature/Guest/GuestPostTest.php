<?php

namespace Tests\Feature\Guest;

use App\Models\Comment;

/**
 * AC-3：访客帖子详情与评论。
 */
class GuestPostTest extends GuestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableGuestMode();
    }

    public function test_post_detail_returns_author_post_and_active_comments(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user, ['content' => 'DETAIL_POST']);

        Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'A public comment',
        ]);

        $response = $this->guestGet('post/'.$post->hash_id)->assertOk();

        $data = $response->json('data');

        $this->assertSame($user->id, $data['author']['id']);
        $this->assertSame('DETAIL_POST', $data['post']['content']);
        $this->assertNotEmpty($data['comments']['data'] ?? $data['comments']);
    }

    public function test_sensitive_post_returns_404(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user, ['is_sensitive' => true]);

        $this->guestGet('post/'.$post->hash_id)->assertNotFound();
    }

    public function test_unknown_hash_returns_404(): void
    {
        $this->guestGet('post/'.encode_id(99999999))->assertNotFound();
    }

    public function test_comments_cursor_pagination(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'first',
        ]);

        $this->guestGet('post/'.$post->hash_id.'/comments')->assertOk();

        // 游标翻页（首条之后无更多内容时返回空数组）
        $response = $this->guestGet('post/'.$post->hash_id.'/comments?cursor='.$comment->id)->assertOk();

        $this->assertIsArray($response->json('data'));
    }
}
