<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Report - Clinex Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto py-10">
        <h1 class="text-2xl font-bold mb-6">Edit Lab Report</h1>
        <div class="mb-4 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
            <strong>Warning:</strong> Editing a verified report can have critical consequences. Please double-check all changes before saving.
        </div>
        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('reports.update', $report->id) }}">
            @csrf
            @method('PATCH')
            <div class="mb-4">
                <label class="block text-gray-700">Original Filename</label>
                <input type="text" name="original_filename" value="{{ old('original_filename', $report->original_filename) }}" class="w-full border rounded px-3 py-2" required>
                @error('original_filename') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Report Date</label>
                <input type="date" name="report_date" value="{{ old('report_date', $report->report_date ? $report->report_date->format('Y-m-d') : '') }}" class="w-full border rounded px-3 py-2">
                @error('report_date') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Status</label>
                <select name="status" class="w-full border rounded px-3 py-2">
                    <option value="processing" {{ old('status', $report->status) == 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="processed" {{ old('status', $report->status) == 'processed' ? 'selected' : '' }}>Processed</option>
                    <option value="verified" {{ old('status', $report->status) == 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="error" {{ old('status', $report->status) == 'error' ? 'selected' : '' }}>Error</option>
                </select>
                @error('status') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="flex items-center">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
                <a href="{{ route('reports.show', $report->id) }}" class="ml-4 text-gray-700 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
