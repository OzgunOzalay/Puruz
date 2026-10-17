@php
    $placements = ['story' => 'Story bağlantı çıkartması', 'bio' => 'Profil (bio)', 'post' => 'Gönderi açıklaması', 'dm' => 'DM / mesaj'];
@endphp
<div class="space-y-3">
    @unless ($product->isReachable())
        <p class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
            Bu ürün şu anda müşterilere görünmüyor (durum veya görünürlük). Linki paylaşmadan önce yayına alın.
        </p>
    @endunless
    @foreach ($placements as $key => $label)
        @php($link = $product->instagramLink($key))
        <div x-data="{ copied: false }" class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ $label }}</p>
                <p class="truncate text-xs text-gray-500">{{ $link }}</p>
            </div>
            <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText(@js($link)); copied = true; setTimeout(() => copied = false, 1500)">
                <span x-show="!copied">Kopyala</span><span x-show="copied" x-cloak>Kopyalandı ✓</span>
            </x-filament::button>
        </div>
    @endforeach
    <div x-data="{ copied: false }" class="flex items-center gap-3 rounded-lg border border-dashed border-gray-300 p-3 dark:border-white/10">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium">Kısa link</p>
            <p class="truncate text-xs text-gray-500">{{ $product->shortUrl() }}</p>
        </div>
        <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText(@js($product->shortUrl())); copied = true; setTimeout(() => copied = false, 1500)">
            <span x-show="!copied">Kopyala</span><span x-show="copied" x-cloak>Kopyalandı ✓</span>
        </x-filament::button>
    </div>
    <p class="text-xs text-gray-500">Linklerdeki utm etiketleri sayesinde siparişin hangi paylaşımdan geldiğini panelde görebilirsiniz.</p>
</div>
