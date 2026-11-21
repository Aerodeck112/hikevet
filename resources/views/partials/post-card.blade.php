<a class="post" href="{{ site_url($post->path()) }}">
    <div class="post-img"><x-img :src="$post->image" alt="" sizes="(max-width: 880px) 100vw, 33vw" /></div>
    <time datetime="{{ $post->published_at?->toDateString() }}">{{ \App\Support\Locales::date($post->published_at, false) }}</time>
    <h3>{{ $post->title }}</h3>
    <p>{{ $post->excerpt }}</p>
</a>
