@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">部署管理</h1>

{{-- 新規作成 --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="POST" action="{{ route('admin.departments.store') }}" class="flex space-x-4">
        @csrf
        <input type="text" name="name" placeholder="部署名" class="flex-1 px-3 py-2 border rounded-md" required>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">追加</button>
    </form>
    @error('name') <p class="text-red-500 text-sm mt-2">{{ $message }}</p> @enderror
</div>

{{-- 一覧 --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">部署名</th>
                <th class="px-4 py-3 text-left">所属人数</th>
                <th class="px-4 py-3 text-left">作成日</th>
                <th class="px-4 py-3 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($departments as $dept)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.departments.update', $dept) }}" class="flex space-x-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $dept->name }}" class="px-2 py-1 border rounded text-sm flex-1">
                        <button type="submit" class="text-indigo-600 text-xs hover:underline">変更</button>
                    </form>
                </td>
                <td class="px-4 py-3">{{ $dept->users_count }}人</td>
                <td class="px-4 py-3 text-gray-500">{{ $dept->created_at->format('Y/m/d') }}</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.departments.destroy', $dept) }}" onsubmit="return confirm('削除しますか？')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 text-xs hover:underline">削除</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
