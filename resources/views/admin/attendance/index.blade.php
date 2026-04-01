@extends('layouts.app')

@section('content')
@php
    try {
        $counts = app(\App\Services\AlertService::class)->getAlertCounts();
    } catch (\Throwable $e) {
        $counts = ['missing_clock_outs' => 0, 'shift_overtime' => 0];
    }
@endphp
@if($counts['missing_clock_outs'] > 0 || $counts['shift_overtime'] > 0)
<div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            @if($counts['missing_clock_outs'] > 0)
            <span class="text-sm text-red-700">
                &#9888; 未打刻 {{ $counts['missing_clock_outs'] }}名
            </span>
            @endif
            @if($counts['shift_overtime'] > 0)
            <span class="text-sm text-amber-700">
                &#9888; シフト超過 {{ $counts['shift_overtime'] }}名
            </span>
            @endif
        </div>
        <a href="{{ route('admin.alerts.index') }}" class="text-sm text-red-600 hover:underline">確認する &rarr;</a>
    </div>
</div>
@endif
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">勤怠管理</h1>
    <a href="{{ route('admin.attendance.export', ['year' => $year, 'month' => $month, 'department_id' => $departmentId]) }}"
       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm">CSV出力</a>
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

    <select onchange="location.href='?year={{ $year }}&month={{ $month }}&department_id='+this.value" class="ml-4 text-sm border rounded px-2 py-1">
        <option value="">全部署</option>
        @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ $departmentId === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
        @endforeach
    </select>
</div>

{{-- ユーザー別 --}}
@foreach($users as $u)
    @php $userAttendances = $attendances->get($u->id, collect()); @endphp
    @if($userAttendances->isEmpty()) @continue @endif

    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
            <div>
                <span class="font-semibold">{{ $u->name }}</span>
                <span class="text-sm text-gray-500 ml-2">{{ $u->employee_number }}</span>
                <span class="text-sm text-gray-400 ml-2">{{ $u->department?->name ?? '未所属' }}</span>
            </div>
            @php
                $rule = app(\App\Services\WorkRuleService::class)->resolve($u->id);
                $rounding = ['rounding_unit' => $rule['rounding_unit'], 'clock_in_rounding' => $rule['clock_in_rounding'], 'clock_out_rounding' => $rule['clock_out_rounding']];
                $workDays = $userAttendances->filter(fn($a) => $a->clock_out)->count();
                $totalWork = 0;
                foreach ($userAttendances as $a) {
                    if ($a->clock_out) {
                        $totalWork += app(\App\Services\TimeService::class)->calculateWorkingMinutesWithRounding($a->clock_in, $a->clock_out, $a->breakRecords, $rounding) ?? 0;
                    }
                }
            @endphp
            <div class="text-sm text-gray-600">
                稼働{{ $workDays }}日 / 実働{{ floor($totalWork/60) }}:{{ str_pad($totalWork%60,2,'0',STR_PAD_LEFT) }}
            </div>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-gray-500 text-xs">
                    <th class="px-3 py-2 text-left">日付</th>
                    <th class="px-3 py-2 text-left">出勤</th>
                    <th class="px-3 py-2 text-left">退勤</th>
                    <th class="px-3 py-2 text-left">休憩</th>
                    <th class="px-3 py-2 text-left">実働</th>
                    <th class="px-3 py-2 text-left">備考</th>
                    <th class="px-3 py-2 text-left">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($userAttendances as $att)
                <tr>
                    <td class="px-3 py-2 font-mono">{{ $att->clock_in->format('m/d') }}</td>
                    <td class="px-3 py-2 font-mono">{{ $att->clock_in->format('H:i') }}</td>
                    <td class="px-3 py-2 font-mono">{{ $att->clock_out?->format('H:i') ?? '-' }}</td>
                    <td class="px-3 py-2 font-mono">{{ app(\App\Services\TimeService::class)->calculateBreakMinutes($att->breakRecords) }}分</td>
                    @php
                        $wm = app(\App\Services\TimeService::class)->calculateWorkingMinutesWithRounding($att->clock_in, $att->clock_out, $att->breakRecords, $rounding);
                    @endphp
                    <td class="px-3 py-2 font-mono">{{ $wm !== null ? floor($wm/60).':'.str_pad($wm%60,2,'0',STR_PAD_LEFT) : '-' }}</td>
                    <td class="px-3 py-2 text-gray-500">{{ $att->note ?? '' }}</td>
                    <td class="px-3 py-2">
                        <button onclick="document.getElementById('edit-{{ $att->id }}').classList.toggle('hidden')"
                            class="text-indigo-600 text-xs hover:underline">編集</button>
                    </td>
                </tr>
                <tr id="edit-{{ $att->id }}" class="hidden bg-gray-50">
                    <td colspan="7" class="px-3 py-3">
                        <form method="POST" action="{{ route('admin.attendance.update', $att) }}" class="flex items-end space-x-3">
                            @csrf @method('PUT')
                            <div>
                                <label class="text-xs text-gray-500">出勤</label>
                                <input type="datetime-local" name="clock_in" value="{{ $att->clock_in->format('Y-m-d\TH:i') }}" class="text-sm border rounded px-2 py-1">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">退勤</label>
                                <input type="datetime-local" name="clock_out" value="{{ $att->clock_out?->format('Y-m-d\TH:i') }}" class="text-sm border rounded px-2 py-1">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">備考</label>
                                <input type="text" name="note" value="{{ $att->note }}" class="text-sm border rounded px-2 py-1">
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">保存</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@if($users->isEmpty())
    <p class="text-center text-gray-400 py-8">勤怠データがありません</p>
@endif
@endsection
