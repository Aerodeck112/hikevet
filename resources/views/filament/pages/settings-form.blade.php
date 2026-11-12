<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <div style="margin-top:1.5rem">
            <x-filament::button type="submit">Salvează</x-filament::button>
        </div>
    </form>
    @isset($extra){!! $extra !!}@endisset
</x-filament-panels::page>
