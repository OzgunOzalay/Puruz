<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\WorkshopSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function home(): View
    {
        $featured = Product::query()->listed()->where('is_featured', true)
            ->where('type', ProductType::Physical)->with('variants')->ordered()->limit(8)->get();

        $latest = Product::query()->listed()->where('type', ProductType::Physical)
            ->with('variants')->latest('published_at')->latest('id')->limit(8)->get();

        $workshops = Product::query()->listed()->where('type', ProductType::Workshop)
            ->with('upcomingSessions')->ordered()->limit(3)->get();

        $categories = Category::query()->visible()->whereNull('parent_id')->ordered()->limit(6)->get();

        return view('shop.home', compact('featured', 'latest', 'workshops', 'categories'));
    }

    public function index(Request $request): View
    {
        return $this->listing($request, Product::query()->listed(), 'Tüm ürünler');
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_visible, 404);

        $ids = $category->children()->visible()->pluck('id')->push($category->id);
        $query = Product::query()->listed()->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $ids));

        return $this->listing($request, $query, $category->name, $category);
    }

    private function listing(Request $request, Builder $query, string $title, ?Category $category = null): View
    {
        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('short_description', 'like', '%'.$search.'%')
                ->orWhere('materials', 'like', '%'.$search.'%'));
        }

        if ($request->boolean('stokta')) {
            $query->where(fn (Builder $q) => $q->where('track_stock', false)->orWhere('made_to_order', true)->orWhere('stock', '>', 0)
                ->orWhereHas('variants', fn (Builder $v) => $v->where('is_active', true)->where('stock', '>', 0)));
        }

        match ($request->query('sirala')) {
            'fiyat-artan' => $query->orderBy('price'),
            'fiyat-azalan' => $query->orderByDesc('price'),
            'yeni' => $query->latest('published_at')->latest('id'),
            default => $query->ordered(),
        };

        $products = $query->with(['variants', 'upcomingSessions'])->paginate(24)->withQueryString();
        $categories = Category::query()->visible()->whereNull('parent_id')->ordered()->get();

        return view('shop.index', compact('products', 'title', 'category', 'categories', 'search'));
    }

    public function workshops(): View
    {
        $workshops = Product::query()->listed()->where('type', ProductType::Workshop)
            ->with('upcomingSessions')->ordered()->get();

        return view('shop.workshops', compact('workshops'));
    }

    public function product(string $slug): View
    {
        $product = Product::query()->reachable()->where('slug', $slug)->firstOrFail();

        // Plain increment without touching updated_at; good enough as a popularity hint.
        DB::table('products')->where('id', $product->id)->increment('view_count');

        return $this->renderProduct($product);
    }

    public function preview(Product $product): View
    {
        return $this->renderProduct($product, preview: true);
    }

    public function short(Request $request, int $id): RedirectResponse
    {
        $product = Product::query()->reachable()->findOrFail($id);

        return redirect()->to($product->url($request->query()), 301);
    }

    public function linkInBio(): View
    {
        $products = Product::query()->listed()->where('is_featured', true)->with('variants')->ordered()->limit(6)->get();
        $sessions = WorkshopSession::query()->upcoming()->where('is_visible', true)
            ->whereHas('product', fn (Builder $q) => $q->listed())
            ->with('product')->limit(4)->get();

        return view('shop.link', compact('products', 'sessions'));
    }

    private function renderProduct(Product $product, bool $preview = false): View
    {
        $product->load(['categories', 'variants' => fn ($q) => $q->where('is_active', true), 'upcomingSessions']);

        $related = Product::query()->listed()->whereKeyNot($product->id)
            ->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $product->categories->pluck('id')))
            ->with('variants')->inRandomOrder()->limit(4)->get();

        return view('shop.product', compact('product', 'related', 'preview'));
    }
}
