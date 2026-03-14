@extends('layouts.kiosk')

@section('content')
<div class="text-center">
    <h1 class="text-4xl font-bold mb-2">PochiClock</h1>
    <p class="text-gray-400 mb-12">部署を選択してください</p>

    <div class="grid grid-cols-1 gap-6 max-w-md mx-auto">
        @foreach($departments as $dept)
            <a href="{{ route('kiosk.department', $dept) }}"
               class="block bg-gray-800 hover:bg-gray-700 rounded-xl p-8 text-2xl font-semibold transition">
                {{ $dept->name }}
            </a>
        @endforeach
    </div>

    <div class="mt-12">
        <a href="{{ url('/login') }}" class="text-gray-500 text-sm hover:text-gray-300">管理者ログイン</a>
    </div>
</div>
@endsection
