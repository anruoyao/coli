@php($product = $data['product'])

@if($product->preview_image_url)
    <img class="preview" src="{{ $product->preview_image_url }}" alt="{{ $product->title }}">
@elseif($data['image'])
    <img class="preview" src="{{ $data['image'] }}" alt="{{ $product->title }}">
@endif

<h1>{{ $product->title }}</h1>
<p class="price">
    {{ $product->discount > 0 ? $product->formatted_sale_price : $product->formatted_price }}
</p>

<p class="meta-line">
    {{ __('seo.product.by') }}
    <a href="{{ $product->user->profile_url }}">{{ $product->user->name }}</a>
</p>

@if($product->category_name)
    <p><span class="pill">{{ $product->category_name }}</span></p>
@endif

@if($product->description)
    <h2>{{ __('seo.product.description') }}</h2>
    <div class="bio">{!! nl2br(e($product->description)) !!}</div>
@endif