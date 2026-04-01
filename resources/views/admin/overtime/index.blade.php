@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">残業管理</h1>
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

{{-- 36協定説明 --}}
<div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
    <strong>36協定上限:</strong> 月45時間 / 年360時間。月40時間超で警告、月45時間超で超過バッジを表示します。
</div>

{{-- 残業一覧テーブル --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b text-gray-500 text-xs">
                <th class="px-4 py-3 text-left">氏名</th>
                <th class="px-4 py-3 text-left">部署</th>
                <th class="px-4 py-3 text-right">当月残業</th>
                <th class="px-4 py-3 text-right">年間残業</th>
                <th class="px-4 py-3 text-left">ステータス</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($overtimeData as $row)
            @php
                $mMin = $row['monthly_overtime_min'];
                $yMin = $row['yearly_overtime_min'];
                $mH   = floor($mMin / 60);
                $mM   = str_pad($mMin % 60, 2, '0', STR_PAD_LEFT);
                $yH   = floor($yMin / 60);
                $yM   = str_pad($yMin % 60, 2, '0', STR_PAD_LEFT);

                if ($row['monthly_warning']) {
                    $mColor = 'text-red-600 font-bold';
                } elseif ($row['monthly_danger']) {
                    $mColor = 'text-amber-600';
                } else {
                    $mColor = 'text-green-600';
                }
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['user']->name }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $row['user']->department?->name ?? '未所属' }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $mColor }}">{{ $mH }}:{{ $mM }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $row['yearly_warning'] ? 'text-red-600 font-bold' : 'text-gray-700' }}">{{ $yH }}:{{ $yM }}</td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        @if($row['monthly_warning'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">36協定超過</span>
                        @endif
                        @if($row['yearly_warning'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">年間上限超過</span>
                        @endif
                        @if(!$row['monthly_warning'] && !$row['yearly_warning'])
                            <span class="text-gray-400 text-xs">異常なし</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-400">データがありません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
