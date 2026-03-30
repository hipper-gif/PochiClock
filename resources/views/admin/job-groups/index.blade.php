@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">職種グループ管理</h1>

{{-- 新規作成 --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="POST" action="{{ route('admin.job-groups.store') }}" class="flex space-x-4">
        @csrf
        <input type="text" name="name" placeholder="グループ名（例: 配食-調理）" class="flex-1 px-3 py-2 border rounded-md" required>
        <input type="text" name="description" placeholder="説明（任意）" class="flex-1 px-3 py-2 border rounded-md">
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">追加</button>
    </form>
    @error('name') <p class="text-red-500 text-sm mt-2">{{ $message }}</p> @enderror
</div>

{{-- 一覧 --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">グループ名</th>
                <th class="px-4 py-3 text-left">説明</th>
                <th class="px-4 py-3 text-left">紐付き部署</th>
                <th class="px-4 py-3 text-left">所属人数</th>
                <th class="px-4 py-3 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($jobGroups as $jg)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.job-groups.update', $jg) }}" class="flex space-x-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $jg->name }}" class="px-2 py-1 border rounded text-sm flex-1">
                        <input type="text" name="description" value="{{ $jg->description }}" placeholder="説明" class="px-2 py-1 border rounded text-sm flex-1">
                        <button type="submit" class="text-indigo-600 text-xs hover:underline">変更</button>
                    </form>
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $jg->description ?? '-' }}</td>
                <td class="px-4 py-3 text-gray-500">
                    @if($jg->departments->isEmpty())
                        <span class="text-gray-400">-</span>
                    @else
                        {{ $jg->departments->pluck('name')->join(', ') }}
                    @endif
                </td>
                <td class="px-4 py-3">{{ $jg->users_count }}人</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.job-groups.destroy', $jg) }}" onsubmit="return confirm('削除しますか？')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 text-xs hover:underline">削除</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-gray-400">職種グループがまだ登録されていません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 部署との紐付け --}}
@if($jobGroups->isNotEmpty())
<div class="bg-white rounded-lg shadow p-6 mt-6">
    <h2 class="text-lg font-semibold mb-4">部署の職種グループ紐付け</h2>
    <div class="space-y-3">
        @foreach($departments as $dept)
        <form method="POST" action="{{ route('admin.departments.update', $dept) }}" class="flex items-center space-x-4">
            @csrf @method('PUT')
            <span class="w-32 text-sm font-medium">{{ $dept->name }}</span>
            <input type="hidden" name="name" value="{{ $dept->name }}">
            <select name="job_group_id" class="flex-1 px-3 py-2 border rounded-md text-sm">
                <option value="">未設定</option>
                @foreach($jobGroups as $jg)
                    <option value="{{ $jg->id }}" {{ $dept->job_group_id === $jg->id ? 'selected' : '' }}>{{ $jg->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm hover:bg-indigo-700">保存</button>
        </form>
        @endforeach
    </div>
</div>
@endif
@endsection
