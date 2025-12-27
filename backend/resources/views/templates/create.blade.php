<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mr-4">
                    Create Lab Report Template
                </h2>
                <span class="px-3 py-1 text-sm rounded-full bg-blue-100 text-blue-800 border border-blue-200 shadow-sm">
                    Admin Only
                </span>
            </div>
            <div class="flex items-center space-x-3">
                <button onclick="saveTemplate()"
                    class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Template
                </button>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-100">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Template Info Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Template Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template Name</label>
                        <input type="text" id="template-name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., KV Hospital - Biochemistry & Hematology">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" id="template-description"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Brief description of this template">
                    </div>
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document AI Processor ID</label>
                        <input type="text" id="processor-id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., 1234567890abcdef">
                    </div> -->
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lab Type</label>
                        <select id="lab-type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Lab Type</option>
                            <option value="biochemistry">Biochemistry</option>
                            <option value="hematology">Hematology</option>
                            <option value="serology">Serology/Immunology</option>
                            <option value="mixed">Mixed/Multiple</option>
                        </select>
                    </div> -->
                </div>
            </div>

            <!-- Main workspace container -->
            <div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-250px)]">
                <!-- PDF Viewer Container -->
                <div class="lg:w-1/2 bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 rounded-t-lg">
                        <h3 class="text-lg font-semibold text-gray-900">PDF Preview</h3>
                    </div>
                    <div class="flex-1 p-4">
                        <div class="h-full bg-gray-100 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <p class="text-gray-600 mb-4">Upload a sample PDF to create template</p>
                                <input type="file" id="pdf-upload" accept=".pdf" class="hidden"
                                    onchange="handlePdfUpload(event)">
                                <button onclick="document.getElementById('pdf-upload').click()"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    Upload PDF
                                </button>
                            </div>
                            <canvas id="zone-canvas" style="position: absolute; z-index: 10; display: none;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Extracted Data & Template Mapping Container -->
                <div class="lg:w-1/2 bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col"
                    style="min-height: 800px;">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 rounded-t-lg">
                        <h3 class="text-lg font-semibold text-gray-900">Extract & Map Data</h3>
                    </div>
                    <div class="flex-1 p-4 overflow-y-auto">
                        <!-- Header Section - Patient Info -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-gray-800">Header - Patient Information</h4>
                                <button onclick="addHeaderField()"
                                    class="flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Field
                                </button>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div id="header-fields">
                                    @if($aiData && isset($aiData['entities']))
                                        @foreach($aiData['entities'] as $key => $value)
                                            <div
                                                class="header-field-item flex items-center gap-3 mb-3 p-3 bg-white rounded-lg border border-gray-200">
                                                <div class="flex-1">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Field
                                                        Name</label>
                                                    <input type="text" value="{{ $key }}"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                        placeholder="e.g., Patient Name">
                                                </div>
                                                <div class="flex-1">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Extracted
                                                        Value</label>
                                                    <input type="text"
                                                        value="{{ is_array($value) ? json_encode($value) : $value }}"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                                        placeholder="Enter or edit extracted value">
                                                </div>
                                                <div class="flex-1">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Map to System
                                                        Field</label>
                                                    <select
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                        <option value="">Select field...</option>
                                                        @foreach($clinexFields['patient_info'] as $field)
                                                                                            <option value="{{ $field }}" {{ 
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            (str_contains(strtolower($key), 'name') && $field === 'name') ||
                                                            (str_contains(strtolower($key), 'patient id') && $field === 'patient_id') ||
                                                            (str_contains(strtolower($key), 'age') && $field === 'age') ||
                                                            (str_contains(strtolower($key), 'gender') && $field === 'gender') ||
                                                            (str_contains(strtolower($key), 'lab id') && $field === 'lab_id') ||
                                                            (str_contains(strtolower($key), 'phone') && $field === 'phone')
                                                            ? 'selected' : '' 
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        }}>
                                                                                                {{ ucfirst(str_replace('_', ' ', $field)) }}
                                                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button onclick="removeHeaderField(this)"
                                                    class="text-red-600 hover:text-red-800 p-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="text-center py-8 text-gray-500">
                                            <p>No header fields extracted yet. Upload a PDF first or click "Add Field" to
                                                add manually.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Body Section - Test Results Tables -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-gray-800">Body - Test Results</h4>
                                <button onclick="addTable()"
                                    class="flex items-center px-3 py-1 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Table
                                </button>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div id="table-sections">
                                    @if($aiData && isset($aiData['tables']))
                                        @foreach($aiData['tables'] as $index => $table)
                                            <div class="table-section mb-6 p-4 bg-white rounded-lg border border-gray-200">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h5 class="font-medium text-gray-800">
                                                        {{ $table['name'] ?? 'Table ' . ($index + 1) }}
                                                    </h5>
                                                    <button onclick="removeTable(this)" class="text-red-600 hover:text-red-800">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Table
                                                            Name</label>
                                                        <input type="text" value="{{ $table['name'] ?? '' }}"
                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Test
                                                            Category</label>
                                                        <select
                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                            onchange="handleCategoryChange(this)">
                                                            <option value="">Select category...</option>
                                                            <option value="biochemistry" {{ isset($table['name']) && str_contains(strtolower($table['name']), 'biochemistry') ? 'selected' : '' }}>Biochemistry</option>
                                                            <option value="hematology" {{ isset($table['name']) && str_contains(strtolower($table['name']), 'hematology') ? 'selected' : '' }}>Hematology</option>
                                                            <option value="serology" {{ isset($table['name']) && str_contains(strtolower($table['name']), 'sero') ? 'selected' : '' }}>Serology</option>
                                                            <option value="immunology" {{ isset($table['name']) && str_contains(strtolower($table['name']), 'immunology') ? 'selected' : '' }}>Immunology</option>
                                                            <option value="other">Other (Custom)</option>
                                                        </select>
                                                        <input type="text"
                                                            class="custom-category-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mt-2"
                                                            placeholder="Enter custom category name" style="display: none;">
                                                    </div>
                                                </div>

                                                <!-- Test Results Section -->
                                                <div class="mb-4">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <label class="block text-sm font-medium text-gray-700">Test
                                                            Results</label>
                                                        <button type="button" onclick="addTestRow(this)"
                                                            class="flex items-center px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                            </svg>
                                                            Add Test Row
                                                        </button>
                                                    </div>

                                                    <div class="test-results-container">
                                                        <!-- Header Row -->
                                                        <div
                                                            class="grid grid-cols-6 gap-2 mb-2 text-sm font-medium text-gray-700">
                                                            <div class="px-3 py-2 bg-gray-100 rounded">Test Name</div>
                                                            <div class="px-3 py-2 bg-gray-100 rounded">Result</div>
                                                            <div class="px-3 py-2 bg-gray-100 rounded">Unit</div>
                                                            <div class="px-3 py-2 bg-gray-100 rounded">Reference Range</div>
                                                            <div class="px-3 py-2 bg-gray-100 rounded">Flag</div>
                                                            <div class="px-3 py-2 bg-gray-100 rounded">Actions</div>
                                                        </div>

                                                        <!-- Existing Test Rows from Document AI -->
                                                        @if(isset($table['rows']) && is_array($table['rows']))
                                                            @foreach($table['rows'] as $rowIndex => $row)
                                                                <div class="test-row grid grid-cols-6 gap-2 mb-2">
                                                                    <div class="flex-1">
                                                                        <input type="text" value="{{ $row[0] ?? '' }}"
                                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                            placeholder="Test name">
                                                                    </div>
                                                                    <div class="flex-1">
                                                                        <input type="text" value="{{ $row[1] ?? '' }}"
                                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                            placeholder="Result">
                                                                    </div>
                                                                    <div class="flex-1">
                                                                        <input type="text" value="{{ $row[2] ?? '' }}"
                                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                            placeholder="Unit">
                                                                    </div>
                                                                    <div class="flex-1">
                                                                        <input type="text" value="{{ $row[3] ?? '' }}"
                                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                            placeholder="Reference range">
                                                                    </div>
                                                                    <div class="flex-1">
                                                                        <input type="text" value="{{ $row[4] ?? '' }}"
                                                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                            placeholder="Flag">
                                                                    </div>
                                                                    <div class="flex items-center justify-center">
                                                                        <button type="button" onclick="removeTestRow(this)"
                                                                            class="text-red-600 hover:text-red-800 p-1">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    stroke-width="2"
                                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                                </path>
                                                                            </svg>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Sample Data Preview -->
                                                @if(isset($table['headers']) && isset($table['rows']))
                                                    <div class="overflow-x-auto">
                                                        <table class="w-full text-sm">
                                                            <thead class="bg-gray-100">
                                                                <tr>
                                                                    @foreach($table['headers'] as $header)
                                                                        <th
                                                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                            {{ $header }}
                                                                        </th>
                                                                    @endforeach
                                                                </tr>
                                                            </thead>
                                                            <tbody class="bg-white divide-y divide-gray-200">
                                                                @foreach($table['rows'] as $row)
                                                                    <tr>
                                                                        @foreach($row as $cell)
                                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                                @if(is_array($cell))
                                                                                    {{ json_encode($cell) }}
                                                                                @else
                                                                                    {{ $cell }}
                                                                                @endif
                                                                            </td>
                                                                        @endforeach
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="text-center py-8 text-gray-500">
                                            <p>No tables extracted yet. Upload a PDF first or click "Add Table" to add
                                                manually.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Footer Section -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-md font-semibold text-gray-800">Footer - Lab Information</h4>
                                <button onclick="addFooterField()"
                                    class="flex items-center px-3 py-1 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Field
                                </button>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div id="footer-fields">
                                    <!-- Footer fields will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let headerFieldCount = {{ $aiData ? count($aiData['entities']) : 0 }};
        let tableCount = {{ $aiData ? count($aiData['tables']) : 0 }};
        let footerFieldCount = 0;
        let currentSessionId = null;

        function addHeaderField() {
            const headerFields = document.getElementById('header-fields');
            const fieldHtml = `
                <div class="header-field-item flex items-center gap-3 mb-3 p-3 bg-white rounded-lg border border-gray-200">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Patient Name">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Extracted Value</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Enter extracted value">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Map to System Field</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select field...</option>
                            @foreach($clinexFields['patient_info'] as $field)
                                <option value="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button onclick="removeHeaderField(this)" class="text-red-600 hover:text-red-800 p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            `;
            headerFields.insertAdjacentHTML('beforeend', fieldHtml);
            headerFieldCount++;
        }

        function removeHeaderField(button) {
            button.closest('.header-field-item').remove();
            headerFieldCount--;
        }

        function addFooterField() {
            const footerFields = document.getElementById('footer-fields');
            const fieldHtml = `
                <div class="footer-field-item flex items-center gap-3 mb-3 p-3 bg-white rounded-lg border border-gray-200">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Lab Technician">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Extracted Value</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Enter extracted value">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Map to System Field</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select field...</option>
                            <option value="validated_by">Validated By</option>
                            <option value="lab_technician">Lab Technician</option>
                            <option value="validated_date">Validated Date</option>
                            <option value="lab_contact">Lab Contact</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <button onclick="removeFooterField(this)" class="text-red-600 hover:text-red-800 p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            `;
            footerFields.insertAdjacentHTML('beforeend', fieldHtml);
            footerFieldCount++;
        }

        function removeFooterField(button) {
            button.closest('.footer-field-item').remove();
            footerFieldCount--;
        }

        function addTestRow(button) {
            const table = button.closest('.table-section');
            const testContainer = table.querySelector('.test-results-container');

            // Create new test row
            const newRowHtml = `
                <div class="test-row grid grid-cols-6 gap-2 mb-2">
                    <div class="flex-1">
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Test name">
                    </div>
                    <div class="flex-1">
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Result">
                    </div>
                    <div class="flex-1">
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Unit">
                    </div>
                    <div class="flex-1">
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Reference range">
                    </div>
                    <div class="flex-1">
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Flag">
                    </div>
                    <div class="flex items-center justify-center">
                        <button type="button" onclick="removeTestRow(this)" 
                                class="text-red-600 hover:text-red-800 p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            testContainer.insertAdjacentHTML('beforeend', newRowHtml);

            // Focus on the first input of the new row
            const newRow = testContainer.lastElementChild;
            const firstInput = newRow.querySelector('input[type="text"]');
            if (firstInput) {
                setTimeout(() => {
                    firstInput.focus();
                }, 100);
            }
        }

        function removeTestRow(button) {
            button.closest('.test-row').remove();
        }

        function addTable() {
            const tableSections = document.getElementById('table-sections');
            const tableHtml = `
                <div class="table-section mb-6 p-4 bg-white rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="font-medium text-gray-800">New Table ${tableCount + 1}</h5>
                        <button onclick="removeTable(this)" class="text-red-600 hover:text-red-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Table Name</label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Biochemistry Results">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Test Category</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="handleCategoryChange(this)">
                                <option value="">Select category...</option>
                                <option value="biochemistry">Biochemistry</option>
                                <option value="hematology">Hematology</option>
                                <option value="serology">Serology</option>
                                <option value="immunology">Immunology</option>
                                <option value="other">Other (Custom)</option>
                            </select>
                            <input type="text" class="custom-category-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mt-2" 
                                   placeholder="Enter custom category name" 
                                   style="display: none;"
                                   onblur="addCustomCategory(this)">
                        </div>
                    </div>

                    <!-- Test Results Section for New Tables -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Test Results</label>
                            <button type="button" onclick="addTestRow(this)" 
                                    class="flex items-center px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Test Row
                            </button>
                        </div>
                        
                        <div class="test-results-container">
                            <!-- Header Row -->
                            <div class="grid grid-cols-6 gap-2 mb-2 text-sm font-medium text-gray-700">
                                <div class="px-3 py-2 bg-gray-100 rounded">Test Name</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Result</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Unit</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Reference Range</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Flag</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Actions</div>
                            </div>
                            
                            <!-- Placeholder message -->
                            <div class="text-center py-8 text-gray-500">
                                <p>No test results added yet. Click "Add Test Row" to start adding test results.</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            tableSections.insertAdjacentHTML('beforeend', tableHtml);
            tableCount++;
        }

        // Store custom categories globally
        let customCategories = [];

        function handleCategoryChange(selectElement) {
            const customInput = selectElement.parentElement.querySelector('.custom-category-input');

            if (selectElement.value === 'other') {
                customInput.style.display = 'block';
                customInput.focus();
            } else {
                customInput.style.display = 'none';
                customInput.value = '';
            }
        }

        function addCustomCategory(inputElement) {
            const customValue = inputElement.value.trim();
            const selectElement = inputElement.parentElement.querySelector('select');

            if (customValue && customValue.length > 0) {
                // Check if this custom category already exists
                const existingOption = selectElement.querySelector(`option[value="${customValue.toLowerCase()}"]`);

                if (!existingOption) {
                    // Add to custom categories array
                    customCategories.push(customValue);

                    // Create new option element
                    const newOption = document.createElement('option');
                    newOption.value = customValue.toLowerCase();
                    newOption.textContent = customValue;
                    newOption.selected = true;

                    // Insert before the "Other" option
                    const otherOption = selectElement.querySelector('option[value="other"]');
                    selectElement.insertBefore(newOption, otherOption);

                    // Add to all other category selects in the page
                    addToAllCategorySelects(customValue);

                    // Hide the custom input
                    inputElement.style.display = 'none';
                    inputElement.value = '';
                } else {
                    // Select existing option
                    existingOption.selected = true;
                    inputElement.style.display = 'none';
                    inputElement.value = '';
                }
            } else {
                // If empty, revert to default
                selectElement.selectedIndex = 0;
                inputElement.style.display = 'none';
            }
        }

        function addToAllCategorySelects(customValue) {
            // Add the new custom category to all existing category select elements
            const allCategorySelects = document.querySelectorAll('select');

            allCategorySelects.forEach(select => {
                // Check if this is a category select (has the expected options)
                const hasOtherOption = select.querySelector('option[value="other"]');
                if (hasOtherOption) {
                    // Check if this custom category already exists in this select
                    const existingOption = select.querySelector(`option[value="${customValue.toLowerCase()}"]`);

                    if (!existingOption) {
                        // Create new option element
                        const newOption = document.createElement('option');
                        newOption.value = customValue.toLowerCase();
                        newOption.textContent = customValue;

                        // Insert before the "Other" option
                        const otherOption = select.querySelector('option[value="other"]');
                        select.insertBefore(newOption, otherOption);
                    }
                }
            });
        }

        function saveTemplate() {
            const templateData = {
                name: document.getElementById('template-name').value,
                description: document.getElementById('template-description').value,
                processor_id: 'a2439f686e4b0f79', // Set default processor ID
                lab_type: 'mixed',
                header_fields: [],
                test_sections: [],
                footer_fields: [],
                custom_categories: customCategories || []
            };

            // Collect header fields
            document.querySelectorAll('.header-field-item').forEach(item => {
                const inputs = item.querySelectorAll('input[type="text"]');
                const fieldName = inputs[0].value;
                const extractedValue = inputs[1].value;
                const mappedField = item.querySelector('select').value;

                if (fieldName && mappedField) {
                    templateData.header_fields.push({
                        field_name: fieldName,
                        extracted_value: extractedValue,
                        mapped_field: mappedField
                    });
                }
            });

            // Collect test sections with test results
            document.querySelectorAll('.table-section').forEach(section => {
                const tableName = section.querySelector('input[type="text"]').value;
                const categorySelect = section.querySelector('select');
                const category = categorySelect.value;

                if (tableName && category) {
                    // Collect test results from each row
                    const testResults = [];
                    const testRows = section.querySelectorAll('.test-row');

                    testRows.forEach(row => {
                        const inputs = row.querySelectorAll('input[type="text"]');
                        const testName = inputs[0]?.value || '';
                        const result = inputs[1]?.value || '';
                        const unit = inputs[2]?.value || '';
                        const referenceRange = inputs[3]?.value || '';
                        const flag = inputs[4]?.value || '';

                        // Only add if at least test name is filled (for template structure)
                        if (testName.trim()) {
                            testResults.push({
                                test_name: testName.trim(),
                                result: result.trim(),
                                unit: unit.trim(),
                                reference_range: referenceRange.trim(),
                                flag: flag.trim()
                            });
                        }
                    });

                    templateData.test_sections.push({
                        section_name: tableName,
                        category: category,
                        test_results: testResults
                    });
                }
            });

            // Collect footer fields
            document.querySelectorAll('.footer-field-item').forEach(item => {
                const inputs = item.querySelectorAll('input[type="text"]');
                const fieldName = inputs[0]?.value || '';
                const extractedValue = inputs[1]?.value || '';
                const mappedField = item.querySelector('select')?.value || '';

                if (fieldName && mappedField) {
                    templateData.footer_fields.push({
                        field_name: fieldName,
                        extracted_value: extractedValue,
                        mapped_field: mappedField
                    });
                }
            });

            // Validate required fields
            if (!templateData.name) {
                alert('Please fill in template name');
                return;
            }

            console.log('Template data:', templateData);

            // Send to server - use the correct route
            fetch('/templates', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(templateData)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success || data.template || data.id) {
                        alert('Template saved successfully!');
                        window.location.href = '/templates';
                    } else {
                        alert('Error saving template: ' + (data.message || data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving template: ' + error.message);
                });
        }

        function removeTable(button) {
            button.closest('.table-section').remove();
            tableCount--;
        }

        function configureTable(button) {
            // This function can be removed since we now have direct test row management
            alert('Use the "Add Test Row" button to add test results');
        }

        function setupZoneCanvas(pdfContainer) {
            // Wait for PDF to load
            const pdfFrame = document.getElementById('pdf-iframe');

            pdfFrame.onload = function () {
                // Get or create the canvas
                let canvas = document.getElementById('zone-canvas');
                if (!canvas) {
                    canvas = document.createElement('canvas');
                    canvas.id = 'zone-canvas';
                    canvas.style.position = 'absolute';
                    canvas.style.zIndex = '10';
                    canvas.style.pointerEvents = 'none'; // Allow click through to the PDF
                    pdfContainer.appendChild(canvas);
                }

                // Size the canvas to match the container
                canvas.width = pdfContainer.offsetWidth;
                canvas.height = pdfContainer.offsetHeight;
                canvas.style.display = 'block';

                // Initialize the drawing context
                const ctx = canvas.getContext('2d');

                // Make sure we have the global zones array
                window.zones = window.zones || [];

                // Reset drawing state
                window.isDrawing = false;

                // Create a drawing overlay that will handle events
                const overlay = document.createElement('div');
                overlay.id = 'drawing-overlay';
                overlay.style.position = 'absolute';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.zIndex = '11';
                overlay.style.cursor = 'crosshair';
                pdfContainer.appendChild(overlay);

                // Add event listeners to the overlay instead of canvas
                overlay.addEventListener('mousedown', startDrawingZone);
                overlay.addEventListener('mousemove', drawZone);
                overlay.addEventListener('mouseup', endDrawingZone);

                // Start with drawing disabled
                overlay.style.pointerEvents = 'none';

                // Create buttons - but we'll add them in addExtractButton function
                window.drawingEnabled = false;

                // Add the buttons to the header
                addButtonsToHeader(overlay);
            };
        }

        function addButtonsToHeader(overlay) {
            // Find the header section
            const headerSection = document.querySelector('.bg-gray-50.px-4.py-3.border-b.border-gray-200.rounded-t-lg');

            // Create a container for the buttons
            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'flex gap-2 items-center';

            // Create the drawing toggle button with higher z-index
            const drawToggle = document.createElement('button');
            drawToggle.id = 'drawing-toggle';
            drawToggle.className = 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors relative z-20';
            drawToggle.innerHTML = 'Enable Drawing';
            drawToggle.onclick = function () {
                if (!window.drawingEnabled) {
                    overlay.style.pointerEvents = 'auto';
                    drawToggle.innerHTML = 'Disable Drawing';
                    drawToggle.className = 'px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors relative z-20';
                    window.drawingEnabled = true;
                } else {
                    overlay.style.pointerEvents = 'none';
                    drawToggle.innerHTML = 'Enable Drawing';
                    drawToggle.className = 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors relative z-20';
                    window.drawingEnabled = false;
                }
            };

            // Create the extract button with fixed height and inline-flex to prevent layout shifts
            const extractButton = document.createElement('button');
            extractButton.id = 'extract-zones-btn';
            extractButton.className = 'px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors relative z-20 h-10 inline-flex items-center justify-center min-w-[180px]';
            extractButton.innerHTML = 'Extract Selected Zones';
            extractButton.onclick = extractFromZones;

            // Add buttons to container
            buttonContainer.appendChild(drawToggle);
            buttonContainer.appendChild(extractButton);

            // Make header flex to place button on right side
            headerSection.style.display = 'flex';
            headerSection.style.justifyContent = 'space-between';
            headerSection.style.alignItems = 'center';

            // Add container to header
            headerSection.appendChild(buttonContainer);

            // Make sure the buttons are above the drawing overlay
            headerSection.style.position = 'relative';
            headerSection.style.zIndex = '30';
        }

        // Remove the original addExtractButton function since it's replaced by addButtonsToHeader
        function handlePdfUpload(event) {
            const file = event.target.files[0];
            if (file && file.type === 'application/pdf') {
                // Create a URL for the uploaded PDF
                const fileUrl = URL.createObjectURL(file);

                // Store the file for later extraction
                window.uploadedPdfFile = file;

                // Get the container and clear previous content
                const pdfContainer = document.querySelector('.h-full.bg-gray-100');
                pdfContainer.innerHTML = '';

                // Create an iframe to display the PDF
                const iframe = document.createElement('iframe');
                iframe.id = 'pdf-iframe';
                iframe.src = fileUrl;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                pdfContainer.appendChild(iframe);

                // Set up the canvas for zone drawing
                setupZoneCanvas(pdfContainer);
            }
        }

        function extractFromZones() {
            if (!window.uploadedPdfFile || !window.zones || window.zones.length === 0) {
                alert('Please upload a PDF and draw at least one zone before extracting.');
                return;
            }

            // Use ID instead of :contains selector
            const extractButton = document.getElementById('extract-zones-btn');
            const originalText = extractButton.innerHTML;
            extractButton.innerHTML = '<svg class="animate-spin h-5 w-5 text-white inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Extracting...</span>';
            extractButton.disabled = true;

            // Create form data with the PDF and the zones
            const formData = new FormData();
            formData.append('pdf', window.uploadedPdfFile);
            formData.append('zones', JSON.stringify(window.zones));

            // Send request to extract only from specified zones
            fetch('/templates/extract-zones', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show raw JSON output in a collapsible section
                        showRawJsonOutput(data.data);

                        // Populate form with extracted data
                        populateFormWithExtractedData(data.data);
                        alert('Zone extraction completed successfully!');
                    } else {
                        alert('Error: ' + (data.error || data.message || 'Unknown error'));
                    }

                    // Reset button
                    extractButton.innerHTML = originalText;
                    extractButton.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error extracting from zones: ' + error.message);
                    extractButton.innerHTML = originalText;
                    extractButton.disabled = false;
                });
        }

        // Add a function to display raw JSON output
        function showRawJsonOutput(data) {
            // Check if the json-output div exists, if not create it
            let jsonOutputContainer = document.getElementById('json-output-container');

            if (!jsonOutputContainer) {
                // Create the container for JSON output
                jsonOutputContainer = document.createElement('div');
                jsonOutputContainer.id = 'json-output-container';
                jsonOutputContainer.className = 'bg-white rounded-lg shadow-sm border border-gray-200 p-4 mt-6';

                // Get the mapping container (right side) and insert before the save button section
                const mappingContainer = document.querySelector('.lg\\:w-1\\/2.bg-white.rounded-lg');

                if (mappingContainer) {
                    // Create a header with toggle functionality
                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between mb-3 cursor-pointer';
                    header.innerHTML = `
                <h4 class="text-md font-semibold text-gray-800">Raw Extraction Output (JSON)</h4>
                <button id="toggle-json" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Show JSON
                </button>
            `;

                    jsonOutputContainer.appendChild(header);

                    // Create pre element for JSON content (initially hidden)
                    const jsonPre = document.createElement('pre');
                    jsonPre.id = 'json-content';
                    jsonPre.className = 'bg-gray-50 p-4 rounded-lg overflow-auto text-xs hidden';
                    jsonPre.style.maxHeight = '300px';
                    jsonOutputContainer.appendChild(jsonPre);

                    // Insert the container at the top of the mapping section
                    mappingContainer.insertBefore(jsonOutputContainer, mappingContainer.firstChild.nextSibling);

                    // Add toggle functionality
                    document.getElementById('toggle-json').addEventListener('click', function () {
                        const jsonContent = document.getElementById('json-content');
                        const isHidden = jsonContent.classList.contains('hidden');

                        if (isHidden) {
                            jsonContent.classList.remove('hidden');
                            this.textContent = 'Hide JSON';
                        } else {
                            jsonContent.classList.add('hidden');
                            this.textContent = 'Show JSON';
                        }
                    });
                }
            }

            // Update JSON content
            const jsonPre = document.getElementById('json-content');
            if (jsonPre) {
                jsonPre.textContent = JSON.stringify(data, null, 2);

                // If we have data, automatically show it
                if (data && (Object.keys(data.entities || {}).length > 0 || (data.tables || []).length > 0)) {
                    jsonPre.classList.remove('hidden');
                    const toggleBtn = document.getElementById('toggle-json');
                    if (toggleBtn) toggleBtn.textContent = 'Hide JSON';
                }
            }
        }

        function startDrawingZone(e) {
            window.isDrawing = true;

            // Get scroll position of the container
            const pdfContainer = document.querySelector('.h-full.bg-gray-100');
            const scrollTop = pdfContainer.scrollTop;
            const scrollLeft = pdfContainer.scrollLeft;

            window.startX = e.clientX - pdfContainer.getBoundingClientRect().left + scrollLeft;
            window.startY = e.clientY - pdfContainer.getBoundingClientRect().top + scrollTop;
        }

        function drawZone(e) {
            if (!window.isDrawing) return;

            const canvas = document.getElementById('zone-canvas');
            const ctx = canvas.getContext('2d');
            const pdfContainer = document.querySelector('.h-full.bg-gray-100');
            const scrollTop = pdfContainer.scrollTop;
            const scrollLeft = pdfContainer.scrollLeft;

            // Calculate current mouse position relative to scrolled container
            const currentX = e.clientX - pdfContainer.getBoundingClientRect().left + scrollLeft;
            const currentY = e.clientY - pdfContainer.getBoundingClientRect().top + scrollTop;

            // Clear canvas and redraw all existing zones
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawExistingZones();

            // Draw current zone
            const width = currentX - window.startX;
            const height = currentY - window.startY;

            // Calculate position accounting for scroll
            const x = window.startX - scrollLeft;
            const y = window.startY - scrollTop;

            // Create more visible rectangle during drawing
            ctx.strokeStyle = 'rgba(0, 123, 255, 0.9)';
            ctx.lineWidth = 2;
            ctx.strokeRect(x, y, width, height);

            // Add more obvious semi-transparent fill
            ctx.fillStyle = 'rgba(0, 123, 255, 0.2)';
            ctx.fillRect(x, y, width, height);

            // Add "creating zone..." label
            ctx.fillStyle = 'rgba(0, 0, 0, 0.8)';
            ctx.font = 'bold 12px Arial';
            ctx.fillText('Creating zone...', x + 5, y + 15);
        }

        function endDrawingZone(e) {
            if (!window.isDrawing) return;

            window.isDrawing = false;

            // Get scroll position and calculate coordinates consistently with startDrawingZone
            const pdfContainer = document.querySelector('.h-full.bg-gray-100');
            const scrollTop = pdfContainer.scrollTop;
            const scrollLeft = pdfContainer.scrollLeft;

            // Calculate final position using the same coordinate system as startDrawingZone
            const finalX = e.clientX - pdfContainer.getBoundingClientRect().left + scrollLeft;
            const finalY = e.clientY - pdfContainer.getBoundingClientRect().top + scrollTop;

            // Calculate zone dimensions
            const width = finalX - window.startX;
            const height = finalY - window.startY;

            // Only create a zone if dimensions are significant
            if (Math.abs(width) > 20 && Math.abs(height) > 20) {
                const containerWidth = pdfContainer.scrollWidth;
                const containerHeight = pdfContainer.scrollHeight;

                if (containerWidth === 0 || containerHeight === 0) {
                    alert("Could not determine PDF dimensions. Please try re-uploading the file.");
                    return;
                }

                // Absolute coordinates for drawing
                const absX = Math.min(window.startX, finalX);
                const absY = Math.min(window.startY, finalY);
                const absWidth = Math.abs(width);
                const absHeight = Math.abs(height);

                // Normalized coordinates for backend extraction
                const newZone = {
                    // Absolute (for drawing)
                    abs_x: absX,
                    abs_y: absY,
                    abs_width: absWidth,
                    abs_height: absHeight,
                    // Normalized (for backend)
                    x: absX / containerWidth,
                    y: absY / containerHeight,
                    width: absWidth / containerWidth,
                    height: absHeight / containerHeight,
                    type: 'field',
                    field_name: 'Zone ' + (window.zones.length + 1)
                };

                window.zones.push(newZone);

                // Show dialog to configure the zone
                showZoneConfigDialog(newZone, window.zones.length - 1);
            }

            // Redraw to show the final result
            const canvas = document.getElementById('zone-canvas');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawExistingZones();
        }


        function drawExistingZones() {
            const canvas = document.getElementById('zone-canvas');
            const ctx = canvas.getContext('2d');

            if (!window.zones) return;

            window.zones.forEach((zone, index) => {
                ctx.strokeStyle = zone.type === 'field' ? 'rgba(0, 123, 255, 0.8)' : 'rgba(255, 123, 0, 0.8)';
                ctx.fillStyle = zone.type === 'field' ? 'rgba(0, 123, 255, 0.1)' : 'rgba(255, 123, 0, 0.1)';
                ctx.lineWidth = 2;

                ctx.strokeRect(zone.abs_x, zone.abs_y, zone.abs_width, zone.abs_height);
                ctx.fillRect(zone.abs_x, zone.abs_y, zone.abs_width, zone.abs_height);
                ctx.fillText(zone.field_name, zone.abs_x + 5, zone.abs_y + 15);

                // Add label
                ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
                ctx.font = '12px Arial';
                ctx.fillText(zone.field_name, zone.abs_x + 5, zone.abs_y + 15);
            });
        }

        function showZoneConfigDialog(zone, zoneIndex) {
            // Create modal dialog
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
            modal.id = 'zone-config-modal';

            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 w-96 shadow-xl">
                    <h3 class="text-lg font-semibold mb-4">Configure Zone</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zone Name</label>
                        <input type="text" id="zone-name" value="${zone.field_name}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zone Type</label>
                        <select id="zone-type" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="field" ${zone.type === 'field' ? 'selected' : ''}>Field (Single Value)</option>
                            <option value="table" ${zone.type === 'table' ? 'selected' : ''}>Table (Multiple Rows)</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button onclick="deleteZone(${zoneIndex})" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Delete Zone
                        </button>
                        <button onclick="saveZoneConfig(${zoneIndex})" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Save
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
        }

        function saveZoneConfig(zoneIndex) {
            const nameInput = document.getElementById('zone-name');
            const typeSelect = document.getElementById('zone-type');

            // Update zone with new values
            window.zones[zoneIndex].field_name = nameInput.value;
            window.zones[zoneIndex].type = typeSelect.value;

            // Close modal
            const modal = document.getElementById('zone-config-modal');
            if (modal) modal.remove();

            // Redraw zones
            drawExistingZones();
        }

        function deleteZone(zoneIndex) {
            // Remove zone from array
            window.zones.splice(zoneIndex, 1);

            // Close modal
            const modal = document.getElementById('zone-config-modal');
            if (modal) modal.remove();

            // Redraw zones
            const canvas = document.getElementById('zone-canvas');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawExistingZones();
        }

        function populateFormWithExtractedData(extractedData) {
            console.log('Extracted data:', extractedData);

            // Clear existing fields if needed
            const headerFields = document.getElementById('header-fields');

            // Process entities (single fields) for header section
            if (extractedData.entities && Object.keys(extractedData.entities).length > 0) {
                // Remove placeholder if it exists
                const placeholder = headerFields.querySelector('.text-center.py-8');
                if (placeholder) {
                    placeholder.remove();
                }

                // Add each entity as a field
                Object.entries(extractedData.entities).forEach(([key, value]) => {
                    const fieldHtml = `
                        <div class="header-field-item flex items-center gap-3 mb-3 p-3 bg-white rounded-lg border border-gray-200">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                                <input type="text" value="${key}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="e.g., Patient Name">
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Extracted Value</label>
                                <input type="text" value="${value}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                       placeholder="Enter extracted value">
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Map to System Field</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select field...</option>
                                    ${generateFieldOptions(key)}
                                </select>
                            </div>
                            <button onclick="removeHeaderField(this)" class="text-red-600 hover:text-red-800 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    `;
                    headerFields.insertAdjacentHTML('beforeend', fieldHtml);
                    headerFieldCount++;
                });
            }

            // Process tables
            if (extractedData.tables && extractedData.tables.length > 0) {
                const tableSections = document.getElementById('table-sections');

                // Remove placeholder if it exists
                const placeholder = tableSections.querySelector('.text-center.py-8');
                if (placeholder) {
                    placeholder.remove();
                }

                extractedData.tables.forEach(table => {
                    // Create a new table section
                    const newTableHtml = createTableHtml(table);
                    tableSections.insertAdjacentHTML('beforeend', newTableHtml);
                    tableCount++;
                });
            }
        }

        // Helper function to generate field options with smart mapping
        function generateFieldOptions(fieldName) {
            const patientFields = [
                { value: 'patient_id', label: 'Patient ID' },
                { value: 'name', label: 'Name' },
                { value: 'age', label: 'Age' },
                { value: 'gender', label: 'Gender' },
                { value: 'phone', label: 'Phone' },
                { value: 'requested_by', label: 'Requested By' },
                { value: 'lab_id', label: 'Lab ID' }
            ];

            const fieldNameLower = fieldName.toLowerCase();

            let options = '';
            patientFields.forEach(field => {
                // Check for likely matches
                const isMatch =
                    (fieldNameLower.includes(field.value.replace('_', ' ')) ||
                        fieldNameLower.includes(field.label.toLowerCase()));

                options += `<option value="${field.value}" ${isMatch ? 'selected' : ''}>${field.label}</option>`;
            });

            return options;
        }

        // Helper function to create table HTML
        function createTableHtml(table) {
            const tableName = table.name || `Extracted Table ${tableCount + 1}`;

            // Try to determine category from table name
            let category = '';
            const tableLower = tableName.toLowerCase();
            if (tableLower.includes('biochem')) category = 'biochemistry';
            else if (tableLower.includes('hemat')) category = 'hematology';
            else if (tableLower.includes('sero')) category = 'serology';
            else if (tableLower.includes('immun')) category = 'immunology';

            let rowsHtml = '';

            if (table.rows && table.rows.length > 0) {
                table.rows.forEach(row => {
                    // Format row data appropriately
                    const testName = row[0] || '';
                    const result = row[1] || '';
                    const unit = row[2] || '';
                    const refRange = row[3] || '';
                    const flag = row[4] || '';

                    rowsHtml += `
                        <div class="test-row grid grid-cols-6 gap-2 mb-2">
                            <div class="flex-1">
                                <input type="text" value="${testName}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Test name">
                            </div>
                            <div class="flex-1">
                                <input type="text" value="${result}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Result">
                            </div>
                            <div class="flex-1">
                                <input type="text" value="${unit}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Unit">
                            </div>
                            <div class="flex-1">
                                <input type="text" value="${refRange}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Reference range">
                            </div>
                            <div class="flex-1">
                                <input type="text" value="${flag}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Flag">
                            </div>
                            <div class="flex items-center justify-center">
                                <button type="button" onclick="removeTestRow(this)" 
                                    class="text-red-600 hover:text-red-800 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    `;
                });
            }

            return `
                <div class="table-section mb-6 p-4 bg-white rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="font-medium text-gray-800">${tableName}</h5>
                        <button onclick="removeTable(this)" class="text-red-600 hover:text-red-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Table Name</label>
                            <input type="text" value="${tableName}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Test Category</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                    onchange="handleCategoryChange(this)">
                                <option value="" ${!category ? 'selected' : ''}>Select category...</option>
                                <option value="biochemistry" ${category === 'biochemistry' ? 'selected' : ''}>Biochemistry</option>
                                <option value="hematology" ${category === 'hematology' ? 'selected' : ''}>Hematology</option>
                                <option value="serology" ${category === 'serology' ? 'selected' : ''}>Serology</option>
                                <option value="immunology" ${category === 'immunology' ? 'selected' : ''}>Immunology</option>
                                <option value="other">Other (Custom)</option>
                            </select>
                            <input type="text" class="custom-category-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mt-2" 
                                   placeholder="Enter custom category name" 
                                   style="display: none;"
                                   onblur="addCustomCategory(this)">
                        </div>
                    </div>

                    <!-- Test Results Section -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Test Results</label>
                            <button type="button" onclick="addTestRow(this)" 
                                    class="flex items-center px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Test Row
                            </button>
                        </div>
                        
                        <div class="test-results-container">
                            <!-- Header Row -->
                            <div class="grid grid-cols-6 gap-2 mb-2 text-sm font-medium text-gray-700">
                                <div class="px-3 py-2 bg-gray-100 rounded">Test Name</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Result</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Unit</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Reference Range</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Flag</div>
                                <div class="px-3 py-2 bg-gray-100 rounded">Actions</div>
                            </div>
                            
                            <!-- Test rows -->
                            ${rowsHtml}
                        </div>
                    </div>
                </div>
            `;
        }

        // Initialize Echo if not already done
        // if (typeof window.Echo === 'undefined') {
        //     window.Echo = new Echo({
        //         broadcaster: 'reverb',
        //         key: "{{ config('broadcasting.connections.reverb.app_key') }}",
        //         wsHost: "{{ config('broadcasting.connections.reverb.host') }}",
        //         wsPort: {{ config('broadcasting.connections.reverb.port') }},
        //         wssPort: {{ config('broadcasting.connections.reverb.port') }},
        //         forceTLS: false,
        //         enabledTransports: ['ws', 'wss']
        //         // Removed trailing comma that was causing syntax error
        //     });
        // }
    </script>
</x-app-layout>