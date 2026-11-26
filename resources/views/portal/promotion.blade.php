<aside class="promo">
    @if($promotion->imageUrl())
        <img src="{{ $promotion->imageUrl() }}" alt="" class="promo-img" loading="lazy">
    @endif
    <div class="promo-body">
        <span class="promo-tag">{{ __('portal.promo_tag') }}</span>
        <h2>{{ $promotion->t('title', $lang) }}</h2>
        @if($promotion->t('text', $lang))<p>{{ $promotion->t('text', $lang) }}</p>@endif
        @if($withButton && $promotion->cta_url)
            <a href="{{ route('portal.promotion', [$location->slug, $promotion]) }}" class="btn promo-btn">{{ $promotion->t('cta_label', $lang) ?: __('portal.promo_more') }}</a>
        @endif
    </div>
</aside>
