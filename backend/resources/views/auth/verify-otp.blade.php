{{-- resources/views/auth/verify-otp.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - ClinicSync Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .otp-input {
            width: 3rem;
            height: 3rem;
            text-align: center;
            font-size: 1.5rem;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center font-inter">
    <div class="w-full max-w-md mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="flex items-center justify-center mb-6">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
                <h1 class="text-2xl font-bold text-blue-900">Clinex Admin</h1>
            </div>
            <h2 class="text-xl font-semibold text-center text-blue-900 mb-6">Enter OTP Code</h2>

            <div class="mb-4 text-sm text-gray-600">
                Please enter the 6-digit OTP code sent to your email address. The code is valid for 10 minutes.
            </div>

            <!-- Session Status -->
            @if (session('status'))
                <div class="mb-4 text-center text-sm font-medium text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.otp.verify') }}" id="otpForm">
                @csrf

                <input type="hidden" name="email" value="{{ $email }}">

                <div class="mb-4">
                    <label for="code" class="block mb-2 text-sm font-medium text-gray-700">OTP Code</label>
                    <div class="flex justify-center gap-2">
                        <input type="text"
                            class="otp-input border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600"
                            name="code" id="code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" required>
                    </div>
                    @error('code')
                        <p class="mt-2 text-sm text-red-600 text-center">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6">
                    <button type="submit"
                        class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-300 block text-center">
                        Verify OTP
                    </button>
                </div>

                <div class="mt-6 text-center text-sm">
                    <span class="text-gray-600">Didn't receive code?</span>
                    <form method="POST" action="{{ route('password.otp.resend') }}" class="inline">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <button type="submit" class="text-blue-600 hover:underline ml-1">Resend OTP</button>
                    </form>
                </div>

                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">
                        Back to Login
                    </a>
                </div>
            </form>
        </div>
        <footer class="mt-6 text-center text-gray-600 text-sm">
            <p>© 2025 Clinex. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>