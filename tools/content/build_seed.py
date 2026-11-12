#!/usr/bin/env python3
"""Transforma continutul extras din hikevet.com (tools: crawl + extract) in setul de date
folosit de SiteContentSeeder. Textele sunt cele reale ale site-ului; nu se inventeaza nimic.
Rulare: python3 tools/content/build_seed.py <content.json> <feed.xml>"""
import json, re, sys, os, html
from email.utils import parsedate_to_datetime

ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
content = json.load(open(sys.argv[1], encoding='utf8'))
feed = open(sys.argv[2], encoding='utf8').read() if len(sys.argv) > 2 else ''
manifest = json.load(open(os.path.join(ROOT, 'database/seeders/data/media-manifest.json')))
by_source = {}
for slug, e in manifest.items():
    by_source[e['source']] = slug
    by_source[re.sub(r'-scaled(\.\w+)$', r'\1', e['source'])] = slug

def media(src):
    if not src: return None
    src = re.sub(r'-\d+x\d+(\.[a-z]+)$', r'\1', src)
    for cand in (src, re.sub(r'(\.\w+)$', r'-scaled\1', src)):
        if cand in by_source: return by_source[cand]
    return None

def text_of(h): return html.unescape(re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', h or ''))).strip()
def tidy(h):
    h = re.sub(r'</?(span|div)[^>]*>', '', h or '')
    h = re.sub(r'<p>\s*(&nbsp;| )?\s*</p>', '', h)
    return re.sub(r'\s+', ' ', h).strip()
def excerpt(h, n=170):
    t = text_of(h)
    if len(t) <= n: return t
    cut = t[:n].rsplit(' ', 1)[0].rstrip(',;:')
    return cut + '…'
def blocks(slug): return [b for sec in content[slug]['sections'] for b in sec]

out = {'services': [], 'team': [], 'posts': [], 'faqs': [], 'pages': [], 'partners': [], 'instagram': [], 'redirects': [], 'flagged_texts': []}

# --- Servicii: lista din /servicii/ + corpul fiecarei pagini de serviciu
ICONS = {'analize-de-laborator': 'flask', 'radiografie': 'scan', 'ecografie': 'wave', 'medicina-interna': 'stethoscope',
    'anestezie-inhalatorie-si-analgezie': 'mask', 'terapie-intensiva': 'pulse', 'consultatii-la-domiciliu': 'home',
    'chirurgie-tesuturi-moi': 'scalpel', 'servicii-ortopedie': 'bone', 'dermatologie': 'drop', 'nutritie': 'bowl',
    'oftamologie': 'eye', 'neurologie': 'brain', 'detartraj': 'tooth', 'vaccinare': 'syringe', 'deparazitare-interna': 'shield',
    'deparazitare-externa': 'shield', 'microcipare': 'chip', 'testarea-progesteronului': 'flask', 'pasaport-calatorie': 'passport',
    'citologie-vaginala': 'microscope', 'inseminare-artificiala': 'heart', 'serviciu-de-ambulanta': 'ambulance'}
CATEGORIES = [('diagnostic', 'Diagnostic și imagistică', ['analize-de-laborator', 'radiografie', 'ecografie', 'citologie-vaginala', 'testarea-progesteronului']),
    ('medicina', 'Medicină și specialități', ['medicina-interna', 'dermatologie', 'oftamologie', 'neurologie', 'nutritie', 'terapie-intensiva']),
    ('chirurgie', 'Chirurgie și anestezie', ['chirurgie-tesuturi-moi', 'servicii-ortopedie', 'anestezie-inhalatorie-si-analgezie', 'detartraj']),
    ('preventie', 'Prevenție și documente', ['vaccinare', 'deparazitare-interna', 'deparazitare-externa', 'microcipare', 'pasaport-calatorie', 'inseminare-artificiala']),
    ('acasa', 'La domiciliu și urgențe', ['consultatii-la-domiciliu', 'serviciu-de-ambulanta'])]
order = [b['href'].strip('/') for b in blocks('servicii') if b['type'] == 'button' and b.get('href', '').startswith('/')]
order = list(dict.fromkeys(order))
for slug in content:
    if slug in ICONS and slug not in order: order.append(slug)
cat_of = {s: c for c, _, lst in CATEGORIES for s in lst}
for i, slug in enumerate(order):
    if slug not in content: continue
    bl = blocks(slug)
    name = content[slug]['title'].replace('Servicii – ', '')
    body_parts, promo = [], False
    for b in bl:
        if b['type'] == 'icon-box' and re.search(r'asigur|abonament', (b.get('title', '') + b.get('text', '')).lower()): promo = True
        if promo: continue
        if b['type'] == 'text-editor': body_parts.append(tidy(b['html']))
    body = ' '.join(body_parts)
    booking = next((b['href'] for b in bl if b['type'] == 'button' and 'digitail' in b.get('href', '')), '')
    out['services'].append({'slug': slug, 'name': name, 'category': cat_of.get(slug, 'medicina'), 'excerpt': excerpt(body),
        'body': body, 'icon': ICONS.get(slug, 'paw'), 'booking_url': booking, 'sort': i})
out['service_categories'] = [{'slug': c, 'name': n, 'sort': i} for i, (c, n, _) in enumerate(CATEGORIES)]

# --- Echipa: doar persoanele numite pe site
eb = blocks('echipa')
intro = next(tidy(b['html']) for b in eb if b['type'] == 'text-editor')
radu_bio = [tidy(b['html']) for b in eb if b['type'] == 'text-editor'][1]
out['team'] = [
    {'slug': 'radu-festeu', 'name': 'Dr. Radu Feșteu', 'role_title': 'Medic veterinar', 'bio': radu_bio, 'photo': media('2023/05/0H7A9503.jpg'), 'sort': 0},
    {'slug': 'dregics-szidonia', 'name': 'Dr. Dregics Szidonia', 'role_title': 'Medic veterinar', 'bio': '', 'photo': media('2025/11/WhatsApp-Image-2025-11-04-at-14.06.33.jpeg'), 'sort': 1},
]

# --- Blog
dates = {}
for item in re.findall(r'<item>(.*?)</item>', feed, re.S):
    link = re.search(r'<link>([^<]+)', item); pub = re.search(r'<pubDate>([^<]+)', item)
    if link and pub: dates[link.group(1).rstrip('/').rsplit('/', 1)[-1]] = parsedate_to_datetime(pub.group(1)).strftime('%Y-%m-%d %H:%M:%S')
for slug in content:
    if not re.match(r'(primul-caine|un-ghid-cuprinzator|beneficiile-)', slug): continue
    bl = blocks(slug); parts = []; image = None
    for b in bl:
        if b['type'] == 'image' and not image: image = media(b.get('src'))
        elif b['type'] == 'heading': parts.append('<h2>%s</h2>' % html.escape(b['text']))
        elif b['type'] == 'text-editor': parts.append(tidy(b['html']))
    body = ' '.join(parts)
    out['posts'].append({'slug': slug, 'title': content[slug]['title'], 'excerpt': excerpt(body, 190), 'body': body, 'image': image, 'published_at': dates.get(slug)})
out['posts'].sort(key=lambda p: p['published_at'] or '', reverse=True)

# --- Intrebari frecvente
fb = blocks('intrebari-frecvente')
faq_intro = next(tidy(b['html']) for b in fb if b['type'] == 'text-editor')
out['faqs'] = [{'question': it['q'], 'answer': tidy(it['a'])} for b in fb if b['type'] == 'toggle' for it in b['items']]

# --- Parteneri si Instagram (homepage)
hb = blocks('home')
out['partners'] = [{'image': media(i['src']), 'alt': i.get('alt', '')} for b in hb if b['type'] == 'image-carousel' for i in b['images'] if media(i['src'])]
seen = set(); out['partners'] = [p for p in out['partners'] if not (p['image'] in seen or seen.add(p['image']))]
out['instagram'] = [media(b['src']) for b in hb if b['type'] == 'image' and 'instagram' in b.get('href', '') and media(b['src'])]
partners_text = next(tidy(b['html']) for b in hb if b['type'] == 'text-editor' and 'parteneri' in b['html'].lower())
video_vet_intro = next(tidy(b['html']) for b in hb if b['type'] == 'text-editor' and 'Video-VET' in b['html'])
counters = [{'value': b['to'], 'suffix': b['suffix'], 'label': b['title']} for b in hb if b['type'] == 'counter']

# --- Texte marcate pentru aprobare (formularea "asigurare")
for slug in content:
    for b in blocks(slug):
        t = ' '.join(str(b.get(k, '')) for k in ('title', 'text', 'html'))
        if re.search(r'asigur', t, re.I) and slug not in ('contract-abonament', 'termeni-si-conditii-abonamente', 'anexa-abonamente', 'ghid-proprietar'):
            out['flagged_texts'].append({'page': '/' if slug == 'home' else f'/{slug}/', 'text': text_of(t)[:300]})

def page(slug, title, template, **kw):
    out['pages'].append({'slug': slug, 'title': title, 'template': template, **kw})

ab = blocks('abonamente')
ab_texts = [tidy(b['html']) for b in ab if b['type'] == 'text-editor']
vv = blocks('video-vet')
vv_texts = [tidy(b['html']) for b in vv if b['type'] == 'text-editor']
tur = blocks('tur-3d')

page('home', 'Clinică veterinară în Târgu Mureș', 'home', lead='Oferim servicii medicale de înaltă calitate și îngrijire personalizată.',
     seo_title='HiKeVet – Clinică veterinară în Târgu Mureș',
     blocks={'hero_script': 'Bine ai venit la HiKeVet', 'hero_title': 'Clinică veterinară', 'hero_image': media('2023/07/cute-tabby-cat-with-green-eyes-lying-on-bright-yellow-bean-bag-.jpg'),
             'staff_line': 'Un personal prietenos și profesionist, mereu gata să te ajute.', 'staff_image': media('2023/05/0H7A9586-scaled-e1689027689830.jpg'),
             'video_vet_intro': video_vet_intro, 'video_vet_points': ['Accesibil indiferent de locație.', 'Recomandări de tratament personalizate', 'Rapid și ușor'],
             'counters': counters, 'partners_text': partners_text, 'instagram_url': 'https://www.instagram.com/clinica_veterinara_hikevet/',
             'subscription_image': media('2023/07/Untitled-design.png')})
page('servicii', 'Servicii', 'services', lead=text_of(next((b['html'] for b in blocks('servicii') if b['type'] == 'text-editor'), '')))
page('echipa', 'Echipa', 'team', body=intro, hero_image=media('2023/05/0H7A9513-scaled.jpg'))
page('abonamente', 'Abonamente pentru animăluțe', 'subscriptions', body=ab_texts[0],
     lead='Alege un abonament lunar potrivit pentru tine și pentru animăluțul tău.',
     blocks={'rules_html': next((t for t in ab_texts if 'Reguli abonamente' in t), ''), 'closing': ab_texts[-1], 'image': media('2023/07/img-section-03-min.png')})
page('hikevet-contact', 'Contact', 'contact')
page('intrebari-frecvente', 'Întrebări frecvente', 'faq', body=faq_intro)
page('blog', 'Blog', 'blog')
page('video-vet', 'Video Vet', 'video-vet', lead='Asistentul tău virtual', body=vv_texts[0] if vv_texts else '',
     blocks={'advantages': [text_of(t) for t in vv_texts[1:7]], 'how': vv_texts[7] if len(vv_texts) > 7 else '',
             'booking_url': 'https://vet.digitail.io/clinics/clinica-veterinara-hikevet', 'image': media('2023/07/photo-of-dog-and-computer-on-a-fitness-mat-2022-11-11-10-52-27-utc-scaled.jpg')})
page('tur-3d', 'Tur 3D', 'tour', body=tidy(tur[0]['html']) if tur else '', blocks={'embed_note': 'Turul 3D (Captur3D) se configurează din admin: lipsește codul de inserare în exportul public.'})
for slug, title in [('termeni-si-conditii-abonamente', 'Termeni și condiții abonamente'), ('contract-abonament', 'Contract abonament'),
                    ('anexa-abonamente', 'Anexă abonamente'), ('ghid-proprietar', 'Ghidul proprietarului')]:
    b = blocks(slug)[0]
    page(slug, title, 'legal', body=tidy(b['html']), document_version='2026-11-12-import')
page('politica-de-confidentialitate', 'Politica de confidențialitate', 'legal', status='draft', noindex=True,
     body='<p>Pagina de pe site-ul actual nu are conținut. Textul politicii trebuie furnizat și aprobat de clinică înainte de publicare.</p>')

out['redirects'] = [
    {'from': '/asigurari/', 'to': '/abonamente/', 'note': 'Link intern rupt pe paginile de servicii ale site-ului vechi'},
    {'from': '/my-account/', 'to': '/acces-cont/', 'note': 'WooCommerce'}, {'from': '/shop/', 'to': '/', 'note': 'WooCommerce, magazin gol'},
    {'from': '/cart/', 'to': '/', 'note': 'WooCommerce'}, {'from': '/checkout/', 'to': '/', 'note': 'WooCommerce'},
    {'from': '/category/uncategorized/', 'to': '/blog/', 'note': 'Arhiva categorie WordPress'},
    {'from': '/thank-you-page/', 'to': '/acces-cont/', 'note': 'Confirmarea se afiseaza in configurator'},
    {'from': '/inregistrare-cont/', 'to': '/acces-cont/', 'note': 'Inregistrarea este pe pagina de acces'},
    {'from': '/feed/', 'to': '/blog/', 'note': 'RSS WordPress'},
]
json.dump(out, open(os.path.join(ROOT, 'database/seeders/data/site-content.json'), 'w', encoding='utf8'), ensure_ascii=False, indent=1)
print({k: len(v) for k, v in out.items()})
print('servicii fara corp:', [s['slug'] for s in out['services'] if not s['body']])
print('poze echipa:', [t['photo'] for t in out['team']], '| posts:', [(p['slug'][:20], p['published_at'], p['image']) for p in out['posts']])
