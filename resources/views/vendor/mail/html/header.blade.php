@props(['url'])

<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            {{--
                El slot (o el logo por defecto de Laravel) se reemplaza por tu logo personalizado.
                El valor de $url lo toma de la variable APP_URL en tu .env
            --}}
            <img src="{{ asset('images/logo_ceimic.png') }}" class="logo" alt="{{ config('app.name', 'Laravel') }} Logo"
                style="max-width: 220px; height: auto; border: none;">
        </a>
    </td>
</tr>
