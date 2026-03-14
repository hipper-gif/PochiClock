@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">おはようございます、{{ $user->name }} さん</h1>
        <p class="text-gray-500 mt-1">{{ now()->isoFormat('Y年M月D日（ddd）') }}</p>
    </div>

    {{-- ステータス --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">本日の勤怠</h2>
            @php
                $statusLabels = [
                    'not_started' => ['未出勤', 'bg-gray-100 text-gray-600'],
                    'clocked_in' => ['出勤中', 'bg-green-100 text-green-700'],
                    'on_break' => ['休憩中', 'bg-yellow-100 text-yellow-700'],
                    'clocked_out' => ['退勤済', 'bg-blue-100 text-blue-700'],
                ];
                [$label, $class] = $statusLabels[$status];
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $class }}">{{ $label }}</span>
        </div>

        <div class="grid grid-cols-5 gap-4 text-center">
            <div>
                <p class="text-xs text-gray-500">出勤</p>
                <p class="text-lg font-mono">{{ $roundedTimes ? $roundedTimes['rounded_clock_in']->format('H:i') : '--:--' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">退勤</p>
                <p class="text-lg font-mono">{{ $roundedTimes && $roundedTimes['rounded_clock_out'] ? $roundedTimes['rounded_clock_out']->format('H:i') : '--:--' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">休憩</p>
                <p class="text-lg font-mono">{{ $breakMinutes > 0 ? floor($breakMinutes / 60) . ':' . str_pad($breakMinutes % 60, 2, '0', STR_PAD_LEFT) : '--:--' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">拘束</p>
                @php
                    $bindingMin = ($attendance && $roundedTimes && $roundedTimes['rounded_clock_out'])
                        ? $roundedTimes['rounded_clock_in']->diffInMinutes($roundedTimes['rounded_clock_out'])
                        : null;
                @endphp
                <p class="text-lg font-mono">{{ $bindingMin !== null ? floor($bindingMin / 60) . ':' . str_pad($bindingMin % 60, 2, '0', STR_PAD_LEFT) : '--:--' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">実働</p>
                <p class="text-lg font-mono">{{ $workingMinutes !== null ? floor($workingMinutes / 60) . ':' . str_pad($workingMinutes % 60, 2, '0', STR_PAD_LEFT) : '--:--' }}</p>
            </div>
        </div>
    </div>

    {{-- アラート --}}
    @if(count($alerts) > 0)
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach($alerts as $alert)
            @php
                $alertConfig = [
                    'late' => ['遅刻 ' . $alert['minutes'] . '分', 'bg-red-100 text-red-700'],
                    'early_leave' => ['早退 ' . $alert['minutes'] . '分', 'bg-amber-100 text-amber-700'],
                    'overtime' => ['残業 ' . $alert['minutes'] . '分', 'bg-blue-100 text-blue-700'],
                ];
                [$alertLabel, $alertClass] = $alertConfig[$alert['type']];
            @endphp
            <span class="px-3 py-1 rounded-full text-sm {{ $alertClass }}">{{ $alertLabel }}</span>
        @endforeach
    </div>
    @endif

    {{-- 打刻ボタン --}}
    <div class="grid grid-cols-2 gap-4">
        @if($status === 'not_started')
            <form method="POST" action="{{ route('attendance.clockIn') }}" class="col-span-2" id="clockInForm">
                @csrf
                <input type="hidden" name="latitude" id="clockInLat">
                <input type="hidden" name="longitude" id="clockInLng">
                <button type="submit" class="w-full py-4 bg-green-600 text-white text-xl font-bold rounded-lg hover:bg-green-700 transition">
                    出勤
                </button>
            </form>
        @elseif($status === 'clocked_in')
            <form method="POST" action="{{ route('attendance.breakStart') }}" id="breakStartForm">
                @csrf
                <input type="hidden" name="latitude" id="breakStartLat">
                <input type="hidden" name="longitude" id="breakStartLng">
                <button type="submit" class="w-full py-4 bg-yellow-500 text-white text-xl font-bold rounded-lg hover:bg-yellow-600 transition">
                    休憩開始
                </button>
            </form>
            <form method="POST" action="{{ route('attendance.clockOut') }}" id="clockOutForm">
                @csrf
                <input type="hidden" name="latitude" id="clockOutLat">
                <input type="hidden" name="longitude" id="clockOutLng">
                <button type="submit" class="w-full py-4 bg-red-600 text-white text-xl font-bold rounded-lg hover:bg-red-700 transition">
                    退勤
                </button>
            </form>
        @elseif($status === 'on_break')
            <form method="POST" action="{{ route('attendance.breakEnd') }}" class="col-span-2" id="breakEndForm">
                @csrf
                <input type="hidden" name="latitude" id="breakEndLat">
                <input type="hidden" name="longitude" id="breakEndLng">
                <button type="submit" class="w-full py-4 bg-yellow-600 text-white text-xl font-bold rounded-lg hover:bg-yellow-700 transition">
                    休憩終了
                </button>
            </form>
        @else
            <div class="col-span-2 text-center py-4 text-gray-500">
                本日の勤務は終了しています
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.querySelectorAll('[id$="Lat"]').forEach(el => el.value = pos.coords.latitude);
                document.querySelectorAll('[id$="Lng"]').forEach(el => el.value = pos.coords.longitude);
            },
            function() {},
            { timeout: 10000, enableHighAccuracy: true }
        );
    }
});
</script>
@endsection
