<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;

class RegisterTestController extends Controller
{
    public function store(Request $request)
    {
        $user = DB::table('users')->where('email', $request['email'])->first();
        if ($user) {
            $messageCreationCompteOk = "Identifiants incorects";
        } else {
            $user = new User();
        
            $user->name = $request['name'];
            $user->email = $request['email'];
            $user->password = $request['password'];
            $user->save();

            $messageCreationCompteOk = "Votre compte a bien été créé";
        } 
        // Renvoyer la vue avec le message de succès
        return view('register', compact('messageCreationCompteOk'));
    }
}
