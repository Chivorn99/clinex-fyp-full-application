{{-- resources/views/dashboard.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard - Clinex Admin</title>
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header with welcome message and date -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ Auth::user()->name }}!</h1>
                <p class="text-sm text-gray-600 md:text-base">{{ now()->format('l, F j, Y') }}</p>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="mb-8 fade-in" style="animation-delay: 0.1s">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-blue-50 rounded-lg p-4 flex items-center">
                        <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 font-medium">Recent Reports</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $recentReportsCount }}</p>
                        </div>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4 flex items-center">
                        <div class="p-3 rounded-lg bg-orange-100 text-orange-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-orange-600 font-medium">Recent Patients</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $recentPatientsCount }}</p>
                        </div>
                    </div>

                    <div class="bg-green-50 rounded-lg p-4 flex items-center">
                        <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-green-600 font-medium">Verified Reports</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $verifiedReportsCount }}</p>
                        </div>
                    </div>

                    <div class="bg-yellow-50 rounded-lg p-4 flex items-center">
                        <div class="p-3 rounded-lg bg-yellow-100 text-yellow-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-yellow-600 font-medium">Processed Reports</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $processedReportsCount }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="mb-8 fade-in" style="animation-delay: 0.2s">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- <a href="{{ route('lab-reports.processor') }}"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
                    <div class="flex p-6">
                        <div
                            class="bg-gradient-to-br from-blue-500 to-blue-700 text-white p-4 rounded-lg shadow mr-5 group-hover:scale-105 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">Testing Upload</h3>
                            <p class="text-gray-600 mb-3">Upload and test the engine</p>
                            <div class="flex items-center text-blue-600">
                                <span class="text-sm font-medium">Get started</span>
                                <svg class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform duration-200"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </a> -->

                <a href="{{ route('reports.index') }}"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
                    <div class="flex p-6">
                        <div
                            class="bg-gradient-to-br from-purple-500 to-purple-700 text-white p-4 rounded-lg shadow mr-5 group-hover:scale-105 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">Report Management</h3>
                            <p class="text-gray-600 mb-3">View and manage all lab reports</p>
                            <div class="flex items-center text-purple-600">
                                <span class="text-sm font-medium">Browse reports</span>
                                <svg class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform duration-200"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('users.index') }}"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
                    <div class="flex p-6">
                        <div
                            class="bg-gradient-to-br from-green-500 to-green-700 text-white p-4 rounded-lg shadow mr-5 group-hover:scale-105 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">User Management</h3>
                            <p class="text-gray-600 mb-3">Manage user accounts and permissions</p>
                            <div class="flex items-center text-green-600">
                                <span class="text-sm font-medium">Manage users</span>
                                <svg class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform duration-200"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>
                <a href="{{ route('patients.index') }}"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
                    <div class="flex p-6">
                        <div
                            class="bg-gradient-to-br from-orange-500 to-orange-700 text-white p-4 rounded-lg shadow mr-5 group-hover:scale-105 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">Patient Management</h3>
                            <p class="text-gray-600 mb-3">View and manage all patient</p>
                            <div class="flex items-center text-orange-600">
                                <span class="text-sm font-medium">Manage Patient</span>
                                <svg class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform duration-200"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>
                <a href="{{ route('users.create') }}"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
                    <div class="flex p-6">
                        <div
                            class="bg-gradient-to-br from-pink-500 to-pink-700 text-white p-4 rounded-lg shadow mr-5 group-hover:scale-105 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">Create User Account</h3>
                            <p class="text-gray-600 mb-3">Create new user account</p>
                            <div class="flex items-center text-pink-600">
                                <span class="text-sm font-medium">Manage Patient</span>
                                <svg class="h-4 w-4 ml-1 transform group-hover:translate-x-1 transition-transform duration-200"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>

            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-white py-6 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="border-t border-gray-200 pt-6 text-center">
                    <p class="text-sm text-gray-600">&copy; {{ date('Y') }} Clinex. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</body>

</html>