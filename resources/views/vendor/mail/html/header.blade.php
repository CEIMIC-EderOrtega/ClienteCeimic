@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            {{--
                Asegúrate de que tu logo 'logo_ceimic.png' esté en la carpeta
                'public/images/'. Laravel generará la URL pública automáticamente.
                He añadido un estilo básico para controlar el tamaño.
            --}}
            <img src="{{ asset('images/logo_ceimic.png') }}" class="logo" alt="Logo CEIMIC" style="max-width: 180px; height: auto;">
        </a>
    </td>
</tr>

