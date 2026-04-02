@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">シフト管理</h1>
    @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.shifts.templates') }}"
           class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">テンプレート管理</a>
    @endif
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

{{-- テンプレート凡例 --}}
@if($templates->isNotEmpty())
<div class="flex flex-wrap items-center gap-3 mb-4">
    <span class="text-xs text-gray-500">凡例:</span>
    @foreach($templates as $tpl)
        <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded"
              style="background-color: {{ $tpl->color }}20; color: {{ $tpl->color }}; border: 1px solid {{ $tpl->color }}40;">
            <span class="w-3 h-3 rounded-sm inline-block" style="background-color: {{ $tpl->color }};"></span>
            {{ $tpl->name }} ({{ $tpl->start_time }}-{{ $tpl->end_time }})
        </span>
    @endforeach
</div>
@endif

{{-- カレンダーグリッド --}}
@if($users->isNotEmpty() && $templates->isNotEmpty())
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="text-xs border-collapse w-full">
            <thead>
                <tr class="border-b">
                    <th class="sticky left-0 bg-white z-10 px-2 py-2 text-left font-semibold text-gray-700 min-w-[100px] border-r">名前</th>
                    @foreach($days as $day)
                        @php
                            $bgClass = '';
                            if ($day['dow'] === 0) $bgClass = 'bg-red-50';
                            elseif ($day['dow'] === 6) $bgClass = 'bg-blue-50';
                        @endphp
                        <th class="px-0 py-1 text-center font-normal w-8 min-w-[2rem] {{ $bgClass }}">
                            <div class="{{ $day['dow'] === 0 ? 'text-red-500' : ($day['dow'] === 6 ? 'text-blue-500' : 'text-gray-500') }}">
                                {{ ['日','月','火','水','木','金','土'][$day['dow']] }}
                            </div>
                            <div class="font-semibold text-gray-700">{{ $day['day'] }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($users as $u)
                    @php $userAssignments = $assignments->get($u->id, collect())->keyBy(fn($a) => $a->date->format('Y-m-d')); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="sticky left-0 bg-white z-10 px-2 py-1 font-medium text-gray-700 border-r whitespace-nowrap">
                            {{ $u->name }}
                        </td>
                        @foreach($days as $day)
                            @php
                                $bgClass = '';
                                if ($day['dow'] === 0) $bgClass = 'bg-red-50';
                                elseif ($day['dow'] === 6) $bgClass = 'bg-blue-50';
                                $sa = $userAssignments->get($day['date']);
                            @endphp
                            <td class="px-0 py-1 text-center {{ $bgClass }} border-l border-gray-50">
                                @if($sa)
                                    <div class="w-7 h-7 mx-auto rounded-sm flex items-center justify-center text-white text-[10px] font-bold cursor-pointer relative group"
                                         style="background-color: {{ $sa->shiftTemplate->color }};"
                                         title="{{ $sa->shiftTemplate->name }} {{ $sa->shiftTemplate->start_time }}-{{ $sa->shiftTemplate->end_time }}{{ $sa->note ? ' / '.$sa->note : '' }}">
                                        {{ mb_substr($sa->shiftTemplate->name, 0, 1) }}
                                        {{-- 削除ボタン（ホバー時表示） --}}
                                        <form method="POST" action="{{ route('admin.shifts.removeAssignment', $sa) }}"
                                              class="absolute -top-2 -right-2 hidden group-hover:block"
                                              onsubmit="return confirm('このシフト割当を解除しますか？')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="w-4 h-4 bg-red-500 text-white rounded-full text-[8px] leading-none flex items-center justify-center hover:bg-red-600">x</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="w-7 h-7 mx-auto"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@elseif($templates->isEmpty())
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-sm text-yellow-700">
        シフトテンプレートが未登録です。
        <a href="{{ route('admin.shifts.templates') }}" class="underline font-semibold">テンプレート管理</a>から登録してください。
    </div>
@endif

{{-- シフト割当フォーム --}}
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">シフト割当</h2>
    <form method="POST" action="{{ route('admin.shifts.assign') }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="text-xs text-gray-500 block mb-1">スタッフ</label>
            <select name="user_id" required class="text-sm border rounded px-2 py-1.5">
                <option value="">選択</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">シフト</label>
            <select name="shift_template_id" required class="text-sm border rounded px-2 py-1.5">
                <option value="">選択</option>
                @foreach($templates as $tpl)
                    <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->start_time }}-{{ $tpl->end_time }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">日付</label>
            <input type="date" name="date" required class="text-sm border rounded px-2 py-1.5"
                   value="{{ $year }}-{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}-01">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">備考</label>
            <input type="text" name="note" class="text-sm border rounded px-2 py-1.5" placeholder="任意">
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm hover:bg-indigo-700">割当</button>
    </form>
</div>

{{-- 一括割当フォーム --}}
<div class="bg-white rounded-lg shadow p-4">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">一括割当（週間パターン）</h2>
    <form method="POST" action="{{ route('admin.shifts.bulkAssign') }}" id="bulkForm">
        @csrf
        <div class="flex flex-wrap items-end gap-3 mb-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">スタッフ</label>
                <select id="bulk_user_id" required class="text-sm border rounded px-2 py-1.5">
                    <option value="">選択</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">シフト</label>
                <select id="bulk_template_id" required class="text-sm border rounded px-2 py-1.5">
                    <option value="">選択</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->start_time }}-{{ $tpl->end_time }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">開始日</label>
                <input type="date" id="bulk_start" required class="text-sm border rounded px-2 py-1.5"
                       value="{{ $year }}-{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}-01">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">終了日</label>
                <input type="date" id="bulk_end" required class="text-sm border rounded px-2 py-1.5"
                       value="{{ $year }}-{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}-{{ str_pad($daysInMonth, 2, '0', STR_PAD_LEFT) }}">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500">除外:</label>
                <label class="text-xs"><input type="checkbox" id="exclude_sun" checked class="mr-0.5">日</label>
                <label class="text-xs"><input type="checkbox" id="exclude_sat" class="mr-0.5">土</label>
            </div>
            <button type="button" onclick="generateBulk()" class="bg-green-600 text-white px-4 py-1.5 rounded text-sm hover:bg-green-700">一括割当</button>
        </div>
        <div id="bulkAssignments"></div>
    </form>
</div>

<script>
function generateBulk() {
    const userId = document.getElementById('bulk_user_id').value;
    const templateId = document.getElementById('bulk_template_id').value;
    const startDate = new Date(document.getElementById('bulk_start').value);
    const endDate = new Date(document.getElementById('bulk_end').value);
    const excludeSun = document.getElementById('exclude_sun').checked;
    const excludeSat = document.getElementById('exclude_sat').checked;

    if (!userId || !templateId || !startDate || !endDate) {
        alert('全項目を入力してください');
        return;
    }

    const container = document.getElementById('bulkAssignments');
    container.innerHTML = '';

    let idx = 0;
    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
        const dow = d.getDay();
        if (excludeSun && dow === 0) continue;
        if (excludeSat && dow === 6) continue;

        const dateStr = d.toISOString().split('T')[0];
        container.innerHTML += `
            <input type="hidden" name="assignments[${idx}][user_id]" value="${userId}">
            <input type="hidden" name="assignments[${idx}][shift_template_id]" value="${templateId}">
            <input type="hidden" name="assignments[${idx}][date]" value="${dateStr}">
        `;
        idx++;
    }

    if (idx === 0) {
        alert('対象日がありません');
        return;
    }

    if (confirm(idx + '件のシフトを割り当てますか？')) {
        document.getElementById('bulkForm').submit();
    }
}
</script>

@if($users->isEmpty())
    <p class="text-center text-gray-400 py-8">対象ユーザーがいません</p>
@endif
@endsection
