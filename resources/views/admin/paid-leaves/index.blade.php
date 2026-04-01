@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">有給管理</h1>

{{-- フィルター --}}
<div class="flex items-center space-x-4 mb-6">
    <select onchange="location.href='?department_id='+this.value" class="text-sm border rounded px-2 py-1">
        <option value="">全部署</option>
        @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ $departmentId === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
        @endforeach
    </select>
</div>

{{-- 残日数一覧 --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-gray-50 px-4 py-3 border-b">
        <h2 class="font-semibold text-gray-700">残日数一覧</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-gray-500 text-xs">
                <th class="px-3 py-2 text-left">氏名</th>
                <th class="px-3 py-2 text-left">部署</th>
                <th class="px-3 py-2 text-left">入社日</th>
                <th class="px-3 py-2 text-right">付与合計</th>
                <th class="px-3 py-2 text-right">使用済</th>
                <th class="px-3 py-2 text-right">残日数</th>
                <th class="px-3 py-2 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($users as $user)
            @php $summary = $balanceSummary[$user->id] ?? ['granted' => 0, 'used' => 0, 'remaining' => 0]; @endphp
            <tr>
                <td class="px-3 py-2 font-semibold">{{ $user->name }}</td>
                <td class="px-3 py-2 text-gray-500">{{ $user->department?->name ?? '未所属' }}</td>
                <td class="px-3 py-2 font-mono">{{ $user->hire_date?->format('Y/m/d') ?? '-' }}</td>
                <td class="px-3 py-2 text-right font-mono">{{ number_format($summary['granted'], 1) }}</td>
                <td class="px-3 py-2 text-right font-mono">{{ number_format($summary['used'], 1) }}</td>
                <td class="px-3 py-2 text-right font-mono font-semibold {{ $summary['remaining'] <= 0 ? 'text-red-600' : 'text-green-600' }}">
                    {{ number_format($summary['remaining'], 1) }}
                </td>
                <td class="px-3 py-2">
                    <button onclick="openGrantModal('{{ $user->id }}', '{{ $user->name }}')"
                        class="text-indigo-600 text-xs hover:underline">手動付与</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($users->isEmpty())
        <p class="text-center text-gray-400 py-8">ユーザーがいません</p>
    @endif
</div>

{{-- 申請一覧 --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-gray-50 px-4 py-3 border-b">
        <h2 class="font-semibold text-gray-700">有給申請一覧</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-gray-500 text-xs">
                <th class="px-3 py-2 text-left">氏名</th>
                <th class="px-3 py-2 text-left">取得日</th>
                <th class="px-3 py-2 text-left">種別</th>
                <th class="px-3 py-2 text-left">理由</th>
                <th class="px-3 py-2 text-left">ステータス</th>
                <th class="px-3 py-2 text-left">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($applications as $app)
            <tr>
                <td class="px-3 py-2 font-semibold">{{ $app->user->name }}</td>
                <td class="px-3 py-2 font-mono">{{ $app->leave_date->format('Y/m/d') }}</td>
                <td class="px-3 py-2">{{ $app->leave_type_label }}</td>
                <td class="px-3 py-2 text-gray-500">{{ $app->reason ?? '-' }}</td>
                <td class="px-3 py-2">
                    @if($app->status === 'pending')
                        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded">申請中</span>
                    @elseif($app->status === 'approved')
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">承認済</span>
                    @else
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">却下</span>
                    @endif
                </td>
                <td class="px-3 py-2">
                    @if($app->status === 'pending')
                        <form method="POST" action="{{ route('admin.paid-leaves.approve', $app) }}" class="inline">
                            @csrf @method('PUT')
                            <button type="submit" class="text-green-600 text-xs hover:underline mr-2">承認</button>
                        </form>
                        <form method="POST" action="{{ route('admin.paid-leaves.reject', $app) }}" class="inline">
                            @csrf @method('PUT')
                            <button type="submit" class="text-red-600 text-xs hover:underline">却下</button>
                        </form>
                    @else
                        <span class="text-xs text-gray-400">
                            {{ $app->approver?->name }} ({{ $app->approved_at?->format('m/d H:i') }})
                        </span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($applications->isEmpty())
        <p class="text-center text-gray-400 py-8">申請はありません</p>
    @endif
</div>

{{-- 有給申請フォーム --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-gray-50 px-4 py-3 border-b">
        <h2 class="font-semibold text-gray-700">有給申請（代理申請）</h2>
    </div>
    <div class="px-4 py-4">
        <form method="POST" action="{{ route('admin.paid-leaves.apply') }}" class="flex items-end space-x-3 flex-wrap gap-y-2">
            @csrf
            <div>
                <label class="text-xs text-gray-500">対象者</label>
                <select name="user_id" required class="text-sm border rounded px-2 py-1 block">
                    <option value="">選択してください</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}（{{ $user->department?->name ?? '未所属' }}）</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">取得日</label>
                <input type="date" name="leave_date" required class="text-sm border rounded px-2 py-1 block">
            </div>
            <div>
                <label class="text-xs text-gray-500">種別</label>
                <select name="leave_type" class="text-sm border rounded px-2 py-1 block">
                    <option value="full">全休</option>
                    <option value="half_am">半休（午前）</option>
                    <option value="half_pm">半休（午後）</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">理由</label>
                <input type="text" name="reason" class="text-sm border rounded px-2 py-1 block" placeholder="任意">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-1 rounded text-sm hover:bg-indigo-700">申請</button>
        </form>
    </div>
</div>

{{-- 手動付与モーダル --}}
<div id="grantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">有給手動付与</h3>
        <form method="POST" action="{{ route('admin.paid-leaves.grant') }}">
            @csrf
            <input type="hidden" name="user_id" id="grantUserId">
            <div class="mb-3">
                <label class="text-sm text-gray-600">対象者</label>
                <p id="grantUserName" class="font-semibold"></p>
            </div>
            <div class="mb-3">
                <label class="text-sm text-gray-600">付与日数</label>
                <input type="number" name="granted_days" step="0.5" min="0.5" max="40" required
                    class="w-full text-sm border rounded px-2 py-1">
            </div>
            <div class="mb-4">
                <label class="text-sm text-gray-600">付与日</label>
                <input type="date" name="grant_date" value="{{ date('Y-m-d') }}" required
                    class="w-full text-sm border rounded px-2 py-1">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeGrantModal()" class="px-4 py-1 text-sm text-gray-600 hover:text-gray-800">キャンセル</button>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-1 rounded text-sm hover:bg-indigo-700">付与</button>
            </div>
        </form>
    </div>
</div>

{{-- 一括自動付与 --}}
@if(auth()->user()->isAdmin())
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-gray-50 px-4 py-3 border-b">
        <h2 class="font-semibold text-gray-700">一括自動付与</h2>
    </div>
    <div class="px-4 py-4">
        <p class="text-sm text-gray-600 mb-3">入社日と週所定労働日数をもとに、法定基準で有給を一括付与します。既に同日付与済みのユーザーはスキップされます。</p>
        <form method="POST" action="{{ route('admin.paid-leaves.autoGrant') }}" onsubmit="return confirm('全対象ユーザーに自動付与しますか？')">
            @csrf
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700">一括自動付与を実行</button>
        </form>
    </div>
</div>
@endif

<script>
function openGrantModal(userId, userName) {
    document.getElementById('grantUserId').value = userId;
    document.getElementById('grantUserName').textContent = userName;
    document.getElementById('grantModal').classList.remove('hidden');
}
function closeGrantModal() {
    document.getElementById('grantModal').classList.add('hidden');
}
</script>
@endsection
