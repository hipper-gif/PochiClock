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

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">職種グループ（個人上書き）</label>
                <select name="job_group_id" class="w-full px-3 py-2 border rounded-md">
                    <option value="">部署の設定に従う</option>
                    @foreach($jobGroups as $jg)
                        <option value="{{ $jg->id }}" {{ old('job_group_id', $user->job_group_id) == $jg->id ? 'selected' : '' }}>{{ $jg->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">通常は空欄のままで、部署に紐付いた職種グループが適用されます</p>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">更新</button>
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2 border rounded-md text-gray-600 hover:bg-gray-50">キャンセル</a>
            </div>
        </form>
    </div>
</div>
@endsection
