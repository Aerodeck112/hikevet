#!/usr/bin/env python3
"""Inventar automat al pluginurilor WordPress din source-wordpress/.

Produce docs/generated/plugin-inventory.md: functii, hook-uri, shortcode-uri,
actiuni AJAX/admin-post, tabele, meta keys, options si cron, fiecare cu fisier:linie.
Rulare: python3 tools/audit/plugin_inventory.py
"""
import re, glob, os, collections

ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
SRC = os.path.join(ROOT, 'source-wordpress')
OUT = os.path.join(ROOT, 'docs', 'generated', 'plugin-inventory.md')

PATTERNS = {
    'Shortcode-uri': r"add_shortcode\(\s*'([^']+)'",
    'Actiuni admin-post / AJAX': r"add_action\(\s*'((?:admin_post_|admin_post_nopriv_|wp_ajax_|wp_ajax_nopriv_)[^']+)'",
    'Hook-uri custom si cron': r"(?:add_action|do_action|wp_schedule_event\([^,]+,[^,]+,)\s*\(?\s*'(hv_[a-z0-9_]+)'",
    'Tipuri de post (CPT)': r"register_post_type\(\s*(?:'([^']+)'|self::CPT)",
    'Roluri si capabilitati': r"(?:add_role\(\s*|add_cap\(\s*|current_user_can\(\s*)'([a-z_]+)'",
    'Options': r"(?:get|update|add|delete)_option\(\s*'([a-z0-9_]+)'",
    'Post meta': r"(?:get|update|add|delete)_post_meta\(\s*[^,]+,\s*'([a-z0-9_]+)'",
    'User meta': r"(?:get|update|add|delete)_user_meta\(\s*[^,]+,\s*'([a-z0-9_]+)'",
    'Tipuri tranzactie wallet (wallet_tx)': r"wallet_tx\(\s*[^,]+,\s*'([a-z_]+)'",
}

def main():
    out = ['# Inventar automat pluginuri WordPress', '',
           'Generat de `tools/audit/plugin_inventory.py` din `source-wordpress/`. Nu edita manual.', '']
    for path in sorted(glob.glob(os.path.join(SRC, '*', '*.php'))):
        rel = os.path.relpath(path, ROOT)
        lines = open(path, encoding='utf8', errors='replace').read().split('\n')
        text = '\n'.join(lines)
        ver = re.search(r"const VER = '([^']+)'", text) or re.search(r'Version:\s*([0-9.]+)', text)
        out += [f'## {os.path.basename(os.path.dirname(path))}', '',
                f'- Fisier: `{rel}` ({len(lines)} linii)',
                f'- Versiune in cod: `{ver.group(1) if ver else "?"}`', '']
        consts = [(i + 1, m.group(1), m.group(2).strip()) for i, l in enumerate(lines)
                  for m in [re.match(r"\s*const (\w+)\s*=\s*(.+?);", l)] if m]
        if consts:
            out += ['### Constante', '', '| Linie | Constanta | Valoare |', '|---|---|---|']
            out += [f'| {n} | `{c}` | `{v[:80]}` |' for n, c, v in consts] + ['']
        tables = [(i + 1, m.group(1)) for i, l in enumerate(lines) for m in [re.search(r'CREATE TABLE \$(\w+)', l)] if m]
        if tables:
            out += ['### Tabele create (dbDelta)', '', '| Linie | Variabila tabel |', '|---|---|']
            out += [f'| {n} | `${t}` |' for n, t in tables] + ['']
        for title, pat in PATTERNS.items():
            found = collections.OrderedDict()
            for i, l in enumerate(lines):
                for m in re.finditer(pat, l):
                    key = m.group(1) or 'self::CPT'
                    found.setdefault(key, []).append(i + 1)
            if found:
                out += [f'### {title}', '', '| Cheie | Linii |', '|---|---|']
                out += [f'| `{k}` | {", ".join(map(str, v[:8]))}{" …" if len(v) > 8 else ""} |' for k, v in found.items()] + ['']
        funcs = [(i + 1, m.group(2), (m.group(1) or '').strip()) for i, l in enumerate(lines)
                 for m in [re.match(r"\s*((?:public|private|protected|static)\s+(?:static\s+)?)?function (\w+)\(", l)] if m]
        out += [f'### Functii ({len(funcs)})', '', '| Linie | Functie | Vizibilitate |', '|---|---|---|']
        out += [f'| {n} | `{f}()` | {v or "-"} |' for n, f, v in funcs] + ['']
    open(OUT, 'w', encoding='utf8').write('\n'.join(out))
    print('scris', os.path.relpath(OUT, ROOT), len(out), 'linii')

if __name__ == '__main__':
    main()
