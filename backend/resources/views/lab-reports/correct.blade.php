<!DOCTYPE html>
<html>

<head>
    <title>Correct Lab Report - {{ $labReport->id }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .side-by-side {
            display: flex;
            gap: 20px;
        }

        .pdf-side {
            flex: 1;
        }

        .data-side {
            flex: 1;
            max-height: 800px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .section-container {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-title {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px 20px;
            margin: 0;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .section-title:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-1px);
        }

        .section-title.active {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .dropdown-arrow {
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        .section-title.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            background-color: #f8f9fa;
        }

        .section-content.active {
            max-height: none;
            padding: 20px;
            overflow-y: auto;
        }

        .test-count {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: normal;
        }

        .test-row {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background-color: white;
            transition: all 0.2s ease;
        }

        .test-row:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
        }

        .test-field {
            margin-bottom: 12px;
        }

        .test-field:last-child {
            margin-bottom: 0;
        }

        .test-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
            font-size: 14px;
        }

        .test-field input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .test-field input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }

        .changed {
            background-color: #fff3cd;
            border-color: #ffc107 !important;
        }

        .changed:focus {
            border-color: #ffc107 !important;
            box-shadow: 0 0 0 3px rgba(255,193,7,0.2) !important;
        }

        .save-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(40,167,69,0.2);
        }

        .save-btn:hover {
            background: linear-gradient(135deg, #218838, #1c7430);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(40,167,69,0.3);
        }

        .save-btn:active {
            transform: translateY(0);
        }

        iframe {
            width: 100%;
            height: 800px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .stats-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .expand-all-btn {
            background-color: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }

        .expand-all-btn:hover {
            background-color: #5a6268;
        }

        .data-side::-webkit-scrollbar {
            width: 8px;
        }

        .data-side::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .data-side::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .data-side::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .section-content::-webkit-scrollbar {
            width: 6px;
        }

        .section-content::-webkit-scrollbar-track {
            background: #e9ecef;
            border-radius: 3px;
        }

        .section-content::-webkit-scrollbar-thumb {
            background: #ced4da;
            border-radius: 3px;
        }

        .section-content::-webkit-scrollbar-thumb:hover {
            background: #adb5bd;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Correct Lab Report #{{ $labReport->id }}</h1>

        <div class="side-by-side">
            <!-- PDF Viewer -->
            <div class="pdf-side">
                <h3>Original PDF</h3>
                <iframe src="{{ $pdfUrl }}" frameborder="0"></iframe>
            </div>

            <!-- Extracted Data Editor -->
            <div class="data-side">
                <h3>Extracted Data - Please Review & Correct</h3>

                <!-- Stats Bar -->
                <div class="stats-bar">
                    <div class="stat-item">
                        <span class="stat-number" id="totalSections">0</span>
                        <span class="stat-label">Sections</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="totalTests">0</span>
                        <span class="stat-label">Tests</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="changedFields">0</span>
                        <span class="stat-label">Modified</span>
                    </div>
                </div>

                <button type="button" class="expand-all-btn" id="expandAllBtn">Expand All Sections</button>

                <form id="correctionForm">
                    <!-- Patient Info Section -->
                    @if(isset($extractedData['patient_info']) && count($extractedData['patient_info']) > 0)
                        <div class="section-container">
                            <div class="section-title" data-section="patient_info">
                                <span>
                                    👤 PATIENT INFORMATION
                                    <span class="test-count">{{ count($extractedData['patient_info']) }} fields</span>
                                </span>
                                <span class="dropdown-arrow">▼</span>
                            </div>
                            <div class="section-content">
                                @foreach($extractedData['patient_info'] as $field => $value)
                                    <div class="test-row">
                                        <div class="test-field">
                                            <label>{{ ucfirst(str_replace('_', ' ', $field)) }}:</label>
                                            <input type="text" name="patient_info[{{ $field }}]" value="{{ $value }}"
                                                data-original="{{ $value }}" data-type="patient_info" data-field="{{ $field }}">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Test Results Sections -->
                    @if(isset($extractedData['test_results']))
                        @foreach($extractedData['test_results'] as $sectionName => $tests)
                            <div class="section-container">
                                <div class="section-title" data-section="{{ $sectionName }}">
                                    <span>
                                        🧪 {{ strtoupper(str_replace('_', ' ', $sectionName)) }}
                                        <span class="test-count">{{ count($tests) }} tests</span>
                                    </span>
                                    <span class="dropdown-arrow">▼</span>
                                </div>
                                <div class="section-content">
                                    @foreach($tests as $index => $test)
                                        <div class="test-row">
                                            <div class="test-field">
                                                <label>Test Name:</label>
                                                <input type="text" name="test_results[{{ $sectionName }}][{{ $index }}][test_name]"
                                                    value="{{ $test['test_name'] }}" data-original="{{ $test['test_name'] }}"
                                                    data-type="test_name" data-section="{{ $sectionName }}" data-index="{{ $index }}">
                                            </div>

                                            <div class="test-field">
                                                <label>Value:</label>
                                                <input type="text" name="test_results[{{ $sectionName }}][{{ $index }}][value]"
                                                    value="{{ $test['value'] }}" data-original="{{ $test['value'] }}" data-type="value"
                                                    data-section="{{ $sectionName }}" data-index="{{ $index }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif

                    <button type="submit" class="save-btn">💾 Save Corrections & Train System</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('correctionForm');
            const inputs = form.querySelectorAll('input[data-original]');
            const sectionTitles = document.querySelectorAll('.section-title');
            const expandAllBtn = document.getElementById('expandAllBtn');
            let corrections = [];
            let allExpanded = false;

            // Initialize stats
            updateStats();

            // Section toggle functionality
            sectionTitles.forEach(title => {
                title.addEventListener('click', function () {
                    const content = this.nextElementSibling;
                    const isActive = content.classList.contains('active');

                    if (isActive) {
                        content.classList.remove('active');
                        this.classList.remove('active');
                    } else {
                        content.classList.add('active');
                        this.classList.add('active');
                    }
                });
            });

            // Expand/Collapse all functionality
            expandAllBtn.addEventListener('click', function () {
                const contents = document.querySelectorAll('.section-content');
                const titles = document.querySelectorAll('.section-title');

                if (allExpanded) {
                    contents.forEach(content => content.classList.remove('active'));
                    titles.forEach(title => title.classList.remove('active'));
                    this.textContent = 'Expand All Sections';
                    allExpanded = false;
                } else {
                    contents.forEach(content => content.classList.add('active'));
                    titles.forEach(title => title.classList.add('active'));
                    this.textContent = 'Collapse All Sections';
                    allExpanded = true;
                }
            });

            // Track changes and update stats
            inputs.forEach(input => {
                input.addEventListener('input', function () {
                    const original = this.dataset.original;
                    const current = this.value;

                    if (original !== current) {
                        this.classList.add('changed');
                    } else {
                        this.classList.remove('changed');
                    }

                    updateStats();
                });
            });

            function updateStats() {
                const totalSections = document.querySelectorAll('.section-container').length;
                const totalTests = inputs.length;
                const changedFields = document.querySelectorAll('.changed').length;

                document.getElementById('totalSections').textContent = totalSections;
                document.getElementById('totalTests').textContent = totalTests;
                document.getElementById('changedFields').textContent = changedFields;
            }

            // Handle form submission
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                corrections = [];
                const formData = new FormData(form);
                const extractedData = { patient_info: {}, test_results: {} };

                // Build corrections array and updated data
                inputs.forEach(input => {
                    const original = input.dataset.original;
                    const corrected = input.value;
                    const type = input.dataset.type;

                    if (original !== corrected && corrected.trim() !== '') {
                        corrections.push({
                            original: original,
                            corrected: corrected,
                            type: type
                        });
                    }
                });

                // Build updated extracted data structure
                for (let [key, value] of formData.entries()) {
                    const keys = key.match(/(\w+)\[([^\]]+)\](?:\[([^\]]+)\])?(?:\[([^\]]+)\])?/);
                    if (keys) {
                        if (keys[1] === 'patient_info') {
                            extractedData.patient_info[keys[2]] = value;
                        } else if (keys[1] === 'test_results') {
                            if (!extractedData.test_results[keys[2]]) {
                                extractedData.test_results[keys[2]] = [];
                            }
                            if (!extractedData.test_results[keys[2]][keys[3]]) {
                                extractedData.test_results[keys[2]][keys[3]] = {};
                            }
                            extractedData.test_results[keys[2]][keys[3]][keys[4]] = value;
                        }
                    }
                }

                // Show loading state
                const saveBtn = document.querySelector('.save-btn');
                const originalText = saveBtn.textContent;
                saveBtn.textContent = '💾 Saving...';
                saveBtn.disabled = true;

                // Send to server
                fetch(`/lab-reports/{{ $labReport->id }}/corrections`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        corrections: corrections,
                        extractedData: extractedData
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`✅ ${data.message}`);
                            // Remove changed styling
                            inputs.forEach(input => input.classList.remove('changed'));
                            updateStats();
                        } else {
                            alert('❌ Error saving corrections');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ Error saving corrections');
                    })
                    .finally(() => {
                        saveBtn.textContent = originalText;
                        saveBtn.disabled = false;
                    });
            });
        });
    </script>
</body>

</html>