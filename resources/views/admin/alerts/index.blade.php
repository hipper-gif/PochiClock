@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">アラート</h1>
</div>

{{-- 日付選択 --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('admin.alerts.index') }}" class="flex items-center space-x-3">
        <label class="text-sm text-gray-600 font-medium">確認日:</label>
        <input type="date" name="date" value="{{ $date }}"
               class="text-sm border rounded px-2 py-1">
        <button type="submit"
                class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm hover:bg-indigo-700">確認</button>
    </form>
    <p class="mt-2 text-sm text-gray-500">
        対象日: <span class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse($date)->format('Y年m月d日') }}</span>
    </p>
</div>

{{-- 未打刻アラート --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-rose-50 px-4 py-3 border-b border-rose-100">
        <h2 class="font-semibold text-rose-800">
            未打刻アラート
            <span class="ml-2 text-sm font-normal">({{ $missingClockOuts->count() }}件)</span>
        </h2>
    </div>

    @if($missingClockOuts->isEmpty())
        <div class="px-4 py-6 text-center text-green-700 text-sm">
            ✓ 問題ありません
        </div>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-gray-500 text-xs">
                    <th class="px-4 py-2 text-left">氏名</th>
                    <th class="px-4 py-2 text-left">部署</th>
                    <th class="px-4 py-2 text-left">出勤時刻</th>
                    <th class="px-4 py-2 text-left">状態</th>
                    <th class="px-4 py-2 text-left">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($missingClockOuts as $att)
                <tr>
                    <td class="px-4 py-2 font-medium">{{ $att->user?->name ?? '-' }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $att->user?->department?->name ?? '未所属' }}</td>
                    <td class="px-4 py-2 font-mono">{{ $att->clock_in->format('H:i') }}</td>
                    <td class="px-4 py-2">
                        <span class="inline-block bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded">退勤未打刻</span>
                    </td>
                    <td class="px-4 py-2">
                        <a href="{{ route('admin.attendance.index', ['user_id' => $att->user_id]) }}"
                           class="text-indigo-600 text-xs hover:underline">勤怠確認</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- シフト超過アラート --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-amber-50 px-4 py-3 border-b border-amber-100">
        <h2 class="font-semibold text-amber-800">
            シフト超過アラート
            <span class="ml-2 text-sm font-normal">({{ $shiftOvertime->count() }}件)</span>
        </h2>
    </div>

    @if($shiftOvertime->isEmpty())
        <div class="px-4 py-6 text-center text-green-700 text-sm">
            ✓ 問題ありません
        </div>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-gray-500 text-xs">
                    <th class="px-4 py-2 text-left">氏名</th>
                    <th class="px-4 py-2 text-left">部署</th>
                    <th class="px-4 py-2 text-left">所定終業</th>
                    <th class="px-4 py-2 text-left">実際退勤</th>
                    <th class="px-4 py-2 text-left">超過</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($shiftOvertime as $item)
                @php $att = $item['attendance']; @endphp
                <tr>
                    <td class="px-4 py-2 font-medium">{{ $att->user?->name ?? '-' }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $att->user?->department?->name ?? '未所属' }}</td>
                    <td class="px-4 py-2 font-mono">{{ $item['work_end_time'] }}</td>
                    <td class="px-4 py-2 font-mono">{{ $att->clock_out->format('H:i') }}</td>
                    <td class="px-4 py-2">
                        <span class="inline-block bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded">{{ $item['overtime_minutes'] }}分超過</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
