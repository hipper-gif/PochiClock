@extends('layouts.app')

@section('content')
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
                $workDays = $userAttendances->filter(fn($a) => $a->clock_out)->groupBy(fn($a) => $a->clock_in->toDateString())->count();
                $totalWork = 0;
                foreach ($userAttendances as $a) {
                    if ($a->clock_out) {
                        $totalWork += app(\App\Services\TimeService::class)->calculateWorkingMinutesWithCutoff($a->clock_in, $a->clock_out, $a->breakRecords, $rounding, $rule) ?? 0;
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
                    <th class="px-3 py-2 text-left">回</th>
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
                    <td class="px-3 py-2 font-mono">
                        {{ $att->clock_in->format('m/d') }}
                        @if($att->modification_count > 0)
                            <span class="ml-1 text-xs bg-amber-100 text-amber-700 px-1 rounded">修正済</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-mono text-gray-400">{{ $att->session_number > 1 ? $att->session_number : '' }}</td>
                    @php
                        $effectiveClockIn = app(\App\Services\TimeService::class)->getEffectiveClockIn($att->clock_in, $rule, $att->session_number ?? 1);
                        $cutoffApplied = !$effectiveClockIn->eq($att->clock_in);
                    @endphp
                    <td class="px-3 py-2 font-mono">
                        {{ $effectiveClockIn->format('H:i') }}
                        @if($cutoffApplied)
                            <span class="text-xs text-amber-600 block">実際: {{ $att->clock_in->format('H:i') }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-mono">{{ $att->clock_out?->format('H:i') ?? '-' }}</td>
                    <td class="px-3 py-2 font-mono">{{ app(\App\Services\TimeService::class)->calculateBreakMinutes($att->breakRecords) }}分</td>
                    @php
                        $wm = app(\App\Services\TimeService::class)->calculateWorkingMinutesWithCutoff($att->clock_in, $att->clock_out, $att->breakRecords, $rounding, $rule);
                    @endphp
                    <td class="px-3 py-2 font-mono">{{ $wm !== null ? floor($wm/60).':'.str_pad($wm%60,2,'0',STR_PAD_LEFT) : '-' }}</td>
                    <td class="px-3 py-2 text-gray-500">{{ $att->note ?? '' }}</td>
                    <td class="px-3 py-2">
                        <button onclick="document.getElementById('edit-{{ $att->id }}').classList.toggle('hidden')"
                            class="text-indigo-600 text-xs hover:underline">編集</button>
                    </td>
                </tr>
                <tr id="edit-{{ $att->id }}" class="hidden bg-gray-50">
                    <td colspan="8" class="px-3 py-3">
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
                                <label class="text-xs text-gray-500">回</label>
                                <input type="number" name="session_number" value="{{ $att->session_number }}" min="1" max="10" class="text-sm border rounded px-2 py-1 w-16">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">備考</label>
                                <input type="text" name="note" value="{{ $att->note }}" class="text-sm border rounded px-2 py-1">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">修正理由</label>
                                <input type="text" name="reason" placeholder="例: 打刻漏れ" class="text-sm border rounded px-2 py-1">
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">保存</button>
                        </form>
                        @if($att->auditLogs->isNotEmpty())
                        <div class="mt-4 border-t pt-3">
                            <p class="text-xs font-semibold text-gray-600 mb-2">修正履歴</p>
                            <div class="space-y-1">
                                @foreach($att->auditLogs as $log)
                                <div class="text-xs text-gray-500 bg-gray-50 rounded px-2 py-1">
                                    <span class="font-medium text-gray-700">{{ $log->created_at->format('Y/m/d H:i') }}</span>
                                    <span class="ml-1">{{ $log->user_name ?? '不明' }}</span>
                                    @if($log->old_values && $log->new_values)
                                        @php
                                            $fieldLabels = ['clock_in' => '出勤', 'clock_out' => '退勤', 'note' => '備考', 'session_number' => '回'];
                                            $changed = array_intersect_key($log->new_values, $log->old_values ?? []);
                                        @endphp
                                        @foreach($changed as $field => $newVal)
                                            @if(isset($fieldLabels[$field]))
                                            <span class="ml-1 text-gray-400">{{ $fieldLabels[$field] }}:
                                                {{ $log->old_values[$field] ?? '-' }} → {{ $newVal ?? '-' }}
                                            </span>
                                            @endif
                                        @endforeach
                                    @endif
                                    @if($log->reason)
                                        <span class="ml-1 text-indigo-600">（{{ $log->reason }}）</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
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
