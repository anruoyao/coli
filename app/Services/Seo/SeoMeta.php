<?php

namespace App\Services\Seo;

/**
 * SEO 元数据载体：由 SeoResolver 针对具体路径解析生成，
 * 由 apps.seo.index 视图渲染为独立的可抓取 HTML 页面。
 */
class SeoMeta
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $image = null,
        /** website | profile | article | story | job | product */
        public string $type = 'website',
        /** JSON-LD 结构化数据（关联数组，渲染时 json_encode） */
        public array $jsonLd = [],
        /** 正文快照局部视图名（apps.seo.parts.*） */
        public string $bodyView = 'apps.seo.parts.website',
        /** 传给正文快照视图的数据 */
        public array $data = [],
    ) {
    }

    public function ogType(): string
    {
        return match ($this->type) {
            'profile' => 'profile',
            'article' => 'article',
            'job' => 'website',
            'product' => 'product',
            default => 'website',
        };
    }
}