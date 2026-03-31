@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">ユーザー新規作成</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">社員番号</label>
                <input type="text" name="employee_number" value="{{ old('employee_number') }}"
                    class="w-full px-3 py-2 border rounded-md" required>
                @error('employee_number') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">名前</label>
                <input type="text" name="name" value="{{ old('name') }}"
                    class="w-full px-3 py-2 border rounded-md" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email') }}"
                    class="w-full px-3 py-2 border rounded-md" required>
                @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">パスワード（8文字以上）</label>
                <input type="password" name="password"
                    class="w-full px-3 py-2 border rounded-md" required>
                @error('password') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ロール</label>
                <select name="role" class="w-full px-3 py-2 border rounded-md">
                    <option value="EMPLOYEE">EMPLOYEE</option>
                    <option value="ADMIN">ADMIN</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">部署</label>
                <select name="department_id" class="w-full px-3 py-2 border rounded-md">
                    <option value="">なし</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">PIN（4桁数字、任意）</label>
                <div class="flex space-x-2">
                    <input type="text" name="kiosk_code" id="kiosk_code" value="{{ old('kiosk_code') }}" maxlength="4" pattern="\d{4}"
                        class="flex-1 px-3 py-2 border rounded-md" placeholder="4桁の数字">
                    <button type="button" onclick="generatePin()"
                        class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 text-sm whitespace-nowrap">自動生成</button>
                </div>
                @error('kiosk_code') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">作成</button>
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2 border rounded-md text-gray-600 hover:bg-gray-50">キャンセル</a>
            </div>
        </form>
    </div>
</div>

<script>
function generatePin() {
    const pin = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
    document.getElementById('kiosk_code').value = pin;
}
</script>
@endsection
