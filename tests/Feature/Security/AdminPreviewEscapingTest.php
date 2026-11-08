<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminPreviewEscapingTest extends TestCase
{
    public function test_publication_content_is_rendered_as_text(): void
    {
        $post = (object) [
            'type' => new class {
                public function isMedia(): bool { return false; }
                public function isPoll(): bool { return false; }
            },
            'media' => new Collection(),
            'content' => '<img src=x onerror=alert(1)>',
            'created_at' => new class {
                public function getCalendar(): string { return 'today'; }
            },
            'url' => '/publication/test',
        ];

        $html = view('components.entity.previews.publication', ['postData' => $post])->render();

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function test_user_bio_is_rendered_as_text(): void
    {
        $user = (object) [
            'cover_url' => '/cover.jpg',
            'avatar_url' => '/avatar.jpg',
            'name' => 'Test User',
            'caption' => '',
            'username' => 'test',
            'bio' => '<svg onload=alert(1)>',
            'profile_url' => '/@test',
        ];

        $html = view('components.entity.previews.user', ['userData' => $user])->render();

        $this->assertStringContainsString('&lt;svg onload=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<svg onload=alert(1)>', $html);
    }

    public function test_group_description_is_rendered_as_text(): void
    {
        $group = (object) [
            'avatar_url' => '/group.jpg',
            'name' => 'Test Group',
            'participants_count' => 1,
            'description' => '<img src=x onerror=alert(1)>',
            'url' => '/group/test',
        ];

        $html = view('components.entity.previews.group', ['groupData' => $group])->render();

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }
}
