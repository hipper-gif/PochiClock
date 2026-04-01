@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">振替管理</h1>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-6 text-sm">
        {{ session('success') }}
    </div>
@endif

{{-- フィルター --}}
<div class="flex items-center space-x-4 mb-6">
    @php
        $prevYear = $year - 1;
        $nextYear = $year + 1;
    @endphp
    <a href="?year={{ $prevYear }}&department_id={{ $departmentId }}" class="text-sm text-indigo-600">&larr; 前年</a>
    <span class="text-lg font-semibold">{{ $year }}年</span>
    <a href="?year={{ $nextYear }}&department_id={{ $departmentId }}" class="text-sm text-indigo-600">次年 &rarr;</a>

    <select onchange="location.href='?year={{ $year }}&department_id='+this.value" class="ml-4 text-sm border rounded px-2 py-1">
        <option value="">全部署</option>
        @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ $departmentId === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
        @endforeach
    </select>
</div>

{{-- サマリーテーブル --}}
<div class="bg-white rounded-lg shadow overflow-hidden mb-8">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b text-gray-500 text-xs">
                <th class="px-4 py-3 text-left">氏名</th>
                <th class="px-4 py-3 text-left">部署</th>
                <th class="px-4 py-3 text-right">残業累計(h)</th>
                <th class="px-4 py-3 text-right">消化(h)</th>
                <th class="px-4 py-3 text-right">残り(h)</th>
                <th class="px-4 py-3 text-left">進捗</th>
                <th class="px-4 py-3 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($data as $row)
            @php
                $ratio = $row['overtime_hours'] > 0
                    ? min(100, round($row['used_hours'] / $row['overtime_hours'] * 100))
                    : 0;
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['user']->name }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $row['user']->department?->name ?? '未所属' }}</td>
                <td class="px-4 py-3 text-right font-mono text-gray-700">{{ number_format($row['overtime_hours'], 1) }}</td>
                <td class="px-4 py-3 text-right font-mono text-indigo-600">{{ number_format($row['used_hours'], 1) }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $row['remain_hours'] > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' }}">{{ number_format($row['remain_hours'], 1) }}</td>
                <td class="px-4 py-3">
                    <div class="w-32">
                        <div class="flex justify-between text-xs text-gray-400 mb-0.5">
                            <span>消化{{ $ratio }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $ratio }}%"></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <button onclick="document.getElementById('detail-{{ $row['user']->id }}').classList.toggle('hidden')"
                        class="text-indigo-600 text-xs hover:underline">詳細 / 登録</button>
                </td>
            </tr>
            {{-- 展開詳細行 --}}
            <tr id="detail-{{ $row['user']->id }}" class="hidden">
                <td colspan="7" class="px-4 py-4 bg-gray-50">
                    {{-- 振替登録フォーム --}}
                    <form method="POST" action="{{ route('admin.comp-leaves.store') }}" class="flex items-end space-x-3 mb-4">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $row['user']->id }}">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">日付</label>
                            <input type="date" name="leave_date" required
                                class="text-sm border rounded px-2 py-1">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">時間数</label>
                            <select name="hours" class="text-sm border rounded px-2 py-1">
                                <option value="4.0">4.0h（半日）</option>
                                <option value="8.0" selected>8.0h（1日）</option>
                                <option value="custom">その他</option>
                            </select>
                        </div>
                        <div class="custom-hours-{{ $row['user']->id }} hidden">
                            <label class="block text-xs text-gray-500 mb-1">時間数（手入力）</label>
                            <input type="number" name="hours_custom" step="0.5" min="0.5" max="24"
                                class="text-sm border rounded px-2 py-1 w-20" placeholder="例: 6.0">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">備考</label>
                            <input type="text" name="note" maxlength="255"
                                class="text-sm border rounded px-2 py-1 w-48" placeholder="任意">
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm hover:bg-indigo-700">振替登録</button>
                    </form>

                    {{-- 振替一覧 --}}
                    @if($row['leaves']->isNotEmpty())
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-gray-400 border-b">
                                <th class="py-1 text-left">日付</th>
                                <th class="py-1 text-right">時間(h)</th>
                                <th class="py-1 text-left">備考</th>
                                <th class="py-1 text-left">承認者</th>
                                <th class="py-1 text-left">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($row['leaves'] as $leave)
                            <tr>
                                <td class="py-1.5 font-mono text-gray-700">{{ $leave->leave_date->format('Y/m/d') }}</td>
                                <td class="py-1.5 font-mono text-right text-gray-700">{{ number_format((float)$leave->hours, 1) }}</td>
                                <td class="py-1.5 text-gray-500">{{ $leave->note ?? '-' }}</td>
                                <td class="py-1.5 text-gray-500">{{ $leave->approver?->name ?? '-' }}</td>
                                <td class="py-1.5">
                                    <form method="POST" action="{{ route('admin.comp-leaves.destroy', $leave) }}"
                                        onsubmit="return confirm('この振替を削除しますか？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:underline text-xs">削除</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                        <p class="text-xs text-gray-400">振替実績がありません</p>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">データがありません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
// カスタム時間数入力の切り替え
document.querySelectorAll('select[name="hours"]').forEach(function(select) {
    select.addEventListener('change', function() {
        var row = this.closest('form');
        var userId = row.querySelector('input[name="user_id"]').value;
        var customDiv = document.querySelector('.custom-hours-' + userId);
        if (this.value === 'custom') {
            customDiv.classList.remove('hidden');
            this.name = 'hours_select'; // disable the select as hours source
            customDiv.querySelector('input').name = 'hours';
        } else {
            customDiv.classList.add('hidden');
            this.name = 'hours';
            customDiv.querySelector('input').name = 'hours_custom';
        }
    });
});
</script>
@endsection
