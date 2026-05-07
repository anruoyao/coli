<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Post;
use App\Models\User;
use App\Models\Product;
use App\Models\JobListing;
use Illuminate\Http\Request;
use App\Services\Seo\CrawlerDetector;
use Symfony\Component\HttpFoundation\Response;

class SeoRendererMiddleware
{
    private CrawlerDetector $detector;

    public function __construct()
    {
        $this->detector = new CrawlerDetector();
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->detector->isCrawler(userAgent: $request->userAgent() ?? '')) {
            return $next($request);
        }

        $path = $request->path();

        if (preg_match('#^publication/([a-zA-Z0-9]+)$#', $path, $matches)) {
            return $this->renderPost(hashId: $matches[1]);
        }

        if (preg_match('#^@([a-zA-Z0-9._]+)$#', $path, $matches)) {
            return $this->renderProfile(username: $matches[1]);
        }

        if (preg_match('#^marketplace/product/([a-zA-Z0-9]+)$#', $path, $matches)) {
            return $this->renderProduct(hashId: $matches[1]);
        }

        if (preg_match('#^jobs/([a-zA-Z0-9]+)$#', $path, $matches)) {
            return $this->renderJob(hashId: $matches[1]);
        }

        return $next($request);
    }

    private function renderPost(string $hashId): Response
    {
        $post = Post::with(['user', 'media', 'linkSnapshot'])
            ->findByHashId(hashId: $hashId);

        if (! $post || ! $post->user) {
            return $this->notFound();
        }

        $image = $this->extractImage(model: $post, imageField: 'image');
        $description = $this->extractDescription(content: strip_tags($post->content ?? ''));

        $data = [
            'title' => $post->user->name . ' on ' . config('app.name'),
            'description' => $description,
            'image' => $image,
            'url' => url("publication/{$post->hash_id}"),
            'published_at' => $post->created_at?->toIso8601String(),
            'author' => $post->user->name,
            'type' => 'article',
        ];

        return response(
            content: view('seo.post', $data)->render(),
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    private function renderProfile(string $username): Response
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            return $this->notFound();
        }

        $image = $user->avatar
            ? storage_url($user->avatar, static_storage_disk())
            : asset(config('user.avatar'));

        $data = [
            'title' => $user->name . ' (@' . $user->username . ') on ' . config('app.name'),
            'description' => $user->caption ?? ($user->name . ' on ' . config('app.name')),
            'image' => $image,
            'url' => url("@{$user->username}"),
            'type' => 'profile',
        ];

        return response(
            content: view('seo.profile', $data)->render(),
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    private function renderProduct(string $hashId): Response
    {
        $product = Product::with(['user', 'media'])->findByHashId(hashId: $hashId);

        if (! $product || ! $product->user) {
            return $this->notFound();
        }

        $image = $this->extractImage(model: $product, imageField: 'image');
        $description = $product->description
            ? $this->extractDescription(content: strip_tags($product->description))
            : $product->name . ' on ' . config('app.name');

        $data = [
            'title' => $product->name ?? 'Product on ' . config('app.name'),
            'description' => $description,
            'image' => $image,
            'url' => url("marketplace/product/{$product->hash_id}"),
            'type' => 'product',
        ];

        return response(
            content: view('seo.product', $data)->render(),
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    private function renderJob(string $hashId): Response
    {
        $job = JobListing::with(['user'])->findByHashId(hashId: $hashId);

        if (! $job || ! $job->user) {
            return $this->notFound();
        }

        $data = [
            'title' => $job->title ?? 'Job on ' . config('app.name'),
            'description' => $this->extractDescription(content: strip_tags($job->description ?? '')),
            'image' => asset(config('app.favicon', 'favicon.ico')),
            'url' => url("jobs/{$job->hash_id}"),
            'type' => 'job',
        ];

        return response(
            content: view('seo.job', $data)->render(),
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    private function extractImage($model, string $imageField = 'image'): string
    {
        if (! empty($model->{$imageField})) {
            return storage_url($model->{$imageField}, static_storage_disk());
        }

        if ($model->relationLoaded('media') && $model->media->isNotEmpty()) {
            $firstMedia = $model->media->first();

            if (! empty($firstMedia->{$imageField})) {
                return storage_url($firstMedia->{$imageField}, static_storage_disk());
            }
        }

        return asset(config('app.favicon', 'favicon.ico'));
    }

    private function extractDescription(string $content): string
    {
        $text = preg_replace('/\s+/', ' ', $content);
        $text = trim($text);

        if (mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 197) . '...';
        }

        return $text ?: config('app.name');
    }

    private function notFound(): Response
    {
        return response(
            content: '',
            status: 404,
        );
    }
}
