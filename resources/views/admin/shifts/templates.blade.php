@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">シフトテンプレート管理</h1>
    <a href="{{ route('admin.shifts.index') }}"
       class="text-sm text-indigo-600 hover:underline">&larr; シフトカレンダーに戻る</a>
</div>

{{-- 新規作成フォーム --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">新規テンプレート作成</h2>
    <form method="POST" action="{{ route('admin.shifts.storeTemplate') }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="text-xs text-gray-500 block mb-1">名前</label>
            <input type="text" name="name" required placeholder="早番" class="text-sm border rounded px-2 py-1.5 w-28">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">色</label>
            <input type="color" name="color" value="#6366f1" class="h-8 w-12 border rounded cursor-pointer">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">開始時刻</label>
            <input type="time" name="start_time" required class="text-sm border rounded px-2 py-1.5">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">終了時刻</label>
            <input type="time" name="end_time" required class="text-sm border rounded px-2 py-1.5">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">休憩(分)</label>
            <input type="number" name="break_minutes" value="60" min="0" class="text-sm border rounded px-2 py-1.5 w-20">
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm hover:bg-indigo-700">作成</button>
    </form>
</div>

{{-- テンプレート一覧 --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-gray-500 text-xs">
                <th class="px-4 py-3 text-left">名前</th>
                <th class="px-4 py-3 text-left">色</th>
                <th class="px-4 py-3 text-left">開始</th>
                <th class="px-4 py-3 text-left">終了</th>
                <th class="px-4 py-3 text-left">休憩</th>
                <th class="px-4 py-3 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($templates as $tpl)
                <tr>
                    <td class="px-4 py-3 font-medium">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-4 h-4 rounded-sm inline-block" style="background-color: {{ $tpl->color }};"></span>
                            {{ $tpl->name }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $tpl->color }}</td>
                    <td class="px-4 py-3 font-mono">{{ $tpl->start_time }}</td>
                    <td class="px-4 py-3 font-mono">{{ $tpl->end_time }}</td>
                    <td class="px-4 py-3">{{ $tpl->break_minutes }}分</td>
                    <td class="px-4 py-3">
                        <button onclick="document.getElementById('edit-{{ $tpl->id }}').classList.toggle('hidden')"
                            class="text-indigo-600 text-xs hover:underline mr-2">編集</button>
                        <form method="POST" action="{{ route('admin.shifts.destroyTemplate', $tpl) }}" class="inline"
                              onsubmit="return confirm('「{{ $tpl->name }}」を削除しますか？割当済みのシフトも削除されます。')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 text-xs hover:underline">削除</button>
                        </form>
                    </td>
                </tr>
                {{-- 編集行 --}}
                <tr id="edit-{{ $tpl->id }}" class="hidden bg-gray-50">
                    <td colspan="6" class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.shifts.updateTemplate', $tpl) }}" class="flex flex-wrap items-end gap-3">
                            @csrf @method('PUT')
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">名前</label>
                                <input type="text" name="name" value="{{ $tpl->name }}" required class="text-sm border rounded px-2 py-1.5 w-28">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">色</label>
                                <input type="color" name="color" value="{{ $tpl->color }}" class="h-8 w-12 border rounded cursor-pointer">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">開始時刻</label>
                                <input type="time" name="start_time" value="{{ $tpl->start_time }}" required class="text-sm border rounded px-2 py-1.5">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">終了時刻</label>
                                <input type="time" name="end_time" value="{{ $tpl->end_time }}" required class="text-sm border rounded px-2 py-1.5">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">休憩(分)</label>
                                <input type="number" name="break_minutes" value="{{ $tpl->break_minutes }}" min="0" class="text-sm border rounded px-2 py-1.5 w-20">
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700">保存</button>
                            <button type="button" onclick="document.getElementById('edit-{{ $tpl->id }}').classList.add('hidden')"
                                    class="text-gray-500 text-sm hover:underline">キャンセル</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">テンプレートがありません</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
