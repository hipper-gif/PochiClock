@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">勤怠履歴</h1>
        <div class="flex items-center space-x-4">
            @php
                $prev = \Carbon\Carbon::create($year, $month, 1)->subMonth();
                $next = \Carbon\Carbon::create($year, $month, 1)->addMonth();
            @endphp
            <a href="?year={{ $prev->year }}&month={{ $prev->month }}" class="text-sm text-indigo-600 hover:underline">&larr; 前月</a>
            <span class="text-lg font-semibold">{{ $year }}年{{ $month }}月</span>
            <a href="?year={{ $next->year }}&month={{ $next->month }}" class="text-sm text-indigo-600 hover:underline">次月 &rarr;</a>
        </div>
    </div>

    {{-- 集計 --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">稼働日数</p>
            <p class="text-2xl font-bold">{{ $totalWorkDays }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">拘束時間</p>
            <p class="text-2xl font-bold">{{ floor($totalBindingMinutes / 60) }}:{{ str_pad($totalBindingMinutes % 60, 2, '0', STR_PAD_LEFT) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">実働時間</p>
            <p class="text-2xl font-bold">{{ floor($totalWorkingMinutes / 60) }}:{{ str_pad($totalWorkingMinutes % 60, 2, '0', STR_PAD_LEFT) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">休憩合計</p>
            <p class="text-2xl font-bold">{{ floor($totalBreakMinutes / 60) }}:{{ str_pad($totalBreakMinutes % 60, 2, '0', STR_PAD_LEFT) }}</p>
        </div>
    </div>

    {{-- テーブル --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-gray-600">日付</th>
                    <th class="px-4 py-3 text-left text-gray-600">回</th>
                    <th class="px-4 py-3 text-left text-gray-600">出勤</th>
                    <th class="px-4 py-3 text-left text-gray-600">退勤</th>
                    <th class="px-4 py-3 text-left text-gray-600">休憩</th>
                    <th class="px-4 py-3 text-left text-gray-600">拘束</th>
                    <th class="px-4 py-3 text-left text-gray-600">実働</th>
                    <th class="px-4 py-3 text-left text-gray-600">備考</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($groupedRecords as $date => $dayRecords)
                    @php
                        $dayTotalWorkMin = 0;
                        $dayTotalBindMin = 0;
                        $dayTotalBreakMin = 0;
                    @endphp
                    @foreach($dayRecords as $record)
                        @php
                            $rounded = app(\App\Services\TimeService::class)->getRoundedTimesWithCutoff($record->clock_in, $record->clock_out, $rounding, $rule, $record->session_number ?? 1);
                            $breakMin = app(\App\Services\TimeService::class)->calculateBreakMinutes($record->breakRecords);
                            $bindMin = $rounded['rounded_clock_out'] ? $rounded['rounded_clock_in']->diffInMinutes($rounded['rounded_clock_out']) : null;
                            $workMin = $bindMin !== null ? max(0, $bindMin - $breakMin) : null;
                            if ($bindMin !== null) {
                                $dayTotalBindMin += $bindMin;
                                $dayTotalBreakMin += $breakMin;
                                $dayTotalWorkMin += $workMin;
                            }
                        @endphp
                        <tr class="hover:bg-gray-50">
                            @if($loop->first)
                                <td class="px-4 py-3 font-mono" rowspan="{{ $dayRecords->count() }}">{{ $record->clock_in->format('m/d') }}<span class="text-gray-400 ml-1">{{ $record->clock_in->isoFormat('ddd') }}</span></td>
                            @endif
                            <td class="px-4 py-3 font-mono text-gray-400">{{ $dayRecords->count() > 1 ? $record->session_number : '' }}</td>
                            <td class="px-4 py-3 font-mono">
                                {{ $rounded['rounded_clock_in']->format('H:i') }}
                                @if($rounded['cutoff_applied'] ?? false)
                                    <span class="text-xs text-amber-600 block" title="早出カット適用">実際: {{ $rounded['actual_clock_in']->format('H:i') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono">{{ $rounded['rounded_clock_out'] ? $rounded['rounded_clock_out']->format('H:i') : '--:--' }}</td>
                            <td class="px-4 py-3 font-mono">{{ $breakMin > 0 ? floor($breakMin / 60) . ':' . str_pad($breakMin % 60, 2, '0', STR_PAD_LEFT) : '-' }}</td>
                            <td class="px-4 py-3 font-mono">{{ $bindMin !== null ? floor($bindMin / 60) . ':' . str_pad($bindMin % 60, 2, '0', STR_PAD_LEFT) : '-' }}</td>
                            <td class="px-4 py-3 font-mono">{{ $workMin !== null ? floor($workMin / 60) . ':' . str_pad($workMin % 60, 2, '0', STR_PAD_LEFT) : '-' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->note ?? '' }}</td>
                        </tr>
                    @endforeach
                    {{-- 日別小計（複数セッションの場合のみ） --}}
                    @if($dayRecords->count() > 1)
                        <tr class="bg-gray-50 border-t border-gray-200">
                            <td class="px-4 py-2 text-right text-xs text-gray-500" colspan="5">日計</td>
                            <td class="px-4 py-2 font-mono text-xs font-semibold">{{ floor($dayTotalBindMin / 60) }}:{{ str_pad($dayTotalBindMin % 60, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-4 py-2 font-mono text-xs font-semibold">{{ floor($dayTotalWorkMin / 60) }}:{{ str_pad($dayTotalWorkMin % 60, 2, '0', STR_PAD_LEFT) }}</td>
                            <td></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">この月の勤怠データはありません</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
