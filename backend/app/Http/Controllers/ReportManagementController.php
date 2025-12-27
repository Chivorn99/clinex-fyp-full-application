<?php

namespace App\Http\Controllers;

use App\Models\LabReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportManagementController extends Controller
{
    /**
     * Display a listing of all reports.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Fetch all lab reports with pagination
        $reports = LabReport::with(['patient:id,name', 'uploadedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.reports.index', compact('reports'));
    }

    /**
     * Display the specified report.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $report = LabReport::with(['patient', 'uploadedBy', 'verifiedBy', 'extractedData'])
            ->findOrFail($id);

        return view('admin.reports.show', compact('report'));
    }

    /**
     * Download the original report file.
     *
     * @param  int  $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
     */
    public function download($id)
    {
        $report = LabReport::findOrFail($id);

        try {
            if (!$report->fileExists()) {
                return back()->with('error', 'Report file not found on the server.');
            }

            // Get file path using the model's getFullPath method
            $filePath = $report->getFullPath();

            // Use stored filename if original isn't available
            $fileName = $report->attributes['original_filename'] ?? $report->attributes['stored_filename'] ?? 'report.pdf';

            return response()->download($filePath, $fileName);
        } catch (\Exception $e) {
            return back()->with('error', 'Could not download file: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $report = LabReport::findOrFail($id);
        return view('admin.reports.edit', compact('report'));
    }

    public function update(Request $request, $id)
    {
        $report = LabReport::findOrFail($id);

        $validated = $request->validate([
            'original_filename' => 'required|string|max:255',
            'report_date' => 'nullable|date',
            'status' => 'required|in:processing,processed,verified,error',
        ]);

        $report->update($validated);

        return redirect()->route('reports.show', $report->id)
            ->with('success', 'Report updated successfully.');
    }

    public function destroy($id)
    {
        $report = LabReport::findOrFail($id);
        // Add any additional checks or logic here
        $report->delete();

        return redirect()->route('reports.index')->with('success', 'Report deleted successfully.');
    }
}
