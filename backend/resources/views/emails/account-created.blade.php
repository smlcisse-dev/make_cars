@extends('emails.layouts.simple')

@section('content')
    <p style="margin:0 0 16px;">Bonjour {{ $user->name }},</p>

    <p style="margin:0 0 16px;">
        Votre compte a été créé. Veuillez accéder à votre profil pour le compléter entièrement.
    </p>

    <p style="margin:0 0 24px;">
        Une fois votre profil et vos informations légales renseignés, vous pourrez soumettre votre
        dossier pour validation par notre équipe.
    </p>

    @include('emails.partials.button', ['url' => $profileUrl, 'label' => 'Compléter mon profil'])

    <p style="margin:0; color:#6b7280; font-size:12px;">
        Vous devrez vous connecter avec votre email et le mot de passe choisi à l'inscription.
    </p>
@endsection
