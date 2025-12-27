{{-- resources/views/profile/edit.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profile - Clinex Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-card {
            transition: all 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
    <script src="//unpkg.com/alpinejs" defer></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="ml-2 text-xl font-bold text-blue-900">Clinex</span>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center focus:outline-none">
                            <span class="text-sm text-gray-700 mr-2">{{ Auth::user()->name }}</span>
                            <img class="h-8 w-8 rounded-full object-cover border-2 border-gray-200"
                                src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF"
                                alt="{{ Auth::user()->name }}">
                        </button>
                        <!-- Dropdown -->
                        <div x-show="open" @click.away="open = false"
                            class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                            x-cloak>
                            <div class="py-3 px-4 border-b border-gray-100 flex items-center">
                                <img class="h-10 w-10 rounded-full object-cover border-2 border-gray-200"
                                    src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF&size=128"
                                    alt="{{ Auth::user()->name }}">
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</div>
                                    <div class="text-xs text-gray-500">{{ Auth::user()->email }}</div>
                                </div>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('profile.edit') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Profile</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center">
                <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-700 mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold text-gray-900">Profile Settings</h1>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="space-y-8">
                <!-- Profile Information -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden section-card fade-in"
                    style="animation-delay: 0.1s;">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 rounded-t-lg">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <h2 class="ml-3 text-lg font-medium text-gray-900">Profile Information</h2>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Update your account's profile information and email
                            address.</p>
                    </div>
                    <div class="px-4 py-6 sm:p-6 bg-white">
                        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <!-- Name -->
                            <div class="mb-4">
                                <label class="block text-gray-700">Name</label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full border rounded px-3 py-2" required>
                            </div>
                            <!-- Email -->
                            <div class="mb-4">
                                <label class="block text-gray-700">Email</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full border rounded px-3 py-2" required>
                            </div>
                            <!-- Phone Number -->
                            <div class="mb-4">
                                <label class="block text-gray-700">Phone Number</label>
                                <input type="text" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="w-full border rounded px-3 py-2">
                            </div>
                            <!-- Specialization -->
                            <div class="mb-4">
                                <label class="block text-gray-700">Specialization</label>
                                <input type="text" name="specialization" value="{{ old('specialization', $user->specialization) }}" class="w-full border rounded px-3 py-2">
                            </div>
                            <!-- Profile Picture Upload -->
                            <div class="mb-4">
                                <label class="block text-gray-700">Profile Picture</label>
                                @if($user->profile_pic)
                                    <div class="mb-2">
                                        <img src="{{ Storage::disk('public')->url($user->profile_pic) }}" alt="Profile Picture" class="h-16 w-16 rounded-full object-cover border-2 border-gray-200">
                                    </div>
                                @endif
                                <input type="file" name="profile_pic" class="block w-full text-sm text-gray-500">
                                @if($user->profile_pic)
                                    <div class="mt-2">
                                        <label>
                                            <input type="checkbox" name="remove_profile_pic" value="1">
                                            Remove current picture
                                        </label>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Update Password -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden section-card fade-in"
                    style="animation-delay: 0.2s;">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 rounded-t-lg">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <h2 class="ml-3 text-lg font-medium text-gray-900">Update Password</h2>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Ensure your account is using a long, random password to
                            stay secure.</p>
                    </div>
                    <div class="px-4 py-6 sm:p-6 bg-white">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <!-- Delete Account -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden section-card fade-in"
                    style="animation-delay: 0.3s;">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 rounded-t-lg">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <h2 class="ml-3 text-lg font-medium text-gray-900">Delete Account</h2>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Once your account is deleted, all of its resources and
                            data will be permanently deleted.</p>
                    </div>
                    <div class="px-4 py-6 sm:p-6 bg-white">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="border-t border-gray-200 pt-6 text-center">
                <p class="text-sm text-gray-600">&copy; {{ date('Y') }} ClinicSync. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>

</html>