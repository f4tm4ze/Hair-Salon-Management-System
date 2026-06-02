<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate(['password' => 'required']);

        if (Hash::check($request->password, Auth::user()->password)) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Incorrect password.'], 422);
    }
}
