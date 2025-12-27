<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Upload Lab Reports
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Template Selection -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Step 1: Select Template</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="template-selection">
                            <!-- Templates will be loaded here -->
                        </div>
                    </div>

                    <!-- Upload Area -->
                    <div class="mb-8" id="upload-area" style="display: none;">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Step 2: Upload PDF Files</h3>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center" id="drop-zone">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-gray-600 mb-4">Drag and drop PDF files here, or click to browse</p>
                            <input type="file" id="file-input" multiple accept=".pdf" class="hidden">
                            <button onclick="document.getElementById('file-input').click()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Choose Files
                            </button>
                        </div>
                        
                        <div class="mt-4">
                            <div id="file-list" class="space-y-2"></div>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button id="upload-btn" onclick="uploadFiles()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors" disabled>
                                Upload Files
                            </button>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div id="upload-progress" class="mb-8" style="display: none;">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload Progress</h3>
                        <div class="space-y-2" id="progress-list">
                            <!-- Progress items will be shown here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedTemplate = null;
        let selectedFiles = [];

        // Load templates on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTemplates();
            setupFileUpload();
        });

        function loadTemplates() {
            fetch('/api/templates/upload')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTemplates(data.templates);
                    } else {
                        console.error('Failed to load templates');
                    }
                })
                .catch(error => {
                    console.error('Error loading templates:', error);
                });
        }

        function displayTemplates(templates) {
            const container = document.getElementById('template-selection');
            container.innerHTML = '';

            if (templates.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-8">
                        <p class="text-gray-600 mb-4">No templates available. Please contact your administrator.</p>
                    </div>
                `;
                return;
            }

            templates.forEach(template => {
                const templateCard = document.createElement('div');
                templateCard.className = 'border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 transition-colors template-card';
                templateCard.dataset.templateId = template.id;
                templateCard.innerHTML = `
                    <h4 class="font-medium text-gray-900 mb-2">${template.name}</h4>
                    <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                `;
                
                templateCard.addEventListener('click', () => selectTemplate(template));
                container.appendChild(templateCard);
            });
        }

        function selectTemplate(template) {
            selectedTemplate = template;
            
            // Update visual selection
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('border-blue-500', 'bg-blue-50');
            });
            
            document.querySelector(`[data-template-id="${template.id}"]`).classList.add('border-blue-500', 'bg-blue-50');
            
            // Show upload area
            document.getElementById('upload-area').style.display = 'block';
        }

        function setupFileUpload() {
            const dropZone = document.getElementById('drop-zone');
            const fileInput = document.getElementById('file-input');

            // Handle drag and drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-blue-500', 'bg-blue-50');
            });

            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-blue-500', 'bg-blue-50');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-blue-500', 'bg-blue-50');
                
                const files = Array.from(e.dataTransfer.files).filter(file => file.type === 'application/pdf');
                addFiles(files);
            });

            // Handle file input change
            fileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                addFiles(files);
            });
        }

        function addFiles(files) {
            selectedFiles = [...selectedFiles, ...files];
            displayFileList();
            updateUploadButton();
        }

        function displayFileList() {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg';
                fileItem.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-900">${file.name}</span>
                        <span class="text-xs text-gray-500 ml-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                    </div>
                    <button onclick="removeFile(${index})" class="text-red-600 hover:text-red-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                fileList.appendChild(fileItem);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayFileList();
            updateUploadButton();
        }

        function updateUploadButton() {
            const uploadBtn = document.getElementById('upload-btn');
            uploadBtn.disabled = selectedFiles.length === 0 || !selectedTemplate;
        }

        function uploadFiles() {
            if (!selectedTemplate || selectedFiles.length === 0) {
                alert('Please select a template and files to upload');
                return;
            }

            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            formData.append('template_id', selectedTemplate.id);

            // Show progress
            document.getElementById('upload-progress').style.display = 'block';
            document.getElementById('upload-btn').disabled = true;

            fetch('/api/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUploadResults(data.data);
                    // Reset form
                    selectedFiles = [];
                    displayFileList();
                    updateUploadButton();
                } else {
                    alert('Upload failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Upload failed');
            })
            .finally(() => {
                document.getElementById('upload-btn').disabled = false;
            });
        }

        function displayUploadResults(results) {
            const progressList = document.getElementById('progress-list');
            progressList.innerHTML = '';

            results.forEach(result => {
                const progressItem = document.createElement('div');
                progressItem.className = 'flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200';
                progressItem.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-900">${result.filename}</span>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                        ${result.status}
                    </span>
                `;
                progressList.appendChild(progressItem);
            });

            // Show success message
            setTimeout(() => {
                alert('Files uploaded successfully! They will be processed shortly.');
            }, 500);
        }
    </script>
</x-app-layout>
