@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">監査ログ</h1>
</div>

{{-- フィルター --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="text-xs text-gray-500 block mb-1">対象</label>
            <select name="type" class="text-sm border rounded px-2 py-1">
                <option value="">すべて</option>
                @foreach($auditableTypes as $class => $label)
                    <option value="{{ $class }}" {{ request('type') === $class ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">操作者</label>
            <select name="user_id" class="text-sm border rounded px-2 py-1">
                <option value="">すべて</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') === $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">開始日</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="text-sm border rounded px-2 py-1">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">終了日</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="text-sm border rounded px-2 py-1">
        </div>
        <div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-1 rounded text-sm hover:bg-indigo-700">検索</button>
            <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-gray-500 hover:text-gray-700 ml-2">リセット</a>
        </div>
    </form>
</div>

{{-- ログ一覧 --}}
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">日時</th>
                <th class="px-4 py-3 text-left">操作者</th>
                <th class="px-4 py-3 text-left">操作</th>
                <th class="px-4 py-3 text-left">対象</th>
                <th class="px-4 py-3 text-left">変更前</th>
                <th class="px-4 py-3 text-left">変更後</th>
                <th class="px-4 py-3 text-left">理由</th>
                <th class="px-4 py-3 text-left">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($logs as $log)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ $log->user_name ?? 'システム' }}</td>
                <td class="px-4 py-3">
                    @php
                        $actionLabels = ['created' => '作成', 'updated' => '更新', 'deleted' => '削除'];
                        $actionColors = ['created' => 'bg-green-100 text-green-700', 'updated' => 'bg-blue-100 text-blue-700', 'deleted' => 'bg-red-100 text-red-700'];
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded {{ $actionColors[$log->action] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ $actionLabels[$log->action] ?? $log->action }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @php
                        $typeLabels = [
                            'App\\Models\\Attendance' => '勤怠',
                            'App\\Models\\BreakRecord' => '休憩',
                            'App\\Models\\WorkRule' => '勤務ルール',
                            'App\\Models\\User' => 'ユーザー',
                        ];
                    @endphp
                    <span class="text-xs text-gray-600">{{ $typeLabels[$log->auditable_type] ?? class_basename($log->auditable_type) }}</span>
                    <span class="text-xs text-gray-400 font-mono ml-1">{{ Str::substr($log->auditable_id, 0, 8) }}...</span>
                </td>
                <td class="px-4 py-3">
                    @if($log->old_values)
                        <div class="max-w-xs text-xs text-gray-600 space-y-0.5">
                            @foreach($log->old_values as $key => $value)
                                <div><span class="font-semibold">{{ $key }}:</span> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-300">-</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($log->new_values)
                        <div class="max-w-xs text-xs text-gray-600 space-y-0.5">
                            @foreach($log->new_values as $key => $value)
                                <div><span class="font-semibold">{{ $key }}:</span> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-300">-</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ $log->reason ?? '-' }}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $log->ip_address ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">監査ログがありません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ページネーション --}}
@if($logs->hasPages())
<div class="mt-4">
    {{ $logs->withQueryString()->links() }}
</div>
@endif
@endsection
