<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QrController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        if (! $user->qr_token) {
            $user->generateQrToken();
        }

        $qrUrl = $user->getQrCodeUrl();

        return view('qr.show', compact('user', 'qrUrl'));
    }

    public function regenerate()
    {
        auth()->user()->generateQrToken();

        return back()->with('success', 'QRコードを再発行しました');
    }
}
