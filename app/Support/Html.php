<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** Curata HTML-ul introdus in admin: fara scripturi, stiluri inline, iframe-uri sau atribute de eveniment. */
class Html
{
    public static function clean(string $html): string
    {
        static $sanitizer = null;

        $sanitizer ??= new HtmlSanitizer((new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')->allowElement('u')
            ->allowElement('h2')->allowElement('h3')->allowElement('h4')->allowElement('ul')->allowElement('ol')->allowElement('li')
            ->allowElement('blockquote')->allowElement('hr')
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')->allowElement('th')->allowElement('td')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener')
            ->withMaxInputLength(500000));

        return trim($sanitizer->sanitize($html));
    }
}
