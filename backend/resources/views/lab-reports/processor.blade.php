{{-- resources/views/lab-reports/processor.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lab Report Processor - ClinicSync Admin</title>
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
</head>

<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                            <span class="ml-2 text-xl font-bold text-blue-900">Clinex</span>
                        </a>
                    </div>
                    <div class="ml-6">
                        <h1 class="text-lg font-semibold text-gray-800">Lab Report Processor</h1>
                    </div>
                </div>
                <div class="flex items-center">
                    <div id="action-buttons" class="flex items-center space-x-3 mr-4">
                        <!-- Action buttons will be added here dynamically -->
                    </div>
                    <div class="ml-3 relative">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-700 mr-2">{{ Auth::user()->name }}</span>
                            <img class="h-8 w-8 rounded-full object-cover border-2 border-gray-200"
                                src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF"
                                alt="{{ Auth::user()->name }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h2 class="text-xl font-semibold text-gray-900">Process Lab Report</h2>
                </div>
                <a href="{{ route('dashboard') }}"
                    class="flex items-center text-blue-600 hover:text-blue-800 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="text-sm font-medium">Back to Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Processor Container -->
        <div class="bg-white rounded-xl shadow-lg mb-6">
            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6" style="height: calc(100vh - 200px);">

                <div id="pdf-viewer-container"
                    class="border border-gray-300 rounded-lg flex flex-col bg-gray-50 shadow-md">
                    <div class="flex items-center justify-center h-full" id="pdf-upload-placeholder">
                        <div class="text-center p-8">
                            <svg class="w-24 h-24 mx-auto text-gray-300" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">Upload a Lab Report</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by uploading a PDF document.</p>
                            <div class="mt-6">
                                <input type="file" id="pdf-upload-input" class="hidden" accept=".pdf" />
                                <button type="button" onclick="document.getElementById('pdf-upload-input').click()"
                                    class="inline-flex items-center px-5 py-3 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Select PDF File
                                </button>
                            </div>
                        </div>
                    </div>
                    <iframe id="pdf-iframe" class="w-full h-full rounded-lg" style="display: none;"></iframe>
                </div>

                <div id="data-panel" class="border border-gray-300 rounded-lg flex flex-col bg-white shadow-md">
                    <div class="flex items-center justify-between border-b border-gray-200 p-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            Extracted Data
                        </h3>
                    </div>
                    <div class="flex-grow p-6 overflow-y-auto" id="data-display-area">
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center text-gray-500 p-8">
                                <svg class="w-24 h-24 mx-auto text-gray-300" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-3 text-lg font-medium text-gray-900">No Data Available</h3>
                                <p class="mt-1 text-sm text-gray-500">Upload and process a lab report to view extracted
                                    data here.</p>
                            </div>
                        </div>
                    </div>
                    <div id="raw-json-container" class="p-4 border-t border-gray-200" style="display: none;">
                        <details>
                            <summary
                                class="cursor-pointer text-sm font-medium text-blue-600 hover:text-blue-800 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                View Raw JSON Output
                            </summary>
                            <pre id="raw-json-output"
                                class="mt-2 p-4 bg-gray-800 text-white rounded-md text-xs overflow-x-auto max-h-64"></pre>
                        </details>
                    </div>
                </div>

            </div>
        </div>
    </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pdfUploadInput = document.getElementById('pdf-upload-input');
            const pdfViewerContainer = document.getElementById('pdf-viewer-container');
            const pdfUploadPlaceholder = document.getElementById('pdf-upload-placeholder');
            const pdfIframe = document.getElementById('pdf-iframe');
            const dataDisplayArea = document.getElementById('data-display-area');
            const actionButtons = document.getElementById('action-buttons');
            let uploadedFile = null;

            pdfUploadInput.addEventListener('change', function (event) {
                const file = event.target.files[0];
                if (file && file.type === 'application/pdf') {
                    uploadedFile = file;
                    const fileUrl = URL.createObjectURL(file);
                    pdfIframe.src = fileUrl;
                    pdfIframe.style.display = 'block';
                    pdfUploadPlaceholder.style.display = 'none';
                    updateActionButtons('extract');
                }
            });

            function updateActionButtons(state) {
                actionButtons.innerHTML = ''; // Clear existing buttons
                if (state === 'extract') {
                    const extractButton = document.createElement('button');
                    extractButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>Extract Data';
                    extractButton.className = 'flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-300 shadow-sm font-medium';
                    extractButton.onclick = handleExtractData;
                    actionButtons.appendChild(extractButton);
                } else if (state === 'save') {
                    const saveButton = document.createElement('button');
                    saveButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>Save Verified Data';
                    saveButton.className = 'flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-300 shadow-sm font-medium';
                    saveButton.onclick = handleSaveData;
                    actionButtons.appendChild(saveButton);
                }
            }

            function handleExtractData() {
                if (!uploadedFile) {
                    alert('Please upload a PDF file first.');
                    return;
                }

                const extractButton = actionButtons.querySelector('button');
                extractButton.innerHTML = '<div class="flex items-center"><svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...</div>';
                extractButton.disabled = true;
                extractButton.className = 'flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg opacity-75 cursor-not-allowed shadow-sm font-medium';

                const formData = new FormData();
                formData.append('pdf_file', uploadedFile);

                fetch("{{ route('lab-reports.test-extract') }}", {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                })
                    .then(response => response.json())
                    .then(result => {
                        console.log(result); // <-- Add this line
                        if (result.success) {
                            // Display the extracted data as pretty JSON
                            dataDisplayArea.innerHTML = `
                                <div class="bg-gray-900 text-white rounded-lg p-6 overflow-x-auto">
                                    <pre class="text-xs">${JSON.stringify(result.data, null, 2)}</pre>
                                </div>
                            `;
                            updateActionButtons(); // Optionally remove save button
                        } else {
                            throw new Error(result.message || 'Could not extract data from the document.');
                        }
                    })
                    .catch(error => {
                        // Show error message in data panel instead of alert
                        dataDisplayArea.innerHTML = `
                            <div class="flex items-center justify-center h-full">
                                <div class="text-center p-8 max-w-md">
                                    <div class="bg-red-100 text-red-600 p-3 rounded-full w-16 h-16 mx-auto flex items-center justify-center mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900">Processing Error</h3>
                                    <p class="mt-2 text-sm text-gray-600">${error.message || 'An error occurred while processing the document.'}</p>
                                    <button class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                                            onclick="location.reload()">
                                        Try Again
                                    </button>
                                </div>
                            </div>
                        `;
                        extractButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>Extract Data';
                        extractButton.className = 'flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-300 shadow-sm font-medium';
                        extractButton.disabled = false;
                    });
            }

            function handleSaveData() {
                const saveButton = actionButtons.querySelector('button');
                saveButton.disabled = true;
                saveButton.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';

                // Collect form data
                const patientData = {};
                const labData = {};
                const testResults = [];

                // Get patient info fields
                document.querySelectorAll('#data-display-area input[id]').forEach(input => {
                    if (input.closest('.test-result-row')) return; // Skip test results

                    const inputId = input.id;
                    const inputValue = input.value;

                    // Determine if this is patient info or lab info based on position in DOM
                    if (input.closest('div').previousElementSibling &&
                        input.closest('div').previousElementSibling.textContent.includes('Patient')) {
                        patientData[inputId] = inputValue;
                    } else {
                        labData[inputId] = inputValue;
                    }
                });

                // Get test results
                document.querySelectorAll('.test-result-row').forEach(row => {
                    const inputs = row.querySelectorAll('input');
                    const category = row.querySelector('td:first-child').textContent;
                    const testName = inputs[0].value;
                    const result = inputs[1].value;
                    const unit = inputs[2].value;
                    const referenceRange = inputs[3].value;
                    const flag = inputs[4].value;

                    testResults.push({
                        category,
                        testName,
                        result,
                        unit,
                        referenceRange,
                        flag
                    });
                });

                const formData = {
                    pdf_id: uploadedFile.name, // This should be replaced with actual PDF ID from server
                    patientInfo: patientData,
                    labInfo: labData,
                    testResults: testResults
                };

                // Send data to server
                fetch('/api/lab-reports/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(formData)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message in data panel
                            dataDisplayArea.innerHTML += `
                            <div class="fixed inset-0 flex items-center justify-center z-50 bg-gray-900 bg-opacity-50">
                                <div class="bg-white rounded-lg p-8 max-w-md shadow-xl transform transition-all">
                                    <div class="flex justify-center">
                                        <div class="bg-green-100 text-green-600 p-3 rounded-full w-16 h-16 flex items-center justify-center">
                                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-medium text-center mt-4 text-gray-900">Success!</h3>
                                    <p class="text-sm text-gray-600 text-center mt-2">Lab report data was saved successfully.</p>
                                    <div class="mt-6 flex justify-center">
                                        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700" onclick="window.location.href='/dashboard'">
                                            Return to Dashboard
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                            saveButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Saved';
                            saveButton.className = 'flex items-center px-4 py-2 bg-green-600 text-white rounded-lg transition-all duration-300 shadow-sm font-medium cursor-not-allowed opacity-75';
                        } else {
                            // Show error message
                            dataDisplayArea.innerHTML += `
                            <div class="fixed inset-0 flex items-center justify-center z-50 bg-gray-900 bg-opacity-50">
                                <div class="bg-white rounded-lg p-8 max-w-md shadow-xl">
                                    <div class="flex justify-center">
                                        <div class="bg-red-100 text-red-600 p-3 rounded-full w-16 h-16 flex items-center justify-center">
                                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-medium text-center mt-4 text-gray-900">Error</h3>
                                    <p class="text-sm text-gray-600 text-center mt-2">${data.message || 'An error occurred while saving the data.'}</p>
                                    <div class="mt-6 flex justify-center">
                                        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700" onclick="this.closest('.fixed').remove()">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                            saveButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>Save Data';
                            saveButton.className = 'flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-sm font-medium';
                            saveButton.disabled = false;
                        }
                    })
                    .catch(error => {
                        // Show error message
                        dataDisplayArea.innerHTML += `
                        <div class="fixed inset-0 flex items-center justify-center z-50 bg-gray-900 bg-opacity-50">
                            <div class="bg-white rounded-lg p-8 max-w-md shadow-xl">
                                <div class="flex justify-center">
                                    <div class="bg-red-100 text-red-600 p-3 rounded-full w-16 h-16 flex items-center justify-center">
                                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <h3 class="text-lg font-medium text-center mt-4 text-gray-900">Error</h3>
                                <p class="text-sm text-gray-600 text-center mt-2">${error.message || 'An error occurred while saving the data.'}</p>
                                <div class="mt-6 flex justify-center">
                                    <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700" onclick="this.closest('.fixed').remove()">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                        saveButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>Save Data';
                        saveButton.className = 'flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-sm font-medium';
                        saveButton.disabled = false;
                    });
            }

            function populateDataPanel(data) {
                dataDisplayArea.innerHTML = ''; // Clear placeholder

                // Display Patient & Lab Info
                dataDisplayArea.innerHTML += createInfoSection('Patient Information', data.patientInfo, 'user');
                dataDisplayArea.innerHTML += createInfoSection('Lab Information', data.labInfo, 'beaker');

                // Display Test Results
                dataDisplayArea.innerHTML += createTestResultsSection(data.testResults);

                // Display Raw JSON
                document.getElementById('raw-json-output').textContent = JSON.stringify(data, null, 2);
                document.getElementById('raw-json-container').style.display = 'block';
            }

            function createInfoSection(title, infoObject, icon) {
                let fieldsHtml = '';
                for (const [key, value] of Object.entries(infoObject)) {
                    const label = key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());
                    fieldsHtml += `
                        <div class="sm:col-span-1">
                            <label for="${key}" class="block text-sm font-medium text-gray-500">${label}</label>
                            <input type="text" name="${key}" id="${key}" value="${value || ''}" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    `;
                }

                const iconSvg = icon === 'user'
                    ? `<svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>`
                    : `<svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>`;

                return `
                    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 rounded-t-lg">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                               ${iconSvg}
                                <span class="ml-3">${title}</span>
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-1 gap-y-4 gap-x-4 sm:grid-cols-2">
                               ${fieldsHtml}
                            </div>
                        </div>
                    </div>`;
            }

            function createTestResultsSection(results) {
                if (!results || results.length === 0) {
                    return `
                        <div class="bg-white rounded-lg shadow-sm mb-8 border border-gray-200">
                            <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 rounded-t-lg">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <span class="ml-3">Test Results</span>
                                </h3>
                            </div>
                            <div class="p-12 flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="h-12 w-12 text-gray-300 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="mt-2 text-gray-500">No test results found in the document.</p>
                                </div>
                            </div>
                        </div>
                     `;
                }

                let rowsHtml = '';
                results.forEach((item, index) => {
                    // Determine if the value is abnormal based on flag
                    const hasFlag = item.flag && item.flag.trim().length > 0;
                    const resultClass = hasFlag ? 'font-medium text-red-700 bg-red-50' : '';

                    rowsHtml += `
                        <tr class="test-result-row hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${item.category}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700">
                                <input type="text" value="${item.testName || ''}" 
                                    class="w-full bg-transparent border-gray-300 rounded-md p-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm ${resultClass}">
                                <input type="text" value="${item.result || ''}" 
                                    class="w-full bg-transparent ${hasFlag ? 'font-medium text-red-700' : ''} border-gray-300 rounded-md p-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700">
                                <input type="text" value="${item.unit || ''}" 
                                    class="w-full bg-transparent border-gray-300 rounded-md p-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700">
                                <input type="text" value="${item.referenceRange || ''}" 
                                    class="w-full bg-transparent border-gray-300 rounded-md p-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm ${hasFlag ? 'text-red-700' : 'text-gray-700'}">
                                <input type="text" value="${item.flag || ''}" 
                                    class="w-20 bg-transparent ${hasFlag ? 'text-red-700 font-medium' : ''} border-gray-300 rounded-md p-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                        </tr>
                     `;
                });

                return `
                    <div class="bg-white rounded-lg shadow-sm mb-8 border border-gray-200">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 rounded-t-lg">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <span class="ml-3">Test Results</span>
                            </h3>
                        </div>
                        <div class="overflow-x-auto px-4 py-4">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flag</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${rowsHtml}
                                </tbody>
                            </table>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 rounded-b-lg border-t border-gray-200 text-right">
                            <span class="text-xs text-gray-500">Click on any field to edit</span>
                        </div>
                    </div>
                 `;
            }
        });
    </script>

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