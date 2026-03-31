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

    {{-- QRトークン管理 --}}
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">QRトークン</h2>

        <div class="mb-4">
            <span class="text-sm text-gray-600">ステータス:</span>
            @if($user->qr_token)
                <span class="inline-block px-2 py-0.5 text-xs rounded bg-green-100 text-green-700">設定済み</span>
            @else
                <span class="inline-block px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-600">未設定</span>
            @endif
        </div>

        <div class="flex space-x-4">
            <form method="POST" action="{{ route('admin.users.resetQrToken', $user) }}">
                @csrf
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700"
                        onclick="return confirm('QRトークンを{{ $user->qr_token ? '再' : '' }}発行しますか？')">
                    QRトークン{{ $user->qr_token ? '再' : '' }}発行
                </button>
            </form>

            @if($user->qr_token)
                <form method="POST" action="{{ route('admin.users.clearQrToken', $user) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm hover:bg-red-700"
                            onclick="return confirm('QRトークンを削除しますか？\nこのユーザーはQR打刻ができなくなります。')">
                        QRトークン削除
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
