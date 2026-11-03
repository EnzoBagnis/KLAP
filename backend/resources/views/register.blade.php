<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h1>Page de création de compte</h1>
    <h2>S'inscrire</h2>

    @if (isset($messageCreationCompteOk))
        <p>{{ $messageCreationCompteOk }}</p>
    @endif 

    <form action="{{ url('user/register') }}" method="post">
        @csrf
        <label for="name">nom : </label>
        <input type="text" name="name" placeholder="jean" required> <br><br>

        <label for="email">email : </label>
        <input type="email" name="email" placeholder="jean@gmail.com" required> <br><br>
 
        <label for="password">password : </label>
        <input type="password" name="password" placeholder="*****" required> <br><br>
        
        <button type="submit">S'inscrire</button> 
    </form>

    <a href='/connexion'>Se connecter</a>
</body>
</html>