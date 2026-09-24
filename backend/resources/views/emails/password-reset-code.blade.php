@extends('emails.layouts.simple')

@section('content')
    <p style="margin:0 0 16px;">Bonjour,</p>

    <p style="margin:0 0 16px;">
        Vous avez demandé à changer le mot de passe de votre compte Make Cars. Saisissez le code
        ci-dessous sur la page « Mot de passe oublié » :
    </p>

    <p style="margin:0 0 16px; text-align:center; font-size:32px; font-weight:bold; letter-spacing:8px; color:#111827;">
        {{ $code }}
    </p>

    <p style="margin:0 0 16px;">Ce code est valable {{ $validityMinutes }} minutes.</p>

    <p style="margin:0; color:#6b7280; font-size:12px;">
        Ne communiquez ce code à personne. Si vous n'êtes pas à l'origine de cette demande, ignorez
        cet email : votre mot de passe actuel reste valable.
    </p>
@endsection
