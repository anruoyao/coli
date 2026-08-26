<?php

namespace App\Services\Seo;

use App\Enums\Job\JobType;
use App\Enums\Media\MediaType;
use App\Models\JobListing;
use App\Models\Post;
use App\Models\Product;
use App\Models\Story;
use App\Models\User;
use App\Settings\SeoSettings;
use Illuminate\Support\Str;

/**
 * SEO 路径解析器。
 *
 * 将「无需登录即可公开展示」的内容路由（用户主页 / 帖子 / 故事 / 职位 / 商品）
 * 解析为 SeoMeta，供访客/爬虫获得完整 meta + 结构化数据 + 正文快照。
 */
class SeoResolver
{
    public function __construct(protected SeoSettings $settings)
    {
    }

    public function resolve(string $path): ?SeoMeta
    {
        if (! $this->settings->seo_head_enabled) {
            return null;
        }

        $path = trim($path, '/');

        if (preg_match('#^@([a-zA-Z0-9._]{1,32})$#', $path, $matches)) {
            return $this->profile($matches[1]);
        }

        if (preg_match('#^publication/([a-zA-Z0-9]{10,})$#', $path, $matches)) {
            return $this->post($matches[1]);
        }

        if (preg_match('#^stories/([a-zA-Z0-9\-]{8,})$#', $path, $matches)) {
            return $this->story($matches[1]);
        }

        if (preg_match('#^jobs/([a-zA-Z0-9]{10,})$#', $path, $matches)) {
            return $this->job($matches[1]);
        }

        if (preg_match('#^marketplace/product/([a-zA-Z0-9]{10,})$#', $path, $matches)) {
            return $this->product($matches[1]);
        }

        return null;
    }

    protected function profile(string $username): ?SeoMeta
    {
        $user = User::query()
            ->active()
            ->withCount('followers', 'followings')
            ->where('username', $username)
            ->first();

        if (! $user) {
            return null;
        }

        $description = $user->bio ? $this->excerpt($user->bio) : __('seo.profile.description', [
            'name' => $user->name,
            'app' => config('app.name'),
        ]);

        return new SeoMeta(
            title: $user->name,
            description: $description,
            canonical: $user->profile_url,
            image: $user->avatar_url,
            type: 'profile',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'mainEntity' => [
                    '@type' => 'Person',
                    'name' => $user->name,
                    'url' => $user->profile_url,
                    'image' => $user->avatar_url,
                    'description' => $description,
                ],
            ],
            bodyView: 'apps.seo.parts.profile',
            data: [
                'user' => $user,
                'posts' => Post::query()
                    ->active()
                    ->where('user_id', $user->id)
                    ->where('is_sensitive', false)
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(),
            ],
        );
    }

    protected function post(string $hashId): ?SeoMeta
    {
        $post = Post::query()
            ->active()
            ->whereHashId($hashId)
            ->whereHas('user', fn ($query) => $query->active())
            ->with(['user', 'media' => fn ($query) => $query->where('type', MediaType::IMAGE)])
            ->first();

        if (! $post) {
            return null;
        }

        $excerpt = $this->excerpt($post->content);
        $image = $post->media->first()?->source_url;

        return new SeoMeta(
            title: $post->user->name.': '.$excerpt,
            description: $excerpt,
            canonical: $post->url,
            image: $image,
            type: 'article',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'SocialMediaPosting',
                'headline' => $excerpt,
                'text' => Str::limit($post->content, 10000),
                'datePublished' => $post->created_at ? \Illuminate\Support\Carbon::parse($post->created_at->getTimestamp())->toIso8601String() : null,
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $post->user->name,
                    'url' => $post->user->profile_url,
                ],
                'image' => $image,
                'mainEntityOfPage' => $post->url,
            ],
            bodyView: 'apps.seo.parts.post',
            data: [
                'post' => $post,
                'image' => $image,
            ],
        );
    }

    protected function story(string $storyUuid): ?SeoMeta
    {
        $story = Story::query()
            ->active()
            ->where('story_uuid', $storyUuid)
            ->with(['user', 'frames.media' => fn ($query) => $query->where('type', MediaType::IMAGE)])
            ->first();

        if (! $story) {
            return null;
        }

        $image = $story->frames
            ->first(fn ($frame) => $frame->media->isNotEmpty())
            ?->media
            ->first()
            ?->source_url;

        return new SeoMeta(
            title: __('seo.story.title', ['name' => $story->user->name]),
            description: __('seo.story.description', ['name' => $story->user->name]),
            canonical: $story->url,
            image: $image,
            type: 'story',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => __('seo.story.title', ['name' => $story->user->name]),
                'description' => __('seo.story.description', ['name' => $story->user->name]),
                'image' => $image,
            ],
            bodyView: 'apps.seo.parts.story',
            data: [
                'story' => $story,
                'image' => $image,
            ],
        );
    }

    protected function job(string $hashId): ?SeoMeta
    {
        $job = JobListing::query()
            ->listable()
            ->whereHashId($hashId)
            ->with(['user', 'category'])
            ->first();

        if (! $job) {
            return null;
        }

        $description = $this->excerpt($job->description ?: $job->overview);

        return new SeoMeta(
            title: $job->title,
            description: $description,
            canonical: $job->url,
            type: 'job',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'JobPosting',
                'title' => $job->title,
                'description' => $description,
                'datePosted' => $job->created_at ? \Illuminate\Support\Carbon::parse($job->created_at->getTimestamp())->toIso8601String() : null,
                'employmentType' => $job->type === JobType::VACANCY ? 'FULL_TIME' : 'CONTRACTOR',
                'jobLocation' => $job->is_remote
                    ? ['@type' => 'Place', 'description' => __('seo.job.remote')]
                    : ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job->location ?: '']],
                'hiringOrganization' => [
                    '@type' => 'Organization',
                    'name' => $job->user->name,
                    'sameAs' => $job->user->profile_url,
                ],
            ],
            bodyView: 'apps.seo.parts.job',
            data: [
                'job' => $job,
            ],
        );
    }

    protected function product(string $hashId): ?SeoMeta
    {
        $product = Product::query()
            ->listable()
            ->whereHashId($hashId)
            ->with(['user', 'media' => fn ($query) => $query->where('type', MediaType::IMAGE)])
            ->first();

        if (! $product) {
            return null;
        }

        $description = $this->excerpt($product->description);
        $image = $product->media->first()?->source_url;
        $inStock = (int) $product->stock_quantity > 0;

        return new SeoMeta(
            title: $product->title,
            description: $description,
            canonical: $product->url,
            image: $image,
            type: 'product',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->title,
                'image' => $image,
                'description' => $description,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => $product->price,
                    'priceCurrency' => $product->currency,
                    'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ],
            bodyView: 'apps.seo.parts.product',
            data: [
                'product' => $product,
                'image' => $image,
            ],
        );
    }

    protected function excerpt(?string $text): string
    {
        $text = trim(strip_tags((string) $text));

        return $text === '' ? config('app.name') : Str::limit($text, 160);
    }
}