{{-- resources/views/admin/reports/show.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Report Details - Clinex Admin</title>
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
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="{{ route('reports.index') }}" class="text-blue-600 hover:text-blue-700 mr-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                    </a>
                    <h1 class="text-2xl font-semibold text-gray-900">Report Details</h1>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('reports.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Back to Reports
                    </a>
                    @if($report->status === 'verified')
                        <a href="{{ route('reports.edit', $report->id) }}"
                           onclick="return confirm('WARNING: Editing a verified report can have critical consequences. Are you sure you want to proceed?');"
                           class="inline-flex items-center px-4 py-2 border border-yellow-400 shadow-sm text-sm font-medium rounded-md text-yellow-800 bg-yellow-100 hover:bg-yellow-200">
                            <svg class="mr-2 -ml-1 h-5 w-5 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13h6m2 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Edit
                        </a>
                    @endif
                    <form action="{{ route('reports.destroy', $report->id) }}" method="POST" onsubmit="return confirm('DANGER: Deleting a report will remove all medical data and cannot be undone. Are you absolutely sure?');" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-red-400 shadow-sm text-sm font-medium rounded-md text-red-800 bg-red-100 hover:bg-red-200">
                            <svg class="mr-2 -ml-1 h-5 w-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="py-10 fade-in">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Report Details Section -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 rounded-t-lg">
                    <div class="flex flex-wrap items-center justify-between">
                        <h2 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Report Information
                        </h2>
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium
                            @if($report->status === 'processed')
                                bg-green-100 text-green-800
                            @elseif($report->status === 'processing')
                                bg-blue-100 text-blue-800
                            @elseif($report->status === 'error')
                                bg-red-100 text-red-800
                            @elseif($report->status === 'verified')
                                bg-purple-100 text-purple-800
                            @else
                                bg-yellow-100 text-yellow-800
                            @endif
                        ">
                            {{ ucfirst($report->status) }}
                        </span>
                    </div>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Report ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">#{{ $report->id }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Original Filename</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $report->original_filename }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Report Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $report->report_date ? $report->report_date->format('M d, Y') : 'Not Available' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Upload Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $report->created_at->format('M d, Y h:i A') }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Lab Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $report->lab_name ?? 'Not Available' }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uploaded By</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $report->uploadedBy ? $report->uploadedBy->name : 'System' }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Process Status</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($report->status) }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Processing Time</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $report->processed_at ? 
                                    $report->processed_at->diffForHumans($report->created_at, ['parts' => 2, 'short' => true]) :
                                    'Not processed yet' }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Batch</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $report->batch_id ? "Batch #" . $report->batch_id : 'No batch' }}
                            </dd>
                        </div>
                    </dl>
                </div>
                
                <div class="border-t border-gray-200 px-4 py-4 sm:px-6">
                    @if($report->file_path)
                    <a href="{{ route('reports.download', $report->id) }}" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download Original PDF
                    </a>
                    @else
                    <span class="text-sm text-gray-500">Original file not available</span>
                    @endif
                </div>
            </div>

            <!-- Patient Information Section -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 rounded-t-lg">
                    <h2 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Patient Information
                    </h2>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    @if($report->patient)
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Patient Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $report->patient->name }}</dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Patient ID</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $report->patient->patient_id ?? 'Not Available' }}</dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date of Birth</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $report->patient->date_of_birth ? $report->patient->date_of_birth->format('M d, Y') : 'Not Available' }}
                                </dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Gender</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $report->patient->gender ?? 'Not Available' }}</dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $report->patient->email ?? 'Not Available' }}</dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $report->patient->phone ?? 'Not Available' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-500">No patient associated with this report</p>
                    @endif
                </div>
            </div>
            
            <!-- Extracted Data Section -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 rounded-t-lg">
                    <h2 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                        Extracted Data
                    </h2>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    @if($report->extractedData && count($report->extractedData) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test Name</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Normal Range</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($report->extractedData as $data)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $data->test_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $data->result }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $data->normal_range }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $data->units }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            @if($data->status === 'normal')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-green-100 text-green-800">
                                                    Normal
                                                </span>
                                            @elseif($data->status === 'high')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-red-100 text-red-800">
                                                    High
                                                </span>
                                            @elseif($data->status === 'low')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                                                    Low
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                                    Unknown
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No extracted data available</p>
                    @endif
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
