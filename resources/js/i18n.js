// Textele din scripturi, in limba paginii. Dictionarul vine din lang/{en,hu}.json (App\Support\JsStrings);
// in romana cheia este chiar textul afisat.
export function t(text, vars = {}) {
    let out = (window.hvI18n && window.hvI18n[text]) || text;
    for (const [key, value] of Object.entries(vars)) out = out.split(':' + key).join(value);
    return out;
}

export const intl = () => document.documentElement.dataset.intl || 'ro-RO';
