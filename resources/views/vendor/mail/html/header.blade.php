@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            {{--
                Asegúrate de que tu logo 'logo_ceimic.png' esté en la carpeta
                'public/images/'. Laravel generará la URL pública automáticamente.
                He añadido un estilo básico para controlar el tamaño.
            --}}
            {{-- En resources/views/vendor/mail/html/header.blade.php --}}
            <a href="{{ $url }}" style="display: inline-block;">
                @if (trim($slot) === 'Laravel')
                    {{-- Logo por defecto de Laravel --}}
                    <img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
                @else
                    {{-- Tu logo personalizado --}}
                    <img src="{{ asset('images/logo_ceimic.png') }}" class="logo" alt="Logo CEIMIC"
                        style="max-width: 220px; height: auto;">
                @endif
            </a>
        </a>
    </td>

</tr>
