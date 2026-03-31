@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">

    {{-- PWA インストールバナー --}}
    <div id="install-banner" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-800">ホーム画面に追加</p>
                <p class="text-xs text-indigo-600">ワンタップで打刻できるようになります</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="installPwa()" class="px-3 py-1 bg-indigo-600 text-white text-sm rounded">追加</button>
                <button onclick="dismissInstall()" class="px-3 py-1 text-indigo-600 text-sm">閉じる</button>
            </div>
        </div>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">おはようございます、{{ $user->name }} さん</h1>
        <p class="text-gray-500 mt-1">{{ now()->isoFormat('Y年M月D日（ddd）') }}</p>
    </div>

    {{-- 現在時刻 --}}
    <div class="text-center mb-6">
        <p id="current-time" class="text-5xl font-mono font-bold text-gray-800 tabular-nums">--:--:--</p>
    </div>

    {{-- 本日の全セッション一覧 --}}
    @if($todayAttendances->count() > 0)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">本日の勤怠</h2>
            @php
                $statusLabels = [
                    'not_started' => ['未出勤', 'bg-gray-100 text-gray-600'],
                    'clocked_in' => ['出勤中', 'bg-green-100 text-green-700'],
                    'on_break' => ['休憩中', 'bg-yellow-100 text-yellow-700'],
                    'clocked_out' => ['退勤済', 'bg-blue-100 text-blue-700'],
                ];
                [$label, $class] = $statusLabels[$status];
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $class }}">{{ $label }}</span>
        </div>

        @foreach($todayAttendances as $session)
            @php
                $sessionRounded = app(\App\Services\TimeService::class)->getRoundedTimes(
                    $session->clock_in,
                    $session->clock_out,
                    [
                        'rounding_unit' => $rule['rounding_unit'],
                        'clock_in_rounding' => $rule['clock_in_rounding'],
                        'clock_out_rounding' => $rule['clock_out_rounding'],
                    ]
                );
                $sessionBreakMin = app(\App\Services\TimeService::class)->calculateBreakMinutes($session->breakRecords);
                $sessionBindMin = ($session->clock_out && $sessionRounded['rounded_clock_out'])
                    ? $sessionRounded['rounded_clock_in']->diffInMinutes($sessionRounded['rounded_clock_out'])
                    : null;
                $sessionWorkMin = $sessionBindMin !== null ? max(0, $sessionBindMin - $sessionBreakMin) : null;
                $isActive = is_null($session->clock_out);
            @endphp
            <div class="mb-3 {{ $isActive ? 'border-l-4 border-green-500 pl-3' : '' }}">
                @if($todayAttendances->count() > 1)
                    <p class="text-xs text-gray-400 mb-1">セッション{{ $session->session_number }}</p>
                @endif
                <div class="grid grid-cols-5 gap-4 text-center">
                    <div>
                        <p class="text-xs text-gray-500">出勤</p>
                        <p class="text-lg font-mono">{{ $sessionRounded['rounded_clock_in']->format('H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">退勤</p>
                        <p class="text-lg font-mono">{{ $sessionRounded['rounded_clock_out'] ? $sessionRounded['rounded_clock_out']->format('H:i') : '--:--' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">休憩</p>
                        <p class="text-lg font-mono">{{ $sessionBreakMin > 0 ? floor($sessionBreakMin / 60) . ':' . str_pad($sessionBreakMin % 60, 2, '0', STR_PAD_LEFT) : '--:--' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">拘束</p>
                        <p class="text-lg font-mono">{{ $sessionBindMin !== null ? floor($sessionBindMin / 60) . ':' . str_pad($sessionBindMin % 60, 2, '0', STR_PAD_LEFT) : '--:--' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">実働</p>
                        <p class="text-lg font-mono">{{ $sessionWorkMin !== null ? floor($sessionWorkMin / 60) . ':' . str_pad($sessionWorkMin % 60, 2, '0', STR_PAD_LEFT) : '--:--' }}</p>
                    </div>
                </div>
            </div>
            @if(!$loop->last)
                <hr class="my-2 border-gray-100">
            @endif
        @endforeach

        {{-- 日合計（複数セッションの場合のみ表示） --}}
        @if($todayAttendances->count() > 1)
            <hr class="my-3 border-gray-300">
            <div class="text-center">
                <span class="text-sm text-gray-500">本日合計 実働:</span>
                <span class="text-lg font-bold font-mono ml-2">{{ floor($totalDailyWorkingMinutes / 60) }}:{{ str_pad($totalDailyWorkingMinutes % 60, 2, '0', STR_PAD_LEFT) }}</span>
            </div>
        @endif
    </div>
    @else
    {{-- 未出勤の場合 --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">本日の勤怠</h2>
            <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">未出勤</span>
        </div>
        <div class="grid grid-cols-5 gap-4 text-center">
            <div><p class="text-xs text-gray-500">出勤</p><p class="text-lg font-mono">--:--</p></div>
            <div><p class="text-xs text-gray-500">退勤</p><p class="text-lg font-mono">--:--</p></div>
            <div><p class="text-xs text-gray-500">休憩</p><p class="text-lg font-mono">--:--</p></div>
            <div><p class="text-xs text-gray-500">拘束</p><p class="text-lg font-mono">--:--</p></div>
            <div><p class="text-xs text-gray-500">実働</p><p class="text-lg font-mono">--:--</p></div>
        </div>
    </div>
    @endif

    {{-- アラート --}}
    @if(count($alerts) > 0)
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach($alerts as $alert)
            @php
                $alertConfig = [
                    'late' => ['遅刻 ' . $alert['minutes'] . '分', 'bg-red-100 text-red-700'],
                    'early_leave' => ['早退 ' . $alert['minutes'] . '分', 'bg-amber-100 text-amber-700'],
                    'overtime' => ['残業 ' . $alert['minutes'] . '分', 'bg-blue-100 text-blue-700'],
                ];
                [$alertLabel, $alertClass] = $alertConfig[$alert['type']];
            @endphp
            <span class="px-3 py-1 rounded-full text-sm {{ $alertClass }}">{{ $alertLabel }}</span>
        @endforeach
    </div>
    @endif

    {{-- GPS ステータス --}}
    <div id="gps-status" class="hidden mb-4 text-center">
        <span id="gps-indicator" class="inline-flex items-center px-3 py-1 rounded-full text-xs">
            <span id="gps-dot" class="w-2 h-2 rounded-full mr-1.5"></span>
            <span id="gps-text"></span>
        </span>
    </div>

    {{-- 打刻ボタン --}}
    <div class="grid grid-cols-2 gap-4">
        @if($status === 'not_started' || ($status === 'clocked_out' && $rule['allow_multiple_clock_ins']))
            <form method="POST" action="{{ route('attendance.clockIn') }}" class="col-span-2" id="clockInForm">
                @csrf
                <input type="hidden" name="latitude" id="clockInLat">
                <input type="hidden" name="longitude" id="clockInLng">
                <button type="button" onclick="getLocationAndSubmit('clockInForm')" class="w-full py-5 bg-green-600 text-white text-xl font-bold rounded-lg hover:bg-green-700 active:bg-green-800 transition min-h-[56px]">
                    @if($status === 'clocked_out' && $todayAttendances->count() > 0)
                        次のセッションの出勤
                    @else
                        出勤
                    @endif
                </button>
            </form>
        @elseif($status === 'clocked_in')
            <form method="POST" action="{{ route('attendance.breakStart') }}" id="breakStartForm">
                @csrf
                <input type="hidden" name="latitude" id="breakStartLat">
                <input type="hidden" name="longitude" id="breakStartLng">
                <button type="button" onclick="getLocationAndSubmit('breakStartForm')" class="w-full py-5 bg-yellow-500 text-white text-xl font-bold rounded-lg hover:bg-yellow-600 active:bg-yellow-700 transition min-h-[56px]">
                    休憩開始
                </button>
            </form>
            <form method="POST" action="{{ route('attendance.clockOut') }}" id="clockOutForm">
                @csrf
                <input type="hidden" name="latitude" id="clockOutLat">
                <input type="hidden" name="longitude" id="clockOutLng">
                <button type="button" onclick="getLocationAndSubmit('clockOutForm')" class="w-full py-5 bg-red-600 text-white text-xl font-bold rounded-lg hover:bg-red-700 active:bg-red-800 transition min-h-[56px]">
                    退勤
                </button>
            </form>
        @elseif($status === 'on_break')
            <form method="POST" action="{{ route('attendance.breakEnd') }}" class="col-span-2" id="breakEndForm">
                @csrf
                <input type="hidden" name="latitude" id="breakEndLat">
                <input type="hidden" name="longitude" id="breakEndLng">
                <button type="button" onclick="getLocationAndSubmit('breakEndForm')" class="w-full py-5 bg-yellow-600 text-white text-xl font-bold rounded-lg hover:bg-yellow-700 active:bg-yellow-800 transition min-h-[56px]">
                    休憩終了
                </button>
            </form>
        @else
            <div class="col-span-2 text-center py-4 text-gray-500">
                本日の勤務は終了しています
            </div>
        @endif
    </div>
</div>

<script>
// 現在時刻の表示
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('current-time').textContent = h + ':' + m + ':' + s;
}
updateClock();
setInterval(updateClock, 1000);

// GPS取得してからフォーム送信
function getLocationAndSubmit(formId) {
    const form = document.getElementById(formId);
    const btn = form.querySelector('button');
    const originalText = btn.textContent;

    if ('geolocation' in navigator) {
        btn.disabled = true;
        btn.textContent = '位置情報を取得中...';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                // フォーム内のhidden inputに座標をセット
                const latInput = form.querySelector('input[name="latitude"]');
                const lngInput = form.querySelector('input[name="longitude"]');
                if (latInput) latInput.value = position.coords.latitude;
                if (lngInput) lngInput.value = position.coords.longitude;
                form.submit();
            },
            function(error) {
                // GPS取得失敗でも打刻は実行する
                form.submit();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    } else {
        form.submit();
    }
}

// GPS状態の表示（ページ読み込み時）
document.addEventListener('DOMContentLoaded', function() {
    var gpsStatus = document.getElementById('gps-status');
    var gpsDot = document.getElementById('gps-dot');
    var gpsText = document.getElementById('gps-text');
    var gpsIndicator = document.getElementById('gps-indicator');

    if ('geolocation' in navigator) {
        gpsStatus.classList.remove('hidden');
        gpsDot.classList.add('bg-yellow-400');
        gpsIndicator.classList.add('bg-yellow-50', 'text-yellow-700');
        gpsText.textContent = 'GPS確認中...';

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                // バックグラウンドで全フォームの座標を事前セット
                document.querySelectorAll('input[name="latitude"]').forEach(function(el) { el.value = pos.coords.latitude; });
                document.querySelectorAll('input[name="longitude"]').forEach(function(el) { el.value = pos.coords.longitude; });

                gpsDot.classList.remove('bg-yellow-400');
                gpsDot.classList.add('bg-green-500');
                gpsIndicator.classList.remove('bg-yellow-50', 'text-yellow-700');
                gpsIndicator.classList.add('bg-green-50', 'text-green-700');
                gpsText.textContent = 'GPS取得済み';
            },
            function() {
                gpsDot.classList.remove('bg-yellow-400');
                gpsDot.classList.add('bg-red-400');
                gpsIndicator.classList.remove('bg-yellow-50', 'text-yellow-700');
                gpsIndicator.classList.add('bg-red-50', 'text-red-700');
                gpsText.textContent = 'GPS取得できません';
            },
            { timeout: 10000, enableHighAccuracy: true }
        );
    }
});

// PWA インストールプロンプト
let deferredPrompt;

window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferredPrompt = e;

    // 以前に閉じていなければバナーを表示
    if (!localStorage.getItem('pwa-install-dismissed')) {
        document.getElementById('install-banner').classList.remove('hidden');
    }
});

function installPwa() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(choiceResult) {
            if (choiceResult.outcome === 'accepted') {
                document.getElementById('install-banner').classList.add('hidden');
            }
            deferredPrompt = null;
        });
    }
}

function dismissInstall() {
    document.getElementById('install-banner').classList.add('hidden');
    localStorage.setItem('pwa-install-dismissed', '1');
}
</script>
@endsection
