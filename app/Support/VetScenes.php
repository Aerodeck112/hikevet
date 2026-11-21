<?php

namespace App\Support;

/**
 * Scenele de pe paginile de servicii: medicul ii face lui Hiko (sau Razei) exact serviciul respectiv.
 * Toate scenele au acelasi cadru (cabinet 400x300, masa, medic in stanga, pacient pe masa);
 * diferă instrumentul din mana medicului, decorul si animatia. Desenele sunt SVG inline, fara imagini.
 */
class VetScenes
{
    private const INK = '#1F1638';

    /** Pozitia pacientului pe masa (nested svg). */
    private const DOG = ['x' => 200, 'y' => 70, 'w' => 160, 'h' => 153];

    private const CAT = ['x' => 215, 'y' => 82, 'w' => 125, 'h' => 136];

    public static function has(?string $slug): bool
    {
        return $slug !== null && isset(self::scenes()[$slug]);
    }

    /** @return array{patient: string, caption: string, label: string, svg: string}|null */
    public static function get(?string $slug): ?array
    {
        if (! self::has($slug)) {
            return null;
        }
        $s = self::scenes()[$slug] + ['room' => 'clinic', 'doctor' => 'coat', 'asleep' => false, 'hand' => null, 'tool' => '', 'extras' => '', 'front' => '', 'lift' => 0];

        $s['caption'] = __($s['caption']);
        $s['label'] = __($s['label']);

        return ['patient' => $s['patient'], 'caption' => $s['caption'], 'label' => $s['label'], 'svg' => self::compose($slug, $s)];
    }

    private static function compose(string $slug, array $s): string
    {
        $ink = self::INK;
        $clip = 'vs-clip-'.$slug;
        $out = '<svg class="vet-scene-art" viewBox="0 0 400 300" role="img" aria-label="'.e($s['label']).'" focusable="false">';
        $out .= '<defs><clipPath id="'.$clip.'"><rect x="4" y="4" width="392" height="292" rx="26"/></clipPath></defs>';
        $out .= '<g clip-path="url(#'.$clip.')">';

        if ($s['room'] === 'road') {
            $out .= '<rect x="0" y="0" width="400" height="300" fill="#D7F1E5"/><rect x="0" y="236" width="400" height="64" fill="#4B4560"/>'
                .'<path d="M20 268 h50 M110 268 h50 M200 268 h50 M290 268 h50 M380 268 h40" stroke="#FFC21F" stroke-width="6" stroke-linecap="round" class="vs-road"/>'
                .'<circle cx="340" cy="56" r="26" fill="#FFC21F"/><path d="M40 70 q20 -18 40 0 q16 -12 30 4 h-70 z" fill="#fff"/>';
        } elseif ($s['room'] === 'home') {
            $out .= '<rect x="0" y="0" width="400" height="300" fill="#FFF3E0"/><rect x="0" y="250" width="400" height="50" fill="#E9C9A0"/>'
                .'<rect x="170" y="22" width="96" height="70" rx="8" fill="#CFE6FF" stroke="'.$ink.'" stroke-width="4"/><path d="M218 22 v70 M170 57 h96" stroke="'.$ink.'" stroke-width="3"/>'
                .'<path d="M160 18 q12 40 0 82 h18 q-8 -42 0 -82 z M276 18 q-12 40 0 82 h-18 q8 -42 0 -82 z" fill="#E6177A" stroke="'.$ink.'" stroke-width="3"/>';
        } else {
            $out .= '<rect x="0" y="0" width="400" height="300" fill="#FFFFFF"/><rect x="0" y="252" width="400" height="48" fill="#D7F1E5"/>'
                .'<path d="M0 252 h400" stroke="'.$ink.'" stroke-width="3"/>';
        }

        $out .= $s['extras'];

        if ($s['room'] === 'clinic') {
            $out .= '<rect x="150" y="208" width="226" height="14" rx="6" fill="#D9E3F5" stroke="'.$ink.'" stroke-width="4"/>'
                .'<rect x="206" y="222" width="114" height="34" fill="#B9C6E4" stroke="'.$ink.'" stroke-width="4"/>'
                .'<rect x="186" y="254" width="154" height="10" rx="5" fill="#8E9CC2" stroke="'.$ink.'" stroke-width="3"/>';
        } elseif ($s['room'] === 'home') {
            $out .= '<path d="M150 200 q0 -26 26 -26 h190 q26 0 26 26 v60 h-242 z" fill="#1E3BA8" stroke="'.$ink.'" stroke-width="4"/>'
                .'<rect x="140" y="204" width="262" height="40" rx="14" fill="#3A5BD0" stroke="'.$ink.'" stroke-width="4"/>';
        }

        if ($s['patient'] !== 'none' && $s['room'] !== 'road') {
            $out .= self::patient($s['patient'], $s['lift']);
            if ($s['asleep']) {
                $out .= self::sleepingEyes($s['patient'], $s['lift']);
            }
        }
        $out .= $s['front'];

        if ($s['room'] !== 'road') {
            $out .= self::doctor($s['doctor'], $s['hand']);
        }
        $out .= $s['tool'];
        $out .= '</g><rect x="4" y="4" width="392" height="292" rx="26" fill="none" stroke="'.$ink.'" stroke-width="5"/></svg>';

        return $out;
    }

    /** Hiko sau Raza, desenati din aceleasi fisiere ca pe restul site-ului. */
    private static function patient(string $kind, int $lift = 0): string
    {
        $box = $kind === 'cat' ? self::CAT : self::DOG;
        $svg = view('partials.mascot-'.($kind === 'cat' ? 'cat' : 'dog'))->render();
        $svg = preg_replace('/\{\{--.*?--\}\}/s', '', $svg);

        return preg_replace('/<svg class="mascot ([^"]+)"/', '<svg class="mascot $1 vs-patient" x="'.$box['x'].'" y="'.($box['y'] - $lift).'" width="'.$box['w'].'" height="'.$box['h'].'"', $svg, 1);
    }

    private static function sleepingEyes(string $kind, int $lift = 0): string
    {
        $ink = self::INK;
        if ($kind === 'cat') {
            [$l, $r, $y] = [263.9, 291.2, 129.7 - $lift];

            return '<g class="vs-closed"><ellipse cx="'.$l.'" cy="'.$y.'" rx="9" ry="10" fill="#F59A3A"/><ellipse cx="'.$r.'" cy="'.$y.'" rx="9" ry="10" fill="#F59A3A"/>'
                .'<path d="M'.($l - 7).' '.($y + 1).' q7 6 14 0 M'.($r - 7).' '.($y + 1).' q7 6 14 0" stroke="'.$ink.'" stroke-width="3" fill="none" stroke-linecap="round"/></g>';
        }
        [$l, $r, $y] = [260, 300, 140.5];

        return '<g class="vs-closed"><circle cx="'.$l.'" cy="'.$y.'" r="10.5" fill="#E9A25B"/><circle cx="'.$r.'" cy="'.$y.'" r="10.5" fill="#E9A25B"/>'
            .'<path d="M'.($l - 7).' '.$y.' q7 6 14 0 M'.($r - 7).' '.$y.' q7 6 14 0" stroke="'.$ink.'" stroke-width="3" fill="none" stroke-linecap="round"/></g>'
            .'<g class="vs-zzz" fill="#1E3BA8" font-family="Arial Black, sans-serif" font-weight="900"><text x="330" y="96" font-size="20">z</text><text x="346" y="78" font-size="15">z</text><text x="358" y="64" font-size="11">z</text></g>';
    }

    /** Medicul: halat alb, stetoscop, un brat intins spre mana data. */
    private static function doctor(string $variant, ?array $hand): string
    {
        $ink = self::INK;
        $skin = '#F2C9A6';
        $o = '<g class="vs-doctor">'
            .'<path d="M34 300 L44 178 Q50 136 100 130 Q150 136 156 178 L166 300 Z" fill="#fff" stroke="'.$ink.'" stroke-width="4" stroke-linejoin="round"/>'
            .'<path d="M84 133 L100 162 L116 133 Z" fill="#13958A" stroke="'.$ink.'" stroke-width="3" stroke-linejoin="round"/>'
            .'<path d="M100 162 V300" stroke="'.$ink.'" stroke-width="3"/>';
        if ($variant === 'apron') {
            $o .= '<path d="M56 300 L62 184 Q100 172 138 184 L144 300 Z" fill="#FFC21F" stroke="'.$ink.'" stroke-width="4" stroke-linejoin="round"/>';
        } else {
            $o .= '<path d="M84 136 Q76 170 92 186 M116 136 Q124 170 108 186" stroke="'.$ink.'" stroke-width="3" fill="none"/>'
                .'<circle cx="100" cy="190" r="6.5" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/>'
                .'<rect x="118" y="196" width="24" height="14" rx="3" fill="#E6177A" stroke="'.$ink.'" stroke-width="2.5"/>';
        }
        $o .= '<rect x="91" y="116" width="18" height="18" fill="'.$skin.'" stroke="'.$ink.'" stroke-width="3"/>'
            .'<circle cx="100" cy="96" r="30" fill="'.$skin.'" stroke="'.$ink.'" stroke-width="4"/>';
        if ($variant === 'surgery') {
            $o .= '<path d="M68 92 Q68 58 100 58 Q132 58 132 92 Q116 82 100 82 Q84 82 68 92 Z" fill="#13958A" stroke="'.$ink.'" stroke-width="4"/>'
                .'<circle cx="89" cy="98" r="3.4" fill="'.$ink.'"/><circle cx="111" cy="98" r="3.4" fill="'.$ink.'"/>'
                .'<path d="M76 106 Q100 100 124 106 L120 122 Q100 128 80 122 Z" fill="#BFE8DA" stroke="'.$ink.'" stroke-width="3" stroke-linejoin="round"/>';
        } else {
            $o .= '<path d="M68 96 Q66 60 100 60 Q134 60 132 94 Q120 76 100 78 Q82 78 68 96 Z" fill="#5A3B27" stroke="'.$ink.'" stroke-width="4" stroke-linejoin="round"/>'
                .'<circle cx="89" cy="99" r="3.4" fill="'.$ink.'"/><circle cx="111" cy="99" r="3.4" fill="'.$ink.'"/>'
                .'<circle cx="82" cy="109" r="4" fill="#F4A3B4" opacity=".7"/><circle cx="118" cy="109" r="4" fill="#F4A3B4" opacity=".7"/>'
                .'<path d="M90 111 Q100 119 110 111" stroke="'.$ink.'" stroke-width="3" fill="none" stroke-linecap="round"/>';
        }
        if ($hand) {
            [$hx, $hy] = $hand;
            $mx = (138 + $hx) / 2;
            $my = max(158, $hy) + 22;
            $path = "M138 160 Q{$mx} {$my} {$hx} {$hy}";
            $o .= '<g class="vs-arm"><path d="'.$path.'" stroke="'.$ink.'" stroke-width="22" fill="none" stroke-linecap="round"/>'
                .'<path d="'.$path.'" stroke="#fff" stroke-width="14" fill="none" stroke-linecap="round"/>'
                .'<circle cx="'.$hx.'" cy="'.$hy.'" r="9.5" fill="'.$skin.'" stroke="'.$ink.'" stroke-width="3"/></g>';
        }

        return $o.'</g>';
    }

    private static function microscope(): string
    {
        $ink = self::INK;

        return '<g class="vs-scope"><rect x="168" y="196" width="64" height="12" rx="4" fill="#8E9CC2" stroke="'.$ink.'" stroke-width="3"/>'
            .'<path d="M214 196 V150 Q214 132 196 128" stroke="'.$ink.'" stroke-width="10" fill="none" stroke-linecap="round"/>'
            .'<path d="M214 196 V150 Q214 132 196 128" stroke="#C9D3E6" stroke-width="5" fill="none" stroke-linecap="round"/>'
            .'<rect x="174" y="166" width="40" height="7" rx="3" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/>'
            .'<g transform="rotate(-24 192 140)"><rect x="184" y="112" width="16" height="48" rx="4" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><rect x="186" y="100" width="12" height="14" rx="3" fill="'.$ink.'"/></g>'
            .'<circle cx="190" cy="168" r="5" fill="#FFC21F" class="vs-glow"/></g>';
    }

    private static function heart(float $x, float $y, float $s, string $class = 'vs-beat', string $fill = '#E6177A'): string
    {
        return '<path class="'.$class.'" transform="translate('.$x.' '.$y.') scale('.$s.')" d="M0 6 C0 -2 10 -2 10 6 C10 -2 20 -2 20 6 C20 14 10 18 10 22 C10 18 0 14 0 6 Z" fill="'.$fill.'" stroke="'.self::INK.'" stroke-width="2"/>';
    }

    private static function calendar(string $mark): string
    {
        $ink = self::INK;

        return '<g><rect x="158" y="18" width="78" height="66" rx="8" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><rect x="158" y="18" width="78" height="16" rx="6" fill="#E6177A" stroke="'.$ink.'" stroke-width="3"/>'
            .'<g fill="#C9D3E6">'.implode('', array_map(fn ($i) => '<rect x="'.(165 + ($i % 5) * 13).'" y="'.(40 + intdiv($i, 5) * 13).'" width="9" height="9" rx="2"/>', range(0, 14))).'</g>'
            .$mark.'</g>';
    }

    /** @return array<string, array<string, mixed>> */
    private static function scenes(): array
    {
        $ink = self::INK;

        return [
            'ecografie' => [
                'patient' => 'dog', 'label' => 'Medicul îi face lui Hiko o ecografie', 'caption' => 'Nu doare deloc. Doar gelul e cam rece!',
                'hand' => [262, 212],
                'extras' => '<rect x="292" y="20" width="96" height="70" rx="10" fill="'.$ink.'"/><path d="M340 30 L316 80 Q340 90 364 80 Z" fill="#3B3355"/>'
                    .'<ellipse class="vs-blob" cx="340" cy="66" rx="9" ry="6" fill="#B7B0D4"/><path d="M322 74 q18 6 36 0" stroke="#8B84A8" stroke-width="2" fill="none"/>'
                    .'<rect x="334" y="90" width="12" height="12" fill="#8E9CC2" stroke="'.$ink.'" stroke-width="2"/>',
                'tool' => '<path d="M272 222 Q372 246 384 160 Q390 104 346 100" stroke="'.$ink.'" stroke-width="3" fill="none"/>'
                    .'<g class="vs-slide"><g transform="translate(268 212) rotate(-28)"><rect x="-7" y="-26" width="14" height="32" rx="7" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/><rect x="-9" y="4" width="18" height="8" rx="3" fill="'.$ink.'"/></g></g>'
                    .'<circle cx="290" cy="214" r="3" fill="#9FD5FF"/><circle cx="252" cy="216" r="2.5" fill="#9FD5FF"/>',
            ],
            'radiografie' => [
                'patient' => 'dog', 'doctor' => 'apron', 'label' => 'Medicul îi face lui Hiko o radiografie', 'caption' => 'Stai nemișcat… flash! Uite-mi oasele!',
                'hand' => [176, 186],
                'extras' => '<rect x="162" y="18" width="84" height="62" rx="8" fill="'.$ink.'"/>'
                    .'<g class="vs-xray" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"><circle cx="204" cy="40" r="13"/><path d="M196 52 v20 M212 52 v20 M190 60 h28 M192 66 h24 M204 53 v19"/></g>'
                    .'<path d="M300 0 V30" stroke="'.$ink.'" stroke-width="8"/><rect x="272" y="28" width="56" height="28" rx="8" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="4"/>'
                    .'<path class="vs-flash" d="M282 56 L318 56 L346 206 L254 206 Z" fill="#FFF3B0" opacity=".7"/>',
                'tool' => '<rect x="168" y="176" width="16" height="22" rx="4" fill="#FF6A1A" stroke="'.$ink.'" stroke-width="3"/><circle cx="176" cy="183" r="3" fill="#fff"/>',
            ],
            'medicina-interna' => [
                'patient' => 'dog', 'label' => 'Medicul îl ascultă pe Hiko cu stetoscopul', 'caption' => 'Mi-a ascultat inima. Bate pentru recompense!',
                'hand' => [256, 208],
                'tool' => '<path d="M100 190 Q180 250 262 212" stroke="'.$ink.'" stroke-width="3" fill="none"/><circle cx="266" cy="210" r="9" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/>',
                'front' => self::heart(330, 104, 1.2).self::heart(352, 80, 0.8, 'vs-beat vs-beat--late'),
            ],
            'anestezie-inhalatorie-si-analgezie' => [
                'patient' => 'dog', 'asleep' => true, 'label' => 'Hiko doarme liniștit sub anestezie, cu masca pe bot', 'caption' => 'Zzz… mă trezesc când e totul gata.',
                'hand' => [262, 168],
                'extras' => '<rect x="360" y="110" width="34" height="98" rx="6" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="4"/><circle cx="377" cy="132" r="8" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><rect x="368" y="150" width="18" height="30" rx="4" fill="#21B073" stroke="'.$ink.'" stroke-width="3" class="vs-glow"/>',
                'tool' => '<path d="M296 176 Q340 190 362 170" stroke="'.$ink.'" stroke-width="5" fill="none"/><path d="M296 176 Q340 190 362 170" stroke="#9FD5FF" stroke-width="2" fill="none"/>'
                    .'<ellipse cx="282" cy="174" rx="20" ry="15" fill="#DFF3FF" fill-opacity=".55" stroke="'.$ink.'" stroke-width="3"/>',
            ],
            'terapie-intensiva' => [
                'patient' => 'dog', 'label' => 'Hiko la terapie intensivă, cu perfuzie și monitor', 'caption' => 'Aici cineva mă veghează zi și noapte.',
                'hand' => [196, 186],
                'extras' => '<rect x="158" y="20" width="96" height="58" rx="8" fill="'.$ink.'"/><path class="vs-ecg" d="M164 52 h18 l6 -16 l8 30 l6 -14 h14 l5 -8 l5 8 h22" stroke="#4AE3A6" stroke-width="3" fill="none" stroke-linejoin="round"/>'
                    .'<path d="M378 26 V250" stroke="'.$ink.'" stroke-width="4"/><path d="M362 26 h30" stroke="'.$ink.'" stroke-width="4"/>'
                    .'<rect x="364" y="30" width="28" height="40" rx="8" fill="#CFE6FF" stroke="'.$ink.'" stroke-width="3"/><circle class="vs-drop" cx="378" cy="80" r="3" fill="#9FD5FF"/>'
                    .'<path d="M378 72 V96 Q378 190 322 206" stroke="'.$ink.'" stroke-width="2.5" fill="none"/>',
                'tool' => '<rect x="182" y="170" width="28" height="34" rx="3" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><path d="M187 180 h18 M187 187 h18 M187 194 h12" stroke="'.$ink.'" stroke-width="2"/>',
            ],
            'consultatii-la-domiciliu' => [
                'patient' => 'cat', 'room' => 'home', 'lift' => 22, 'label' => 'Medicul o vizitează pe Raza acasă, pe canapea', 'caption' => 'Doctorul vine la mine. Nici nu cobor de pe canapea!',
                'hand' => [190, 238],
                'tool' => '<rect x="170" y="236" width="54" height="40" rx="8" fill="#1F1638"/><rect x="186" y="228" width="22" height="10" rx="4" fill="none" stroke="'.$ink.'" stroke-width="4"/>'
                    .'<path d="M197 246 v20 M187 256 h20" stroke="#fff" stroke-width="5" stroke-linecap="round"/>',
            ],
            'chirurgie-tesuturi-moi' => [
                'patient' => 'dog', 'asleep' => true, 'doctor' => 'surgery', 'label' => 'Echipa o operează pe Hiko, care doarme sub anestezie', 'caption' => 'Zzz… Echipa lucrează, eu visez oase.',
                'hand' => [250, 204],
                'extras' => '<path d="M290 0 V22" stroke="'.$ink.'" stroke-width="6"/><path d="M254 40 Q290 6 326 40 Z" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="4"/>'
                    .'<path class="vs-glow" d="M262 42 L318 42 L344 110 L236 110 Z" fill="#FFF3B0" opacity=".55"/>',
                'front' => '<rect x="214" y="196" width="134" height="20" rx="8" fill="#13958A" stroke="'.$ink.'" stroke-width="3"/>',
                'tool' => '<rect x="244" y="194" width="16" height="12" rx="2" fill="#fff" stroke="'.$ink.'" stroke-width="2.5"/>',
            ],
            'servicii-ortopedie' => [
                'patient' => 'dog', 'label' => 'Medicul îi pune lui Hiko un bandaj la lăbuță', 'caption' => 'Lăbuța mea are ghips albastru. Arată bine, nu?',
                'hand' => [292, 190],
                'front' => '<rect x="296" y="196" width="42" height="24" rx="10" fill="#1E3BA8" stroke="'.$ink.'" stroke-width="3"/><path d="M304 202 l6 10 M316 200 l6 12 M328 202 l5 10" stroke="#fff" stroke-width="2" opacity=".6"/>'
                    .'<g class="vs-sparkle"><path d="M350 186 l3 -8 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 z" fill="#FFC21F" stroke="'.$ink.'" stroke-width="1.5"/></g>',
                'tool' => '<rect x="280" y="180" width="22" height="18" rx="6" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><path d="M302 190 Q306 196 300 200" stroke="#fff" stroke-width="5" fill="none"/>',
            ],
            'dermatologie' => [
                'patient' => 'cat', 'label' => 'Medicul se uită cu lupa la blana Razei', 'caption' => 'Se uită la fiecare fir de blană. Sunt impecabilă, evident.',
                'hand' => [238, 186],
                'tool' => '<g class="vs-wander"><path d="M240 186 L258 170" stroke="'.$ink.'" stroke-width="7" stroke-linecap="round"/><circle cx="272" cy="160" r="18" fill="#DFF3FF" fill-opacity=".6" stroke="'.$ink.'" stroke-width="4"/><path d="M262 152 q6 -6 12 -6" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/></g>',
                'front' => '<g class="vs-sparkle"><path d="M316 150 l2 -6 l2 6 l6 2 l-6 2 l-2 6 l-2 -6 l-6 -2 z" fill="#FFC21F"/></g>',
            ],
            'nutritie' => [
                'patient' => 'cat', 'lift' => 14, 'label' => 'Raza stă pe cântar, iar medicul îi face un plan de hrană', 'caption' => '4,2 kg de eleganță. Și un plan de masă nou.',
                'hand' => [182, 172],
                'extras' => '<path d="M158 196 q22 16 44 0 z" fill="#FF6A1A" stroke="'.$ink.'" stroke-width="3"/><g fill="#5A3B27"><circle cx="170" cy="194" r="3"/><circle cx="180" cy="192" r="3"/><circle cx="190" cy="194" r="3"/></g>',
                'front' => '<rect x="226" y="194" width="108" height="14" rx="5" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/><rect x="258" y="196" width="44" height="10" rx="2" fill="'.$ink.'"/>'
                    .'<text class="vs-blink" x="280" y="205" text-anchor="middle" font-family="Arial, sans-serif" font-size="9" font-weight="700" fill="#4AE3A6">4,2 kg</text>',
                'tool' => '<rect x="166" y="150" width="30" height="38" rx="3" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><path d="M171 162 h20 M171 169 h20 M171 176 h14" stroke="'.$ink.'" stroke-width="2"/>'.self::heart(184, 146, 0.45, 'vs-beat'),
            ],
            'oftamologie' => [
                'patient' => 'cat', 'label' => 'Medicul se uită în ochii Razei cu oftalmoscopul', 'caption' => 'Lumina e puternică, dar clipesc ca o vedetă.',
                'hand' => [232, 140],
                'extras' => '<rect x="160" y="16" width="70" height="80" rx="6" fill="#fff" stroke="'.$ink.'" stroke-width="3"/>'
                    .'<g fill="'.$ink.'"><path d="M178 28 q8 -6 16 0 q-8 6 -16 0 z M194 28 l6 -5 v10 z"/><circle cx="186" cy="48" r="5"/><circle cx="202" cy="48" r="5"/><circle cx="182" cy="64" r="3.5"/><circle cx="194" cy="64" r="3.5"/><circle cx="206" cy="64" r="3.5"/><circle cx="180" cy="78" r="2"/><circle cx="188" cy="78" r="2"/><circle cx="196" cy="78" r="2"/><circle cx="204" cy="78" r="2"/><circle cx="212" cy="78" r="2"/></g>',
                'tool' => '<rect x="226" y="132" width="12" height="30" rx="5" fill="'.$ink.'"/><circle cx="240" cy="132" r="8" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/>'
                    .'<path class="vs-beam" d="M246 128 L264 124 L264 136 L246 136 Z" fill="#FFE17A" opacity=".8"/>',
            ],
            'neurologie' => [
                'patient' => 'dog', 'label' => 'Medicul testează reflexele lui Hiko cu ciocănelul', 'caption' => 'Tac! Lăbuța mea răspunde perfect.',
                'hand' => [282, 176],
                'tool' => '<g class="vs-tap"><path d="M282 176 L306 190" stroke="'.$ink.'" stroke-width="5" stroke-linecap="round"/><path d="M298 196 L314 176 L320 200 Z" fill="#E6177A" stroke="'.$ink.'" stroke-width="3" stroke-linejoin="round"/></g>'
                    .'<text class="vs-pop" x="336" y="182" font-family="Arial Black, sans-serif" font-size="16" font-weight="900" fill="#1E3BA8">tac!</text>',
            ],
            'detartraj' => [
                'patient' => 'dog', 'label' => 'Medicul îi curăță lui Hiko dinții', 'caption' => 'Dinți curați, zâmbet de reclamă!',
                'hand' => [252, 182],
                'tool' => '<g class="vs-brush"><path d="M252 182 L276 172" stroke="#13958A" stroke-width="7" stroke-linecap="round"/><rect x="272" y="164" width="14" height="9" rx="2" fill="#fff" stroke="'.$ink.'" stroke-width="2.5"/></g>',
                'front' => '<g class="vs-sparkle"><path d="M306 160 l3 -8 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 z" fill="#FFC21F" stroke="'.$ink.'" stroke-width="1.5"/><path d="M324 178 l2 -5 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 z" fill="#fff" stroke="'.$ink.'" stroke-width="1.5"/></g>',
            ],
            'vaccinare' => [
                'patient' => 'dog', 'label' => 'Medicul îl vaccinează pe Hiko', 'caption' => 'Am fost cuminte. Primesc steluța?',
                'hand' => [208, 206],
                'tool' => '<g transform="rotate(-14 210 204)"><rect x="210" y="198" width="34" height="12" rx="3" fill="#E6F4FF" stroke="'.$ink.'" stroke-width="3"/><rect x="212" y="200" width="16" height="8" fill="#9FD5FF"/><path d="M244 204 h11" stroke="'.$ink.'" stroke-width="2"/><path d="M204 200 v8 M198 204 h6" stroke="'.$ink.'" stroke-width="3"/></g>',
                'front' => '<g class="vs-pop"><path d="M344 70 l7 15 l16 2 l-12 11 l3 16 l-14 -8 l-14 8 l3 -16 l-12 -11 l16 -2 z" fill="#FFC21F" stroke="'.$ink.'" stroke-width="3" stroke-linejoin="round"/></g>',
            ],
            'deparazitare-interna' => [
                'patient' => 'dog', 'label' => 'Medicul îi dă lui Hiko pastila ascunsă într-o recompensă', 'caption' => 'Pastila era în recompensă. Nici n-am simțit!',
                'hand' => [244, 192],
                'tool' => '<g class="vs-bounce"><path d="M250 184 h22 a6 6 0 1 1 0 10 a6 6 0 1 1 0 10 h-22 a6 6 0 1 1 0 -10 a6 6 0 1 1 0 -10 z" fill="#FF6A1A" stroke="'.$ink.'" stroke-width="3"/><rect x="256" y="191" width="12" height="6" rx="3" fill="#fff" stroke="'.$ink.'" stroke-width="1.5"/></g>',
            ],
            'deparazitare-externa' => [
                'patient' => 'cat', 'label' => 'Medicul îi pune Razei o pipetă antiparazitară pe ceafă', 'caption' => 'Puricii? S-au mutat. Fără adresă nouă.',
                'hand' => [236, 150],
                'tool' => '<g transform="rotate(30 244 152)"><rect x="236" y="138" width="12" height="22" rx="4" fill="#21B073" stroke="'.$ink.'" stroke-width="3"/><path d="M242 160 v8" stroke="'.$ink.'" stroke-width="3"/></g><circle class="vs-drop" cx="250" cy="168" r="3" fill="#21B073"/>',
                'front' => '<g class="vs-flee" fill="'.$ink.'"><circle cx="330" cy="160" r="3"/><circle cx="344" cy="176" r="2.5"/><circle cx="320" cy="186" r="2.5"/></g>',
            ],
            'microcipare' => [
                'patient' => 'dog', 'label' => 'Medicul scanează microcipul lui Hiko', 'caption' => 'Bip! Acum toată lumea știe că sunt Hiko.',
                'hand' => [222, 196],
                'tool' => '<rect x="208" y="166" width="34" height="46" rx="8" fill="#FF6A1A" stroke="'.$ink.'" stroke-width="3"/><rect x="214" y="172" width="22" height="14" rx="2" fill="'.$ink.'"/>'
                    .'<text x="225" y="183" text-anchor="middle" font-family="Arial, sans-serif" font-size="8" font-weight="700" fill="#4AE3A6">ID ✓</text>'
                    .'<g class="vs-wave" fill="none" stroke="#1E3BA8" stroke-width="3" stroke-linecap="round"><path d="M248 174 q6 8 0 16"/><path d="M256 168 q10 14 0 28"/></g>',
            ],
            'pasaport-calatorie' => [
                'patient' => 'dog', 'label' => 'Medicul îi arată lui Hiko pașaportul lui de călătorie', 'caption' => 'Am pașaport! Unde mergem în vacanță?',
                'hand' => [218, 168],
                'extras' => '<rect x="160" y="18" width="92" height="62" rx="8" fill="#BDE3FF" stroke="'.$ink.'" stroke-width="3"/>'
                    .'<g class="vs-fly"><path d="M170 50 l20 -2 l10 -12 l6 0 l-5 13 l16 -1 l5 -6 l4 0 l-2 8 l2 8 l-4 0 l-5 -6 l-16 -1 l5 13 l-6 0 l-10 -12 l-20 -2 z" fill="#fff" stroke="'.$ink.'" stroke-width="2"/></g>'
                    .'<rect x="166" y="232" width="34" height="26" rx="5" fill="#FFC21F" stroke="'.$ink.'" stroke-width="3"/><path d="M176 232 v-6 h14 v6" fill="none" stroke="'.$ink.'" stroke-width="3"/><circle cx="183" cy="245" r="4" fill="#E6177A"/>',
                'tool' => '<g transform="rotate(-8 228 160)"><rect x="214" y="142" width="30" height="40" rx="3" fill="#1E3BA8" stroke="'.$ink.'" stroke-width="3"/><circle cx="229" cy="158" r="7" fill="none" stroke="#FFC21F" stroke-width="2"/><path d="M220 172 h18" stroke="#FFC21F" stroke-width="2"/></g>',
            ],
            'analize-de-laborator' => [
                'patient' => 'cat', 'label' => 'Medicul se uită la eprubeta cu analizele Razei', 'caption' => 'O înțepătură mică și gata. Rezultatele vin repede!',
                'hand' => [196, 150],
                'extras' => '<rect x="156" y="188" width="44" height="20" rx="3" fill="#C9D3E6" stroke="'.$ink.'" stroke-width="3"/>'
                    .'<g stroke="'.$ink.'" stroke-width="2.5"><rect x="161" y="168" width="8" height="26" rx="4" fill="#FFC21F"/><rect x="174" y="166" width="8" height="28" rx="4" fill="#9FD5FF"/><rect x="187" y="170" width="8" height="24" rx="4" fill="#21B073"/></g>',
                'front' => '<rect x="254" y="206" width="20" height="9" rx="4" fill="#FF6A1A" stroke="'.$ink.'" stroke-width="2"/>',
                'tool' => '<g class="vs-shake"><rect x="200" y="124" width="10" height="36" rx="5" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><rect x="202" y="140" width="6" height="18" rx="3" fill="#E11D2E"/></g>',
            ],
            'testarea-progesteronului' => [
                'patient' => 'cat', 'label' => 'Laboratorul clinicii, cu microscop și calendar; Raza asistă', 'caption' => 'Laboratorul calculează momentul potrivit.',
                'hand' => [206, 176],
                'extras' => self::calendar(self::heart(202, 52, 0.5)).self::microscope(),
            ],
            'citologie-vaginala' => [
                'patient' => 'cat', 'label' => 'Medicul se uită la microscop în laborator; Raza asistă', 'caption' => 'La microscop vedem ce nu se vede cu ochiul liber.',
                'hand' => [206, 176],
                'extras' => '<rect x="160" y="20" width="76" height="56" rx="8" fill="#fff" stroke="'.$ink.'" stroke-width="3"/><circle cx="198" cy="48" r="20" fill="#F3E8FF" stroke="'.$ink.'" stroke-width="3"/>'
                    .'<g fill="#B48CE8" class="vs-blob"><circle cx="190" cy="44" r="4"/><circle cx="204" cy="40" r="3"/><circle cx="200" cy="54" r="5"/></g>'.self::microscope(),
            ],
            'inseminare-artificiala' => [
                'patient' => 'cat', 'label' => 'Laboratorul clinicii pregătește procedura; Raza asistă', 'caption' => 'Totul se pregătește cu grijă, la momentul potrivit.',
                'hand' => [206, 176],
                'extras' => self::calendar(self::heart(176, 52, 0.45).self::heart(202, 65, 0.45)).self::microscope(),
                'front' => self::heart(344, 92, 0.9, 'vs-float'),
            ],
            'serviciu-de-ambulanta' => [
                'patient' => 'none', 'room' => 'road', 'label' => 'Hiko în ambulanța veterinară, cu girofarul pornit', 'caption' => 'Nino-nino! Venim noi la tine.',
                'extras' => '<g class="vs-drive">'
                    .'<path d="M60 230 V128 Q60 112 76 112 H250 V230 Z" fill="#fff" stroke="'.$ink.'" stroke-width="5" stroke-linejoin="round"/>'
                    .'<path d="M250 230 V140 H300 Q316 140 324 156 L340 190 V230 Z" fill="#fff" stroke="'.$ink.'" stroke-width="5" stroke-linejoin="round"/>'
                    .'<path d="M262 152 H298 Q308 152 312 160 L324 186 H262 Z" fill="#BDE3FF" stroke="'.$ink.'" stroke-width="4"/>'
                    .'<rect x="60" y="184" width="280" height="16" fill="#21B073" stroke="'.$ink.'" stroke-width="4"/>'
                    .'<circle cx="150" cy="150" r="22" fill="#fff" stroke="#E11D2E" stroke-width="4"/><path d="M150 136 v28 M136 150 h28" stroke="#E11D2E" stroke-width="8" stroke-linecap="round"/>'
                    .'<rect x="140" y="98" width="30" height="16" rx="6" class="vs-siren" fill="#1E3BA8" stroke="'.$ink.'" stroke-width="3"/>'
                    .'<g class="vs-wheel"><circle cx="112" cy="232" r="22" fill="'.$ink.'"/><circle cx="112" cy="232" r="9" fill="#C9D3E6"/><path d="M112 214 v36 M94 232 h36" stroke="#4B4560" stroke-width="3"/></g>'
                    .'<g class="vs-wheel"><circle cx="290" cy="232" r="22" fill="'.$ink.'"/><circle cx="290" cy="232" r="9" fill="#C9D3E6"/><path d="M290 214 v36 M272 232 h36" stroke="#4B4560" stroke-width="3"/></g>'
                    .'</g><g class="vs-speed" stroke="'.$ink.'" stroke-width="4" stroke-linecap="round"><path d="M20 150 h26 M12 176 h34 M24 202 h22"/></g>',
                'front' => '<g class="vs-drive">'.preg_replace('/<svg class="mascot ([^"]+)"/', '<svg class="mascot $1 vs-patient" x="258" y="116" width="62" height="60"', preg_replace('/\{\{--.*?--\}\}/s', '', view('partials.mascot-dog')->render()), 1).'</g>',
            ],
        ];
    }
}
