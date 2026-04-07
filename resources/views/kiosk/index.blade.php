@extends('layouts.kiosk')

@section('content')
<div class="text-center">
    <img src="{{ asset('images/logo.png') }}" alt="PochiClock" class="h-20 w-20 object-contain mx-auto mb-3">
    <h1 class="text-4xl font-bold mb-2 text-sky-600">PochiClock</h1>
    <p class="text-gray-500 mb-12">部署を選択してください</p>

    <div class="grid grid-cols-1 gap-5 max-w-md mx-auto">
        @foreach($departments as $dept)
            <a href="{{ route('kiosk.department', $dept) }}"
               class="block bg-white hover:bg-sky-50 border-2 border-sky-200 hover:border-sky-400 rounded-2xl p-8 text-2xl font-semibold text-gray-700 shadow-sm hover:shadow-md transition">
                {{ $dept->name }}
            </a>
        @endforeach
    </div>

    <div class="mt-12">
        <a href="{{ route('login') }}" class="text-gray-400 text-sm hover:text-gray-600">管理者ログイン</a>
    </div>
</div>
@endsection
