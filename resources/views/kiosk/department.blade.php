@extends('layouts.kiosk')

@section('content')
<div class="text-center">
    <div class="flex items-center justify-between mb-8">
        <a href="{{ route('kiosk.index') }}" class="text-gray-400 hover:text-white">&larr; 戻る</a>
        <h1 class="text-2xl font-bold">{{ $department->name }}</h1>
        <div></div>
    </div>

    {{-- コード入力 --}}
    <div id="inputPhase">
        <p class="text-gray-400 mb-4">4桁の社員コードを入力</p>
        <div class="text-5xl font-mono tracking-[0.5em] mb-8 h-16" id="codeDisplay">____</div>

        <div class="grid grid-cols-3 gap-4 max-w-xs mx-auto mb-8">
            @for($i = 1; $i <= 9; $i++)
                <button onclick="addDigit('{{ $i }}')" class="bg-gray-700 hover:bg-gray-600 rounded-xl py-4 text-2xl font-bold transition">{{ $i }}</button>
            @endfor
            <button onclick="clearCode()" class="bg-red-900 hover:bg-red-800 rounded-xl py-4 text-lg transition">クリア</button>
            <button onclick="addDigit('0')" class="bg-gray-700 hover:bg-gray-600 rounded-xl py-4 text-2xl font-bold transition">0</button>
            <button onclick="deleteDigit()" class="bg-gray-700 hover:bg-gray-600 rounded-xl py-4 text-lg transition">←</button>
        </div>

        <div id="errorMsg" class="text-red-400 mb-4 hidden"></div>
    </div>

    {{-- ユーザー確認 + 打刻 --}}
    <div id="actionPhase" class="hidden">
        <div class="bg-gray-800 rounded-xl p-8 mb-8">
            <p class="text-gray-400 text-sm mb-2">確認</p>
            <p class="text-3xl font-bold" id="userName"></p>
            <p class="text-gray-400 mt-2" id="userNumber"></p>
            <p class="mt-4">
                <span id="statusBadge" class="px-4 py-1 rounded-full text-sm"></span>
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4 max-w-md mx-auto mb-8">
            <button id="clockInBtn" onclick="doClockIn()" class="bg-green-600 hover:bg-green-700 rounded-xl py-6 text-xl font-bold transition hidden">出勤</button>
            <button id="clockOutBtn" onclick="doClockOut()" class="bg-red-600 hover:bg-red-700 rounded-xl py-6 text-xl font-bold transition hidden">退勤</button>
        </div>

        <button onclick="resetKiosk()" class="text-gray-400 hover:text-white text-sm">別のユーザー</button>
    </div>

    {{-- 完了メッセージ --}}
    <div id="donePhase" class="hidden">
        <div class="text-6xl mb-8">&#10004;</div>
        <p class="text-3xl font-bold" id="doneMsg"></p>
        <p class="text-gray-400 mt-4" id="doneTime"></p>
        <button onclick="resetKiosk()" class="mt-8 bg-gray-700 hover:bg-gray-600 rounded-xl px-8 py-4 text-lg transition">OK</button>
    </div>
</div>

<script>
const deptId = '{{ $department->id }}';
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let code = '';
let currentUserId = null;

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
    const res = await fetch(`/kiosk/${deptId}/lookup`, {
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
    document.getElementById('userName').textContent = data.user.name;
    document.getElementById('userNumber').textContent = data.user.employee_number;

    const statusConfig = {
        not_started: ['未出勤', 'background:#374151;color:#9ca3af'],
        clocked_in: ['出勤中', 'background:#065f46;color:#6ee7b7'],
        on_break: ['休憩中', 'background:#78350f;color:#fcd34d'],
        clocked_out: ['退勤済', 'background:#1e3a5f;color:#93c5fd'],
    };
    const [label, style] = statusConfig[data.status];
    const badge = document.getElementById('statusBadge');
    badge.textContent = label;
    badge.style.cssText = style;

    document.getElementById('clockInBtn').classList.toggle('hidden', data.status !== 'not_started');
    document.getElementById('clockOutBtn').classList.toggle('hidden', data.status !== 'clocked_in' && data.status !== 'on_break');

    document.getElementById('inputPhase').classList.add('hidden');
    document.getElementById('actionPhase').classList.remove('hidden');
}

async function doClockIn() {
    const res = await fetch(`/kiosk/${deptId}/clock-in`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ user_id: currentUserId })
    });
    const data = await res.json();
    showDone(data.message);
}

async function doClockOut() {
    const res = await fetch(`/kiosk/${deptId}/clock-out`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ user_id: currentUserId })
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
    updateDisplay();
    document.getElementById('errorMsg').classList.add('hidden');
    document.getElementById('inputPhase').classList.remove('hidden');
    document.getElementById('actionPhase').classList.add('hidden');
    document.getElementById('donePhase').classList.add('hidden');
}
</script>
@endsection
