<?php

namespace App\Http\Controllers\Seo;

use App\Models\JobListing;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Controller;
use App\Services\Seo\CrawlerDetector;

class ContentProxyController extends Controller
{
    private CrawlerDetector $detector;

    public function __construct()
    {
        $this->detector = new CrawlerDetector();
    }

    public function post(Request $request, string $hashId): Response
    {
        if (! $this->isCrawler(request: $request)) {
            return $this->renderSpa();
        }

        return $this->renderPostSeo(hashId: $hashId);
    }

    public function profile(Request $request, string $username): Response
    {
        if ($this->isCrawler(request: $request)) {
            return $this->renderProfileSeo(username: $username);
        }

        return $this->renderSpa();
    }

    public function product(Request $request, string $hashId): Response
    {
        if ($this->isCrawler(request: $request)) {
            return $this->renderProductSeo(hashId: $hashId);
        }

        return $this->renderSpa();
    }

    public function job(Request $request, string $hashId): Response
    {
        if ($this->isCrawler(request: $request)) {
            return $this->renderJobSeo(hashId: $hashId);
        }

        return $this->renderSpa();
    }

    private function isCrawler(Request $request): bool
    {
        return $this->detector->isCrawler(userAgent: $request->userAgent() ?? '');
    }

    private function disableDebugbar(): void
    {
        if (app()->has('debugbar')) {
            app('debugbar')->disable();
        }
    }

    private function renderPostSeo(string $hashId): Response
    {
        $this->disableDebugbar();

        $post = Post::with(['user', 'media'])
            ->findByHashId(hashId: $hashId);

        if (! $post || ! $post->user) {
            abort(404);
        }

        $postType = $post->type;
        $plainContent = strip_tags($post->content ?? '');
        $siteName = config('app.name');
        $authorName = $post->user->name;
        $canonicalUrl = url("publication/{$post->hash_id}");
        $publishedAt = Carbon::parse($post->created_at->getTimestamp())->toAtomString();

        $mediaItems = $this->collectMedia(post: $post);

        $ogImage = $this->resolveOgImage(post: $post, mediaItems: $mediaItems);

        $seoTitle = $this->buildSeoTitle(authorName: $authorName, postType: $postType, content: $plainContent, siteName: $siteName);
        $seoDescription = $this->buildSeoDescription(content: $plainContent, mediaItems: $mediaItems, authorName: $authorName);

        return $this->seoResponse(view: 'seo.post', data: [
            'title' => $seoTitle,
            'description' => $seoDescription,
            'image' => $ogImage,
            'url' => $canonicalUrl,
            'published_at' => $publishedAt,
            'author' => $authorName,
            'site_name' => $siteName,
            'content' => $plainContent,
            'post_type' => $postType->value,
            'media_items' => $mediaItems,
        ]);
    }

    private function collectMedia(Post $post): array
    {
        if (! $post->relationLoaded('media') || $post->media->isEmpty()) {
            return [];
        }

        return $post->media->map(function ($media) {
            $type = $media->type->value ?? 'image';

            return [
                'type' => $type,
                'url' => $media->source_url ?? '',
                'thumbnail' => $media->thumbnail_url ?? '',
            ];
        })->toArray();
    }

    private function resolveOgImage(Post $post, array $mediaItems): string
    {
        foreach ($mediaItems as $item) {
            if ($item['type'] === 'image' || $item['type'] === 'gif') {
                return $item['url'] ?: ($item['thumbnail'] ?: '');
            }
            if ($item['type'] === 'video' && ! empty($item['thumbnail'])) {
                return $item['thumbnail'];
            }
        }

        if (! empty($post->image)) {
            return storage_url($post->image, static_storage_disk());
        }

        return asset(config('user.avatar'));
    }

    private function buildSeoTitle(string $authorName, $postType, string $content, string $siteName): string
    {
        $prefix = match ($postType->value ?? 'text') {
            'image', 'gif' => $authorName . ' shared an image',
            'video' => $authorName . ' shared a video',
            'audio' => $authorName . ' shared audio',
            'poll' => $authorName . ' created a poll',
            default => $authorName . ' on ' . $siteName,
        };

        return $prefix;
    }

    private function buildSeoDescription(string $content, array $mediaItems, string $authorName): string
    {
        if (! empty($content)) {
            return $this->truncate(text: $content, limit: 200);
        }

        $imageCount = count(array_filter($mediaItems, fn($m) => in_array($m['type'], ['image', 'gif'])));
        $videoCount = count(array_filter($mediaItems, fn($m) => $m['type'] === 'video'));

        $parts = [];
        if ($imageCount > 0) {
            $parts[] = $imageCount . ' image' . ($imageCount > 1 ? 's' : '');
        }
        if ($videoCount > 0) {
            $parts[] = $videoCount . ' video' . ($videoCount > 1 ? 's' : '');
        }

        $prefix = implode(' and ', $parts);

        return $prefix
            ? $authorName . ' shared ' . $prefix . ' on ' . config('app.name')
            : $authorName . ' on ' . config('app.name');
    }

    private function renderProfileSeo(string $username): Response
    {
        $this->disableDebugbar();

        $user = User::where('username', $username)->first();

        if (! $user) {
            abort(404);
        }

        $image = $user->avatar
            ? storage_url($user->avatar, static_storage_disk())
            : asset(config('user.avatar'));

        return $this->seoResponse(view: 'seo.profile', data: [
            'title' => $user->name . ' (@' . $user->username . ') on ' . config('app.name'),
            'description' => $user->caption ?? ($user->name . ' on ' . config('app.name')),
            'image' => $image,
            'url' => url("@{$user->username}"),
            'username' => $user->username,
        ]);
    }

    private function renderProductSeo(string $hashId): Response
    {
        $this->disableDebugbar();

        $product = Product::with(['user', 'media'])
            ->findByHashId(hashId: $hashId);

        if (! $product || ! $product->user) {
            abort(404);
        }

        $image = $this->extractMediaImage(model: $product);

        return $this->seoResponse(view: 'seo.product', data: [
            'title' => $product->name ?? 'Product on ' . config('app.name'),
            'description' => $this->truncate(text: strip_tags($product->description ?? ''), limit: 200),
            'image' => $image,
            'url' => url("marketplace/product/{$product->hash_id}"),
        ]);
    }

    private function renderJobSeo(string $hashId): Response
    {
        $this->disableDebugbar();

        $job = JobListing::with(['user'])
            ->findByHashId(hashId: $hashId);

        if (! $job || ! $job->user) {
            abort(404);
        }

        return $this->seoResponse(view: 'seo.job', data: [
            'title' => $job->title ?? 'Job on ' . config('app.name'),
            'description' => $this->truncate(text: strip_tags($job->description ?? ''), limit: 200),
            'image' => asset(config('app.favicon', 'favicon.ico')),
            'url' => url("jobs/{$job->hash_id}"),
        ]);
    }

    private function seoResponse(string $view, array $data): Response
    {
        if (app()->has('debugbar')) {
            app('debugbar')->disable();
        }

        return response(
            content: view($view, $data)->render(),
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    private function renderSpa(): Response
    {
        $deviceType = Cookie::get('device_type', 'desktop');

        $view = $deviceType === 'mobile' ? 'mobile::index' : 'desktop::index';

        return response(
            content: view($view)->render(),
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    private function extractPostImage(Post $post): string
    {
        if (! empty($post->image)) {
            return storage_url($post->image, static_storage_disk());
        }

        return $this->extractMediaImage(model: $post);
    }

    private function extractMediaImage($model): string
    {
        if ($model->relationLoaded('media') && $model->media->isNotEmpty()) {
            $firstMedia = $model->media->first();

            if (! empty($firstMedia->image)) {
                return storage_url($firstMedia->image, static_storage_disk());
            }
        }

        return asset(config('user.avatar'));
    }

    private function truncate(string $text, int $limit): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit - 3) . '...';
        }

        return $text ?: config('app.name');
    }
}
