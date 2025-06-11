<x-mail::message>
    # ¡Bienvenido a {{ config('app.name') }}, {{ $user->name }}!

    Tu cuenta ha sido creada exitosamente en nuestro portal de clientes.

    **Usuario:** {{ $user->email }}
    **Clave temporal:** {{ $$user->password }}

    Para garantizar la seguridad de tu cuenta, necesitarás establecer tu propia contraseña. Por favor, haz clic en el
    botón de abajo para ir a la página de inicio y utiliza la opción **"¿Olvidaste tu contraseña?"** para crear tu clave
    de acceso.

    <x-mail::button :url="'https://myclink.ceimic.com'">
        Ir al Portal
    </x-mail::button>

    Gracias por unirte a nosotros,<br>
    El equipo de {{ config('app.name') }}
</x-mail::message>
