@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto text-center">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">QRコード</h1>
    <p class="text-gray-500 mb-6">{{ $user->name }}</p>

    <div class="bg-white rounded-lg shadow p-8 mb-6">
        <canvas id="qrcode" class="mx-auto"></canvas>
        <p class="text-xs text-gray-400 mt-4">タブレットのカメラにかざしてください</p>
    </div>

    <form method="POST" action="{{ route('qr.regenerate') }}">
        @csrf
        <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 underline"
                onclick="return confirm('QRコードを再発行しますか？\n現在のQRコードは無効になります。')">
            QRコードを再発行
        </button>
    </form>

    <div class="mt-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; ダッシュボードに戻る</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    QRCode.toCanvas(document.getElementById('qrcode'), @json($qrUrl), {
        width: 280,
        margin: 2,
        color: {
            dark: '#000000',
            light: '#ffffff',
        }
    });
});
</script>
@endsection
