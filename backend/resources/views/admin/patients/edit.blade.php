<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Patient - Clinex Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-2xl mx-auto py-10">
        <h1 class="text-2xl font-bold mb-6">Edit Patient</h1>
        <div class="mb-4 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
            <strong>Warning:</strong> Editing patient data can have critical consequences. Please double-check all changes before saving.
        </div>
        <form method="POST" action="{{ route('patients.update', $patient->id) }}">
            @csrf
            @method('PATCH')
            <div class="mb-4">
                <label class="block text-gray-700">Patient ID</label>
                <input type="text" name="patient_id" value="{{ old('patient_id', $patient->patient_id) }}" class="w-full border rounded px-3 py-2" required>
                @error('patient_id') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Name</label>
                <input type="text" name="name" value="{{ old('name', $patient->name) }}" class="w-full border rounded px-3 py-2" required>
                @error('name') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Age</label>
                <input type="text" name="age" value="{{ old('age', $patient->age) }}" class="w-full border rounded px-3 py-2" required>
                @error('age') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Gender</label>
                <select name="gender" class="w-full border rounded px-3 py-2" required>
                    <option value="Male" {{ old('gender', $patient->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender', $patient->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('gender') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}" class="w-full border rounded px-3 py-2">
                @error('phone') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $patient->email) }}" class="w-full border rounded px-3 py-2">
                @error('email') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="flex items-center">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
                <a href="{{ route('patients.show', $patient->id) }}" class="ml-4 text-gray-700 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
