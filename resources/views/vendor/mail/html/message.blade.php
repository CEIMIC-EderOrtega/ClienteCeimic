<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{-- Usará el nombre de la aplicación configurado en .env (ej: CEIMIC Clink) --}}
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{-- Aquí se insertará el contenido principal del correo, que trataremos con la localización --}}
    {!! $slot !!}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {!! $subcopy !!}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. {{ __('Todos los derechos reservados.') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>

