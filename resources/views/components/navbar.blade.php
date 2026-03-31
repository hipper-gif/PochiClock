<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-8">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold text-indigo-600">PochiClock</a>
                <a href="{{ route('dashboard') }}" class="text-sm {{ request()->routeIs('dashboard') ? 'text-indigo-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">ダッシュボード</a>
                <a href="{{ route('dashboard.history') }}" class="text-sm {{ request()->routeIs('dashboard.history') ? 'text-indigo-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">勤怠履歴</a>
                <a href="{{ route('profile.show') }}" class="text-sm {{ request()->routeIs('profile.show') ? 'text-indigo-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">プロフィール</a>
                <a href="{{ route('qr.show') }}" class="text-sm {{ request()->routeIs('qr.show') ? 'text-indigo-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">QRコード</a>
                @if(auth()->user()->isAdminOrManager())
                    <span class="text-gray-300">|</span>
                    <a href="{{ auth()->user()->isAdmin() ? route('admin.users.index') : route('admin.attendance.index') }}" class="text-sm {{ request()->is('admin/*') ? 'text-red-600 font-semibold' : 'text-gray-600 hover:text-gray-900' }}">管理画面</a>
                @endif
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                @if(auth()->user()->isAdmin())
                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">管理者</span>
                @elseif(auth()->user()->isManager())
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">部門長</span>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">ログアウト</button>
                </form>
            </div>
        </div>

        @if(request()->is('admin/*'))
        <div class="flex space-x-6 border-t py-2">
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}" class="text-sm {{ request()->routeIs('admin.users.*') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">ユーザー</a>
                <a href="{{ route('admin.departments.index') }}" class="text-sm {{ request()->routeIs('admin.departments.*') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">部署</a>
                <a href="{{ route('admin.job-groups.index') }}" class="text-sm {{ request()->routeIs('admin.job-groups.*') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">職種グループ</a>
            @endif
            <a href="{{ route('admin.attendance.index') }}" class="text-sm {{ request()->routeIs('admin.attendance.*') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">勤怠管理</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.settings.index') }}" class="text-sm {{ request()->routeIs('admin.settings.*') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">勤務ルール</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="text-sm {{ request()->routeIs('admin.audit-logs.*') ? 'text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">監査ログ</a>
            @endif
        </div>
        @endif
    </div>
</nav>
