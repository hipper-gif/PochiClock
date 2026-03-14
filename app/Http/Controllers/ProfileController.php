<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $user->load('department');
        return view('dashboard.profile', compact('user'));
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:1|max:255',
        ]);

        Auth::user()->update(['name' => $request->name]);

        return back()->with('success', '名前を更新しました');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => '現在のパスワードが正しくありません']);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return back()->withErrors(['new_password' => '新しいパスワードは現在と異なるものにしてください']);
        }

        $user->update(['password' => $request->new_password]);

        return back()->with('success', 'パスワードを変更しました');
    }
}
