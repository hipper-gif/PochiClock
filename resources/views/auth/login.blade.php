@extends('layouts.guest')

@section('content')
<div class="w-full max-w-md">
    <div class="bg-white rounded-lg shadow-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-indigo-600">PochiClock</h1>
            <p class="text-gray-500 mt-2">勤怠管理システム</p>
        </div>

        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <div class="mb-4">
                <label for="employee_number" class="block text-sm font-medium text-gray-700 mb-1">社員番号</label>
                <input type="text" name="employee_number" id="employee_number" value="{{ old('employee_number') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    required autofocus>
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">パスワード</label>
                <input type="password" name="password" id="password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    required>
            </div>

            @if($errors->has('login'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded">
                    {{ $errors->first('login') }}
                </div>
            @endif

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-medium">
                ログイン
            </button>
        </form>
    </div>
</div>
@endsection
