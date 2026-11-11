Bonjour {!! $invitation->full_name !!},

{!! $invitation->inviter->name !!} vous invite à utiliser le carnet de bord numérique de KLAP Maison pour la danse.

Pour activer votre compte et choisir votre mot de passe, ouvrez ce lien :
{!! $url !!}

Le lien est valable jusqu'au {!! $invitation->expires_at->timezone('Europe/Paris')->locale('fr')->translatedFormat('j F Y') !!}.

Si vous n'attendiez pas ce message, vous pouvez l'ignorer.
