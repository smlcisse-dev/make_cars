@extends('emails.layouts.simple')

@section('content')
    <p style="margin:0 0 16px;">Bonjour {{ $user->name }},</p>

    <p style="margin:0 0 24px;">
        Votre compte a été validé, vous pouvez vous connecter à votre espace professionnel.
    </p>

    @include('emails.partials.button', ['url' => $loginUrl, 'label' => 'Me connecter'])

    <p style="margin:0; color:#6b7280; font-size:12px;">
        Votre profil est désormais visible des automobilistes sur l'application Make Cars.
    </p>
@endsection
