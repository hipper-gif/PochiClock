@extends('layouts.kiosk')

@section('content')
<div class="text-center">
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('kiosk.department', $department) }}" class="text-gray-400 hover:text-white text-sm">PIN入力に切替</a>
        <div>
            <h1 class="text-xl font-bold">{{ $department->name }}</h1>
            <p class="text-gray-400 text-sm" id="currentTime"></p>
        </div>
        <a href="{{ route('kiosk.index') }}" class="text-gray-400 hover:text-white text-sm">部署選択</a>
    </div>

    {{-- スキャンフェーズ --}}
    <div id="scanPhase">
        <p class="text-gray-400 mb-4">QRコードをカメラにかざしてください</p>
        <div id="qr-reader" class="mx-auto rounded-xl overflow-hidden" style="max-width: 400px;"></div>
        <p id="scanError" class="text-red-400 mt-4 hidden"></p>
    </div>

    {{-- 結果フェーズ --}}
    <div id="resultPhase" class="hidden">
        <div id="resultIcon" class="text-8xl mb-4"></div>
        <p id="resultMessage" class="text-3xl font-bold mb-2"></p>
        <p id="resultTime" class="text-xl text-gray-400 mb-8"></p>
        <p class="text-gray-500 text-sm">次の方をお待ちしています...</p>
    </div>

    {{-- エラーフェーズ --}}
    <div id="errorPhase" class="hidden">
        <div class="text-8xl mb-4">&#10060;</div>
        <p id="errorMessage" class="text-2xl font-bold text-red-400 mb-8"></p>
        <button onclick="resetScanner()" class="bg-gray-700 hover:bg-gray-600 rounded-xl px-8 py-4 text-lg transition">再スキャン</button>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const verifyUrl = '{{ route("kiosk.qrVerify") }}';
const appUrl = '{{ config("app.url") }}';
let html5QrCode = null;
let isProcessing = false;

function updateClock() {
    document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('ja-JP');
}
updateClock();
setInterval(updateClock, 1000);

function startScanner() {
    html5QrCode = new Html5Qrcode('qr-reader');
    html5QrCode.start(
        { facingMode: 'environment' },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1,
        },
        onScanSuccess,
        () => {} // ignore scan failures (no QR found yet)
    ).catch(function(err) {
        document.getElementById('scanError').textContent = 'カメラを起動できません: ' + err;
        document.getElementById('scanError').classList.remove('hidden');
    });
}

function onScanSuccess(decodedText) {
    if (isProcessing) return;
    isProcessing = true;

    // Extract qr_token from URL: {APP_URL}/qr-verify/{qr_token}
    let qrToken = null;
    const prefix = appUrl + '/qr-verify/';
    if (decodedText.startsWith(prefix)) {
        qrToken = decodedText.substring(prefix.length);
    } else {
        // Try extracting from any URL with /qr-verify/
        const match = decodedText.match(/\/qr-verify\/([a-zA-Z0-9]+)/);
        if (match) {
            qrToken = match[1];
        }
    }

    if (!qrToken) {
        showError('無効なQRコードです');
        return;
    }

    // Stop scanner while processing
    if (html5QrCode) {
        html5QrCode.stop().catch(() => {});
    }

    fetch(verifyUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ qr_token: qrToken }),
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => { throw new Error(data.error || 'エラーが発生しました'); });
        }
        return res.json();
    })
    .then(data => {
        showResult(data);
    })
    .catch(err => {
        showError(err.message);
    });
}

function showResult(data) {
    document.getElementById('scanPhase').classList.add('hidden');
    document.getElementById('errorPhase').classList.add('hidden');
    document.getElementById('resultPhase').classList.remove('hidden');

    const icon = data.action === 'clock_in' ? '&#9728;&#65039;' : '&#127769;';
    document.getElementById('resultIcon').innerHTML = icon;
    document.getElementById('resultMessage').textContent = data.message;
    document.getElementById('resultTime').textContent = data.time;

    // Green for clock_in, blue for clock_out
    const phase = document.getElementById('resultPhase');
    if (data.action === 'clock_in') {
        phase.style.color = '#6ee7b7';
    } else {
        phase.style.color = '#93c5fd';
    }

    setTimeout(resetScanner, 5000);
}

function showError(message) {
    document.getElementById('scanPhase').classList.add('hidden');
    document.getElementById('resultPhase').classList.add('hidden');
    document.getElementById('errorPhase').classList.remove('hidden');
    document.getElementById('errorMessage').textContent = message;

    setTimeout(resetScanner, 5000);
}

function resetScanner() {
    isProcessing = false;
    document.getElementById('scanPhase').classList.remove('hidden');
    document.getElementById('resultPhase').classList.add('hidden');
    document.getElementById('errorPhase').classList.add('hidden');
    document.getElementById('resultPhase').style.color = '';

    startScanner();
}

document.addEventListener('DOMContentLoaded', startScanner);
</script>
@endsection
