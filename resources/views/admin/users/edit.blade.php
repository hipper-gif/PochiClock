@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">ユーザー編集</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">社員番号</label>
                <input type="text" value="{{ $user->employee_number }}" class="w-full px-3 py-2 border rounded-md bg-gray-50" disabled>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">名前</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                    class="w-full px-3 py-2 border rounded-md" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                    class="w-full px-3 py-2 border rounded-md" required>
                @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">更新</button>
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2 border rounded-md text-gray-600 hover:bg-gray-50">キャンセル</a>
            </div>
        </form>
    </div>

    {{-- PIN管理セクション --}}
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">PIN管理</h2>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">現在のPIN</label>
            <div class="flex items-center space-x-3">
                @if($user->kiosk_code)
                    <span id="pin-masked" class="font-mono text-lg tracking-widest">****</span>
                    <span id="pin-visible" class="font-mono text-lg tracking-widest hidden">{{ $user->kiosk_code }}</span>
                    <button type="button" onclick="togglePin()" id="pin-toggle-btn"
                        class="text-xs px-2 py-1 border rounded text-gray-600 hover:bg-gray-50">表示</button>
                @else
                    <span class="text-gray-400">未設定</span>
                @endif
            </div>
        </div>

        <div class="flex space-x-3">
            <form method="POST" action="{{ route('admin.users.resetPin', $user) }}">
                @csrf
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm"
                    onclick="return confirm('PINをリセットしますか？')">
                    PINリセット
                </button>
            </form>

            @if($user->kiosk_code)
            <form method="POST" action="{{ route('admin.users.clearPin', $user) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 text-sm"
                    onclick="return confirm('PINを削除しますか？')">
                    PIN削除
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

<script>
function togglePin() {
    const masked = document.getElementById('pin-masked');
    const visible = document.getElementById('pin-visible');
    const btn = document.getElementById('pin-toggle-btn');
    if (masked.classList.contains('hidden')) {
        masked.classList.remove('hidden');
        visible.classList.add('hidden');
        btn.textContent = '表示';
    } else {
        masked.classList.add('hidden');
        visible.classList.remove('hidden');
        btn.textContent = '非表示';
    }
}
</script>
@endsection
