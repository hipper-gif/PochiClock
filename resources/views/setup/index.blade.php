@extends('layouts.guest')

@section('content')
<div class="w-full max-w-2xl mx-auto px-4 py-8">

    {{-- ロゴ・タイトル --}}
    <div class="text-center mb-8">
        <div class="text-4xl font-bold text-indigo-600 mb-2">PochiClock</div>
        <p class="text-gray-600 text-lg">ようこそ！はじめに基本情報を設定してください</p>
    </div>

    {{-- ステップインジケーター --}}
    <div class="flex items-center justify-center mb-8 gap-2">
        <div class="step-indicator flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold bg-indigo-600 text-white">①</div>
        <div class="h-px w-8 bg-gray-300"></div>
        <div class="step-indicator flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold bg-gray-200 text-gray-500">②</div>
        <div class="h-px w-8 bg-gray-300"></div>
        <div class="step-indicator flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold bg-gray-200 text-gray-500">③</div>
        <div class="h-px w-8 bg-gray-300"></div>
        <div class="step-indicator flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold bg-gray-200 text-gray-500">④</div>
    </div>
    <div class="flex justify-between text-xs text-gray-500 mb-8 px-1">
        <span class="step-label font-semibold text-indigo-600">会社情報</span>
        <span class="step-label">部門設定</span>
        <span class="step-label">勤務ルール</span>
        <span class="step-label">管理者登録</span>
    </div>

    {{-- バリデーションエラー --}}
    @if ($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 mb-6">
        <ul class="list-disc list-inside space-y-1 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('setup.store') }}">
        @csrf

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">

            {{-- STEP 1: 会社情報 --}}
            <div class="setup-step">
                <h2 class="text-xl font-bold text-gray-800 mb-6">① 会社情報</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">会社名 <span class="text-red-500">*</span></label>
                    <input type="text" name="company_name" value="{{ old('company_name') }}"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="例: 株式会社Smiley">
                    <p class="text-xs text-gray-500 mt-2">あとから変更することもできます</p>
                </div>
            </div>

            {{-- STEP 2: 部門設定 --}}
            <div class="setup-step hidden">
                <h2 class="text-xl font-bold text-gray-800 mb-2">② 部門設定</h2>
                <p class="text-sm text-gray-500 mb-6">勤怠を管理する部門・チームを登録してください。後から追加・編集できます。</p>

                <div id="dept-list" class="space-y-3">
                    <div class="dept-row flex items-center gap-3">
                        <input type="text"
                            data-name="departments[INDEX][name]"
                            name="departments[0][name]"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="部門名（例: 調理、配達、美容...）"
                            value="{{ old('departments.0.name') }}">
                        <button type="button" onclick="removeDepartment(this)"
                            class="text-gray-400 hover:text-red-500 transition-colors flex-shrink-0 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="button" onclick="addDepartment()"
                    class="mt-4 flex items-center gap-2 text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    部門を追加
                </button>
            </div>

            {{-- STEP 3: 勤務ルール --}}
            <div class="setup-step hidden">
                <h2 class="text-xl font-bold text-gray-800 mb-2">③ 勤務ルール（既定値）</h2>
                <p class="text-sm text-gray-500 mb-6">全社共通の既定勤務時間を設定します。部門・個人ごとに上書き設定することもできます。</p>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">始業時刻 <span class="text-red-500">*</span></label>
                        <input type="time" name="work_start_time" value="{{ old('work_start_time', '09:00') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">終業時刻 <span class="text-red-500">*</span></label>
                        <input type="time" name="work_end_time" value="{{ old('work_end_time', '18:00') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">休憩時間（分） <span class="text-red-500">*</span></label>
                    <input type="number" name="default_break_minutes" value="{{ old('default_break_minutes', 60) }}"
                        min="0" max="480"
                        class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-2">法定休憩（労働6h超 → 45分、8h超 → 60分）を参考に設定してください</p>
                </div>
            </div>

            {{-- STEP 4: 管理者登録 --}}
            <div class="setup-step hidden">
                <h2 class="text-xl font-bold text-gray-800 mb-2">④ 管理者アカウント</h2>
                <p class="text-sm text-gray-500 mb-6">PochiClockを管理するアカウントを作成します。</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">氏名 <span class="text-red-500">*</span></label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="例: 杉原 星">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">社員番号 <span class="text-red-500">*</span></label>
                        <input type="text" name="admin_employee_number" value="{{ old('admin_employee_number') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="例: 001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス <span class="text-red-500">*</span></label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="例: admin@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">パスワード <span class="text-red-500">*</span></label>
                        <input type="password" name="admin_password"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="8文字以上">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">パスワード（確認） <span class="text-red-500">*</span></label>
                        <input type="password" name="admin_password_confirmation"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="もう一度入力してください">
                    </div>
                </div>
            </div>

        </div>

        {{-- ナビゲーションボタン --}}
        <div class="flex justify-between mt-6">
            <button type="button" id="btn-back" onclick="prevStep()"
                class="hidden px-6 py-3 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium transition-colors">
                ← 戻る
            </button>
            <div class="flex-1"></div>
            <button type="button" id="btn-next" onclick="nextStep()"
                class="px-6 py-3 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition-colors">
                次へ →
            </button>
            <button type="submit" id="btn-submit"
                class="hidden px-8 py-3 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 transition-colors">
                セットアップ完了
            </button>
        </div>
    </form>
</div>

<script>
let currentStep = 0;
const steps = document.querySelectorAll('.setup-step');
const indicators = document.querySelectorAll('.step-indicator');
const stepLabels = document.querySelectorAll('.step-label');

function showStep(n) {
    steps.forEach((s, i) => s.classList.toggle('hidden', i !== n));
    indicators.forEach((ind, i) => {
        ind.classList.toggle('bg-indigo-600', i <= n);
        ind.classList.toggle('text-white', i <= n);
        ind.classList.toggle('bg-gray-200', i > n);
        ind.classList.toggle('text-gray-500', i > n);
    });
    stepLabels.forEach((lbl, i) => {
        lbl.classList.toggle('font-semibold', i === n);
        lbl.classList.toggle('text-indigo-600', i === n);
        lbl.classList.remove('font-semibold', 'text-indigo-600');
    });
    if (stepLabels[n]) {
        stepLabels[n].classList.add('font-semibold', 'text-indigo-600');
    }
    document.getElementById('btn-back').classList.toggle('hidden', n === 0);
    document.getElementById('btn-next').classList.toggle('hidden', n === steps.length - 1);
    document.getElementById('btn-submit').classList.toggle('hidden', n !== steps.length - 1);
    currentStep = n;
}

function nextStep() {
    if (currentStep < steps.length - 1) {
        showStep(currentStep + 1);
    }
}

function prevStep() {
    if (currentStep > 0) {
        showStep(currentStep - 1);
    }
}

function addDepartment() {
    const container = document.getElementById('dept-list');
    const newRow = container.querySelector('.dept-row').cloneNode(true);
    newRow.querySelectorAll('input').forEach(inp => inp.value = '');
    container.appendChild(newRow);
    updateDeptIndices();
}

function removeDepartment(btn) {
    const rows = document.querySelectorAll('.dept-row');
    if (rows.length > 1) {
        btn.closest('.dept-row').remove();
    }
    updateDeptIndices();
}

function updateDeptIndices() {
    document.querySelectorAll('.dept-row').forEach((row, i) => {
        row.querySelectorAll('[data-name]').forEach(inp => {
            inp.name = inp.dataset.name.replace('INDEX', i);
        });
    });
}

// Initialize
showStep(0);
</script>
@endsection
