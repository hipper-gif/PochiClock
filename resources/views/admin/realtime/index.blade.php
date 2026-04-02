@extends('layouts.app')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">リアルタイム出勤状況</h1>
    <div class="flex items-center space-x-3">
        <span class="text-sm text-gray-500">最終更新: <span id="updated-at">--:--:--</span></span>
        <button onclick="fetchData()" class="text-sm bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700">更新</button>
    </div>
</div>

<div id="dashboard-container">
    <p class="text-gray-400 text-center py-12">読み込み中...</p>
</div>

<script>
const STATUS_CONFIG = {
    present:  { label: '出勤中', bg: 'bg-green-100', text: 'text-green-700', dot: 'bg-green-500' },
    on_break: { label: '休憩中', bg: 'bg-yellow-100', text: 'text-yellow-700', dot: 'bg-yellow-500' },
    left:     { label: '退勤済', bg: 'bg-blue-100', text: 'text-blue-700', dot: 'bg-blue-400' },
    absent:   { label: '未出勤', bg: 'bg-gray-100', text: 'text-gray-500', dot: 'bg-gray-300' },
};

async function fetchData() {
    try {
        const res = await fetch('{{ route("admin.realtime.data") }}');
        const data = await res.json();
        renderDashboard(data);
    } catch (e) {
        console.error('Failed to fetch', e);
    }
}

function renderDashboard(data) {
    document.getElementById('updated-at').textContent = data.updated_at;
    const container = document.getElementById('dashboard-container');

    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';

    data.departments.forEach(dept => {
        const total = dept.users.length;
        html += `
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">${dept.name}</h2>
                <div class="flex space-x-2 text-xs">
                    <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700">${dept.stats.present}名出勤</span>
                    <span class="px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">${dept.stats.on_break}名休憩</span>
                </div>
            </div>
            <div class="space-y-1">`;

        dept.users.forEach(user => {
            const cfg = STATUS_CONFIG[user.status];
            html += `
                <div class="flex items-center justify-between py-1.5 px-2 rounded ${cfg.bg}">
                    <div class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full ${cfg.dot}"></span>
                        <span class="text-sm font-medium ${cfg.text}">${user.name}</span>
                    </div>
                    <div class="text-xs ${cfg.text}">
                        ${user.clock_in ? user.clock_in : ''}
                        ${user.clock_out ? ' - ' + user.clock_out : ''}
                    </div>
                </div>`;
        });

        html += `</div></div>`;
    });

    html += '</div>';
    container.innerHTML = html;
}

// Initial fetch + auto-refresh every 30s
fetchData();
setInterval(fetchData, 30000);
</script>
@endsection
