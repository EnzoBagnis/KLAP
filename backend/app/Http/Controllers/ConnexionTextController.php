<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;

class ConnexionTextController extends Controller
{
    public function connexion(Request $request) {

        $user = DB::table('users')->where('email', $request['email'])->first();
        
        if ($user){
            $password = $user->password;
            if (Hash::check($request['password'], $user->password)) {
            $messageAccueil = 'Bonjour ' . $user->name . '!'; 
        } } else {
            $messageAccueil = "Identifiants incorrects"; 
        }

        
        return view('connexion', compact('messageAccueil')); 
    }
    
}
