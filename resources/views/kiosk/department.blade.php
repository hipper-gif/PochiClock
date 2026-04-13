@extends('layouts.kiosk')

@section('content')
<div class="text-center">
    <div class="flex items-center justify-between mb-8">
        <a href="{{ route('kiosk.index') }}" class="text-gray-400 hover:text-sky-600">&larr; 戻る</a>
        <h1 class="text-2xl font-bold text-sky-600">{{ $department->name }}</h1>
        <a href="{{ route('kiosk.qr', $department) }}" class="text-gray-400 hover:text-sky-600 text-sm">QRスキャン</a>
    </div>

    {{-- コード入力 --}}
    <div id="inputPhase">
        <p class="text-gray-500 mb-4">4桁の社員コードを入力</p>
        <div class="text-5xl font-mono tracking-[0.5em] mb-8 h-16 text-sky-700" id="codeDisplay">____</div>

        <div class="grid grid-cols-3 gap-4 max-w-xs mx-auto mb-8">
            @for($i = 1; $i <= 9; $i++)
                <button onclick="addDigit('{{ $i }}')" class="bg-white border-2 border-gray-200 hover:border-sky-400 hover:bg-sky-50 rounded-2xl py-4 text-2xl font-bold text-gray-700 shadow-sm transition active:scale-95">{{ $i }}</button>
            @endfor
            <button onclick="clearCode()" class="bg-red-50 border-2 border-red-200 hover:bg-red-100 rounded-2xl py-4 text-lg text-red-600 font-semibold transition active:scale-95">クリア</button>
            <button onclick="addDigit('0')" class="bg-white border-2 border-gray-200 hover:border-sky-400 hover:bg-sky-50 rounded-2xl py-4 text-2xl font-bold text-gray-700 shadow-sm transition active:scale-95">0</button>
            <button onclick="deleteDigit()" class="bg-white border-2 border-gray-200 hover:border-sky-400 hover:bg-sky-50 rounded-2xl py-4 text-lg text-gray-500 shadow-sm transition active:scale-95">&larr;</button>
        </div>

        <div id="errorMsg" class="text-red-500 mb-4 hidden"></div>
    </div>

    {{-- ユーザー確認 + 打刻 --}}
    <div id="actionPhase" class="hidden">
        <div class="bg-white border-2 border-sky-100 rounded-2xl p-8 mb-8 shadow-sm">
            <p class="text-gray-400 text-sm mb-2">確認</p>
            <p class="text-3xl font-bold text-gray-800" id="userName"></p>
            <p class="text-gray-500 mt-2" id="userNumber"></p>
            <p class="mt-4">
                <span id="statusBadge" class="px-4 py-1 rounded-full text-sm font-medium"></span>
            </p>
            <p class="mt-2 text-gray-400 text-sm hidden" id="sessionInfo"></p>
        </div>

        <div class="grid grid-cols-2 gap-4 max-w-md mx-auto mb-8">
            <button id="clockInBtn" onclick="doClockIn()" class="bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl py-6 text-xl font-bold shadow-md hover:shadow-lg transition active:scale-95 hidden">出勤</button>
            <button id="nextSessionBtn" onclick="doClockIn()" class="bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl py-6 text-xl font-bold shadow-md hover:shadow-lg transition active:scale-95 hidden col-span-2">次のセッションの出勤</button>
            <button id="clockOutBtn" onclick="doClockOut()" class="bg-rose-500 hover:bg-rose-600 text-white rounded-2xl py-6 text-xl font-bold shadow-md hover:shadow-lg transition active:scale-95 hidden">退勤</button>
        </div>

        <button onclick="resetKiosk()" class="text-gray-400 hover:text-sky-600 text-sm">別のユーザー</button>
    </div>

    {{-- 完了メッセージ --}}
    <div id="donePhase" class="hidden">
        <div class="text-6xl mb-8 text-emerald-500">&#10004;</div>
        <p class="text-3xl font-bold text-gray-800" id="doneMsg"></p>
        <p class="text-gray-500 mt-4" id="doneTime"></p>
        <button onclick="resetKiosk()" class="mt-8 bg-sky-500 hover:bg-sky-600 text-white rounded-2xl px-8 py-4 text-lg font-semibold shadow-md transition active:scale-95">OK</button>
    </div>
</div>

<script>
const lookupUrl = '{{ route("kiosk.lookup", $department) }}';
const clockInUrl = '{{ route("kiosk.clockIn", $department) }}';
const clockOutUrl = '{{ route("kiosk.clockOut", $department) }}';
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let code = '';
let currentUserId = null;
let sessionData = null;
let idleTimer = null;
const IDLE_SECONDS = 120; // 2分操作なしでトップへ

function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
        window.location.href = '{{ route("kiosk.index") }}';
    }, IDLE_SECONDS * 1000);
}

document.addEventListener('click', resetIdle);
document.addEventListener('keydown', resetIdle);
resetIdle();

function updateDisplay() {
    const display = code.padEnd(4, '_').split('').join('');
    document.getElementById('codeDisplay').textContent = display;
}

function addDigit(d) {
    if (code.length >= 4) return;
    code += d;
    updateDisplay();
    if (code.length === 4) lookup();
}

function deleteDigit() {
    code = code.slice(0, -1);
    updateDisplay();
}

function clearCode() {
    code = '';
    updateDisplay();
    document.getElementById('errorMsg').classList.add('hidden');
}

async function lookup() {
    const res = await fetch(lookupUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ kiosk_code: code })
    });
    const data = await res.json();

    if (!data.success) {
        document.getElementById('errorMsg').textContent = data.message;
        document.getElementById('errorMsg').classList.remove('hidden');
        code = '';
        updateDisplay();
        return;
    }

    currentUserId = data.user.id;
    sessionData = data.session;
    document.getElementById('userName').textContent = data.user.name;
    document.getElementById('userNumber').textContent = data.user.employee_number;

    const statusConfig = {
        not_started: ['未出勤', 'background:#f3f4f6;color:#6b7280'],
        clocked_in: ['出勤中', 'background:#d1fae5;color:#065f46'],
        on_break: ['休憩中', 'background:#fef3c7;color:#92400e'],
        clocked_out: ['退勤済', 'background:#dbeafe;color:#1e40af'],
    };
    const [label, style] = statusConfig[data.status];
    const badge = document.getElementById('statusBadge');
    badge.textContent = label;
    badge.style.cssText = style;

    // Session info display
    const sessionInfoEl = document.getElementById('sessionInfo');
    if (sessionData && sessionData.total > 0) {
        if (sessionData.current) {
            sessionInfoEl.textContent = `セッション${sessionData.current} / 本日${sessionData.total}回`;
        } else {
            sessionInfoEl.textContent = `本日${sessionData.total}回打刻済み`;
        }
        sessionInfoEl.classList.remove('hidden');
    } else {
        sessionInfoEl.classList.add('hidden');
    }

    // Button visibility
    const clockInBtn = document.getElementById('clockInBtn');
    const nextSessionBtn = document.getElementById('nextSessionBtn');
    const clockOutBtn = document.getElementById('clockOutBtn');

    clockInBtn.classList.add('hidden');
    nextSessionBtn.classList.add('hidden');
    clockOutBtn.classList.add('hidden');

    if (data.status === 'not_started') {
        clockInBtn.classList.remove('hidden');
    } else if (data.status === 'clocked_out' && sessionData && sessionData.allow_multiple) {
        nextSessionBtn.classList.remove('hidden');
    }

    if (data.status === 'clocked_in' || data.status === 'on_break') {
        clockOutBtn.classList.remove('hidden');
    }

    document.getElementById('inputPhase').classList.add('hidden');
    document.getElementById('actionPhase').classList.remove('hidden');
}

function getPosition() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
            () => resolve(null),
            { timeout: 5000, maximumAge: 60000 }
        );
    });
}

async function doClockIn() {
    const pos = await getPosition();
    const res = await fetch(clockInUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ user_id: currentUserId, ...pos })
    });
    const data = await res.json();
    showDone(data.message);
}

async function doClockOut() {
    const pos = await getPosition();
    const res = await fetch(clockOutUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ user_id: currentUserId, ...pos })
    });
    const data = await res.json();
    showDone(data.message);
}

function showDone(msg) {
    document.getElementById('doneMsg').textContent = msg;
    document.getElementById('doneTime').textContent = new Date().toLocaleTimeString('ja-JP');
    document.getElementById('actionPhase').classList.add('hidden');
    document.getElementById('donePhase').classList.remove('hidden');
}

function resetKiosk() {
    code = '';
    currentUserId = null;
    sessionData = null;
    updateDisplay();
    document.getElementById('errorMsg').classList.add('hidden');
    document.getElementById('inputPhase').classList.remove('hidden');
    document.getElementById('actionPhase').classList.add('hidden');
    document.getElementById('donePhase').classList.add('hidden');
}
</script>
@endsection
