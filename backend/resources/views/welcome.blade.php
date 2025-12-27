<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClinicSync Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center font-inter">
    <div class="w-full max-w-md mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="flex items-center justify-center mb-6">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
                <h1 class="text-2xl font-bold text-blue-900">Clinex Admin</h1>
            </div>
            <h2 class="text-xl font-semibold text-center text-blue-900 mb-6">Welcome to Back</h2>
            @if (Route::has('login'))
                <div class="space-y-4">
                    <a href="{{ route('login') }}"
                       class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-300 block text-center">
                        Login
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="w-full bg-white text-blue-600 border border-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors duration-300 block text-center">
                            Register
                        </a>
                    @endif
                </div>
            @endif
        </div>
        <footer class="mt-6 text-center text-gray-600 text-sm">
            <p>© 2025 Clinex. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>