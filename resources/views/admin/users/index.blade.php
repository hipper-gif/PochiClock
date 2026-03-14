@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">ユーザー管理</h1>
    <a href="{{ route('admin.users.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">新規作成</a>
</div>

<div class="mb-4 flex space-x-4 text-sm">
    <a href="{{ route('admin.users.index') }}" class="px-3 py-1 rounded {{ !request('dept') && !request('inactive') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">全員</a>
    @foreach($departments as $dept)
        <a href="{{ route('admin.users.index', ['dept' => $dept->id]) }}" class="px-3 py-1 rounded {{ request('dept') === $dept->id ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">{{ $dept->name }}</a>
    @endforeach
    <a href="{{ route('admin.users.index', ['inactive' => '1']) }}" class="px-3 py-1 rounded {{ request('inactive') === '1' ? 'bg-red-100 text-red-700' : 'text-gray-600 hover:bg-gray-100' }}">無効含む</a>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">社員番号</th>
                <th class="px-4 py-3 text-left">名前</th>
                <th class="px-4 py-3 text-left">メール</th>
                <th class="px-4 py-3 text-left">部署</th>
                <th class="px-4 py-3 text-left">ロール</th>
                <th class="px-4 py-3 text-left">キオスク</th>
                <th class="px-4 py-3 text-left">状態</th>
                <th class="px-4 py-3 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($users as $u)
            <tr class="hover:bg-gray-50 {{ !$u->is_active ? 'opacity-50' : '' }}">
                <td class="px-4 py-3 font-mono">{{ $u->employee_number }}</td>
                <td class="px-4 py-3">{{ $u->name }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $u->email }}</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.users.assignDepartment', $u) }}" class="inline">
                        @csrf @method('PUT')
                        <select name="department_id" onchange="this.form.submit()" class="text-xs border rounded px-1 py-0.5">
                            <option value="">未所属</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $u->department_id === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.users.updateRole', $u) }}" class="inline">
                        @csrf @method('PUT')
                        <select name="role" onchange="this.form.submit()" class="text-xs border rounded px-1 py-0.5" {{ $u->id === auth()->id() ? 'disabled' : '' }}>
                            <option value="EMPLOYEE" {{ $u->role->value === 'EMPLOYEE' ? 'selected' : '' }}>EMPLOYEE</option>
                            <option value="ADMIN" {{ $u->role->value === 'ADMIN' ? 'selected' : '' }}>ADMIN</option>
                        </select>
                    </form>
                </td>
                <td class="px-4 py-3 font-mono text-gray-500">{{ $u->kiosk_code ?? '-' }}</td>
                <td class="px-4 py-3">
                    @if($u->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.toggleStatus', $u) }}" class="inline">
                        @csrf @method('PUT')
                        <button type="submit" class="text-xs px-2 py-0.5 rounded {{ $u->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $u->is_active ? '有効' : '無効' }}
                        </button>
                    </form>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">有効</span>
                    @endif
                </td>
                <td class="px-4 py-3 space-x-2">
                    <a href="{{ route('admin.users.edit', $u) }}" class="text-indigo-600 hover:underline text-xs">編集</a>
                    @if($u->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline" onsubmit="return confirm('本当に削除しますか？')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-xs">削除</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
