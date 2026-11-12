@if ($paginator->hasPages())
<nav aria-label="Pagini" style="display:flex;gap:.75rem;justify-content:center">
    @if (! $paginator->onFirstPage())<a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Mai noi</a>@endif
    @if ($paginator->hasMorePages())<a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Mai vechi</a>@endif
</nav>
@endif
