@php
    $name = $field['name'];
    $value = $row[$name] ?? '';
    $value = is_float($value) ? rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') : (string) $value;
    $options = \App\Services\Campaign\CaseForm::options($field['options']);
    $id = 'f-'.$name;
    $isFocus = ($focusField ?? null) === $name;
@endphp
<div class="hv-cp-field {{ $field['full'] || $field['type'] === 'textarea' ? 'is-full' : '' }} {{ $isFocus ? 'is-focus' : '' }}" @if ($isFocus) id="hv-focus" @endif>
    @if ($field['type'] === 'radio')
        <span class="hv-cp-label">{{ $field['label'] }}{{ $field['required'] ? ' *' : '' }}</span>
        <div class="hv-cp-radio">
            @foreach ($options as $option)
                <label><input type="radio" name="data[{{ $name }}]" value="{{ $option }}" @checked($value === $option) @disabled(! $canEdit)> {{ $option }}</label>
            @endforeach
        </div>
    @elseif ($field['type'] === 'file_photo' || $field['type'] === 'file_ci')
        @php $doc = $field['type'] === 'file_photo' ? ($photo ?? null) : ($idCard ?? null); $input = $field['type'] === 'file_photo' ? 'poza_pacient_file' : 'documente_scanate_file'; @endphp
        <label for="{{ $id }}">{{ $field['label'] }}</label>
        @if ($canEdit)
            <input id="{{ $id }}" type="file" name="{{ $input }}" accept="{{ $field['type'] === 'file_photo' ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,application/pdf' }}">
        @endif
        <div data-photo-live>
            @if ($doc && ! $doc->is_missing)
                @if ($doc->isImage())
                    <a href="{{ route('admin.campaign.document', $doc) }}" target="_blank"><img class="hv-cp-photo" src="{{ route('admin.campaign.document', $doc) }}" alt="{{ $field['type'] === 'file_photo' ? 'Poza pacient' : 'Act de identitate' }}"></a>
                @else
                    <a class="hv-cp-btn" href="{{ route('admin.campaign.document', $doc) }}" target="_blank">Deschide documentul actual</a>
                @endif
                <p class="hv-muted" style="font-size:.8rem">Încărcat {{ $doc->created_at?->format('d.m.Y H:i') }} · sursa: {{ $doc->source ?: '-' }} · SHA-256 {{ substr((string) $doc->sha256, 0, 12) }}…</p>
            @elseif ($value !== '')
                <p class="hv-cp-flash hv-cp-flash--warn">Fișierul figurează în dosar, dar nu există în copia preluată din WordPress („Lipsă în sursă”).</p>
            @else
                <p class="hv-muted" style="font-size:.85rem">Nu există încă un fișier încărcat.</p>
            @endif
            @if ($canEdit && $value !== '')
                <label style="font-size:.8rem;color:#991b1b"><input type="checkbox" name="{{ $field['type'] === 'file_photo' ? 'remove_poza_pacient' : 'remove_documente_scanate' }}" value="1"> Scoate fișierul actual fără înlocuire (rămâne în istoricul dosarului)</label>
            @endif
        </div>
    @else
        <label for="{{ $id }}">{{ $field['label'] }}{{ $field['required'] ? ' *' : '' }}</label>
        @if ($field['type'] === 'select')
            <select id="{{ $id }}" name="data[{{ $name }}]" @disabled(! $canEdit || $field['readonly']) @required($field['required'])>
                <option value="">Selectează</option>
                @foreach ($options as $option)
                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                @endforeach
                @if ($value !== '' && ! in_array($value, $options, true))
                    <option value="{{ $value }}" selected>{{ $value }} (valoare existentă)</option>
                @endif
            </select>
        @elseif ($field['type'] === 'textarea')
            <textarea id="{{ $id }}" name="data[{{ $name }}]" @readonly(! $canEdit || $field['readonly']) @required($field['required'])>{{ $value }}</textarea>
        @else
            <input id="{{ $id }}" type="{{ $field['type'] }}" name="data[{{ $name }}]" value="{{ $value }}" @readonly(! $canEdit || $field['readonly']) @required($field['required'] && ! $field['readonly'])>
        @endif
    @endif
</div>
