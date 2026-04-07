<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - キオスク</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-b from-sky-50 to-white min-h-screen text-gray-800">
    <main class="max-w-2xl mx-auto px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
