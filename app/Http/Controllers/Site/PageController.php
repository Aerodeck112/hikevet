<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\PriceList;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TeamMember;
use App\Services\Seo\Seo;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home(Seo $seo)
    {
        $page = Page::query()->where('slug', 'home')->firstOrFail();
        $seo->title = $page->seo_title ?: $seo->title;
        if ($page->seo_description) {
            $seo->description = $page->seo_description;
        }
        $seo->schema(Seo::clinicSchema());
        $seo->schema(['@type' => 'WebSite', 'name' => 'HiKeVet', 'url' => Seo::canonicalUrl('/')]);

        return view('site.home', [
            'seo' => $seo, 'page' => $page, 'b' => $page->blocks ?? [],
            'categories' => ServiceCategory::query()->with(['services' => fn ($q) => $q->where('is_published', true)])->orderBy('sort')->get(),
            'team' => TeamMember::query()->where('is_published', true)->orderBy('sort')->get(),
            'posts' => Post::published()->latest('published_at')->limit(3)->get(),
            'tiers' => $this->tiersForCalculator(),
        ]);
    }

    /** Rezolva orice URL de prim nivel: serviciu, articol sau pagina, exact cu slug-urile din WordPress. */
    public function show(Request $request, Seo $seo, string $slug)
    {
        if ($service = Service::published()->where('slug', $slug)->first()) {
            $seo->page($service->seo_title ?: $service->name, $service->seo_description ?: $service->excerpt, $service->image)
                ->crumbs([['Acasă', '/'], ['Servicii', '/servicii/'], [$service->name, $service->path()]])
                ->schema(['@type' => 'MedicalProcedure', 'name' => $service->name, 'description' => $service->excerpt, 'url' => Seo::canonicalUrl($service->path())]);

            return view('site.service', ['seo' => $seo, 'service' => $service,
                'related' => Service::published()->where('service_category_id', $service->service_category_id)->whereKeyNot($service->id)->orderBy('sort')->limit(5)->get()]);
        }

        if ($post = Post::published()->where('slug', $slug)->first()) {
            $seo->page($post->seo_title ?: $post->title, $post->seo_description ?: $post->excerpt, $post->image)
                ->crumbs([['Acasă', '/'], ['Blog', '/blog/'], [$post->title, $post->path()]])
                ->schema(['@type' => 'Article', 'headline' => $post->title, 'datePublished' => $post->published_at?->toIso8601String(),
                    'dateModified' => $post->updated_at?->toIso8601String(), 'author' => ['@type' => 'Organization', 'name' => 'HiKeVet'],
                    'image' => $post->image ? asset($post->image) : null, 'mainEntityOfPage' => Seo::canonicalUrl($post->path())]);
            $seo->type = 'article';

            return view('site.post', ['seo' => $seo, 'post' => $post,
                'more' => Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get()]);
        }

        $page = Page::query()->where('slug', $slug)->first();
        $preview = $page && ! $page->isPublished() && $request->user()?->can('cms.manage');
        abort_unless($page && ($page->isPublished() || $preview), 404);

        $seo->page($page->seo_title ?: $page->title, $page->seo_description ?: ($page->lead ?: $page->body), $page->og_image ?: $page->hero_image)
            ->crumbs([['Acasă', '/'], [$page->title, $page->path()]]);
        $seo->noindex = $page->noindex || $preview;

        $data = ['seo' => $seo, 'page' => $page, 'b' => $page->blocks ?? [], 'preview' => $preview];

        return match ($page->template) {
            'services' => view('site.services', $data + ['categories' => ServiceCategory::query()->with(['services' => fn ($q) => $q->where('is_published', true)])->orderBy('sort')->get()]),
            'team' => view('site.team', $data + ['team' => TeamMember::query()->where('is_published', true)->orderBy('sort')->get()]),
            'faq' => view('site.faq', $data + ['faqs' => tap(Faq::query()->where('is_published', true)->orderBy('sort')->get(), function ($faqs) use ($seo) {
                if ($faqs->isNotEmpty()) {
                    $seo->schema(['@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($f->answer))]])->all()]);
                }
            })]),
            'blog' => view('site.blog', $data + ['posts' => Post::published()->latest('published_at')->paginate(9)]),
            'contact' => view('site.contact', $data),
            'subscriptions' => view('site.subscriptions', $data + ['tiers' => $this->tiersForCalculator(), 'list' => PriceList::active()->load('tiers.services')]),
            'video-vet' => view('site.video-vet', $data),
            'tour' => view('site.tour', $data),
            'legal' => view('site.legal', $data),
            default => view('site.page', $data),
        };
    }

    private function tiersForCalculator(): array
    {
        return PriceList::active()->tiers->map(fn ($t) => [
            'species' => $t->species, 'label' => $t->label, 'min' => $t->min_kg, 'max' => $t->max_kg, 'inclusive' => $t->max_inclusive,
            'monthly' => $t->price_monthly, 'm6' => $t->price_6m, 'm12' => $t->price_12m,
        ])->values()->all();
    }
}
