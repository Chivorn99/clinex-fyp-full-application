<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\LabReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    /**
     * Display a listing of patients with filtering and search
     */
    public function index(Request $request)
    {
        $query = Patient::query();

        // Search by name, patient_id, or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('patient_id', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // Order by creation date (latest first)
        $patients = $query->latest()->paginate($request->get('per_page', 15));

        // Add lab reports count and latest report date to each patient
        $patients->getCollection()->transform(function ($patient) {
            $patient->lab_reports_count = $patient->labReports()->count();
            $patient->latest_report_date = $patient->labReports()
                ->latest('created_at')
                ->value('created_at');
            return $patient;
        });

        // If the request expects JSON, return as API
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $patients,
                'message' => 'Patients retrieved successfully'
            ]);
        }

        // Otherwise, return the Blade view
        return view('admin.patient.index', compact('patients'));
    }

    /**
     * Store a newly created patient
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|string|unique:patients,patient_id',
            'name' => 'required|string|max:255',
            'age' => 'required|string|max:50',
            'gender' => 'required|in:Male,Female',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $patient = Patient::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $patient,
            'message' => 'Patient created successfully'
        ], 201);
    }

    /**
     * Display the specified patient with lab reports
     */
    public function show(Patient $patient)
    {
        $patient->load([
            'labReports' => function($query) {
                $query->with(['batch', 'uploader', 'verifier'])
                      ->latest();
            }
        ]);

        // Get patient statistics
        $stats = [
            'total_reports' => $patient->labReports()->count(),
            'verified_reports' => $patient->labReports()->whereNotNull('verified_at')->count(),
            'pending_reports' => $patient->labReports()->where('status', 'processed')->whereNull('verified_at')->count(),
            'latest_report_date' => $patient->labReports()->latest()->value('created_at'),
            'first_report_date' => $patient->labReports()->oldest()->value('created_at'),
        ];

        // If API
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'patient' => $patient,
                    'statistics' => $stats
                ],
                'message' => 'Patient details retrieved successfully'
            ]);
        }

        // For web
        return view('admin.patients.show', compact('patient', 'stats'));
    }

    /**
     * Search for patients (for autocomplete/suggestions)
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Query too short'
            ]);
        }

        $patients = Patient::where('name', 'LIKE', "%{$query}%")
            ->orWhere('patient_id', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->select(['id', 'patient_id', 'name', 'phone', 'gender', 'age'])
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $patients,
            'message' => 'Search results retrieved successfully'
        ]);
    }

    /**
     * Show the edit form
     */
    public function edit(Patient $patient)
    {
        return view('admin.patients.edit', compact('patient'));
    }

    /**
     * Handle the update
     */
    public function update(Request $request, Patient $patient)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|string|unique:patients,patient_id,' . $patient->id,
            'name' => 'required|string|max:255',
            'age' => 'required|string|max:50',
            'gender' => 'required|in:Male,Female',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $patient->update($validator->validated());

        return redirect()->route('patients.show', $patient->id)
            ->with('success', 'Patient updated successfully.');
    }

    /**
     * Remove the specified patient
     */
    public function destroy(Patient $patient)
    {
        // Check if patient has lab reports
        if ($patient->labReports()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete patient with existing lab reports'
            ], 422);
        }

        $patient->delete();

        return response()->json([
            'success' => true,
            'message' => 'Patient deleted successfully'
        ]);
    }

    /**
     * Get patient's lab reports with test results
     */
    public function labReports(Patient $patient, Request $request)
    {
        $query = $patient->labReports()
            ->with(['batch', 'uploader', 'verifier', 'extractedData', 'extractedLabInfo']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by verified status
        if ($request->filled('verified')) {
            if ($request->boolean('verified')) {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        $labReports = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $labReports,
            'message' => 'Patient lab reports retrieved successfully'
        ]);
    }
}
