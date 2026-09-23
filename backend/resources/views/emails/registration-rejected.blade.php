@extends('emails.layouts.simple')

@section('content')
    <p style="margin:0 0 16px;">Bonjour {{ $user->name }},</p>

    <p style="margin:0 0 16px;">
        Après examen, votre dossier d'inscription n'a pas pu être validé pour le motif suivant :
    </p>

    <p style="margin:0 0 16px; padding:12px 16px; background:#f9fafb; border-left:4px solid #dc2626; white-space:pre-line;">{{ $reason }}</p>

    <p style="margin:0 0 24px;">
        Vous pouvez corriger votre profil et vos informations légales, puis soumettre à nouveau votre
        dossier pour validation.
    </p>

    @include('emails.partials.button', ['url' => $profileUrl, 'label' => 'Corriger mon dossier'])
@endsection
