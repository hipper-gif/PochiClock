@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">勤務ルール設定</h1>

{{-- システムルール --}}
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">システムデフォルト</h2>
    <form method="POST" action="{{ route('admin.settings.upsertSystem') }}">
        @csrf
        @include('admin.settings._rule_form', ['rule' => $systemRule])
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">保存</button>
    </form>
</div>

{{-- 部署別ルール --}}
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">部署別ルール</h2>
    @foreach($departmentRules as $dr)
        <div class="border rounded p-4 mb-4">
            <div class="flex justify-between items-center mb-3">
                <span class="font-medium">{{ $dr->department?->name ?? '不明' }}</span>
                <form method="POST" action="{{ route('admin.settings.destroy', $dr) }}" onsubmit="return confirm('削除しますか？')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 text-xs hover:underline">削除</button>
                </form>
            </div>
            <form method="POST" action="{{ route('admin.settings.upsertDepartment') }}">
                @csrf
                <input type="hidden" name="department_id" value="{{ $dr->department_id }}">
                @include('admin.settings._rule_form', ['rule' => $dr])
                <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">保存</button>
            </form>
        </div>
    @endforeach

    <details class="mt-4">
        <summary class="cursor-pointer text-indigo-600 text-sm">部署ルールを追加</summary>
        <form method="POST" action="{{ route('admin.settings.upsertDepartment') }}" class="mt-3 border rounded p-4">
            @csrf
            <div class="mb-4">
                <label class="text-sm text-gray-600">部署</label>
                <select name="department_id" class="w-full border rounded px-3 py-2 text-sm" required>
                    <option value="">選択してください</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('admin.settings._rule_form', ['rule' => null])
            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">追加</button>
        </form>
    </details>
</div>

{{-- 個人ルール --}}
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">個人ルール</h2>
    @foreach($userRules as $ur)
        <div class="border rounded p-4 mb-4">
            <div class="flex justify-between items-center mb-3">
                <span class="font-medium">{{ $ur->user?->name ?? '不明' }}</span>
                <form method="POST" action="{{ route('admin.settings.destroy', $ur) }}" onsubmit="return confirm('削除しますか？')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 text-xs hover:underline">削除</button>
                </form>
            </div>
            <form method="POST" action="{{ route('admin.settings.upsertUser') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $ur->user_id }}">
                @include('admin.settings._rule_form', ['rule' => $ur])
                <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">保存</button>
            </form>
        </div>
    @endforeach

    <details class="mt-4">
        <summary class="cursor-pointer text-indigo-600 text-sm">個人ルールを追加</summary>
        <form method="POST" action="{{ route('admin.settings.upsertUser') }}" class="mt-3 border rounded p-4">
            @csrf
            <div class="mb-4">
                <label class="text-sm text-gray-600">ユーザー</label>
                <select name="user_id" class="w-full border rounded px-3 py-2 text-sm" required>
                    <option value="">選択してください</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}（{{ $u->employee_number }}）</option>
                    @endforeach
                </select>
            </div>
            @include('admin.settings._rule_form', ['rule' => null])
            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm">追加</button>
        </form>
    </details>
</div>

{{-- 適用状況 --}}
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4">適用状況</h2>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left">名前</th>
                <th class="px-3 py-2 text-left">部署</th>
                <th class="px-3 py-2 text-left">適用元</th>
                <th class="px-3 py-2 text-left">始業</th>
                <th class="px-3 py-2 text-left">終業</th>
                <th class="px-3 py-2 text-left">休憩</th>
                <th class="px-3 py-2 text-left">早出カット</th>
                <th class="px-3 py-2 text-left">丸め</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($allUsersRules as $item)
            <tr>
                <td class="px-3 py-2">{{ $item['user']->name }}</td>
                <td class="px-3 py-2 text-gray-500">{{ $item['user']->department?->name ?? '-' }}</td>
                <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded bg-gray-100">{{ $item['rule']['source'] }}</span></td>
                <td class="px-3 py-2 font-mono">{{ $item['rule']['work_start_time'] }}</td>
                <td class="px-3 py-2 font-mono">{{ $item['rule']['work_end_time'] }}</td>
                <td class="px-3 py-2">{{ $item['rule']['default_break_minutes'] }}分</td>
                <td class="px-3 py-2 font-mono text-xs">
                    @if($item['rule']['early_clock_in_cutoff'])
                        {{ $item['rule']['early_clock_in_cutoff'] }}
                        @if($item['rule']['early_clock_in_cutoff_pm'])
                            <br><span class="text-gray-400">午後:</span> {{ $item['rule']['early_clock_in_cutoff_pm'] }}
                        @endif
                    @else
                        <span class="text-gray-300">-</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-xs">{{ $item['rule']['rounding_unit'] }}分</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
