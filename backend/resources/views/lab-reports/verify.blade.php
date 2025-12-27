<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mr-4">
                    Lab Report Verification
                </h2>
                <span class="px-3 py-1 text-sm rounded-full bg-amber-100 text-amber-800 border border-amber-200 shadow-sm">
                    #{{ $labReport->id ?? '1' }}
                </span>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 text-sm rounded-full bg-yellow-100 text-yellow-800 border border-yellow-200 shadow-sm flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Pending Verification
                </span>
                <button onclick="toggleFullscreen()" class="flex items-center text-sm bg-gray-100 text-gray-700 py-1 px-3 rounded-md hover:bg-gray-200 transition-colors border border-gray-300 shadow-sm">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"></path>
                    </svg>
                    Fullscreen
                </button>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-100">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Main workspace container -->
            <div id="main-container" class="flex flex-col h-[calc(100vh-130px)] bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <!-- Toolbar -->
                <div class="bg-gradient-to-r from-slate-700 to-slate-800 text-white px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button onclick="setView('split')" id="split-view-btn" class="flex items-center px-3 py-2 rounded-lg bg-slate-600 text-white hover:bg-slate-500 transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Split View
                        </button>
                        <button onclick="setView('pdf')" id="pdf-view-btn" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-600 transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            PDF Only
                        </button>
                        <button onclick="setView('form')" id="form-view-btn" class="flex items-center px-3 py-2 rounded-lg hover:bg-slate-600 transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Form Only
                        </button>
                    </div>
                    <div class="flex items-center">
                        <div class="text-sm text-gray-300 mr-4">
                            <span id="zoom-level">100%</span>
                        </div>
                        <div class="flex bg-slate-600 rounded-lg p-1 shadow-inner">
                            <button onclick="zoomPDF('out')" class="text-white hover:bg-slate-500 p-1 rounded-l-md" title="Zoom Out (Ctrl+-)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                </svg>
                            </button>
                            <button onclick="resetPDFZoom()" class="text-white hover:bg-slate-500 p-1" title="Reset Zoom (Ctrl+0)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                            <button onclick="zoomPDF('in')" class="text-white hover:bg-slate-500 p-1 rounded-r-md" title="Zoom In (Ctrl++)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Workspace -->
                <div class="flex flex-1 overflow-hidden">
                    <!-- PDF Panel -->
                    <div id="pdf-panel" class="flex-1 flex flex-col h-full">
                        <div class="flex-1 relative">
                            <!-- PDF Loading Overlay -->
                            <div id="pdf-loading" class="absolute inset-0 flex items-center justify-center bg-slate-50 bg-opacity-80 z-10">
                                <div class="flex flex-col items-center">
                                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-slate-300 border-t-blue-600"></div>
                                    <p class="mt-4 text-slate-700 font-medium">Loading document...</p>
                                </div>
                            </div>
                            
                            <!-- PDF Container -->
                            <div id="pdf-scroll-container" class="absolute inset-0 overflow-auto bg-slate-100 flex items-center justify-center">
                                <div id="pdf-container" class="my-8 bg-white shadow-xl mx-auto" style="width: 794px; min-height: 1123px;">
                                    <iframe id="pdf-viewer" 
                                            src="{{ $pdfUrl ?? 'https://example.com/sample.pdf' }}" 
                                            class="w-full h-full border-none"
                                            style="min-height: 1123px; transform-origin: top center;"
                                            data-zoom="1">
                                    </iframe>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PDF Controls -->
                        <div class="bg-slate-800 text-white px-4 py-2 flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <button onclick="toggleRawText()" class="flex items-center text-xs bg-slate-700 text-gray-200 py-1 px-2 rounded hover:bg-slate-600 transition-colors">
                                    <span id="toggle-icon-text">Show Raw Text</span>
                                </button>
                                <button onclick="openPDFNewTab()" class="flex items-center text-xs bg-slate-700 text-gray-200 py-1 px-2 rounded hover:bg-slate-600 transition-colors">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Open in New Tab
                                </button>
                            </div>
                            <span class="text-xs text-gray-400">Use mousewheel to zoom or Ctrl++ and Ctrl+- keyboard shortcuts</span>
                        </div>
                    </div>

                    <!-- Raw Text Panel (Hidden by default) -->
                    <div id="raw-text-panel" class="w-1/2 border-l border-gray-200 flex-col h-full" style="display: none;">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-4 py-3 text-white flex items-center justify-between">
                            <h3 class="font-medium flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                </svg>
                                Extracted Raw Text
                            </h3>
                            <div class="flex items-center space-x-2">
                                <button onclick="copyRawText()" class="text-xs bg-purple-500 hover:bg-purple-400 py-1 px-2 rounded transition-colors flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0L12 14"></path>
                                    </svg>
                                    Copy
                                </button>
                                <button onclick="hideRawText()" class="text-xs bg-purple-500 hover:bg-purple-400 py-1 px-2 rounded transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 p-3 overflow-hidden">
                            <pre id="raw-text-display" class="w-full h-full font-mono text-xs bg-gray-50 rounded-lg border border-gray-200 p-4 overflow-auto whitespace-pre-wrap">{{ $rawText ?? 'No raw text available' }}</pre>
                        </div>
                    </div>

                    <!-- Form Panel -->
                    <div id="form-panel" class="w-1/3 border-l border-gray-200 flex flex-col h-full min-w-[300px]">
                        <div class="bg-gradient-to-r from-green-600 to-green-700 px-4 py-3 text-white">
                            <h3 class="font-medium flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                Data Verification
                            </h3>
                        </div>
                        <div class="flex-1 overflow-y-auto bg-gray-50">
                            <div class="p-4" x-data="verificationForm()">
                                <form id="verification-form" action="#" method="POST" class="space-y-5">
                                    @csrf
                                    
                                    <!-- Autosave Message -->
                                    <div x-show="isSaved" x-transition class="bg-green-50 text-green-700 px-3 py-1.5 rounded-md text-sm flex items-center mb-4">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Changes automatically saved
                                    </div>

                                    <!-- Patient Information Section -->
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="bg-blue-50 px-4 py-3 border-b border-blue-100">
                                            <div class="flex justify-between items-center">
                                                <h4 class="font-medium text-blue-800 flex items-center text-sm">
                                                    <svg class="w-4 h-4 mr-1.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                    Patient Information
                                                </h4>
                                                <button @click.prevent="addField('patient_info')" type="button"
                                                    class="text-xs bg-blue-500 text-white py-1 px-2 rounded hover:bg-blue-600 transition-colors flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                    Add Field
                                                </button>
                                            </div>
                                        </div>
                                        <div class="divide-y divide-gray-100">
                                            <template x-for="(field, index) in sections.patient_info || []" :key="index">
                                                <div class="flex items-center gap-2 p-3 hover:bg-gray-50 transition-colors">
                                                    <div class="w-1/3">
                                                        <label x-bind:for="'patient_field_' + index" class="block text-xs font-medium text-gray-700 mb-1">Field Name</label>
                                                        <input x-model="field.key" 
                                                            x-bind:id="'patient_field_' + index"
                                                            type="text"
                                                            placeholder="e.g., Patient ID"
                                                            class="w-full rounded border-gray-300 shadow-sm text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="flex-grow">
                                                        <label x-bind:for="'patient_value_' + index" class="block text-xs font-medium text-gray-700 mb-1">Value</label>
                                                        <input x-model="field.value"
                                                            x-bind:id="'patient_value_' + index"
                                                            type="text" 
                                                            placeholder="Field Value"
                                                            @change="triggerSave"
                                                            class="w-full rounded border-gray-300 shadow-sm text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="pt-5">
                                                        <button @click.prevent="removeField('patient_info', index)"
                                                            class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-50 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                            <div x-show="!sections.patient_info || sections.patient_info.length === 0" class="p-4 text-center text-sm text-gray-500">
                                                No patient information fields added yet.
                                                <button @click.prevent="addField('patient_info')" class="text-blue-600 hover:text-blue-800 font-medium">Add one now</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Test Results Section -->
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="bg-green-50 px-4 py-3 border-b border-green-100">
                                            <div class="flex justify-between items-center">
                                                <h4 class="font-medium text-green-800 flex items-center text-sm">
                                                    <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                                    </svg>
                                                    Test Results
                                                </h4>
                                                <button @click.prevent="addTestResult()" type="button"
                                                    class="text-xs bg-green-500 text-white py-1 px-2 rounded hover:bg-green-600 transition-colors flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                    Add Section
                                                </button>
                                            </div>
                                        </div>
                                        <div class="max-h-[400px] overflow-y-auto">
                                            <template x-for="(section, sectionName) in sections.test_results || {}" :key="sectionName">
                                                <div class="border-b border-gray-200 last:border-0">
                                                    <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                                                        <h5 class="font-medium text-gray-700 capitalize text-xs" x-text="formatSectionName(sectionName)"></h5>
                                                        <div class="flex items-center gap-1">
                                                            <button @click.prevent="addTestToSection(sectionName)" type="button"
                                                                class="text-xs bg-gray-200 text-gray-700 py-1 px-1.5 rounded hover:bg-gray-300 transition-colors">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                                </svg>
                                                            </button>
                                                            <button @click.prevent="removeSection(sectionName)" type="button"
                                                                class="text-xs bg-gray-200 text-red-600 py-1 px-1.5 rounded hover:bg-gray-300 transition-colors">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="divide-y divide-gray-100">
                                                        <template x-for="(test, testIndex) in section" :key="testIndex">
                                                            <div class="flex items-center gap-2 p-3 hover:bg-gray-50 transition-colors">
                                                                <div class="w-2/5">
                                                                    <input x-model="test.test_name" 
                                                                        type="text" 
                                                                        placeholder="Test Name"
                                                                        @change="triggerSave"
                                                                        class="w-full rounded border-gray-300 shadow-sm text-xs focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                                                </div>
                                                                <div class="w-1/4">
                                                                    <input x-model="test.value" 
                                                                        type="text" 
                                                                        placeholder="Result"
                                                                        @change="triggerSave"
                                                                        class="w-full rounded border-gray-300 shadow-sm text-xs focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                                                </div>
                                                                <div class="w-1/4">
                                                                    <input x-model="test.unit" 
                                                                        type="text" 
                                                                        placeholder="Unit"
                                                                        @change="triggerSave"
                                                                        class="w-full rounded border-gray-300 shadow-sm text-xs focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                                                </div>
                                                                <div class="pl-1">
                                                                    <button @click.prevent="removeTestFromSection(sectionName, testIndex)"
                                                                        class="text-red-400 hover:text-red-600 p-1 rounded-full hover:bg-red-50">
                                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                            <div x-show="!sections.test_results || Object.keys(sections.test_results).length === 0" class="p-4 text-center text-sm text-gray-500">
                                                No test results added yet.
                                                <button @click.prevent="addTestResult()" class="text-green-600 hover:text-green-800 font-medium">Add a section</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="sticky bottom-0 bg-white p-4 border-t border-gray-200 shadow-md flex justify-between items-center">
                                        <span class="text-xs text-gray-500">Last updated: <span x-text="lastSaved"></span></span>
                                        
                                        <div class="flex space-x-2">
                                            <button type="button" onclick="saveDraft()"
                                                class="flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors shadow-sm">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                                                </svg>
                                                Save Draft
                                            </button>
                                            <button type="submit"
                                                class="flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 transition-all shadow-sm">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                Approve & Finalize
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize once page is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading spinner once PDF is loaded
            document.getElementById('pdf-viewer').onload = function() {
                document.getElementById('pdf-loading').style.display = 'none';
            };
            
            // Set default layout
            setView('split');
            
            // Initial zoom level
            updateZoomLabel(1);
            
            // Add wheel zoom support
            document.getElementById('pdf-scroll-container').addEventListener('wheel', function(e) {
                if (e.ctrlKey) {
                    e.preventDefault();
                    zoomPDF(e.deltaY < 0 ? 'in' : 'out');
                }
            }, { passive: false });
        });
        
        function verificationForm() {
            return {
                sections: {!! json_encode($parsedData ?? ['patient_info' => [], 'test_results' => []]) !!},
                isSaved: false,
                lastSaved: 'Not saved yet',
                saveTimeout: null,
                
                formatSectionName(name) {
                    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                },
                
                triggerSave() {
                    clearTimeout(this.saveTimeout);
                    this.saveTimeout = setTimeout(() => {
                        this.saveData();
                    }, 1000); // Auto-save after 1 second of inactivity
                },
                
                saveData() {
                    // In production, this would call an API endpoint
                    console.log('Auto-saving:', this.sections);
                    this.isSaved = true;
                    this.lastSaved = new Date().toLocaleTimeString();
                    
                    // Hide saved message after 3 seconds
                    setTimeout(() => {
                        this.isSaved = false;
                    }, 3000);
                },
                
                addField(section) {
                    if (!this.sections[section]) {
                        this.sections[section] = [];
                    }
                    this.sections[section].push({ key: '', value: '' });
                    this.triggerSave();
                },
                
                removeField(section, index) {
                    this.sections[section].splice(index, 1);
                    this.triggerSave();
                },
                
                addTestResult() {
                    const sectionName = prompt('Enter section name (e.g., biochemistry, hematology):');
                    if (sectionName) {
                        const formattedName = sectionName.trim().toLowerCase().replace(/\s+/g, '_');
                        if (!this.sections.test_results) {
                            this.sections.test_results = {};
                        }
                        if (!this.sections.test_results[formattedName]) {
                            this.sections.test_results[formattedName] = [];
                            this.sections.test_results[formattedName].push({ test_name: '', value: '', unit: '' });
                        }
                        this.triggerSave();
                    }
                },
                
                addTestToSection(sectionName) {
                    this.sections.test_results[sectionName].push({ test_name: '', value: '', unit: '' });
                    this.triggerSave();
                },
                
                removeTestFromSection(sectionName, testIndex) {
                    this.sections.test_results[sectionName].splice(testIndex, 1);
                    if (this.sections.test_results[sectionName].length === 0) {
                        this.removeSection(sectionName);
                    }
                    this.triggerSave();
                },
                
                removeSection(sectionName) {
                    if (confirm(`Are you sure you want to delete the "${this.formatSectionName(sectionName)}" section?`)) {
                        delete this.sections.test_results[sectionName];
                        this.triggerSave();
                    }
                }
            }
        }

        // View management functions
        function setView(view) {
            const container = document.getElementById('main-container');
            const pdfPanel = document.getElementById('pdf-panel');
            const formPanel = document.getElementById('form-panel');
            const rawTextPanel = document.getElementById('raw-text-panel');
            
            // Update button styles
            document.getElementById('split-view-btn').className = 'flex items-center px-3 py-2 rounded-lg hover:bg-slate-600 transition-all';
            document.getElementById('pdf-view-btn').className = 'flex items-center px-3 py-2 rounded-lg hover:bg-slate-600 transition-all';
            document.getElementById('form-view-btn').className = 'flex items-center px-3 py-2 rounded-lg hover:bg-slate-600 transition-all';
            
            // Hide all panels first
            hideRawText();
            
            switch(view) {
                case 'split':
                    // Show both PDF and form side by side
                    pdfPanel.style.display = 'flex';
                    pdfPanel.className = 'flex-1 flex flex-col h-full';
                    formPanel.style.display = 'flex';
                    formPanel.className = 'w-1/3 border-l border-gray-200 flex flex-col h-full min-w-[300px]';
                    document.getElementById('split-view-btn').className = 'flex items-center px-3 py-2 rounded-lg bg-slate-600 text-white hover:bg-slate-500 transition-all';
                    break;
                    
                case 'pdf':
                    // Show only PDF
                    pdfPanel.style.display = 'flex';
                    pdfPanel.className = 'flex-1 flex flex-col h-full';
                    formPanel.style.display = 'none';
                    document.getElementById('pdf-view-btn').className = 'flex items-center px-3 py-2 rounded-lg bg-slate-600 text-white hover:bg-slate-500 transition-all';
                    break;
                    
                case 'form':
                    // Show only form
                    pdfPanel.style.display = 'none';
                    formPanel.style.display = 'flex';
                    formPanel.className = 'flex-1 flex flex-col h-full';
                    document.getElementById('form-view-btn').className = 'flex items-center px-3 py-2 rounded-lg bg-slate-600 text-white hover:bg-slate-500 transition-all';
                    break;
            }
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        function toggleRawText() {
            const pdfPanel = document.getElementById('pdf-panel');
            const rawTextPanel = document.getElementById('raw-text-panel');
            const toggleBtn = document.getElementById('toggle-icon-text');
            
            if (rawTextPanel.style.display === 'none' || !rawTextPanel.style.display) {
                rawTextPanel.style.display = 'flex';
                pdfPanel.className = 'w-1/2 flex flex-col h-full';
                toggleBtn.textContent = 'Hide Raw Text';
            } else {
                hideRawText();
            }
        }
        
        function hideRawText() {
            const pdfPanel = document.getElementById('pdf-panel');
            const rawTextPanel = document.getElementById('raw-text-panel');
            const toggleBtn = document.getElementById('toggle-icon-text');
            
            rawTextPanel.style.display = 'none';
            pdfPanel.className = 'flex-1 flex flex-col h-full';
            toggleBtn.textContent = 'Show Raw Text';
        }
        
        function copyRawText() {
            const textarea = document.getElementById('raw-text-display');
            navigator.clipboard.writeText(textarea.textContent);
            
            // Show temporary "Copied!" tooltip
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute top-12 right-4 bg-slate-800 text-white px-2 py-1 rounded text-xs';
            tooltip.textContent = 'Copied!';
            document.getElementById('raw-text-panel').appendChild(tooltip);
            
            setTimeout(() => tooltip.remove(), 2000);
        }

        function updateZoomLabel(level) {
            const zoomLabel = document.getElementById('zoom-level');
            zoomLabel.textContent = `${Math.round(level * 100)}%`;
        }

        function zoomPDF(direction) {
            const iframe = document.getElementById('pdf-viewer');
            const container = document.getElementById('pdf-scroll-container');
            let currentZoom = parseFloat(iframe.dataset.zoom || '1');
            
            if (direction === 'in') {
                currentZoom = Math.min(currentZoom + 0.1, 3);
            } else {
                currentZoom = Math.max(currentZoom - 0.1, 0.5);
            }
            
            iframe.dataset.zoom = currentZoom;
            iframe.style.transform = `scale(${currentZoom})`;
            updateZoomLabel(currentZoom);
            
            // Center the zoom if space allows
            if (currentZoom <= 1) {
                iframe.style.transformOrigin = 'top center';
            } else {
                iframe.style.transformOrigin = 'top center';
            }
        }

        function resetPDFZoom() {
            const iframe = document.getElementById('pdf-viewer');
            iframe.dataset.zoom = '1';
            iframe.style.transform = 'scale(1)';
            iframe.style.transformOrigin = 'top center';
            updateZoomLabel(1);
        }

        function openPDFNewTab() {
            window.open('{{ $pdfUrl ?? "https://example.com/sample.pdf" }}', '_blank');
        }

        function saveDraft() {
            const formData = document.querySelector('[x-data="verificationForm()"]').__x.$data.sections;
            console.log('Saving draft:', formData);
            
            // Show success notification
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center';
            notification.innerHTML = `
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Draft saved successfully!</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 3000);
            
            // Update the last saved time
            document.querySelector('[x-data="verificationForm()"]').__x.$data.lastSaved = new Date().toLocaleTimeString();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveDraft();
            }
            
            // F11 for fullscreen
            if (e.key === 'F11') {
                e.preventDefault();
                toggleFullscreen();
            }
            
            // Ctrl + 0 to reset zoom
            if (e.ctrlKey && (e.key === '0' || e.key === 'NumPad0')) {
                e.preventDefault();
                resetPDFZoom();
            }
            
            // Ctrl + Plus to zoom in
            if (e.ctrlKey && (e.key === '+' || e.key === '=' || e.key === 'NumpadAdd')) {
                e.preventDefault();
                zoomPDF('in');
            }
            
            // Ctrl + Minus to zoom out
            if (e.ctrlKey && (e.key === '-' || e.key === 'NumpadSubtract')) {
                e.preventDefault();
                zoomPDF('out');
            }
            
            // Alt + T to toggle raw text
            if (e.altKey && e.key === 't') {
                e.preventDefault();
                toggleRawText();
            }
        });
    </script>
</x-app-layout>