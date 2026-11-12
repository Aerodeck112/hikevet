#!/usr/bin/env python3
"""Extrage schemele CREATE TABLE din pluginuri in docs/generated/wp-table-schemas.md
si in database/import-spec/wp-tables.json (folosit de importatoare pentru validare dry-run)."""
import re, glob, os, json
ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
CONSTS = {}
md = ['# Scheme tabele custom WordPress', '', 'Generat de `tools/audit/table_schemas.py`. Prefixul real (`wp_`) este variabil.', '']
spec = {}
for path in sorted(glob.glob(os.path.join(ROOT, 'source-wordpress', '*', '*.php'))):
    text = open(path, encoding='utf8', errors='replace').read()
    consts = dict(re.findall(r"const (TABLE\w*)\s*=\s*'([^']+)'", text))
    var_to_table = {}
    for m in re.finditer(r"\$(\w+)\s*=\s*(?:\$this->db_table\(\s*self::(\w+)\s*\)|\$wpdb->prefix\s*\.\s*self::(\w+))", text):
        var_to_table[m.group(1)] = consts.get(m.group(2) or m.group(3), m.group(1))
    for m in re.finditer(r'CREATE TABLE \$(\w+) \((.*?)\) \$charset', text, re.S):
        table = var_to_table.get(m.group(1), m.group(1))
        cols, keys = [], []
        for line in m.group(2).split('\n'):
            line = line.strip().rstrip(',')
            if not line: continue
            if re.match(r'(PRIMARY KEY|UNIQUE KEY|KEY)\b', line): keys.append(line); continue
            name, rest = line.split(None, 1)
            typ = re.match(r'[A-Z]+(?:\([0-9,]+\))?(?: UNSIGNED)?', rest)
            cols.append({'name': name, 'type': typ.group(0) if typ else rest, 'nullable': 'NOT NULL' not in rest})
        spec[table] = {'plugin': os.path.basename(os.path.dirname(path)), 'columns': cols, 'keys': keys}
        md += [f'## `{table}` ({len(cols)} coloane) — {spec[table]["plugin"]}', '', '| Coloana | Tip | NULL |', '|---|---|---|']
        md += [f'| `{c["name"]}` | {c["type"]} | {"da" if c["nullable"] else "nu"} |' for c in cols]
        md += ['', 'Chei: ' + '; '.join(f'`{k}`' for k in keys), '']
os.makedirs(os.path.join(ROOT, 'database', 'import-spec'), exist_ok=True)
open(os.path.join(ROOT, 'docs', 'generated', 'wp-table-schemas.md'), 'w', encoding='utf8').write('\n'.join(md))
json.dump(spec, open(os.path.join(ROOT, 'database', 'import-spec', 'wp-tables.json'), 'w', encoding='utf8'), ensure_ascii=False, indent=1)
print({k: len(v['columns']) for k, v in spec.items()})
