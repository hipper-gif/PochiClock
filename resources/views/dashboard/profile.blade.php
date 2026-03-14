@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">プロフィール</h1>

    {{-- 基本情報（読取専用） --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">基本情報</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">社員番号</dt>
                <dd class="font-mono">{{ $user->employee_number }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">メールアドレス</dt>
                <dd>{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">部署</dt>
                <dd>{{ $user->department?->name ?? '未所属' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">ロール</dt>
                <dd>{{ $user->role->value }}</dd>
            </div>
        </dl>
    </div>

    {{-- 名前変更 --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">名前変更</h2>
        <form method="POST" action="{{ route('profile.updateName') }}">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-indigo-500" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">更新</button>
        </form>
    </div>

    {{-- パスワード変更 --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">パスワード変更</h2>
        <form method="POST" action="{{ route('profile.changePassword') }}">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">現在のパスワード</label>
                <input type="password" name="current_password"
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-indigo-500" required>
                @error('current_password') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">新しいパスワード（8文字以上）</label>
                <input type="password" name="new_password"
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-indigo-500" required>
                @error('new_password') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">パスワード確認</label>
                <input type="password" name="new_password_confirmation"
                    class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-indigo-500" required>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">変更</button>
        </form>
    </div>
</div>
@endsection
