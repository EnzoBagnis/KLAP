<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
</head>
<body>
    <h1>Page de connexion</h1>
    <h2>Se connecter</h2>

    @if (isset($messageAccueil))
        <p>{{ $messageAccueil }}</p>
    @endif

    <form action="{{ url('/connexion') }}" method="post">
        @csrf
        <label for="email">email : </label>
        <input type="text" name="email" placeholder="jean@gmail.com"> <br><br>
 
        <label for="password">password : </label>
        <input type="password" name="password" placeholder="*****"> <br><br>
        
        <button type="submit">Se connecter</button> 
    </form>
    <a href='/register'>Créer un compte<a>
</body>
</html>