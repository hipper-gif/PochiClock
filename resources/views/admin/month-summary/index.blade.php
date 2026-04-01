@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">月次サマリ</h1>
</div>

{{-- フィルター --}}
<div class="flex items-center space-x-4 mb-6">
    @php
        $prev = \Carbon\Carbon::create($year, $month, 1)->subMonth();
        $next = \Carbon\Carbon::create($year, $month, 1)->addMonth();
    @endphp
    <a href="?year={{ $prev->year }}&month={{ $prev->month }}&department_id={{ $departmentId }}" class="text-sm text-indigo-600">&larr; 前月</a>
    <span class="text-lg font-semibold">{{ $year }}年{{ $month }}月</span>
    <a href="?year={{ $next->year }}&month={{ $next->month }}&department_id={{ $departmentId }}" class="text-sm text-indigo-600">次月 &rarr;</a>

    @if(auth()->user()->isAdmin())
    <select onchange="location.href='?year={{ $year }}&month={{ $month }}&department_id='+this.value" class="ml-4 text-sm border rounded px-2 py-1">
        <option value="">全部署</option>
        @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ $departmentId === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
        @endforeach
    </select>
    @elseif(auth()->user()->isManager())
    <span class="ml-4 text-sm text-gray-500">{{ auth()->user()->department?->name ?? '' }}</span>
    @endif
</div>

{{-- サマリーテーブル --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b bg-gray-50 text-gray-500 text-xs">
                <th class="px-4 py-3 text-left">氏名</th>
                <th class="px-4 py-3 text-left">部署</th>
                <th class="px-4 py-3 text-right">出勤日数</th>
                <th class="px-4 py-3 text-right">総労働時間</th>
                <th class="px-4 py-3 text-right">総休憩時間</th>
                <th class="px-4 py-3 text-right">残業時間</th>
                <th class="px-4 py-3 text-right">遅刻</th>
                <th class="px-4 py-3 text-right">早退</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($summaries as $row)
            @php
                $workH = floor($row['total_working_minutes'] / 60);
                $workM = str_pad($row['total_working_minutes'] % 60, 2, '0', STR_PAD_LEFT);
                $breakH = floor($row['total_break_minutes'] / 60);
                $breakM = str_pad($row['total_break_minutes'] % 60, 2, '0', STR_PAD_LEFT);
                $otH = floor($row['overtime_minutes'] / 60);
                $otM = str_pad($row['overtime_minutes'] % 60, 2, '0', STR_PAD_LEFT);
                $overtimeRed = $row['overtime_minutes'] >= 45 * 60;
            @endphp
            <tr class="{{ $row['overtime_warning'] ? 'bg-red-50' : 'hover:bg-gray-50' }}">
                <td class="px-4 py-3 font-medium">
                    {{ $row['user']->name }}
                    @if($row['overtime_warning'])
                        <span class="ml-2 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">36協定注意</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $row['user']->department?->name ?? '未所属' }}</td>
                <td class="px-4 py-3 text-right font-mono">{{ $row['work_days'] }}日</td>
                <td class="px-4 py-3 text-right font-mono">{{ $workH }}:{{ $workM }}</td>
                <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $breakH }}:{{ $breakM }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $overtimeRed ? 'text-red-600 font-semibold' : '' }}">{{ $otH }}:{{ $otM }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $row['late_count'] > 0 ? 'text-amber-600' : '' }}">{{ $row['late_count'] }}回</td>
                <td class="px-4 py-3 text-right font-mono {{ $row['early_leave_count'] > 0 ? 'text-amber-600' : '' }}">{{ $row['early_leave_count'] }}回</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">データがありません</td>
            </tr>
            @endforelse
        </tbody>
        @if(count($summaries) > 0)
        @php
            $totalWorkDays = array_sum(array_column($summaries, 'work_days'));
            $totalWorkMin = array_sum(array_column($summaries, 'total_working_minutes'));
            $totalBreakMin = array_sum(array_column($summaries, 'total_break_minutes'));
            $totalOtMin = array_sum(array_column($summaries, 'overtime_minutes'));
            $totalLate = array_sum(array_column($summaries, 'late_count'));
            $totalEarlyLeave = array_sum(array_column($summaries, 'early_leave_count'));

            $totWorkH = floor($totalWorkMin / 60);
            $totWorkM = str_pad($totalWorkMin % 60, 2, '0', STR_PAD_LEFT);
            $totBreakH = floor($totalBreakMin / 60);
            $totBreakM = str_pad($totalBreakMin % 60, 2, '0', STR_PAD_LEFT);
            $totOtH = floor($totalOtMin / 60);
            $totOtM = str_pad($totalOtMin % 60, 2, '0', STR_PAD_LEFT);
        @endphp
        <tfoot>
            <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold text-gray-700">
                <td class="px-4 py-3" colspan="2">合計（{{ count($summaries) }}名）</td>
                <td class="px-4 py-3 text-right font-mono">{{ $totalWorkDays }}日</td>
                <td class="px-4 py-3 text-right font-mono">{{ $totWorkH }}:{{ $totWorkM }}</td>
                <td class="px-4 py-3 text-right font-mono text-gray-500">{{ $totBreakH }}:{{ $totBreakM }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $totalOtMin >= 45 * 60 ? 'text-red-600' : '' }}">{{ $totOtH }}:{{ $totOtM }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $totalLate > 0 ? 'text-amber-600' : '' }}">{{ $totalLate }}回</td>
                <td class="px-4 py-3 text-right font-mono {{ $totalEarlyLeave > 0 ? 'text-amber-600' : '' }}">{{ $totalEarlyLeave }}回</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
@endsection
