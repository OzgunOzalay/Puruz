<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = collect([
                ['loc' => route('shop.home'), 'lastmod' => null],
                ['loc' => route('shop.index'), 'lastmod' => null],
                ['loc' => route('shop.workshops'), 'lastmod' => null],
            ]);

            Category::query()->visible()->get()->each(fn ($c) => $urls->push(['loc' => $c->url(), 'lastmod' => $c->updated_at]));
            Product::query()->listed()->get()->each(fn ($p) => $urls->push(['loc' => $p->url(), 'lastmod' => $p->updated_at]));
            Page::query()->published()->get()->each(fn ($p) => $urls->push(['loc' => $p->url(), 'lastmod' => $p->updated_at]));

            return view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
