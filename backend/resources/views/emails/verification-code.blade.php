@extends('emails.layouts.simple')

@section('content')
    <p style="margin:0 0 16px;">Bonjour,</p>

    <p style="margin:0 0 16px;">
        Pour finaliser la création de votre compte professionnel Make Cars, saisissez le code
        ci-dessous sur la page d'inscription :
    </p>

    <p style="margin:0 0 16px; text-align:center; font-size:32px; font-weight:bold; letter-spacing:8px; color:#111827;">
        {{ $code }}
    </p>

    <p style="margin:0 0 16px;">Ce code est valable {{ $validityMinutes }} minutes.</p>

    <p style="margin:0; color:#6b7280; font-size:12px;">
        Ne communiquez ce code à personne. Si vous n'êtes pas à l'origine de cette demande, vous pouvez
        ignorer cet email : aucun compte ne sera créé.
    </p>
@endsection
