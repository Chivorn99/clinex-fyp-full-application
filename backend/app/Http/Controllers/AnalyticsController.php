<?php

namespace App\Http\Controllers;

use App\Models\LabReport;
use App\Models\ExtractedData;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Aggregated analytics dashboard data.
     *
     * Accepts ?days=7|14|30|0 (0 = all time).
     * All computation is server-side to avoid shipping raw records to the browser.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $dateFilter = $days > 0 ? now()->subDays($days) : null;

        return response()->json([
            'stats'                  => $this->getStats($dateFilter),
            'report_volume'          => $this->getReportVolume($dateFilter),
            'category_distribution'  => $this->getCategoryDistribution($dateFilter),
            'top_abnormal_tests'     => $this->getTopAbnormalTests($dateFilter),
            'patient_demographics'   => $this->getPatientDemographics($dateFilter),
            'confidence_by_category' => $this->getConfidenceByCategory($dateFilter),
            'technician_activity'    => $this->getTechnicianActivity($dateFilter),
        ]);
    }

    // ── Stats Cards ─────────────────────────────────────────────

    private function getStats($dateFilter): array
    {
        $query = LabReport::query();
        if ($dateFilter) {
            $query->where('created_at', '>=', $dateFilter);
        }

        $totalReports        = (clone $query)->count();
        $pendingVerification = (clone $query)->where('status', 'processed')->count();
        $avgProcessingTime   = (clone $query)->whereNotNull('processing_time')
                                              ->where('processing_time', '>', 0)
                                              ->avg('processing_time') ?? 0;

        $abnormalQuery = ExtractedData::whereNotNull('flag');
        if ($dateFilter) {
            $abnormalQuery->whereHas('labReport', fn ($q) => $q->where('created_at', '>=', $dateFilter));
        }

        return [
            'total_reports'        => $totalReports,
            'pending_verification' => $pendingVerification,
            'avg_processing_time'  => round((float) $avgProcessingTime, 1),
            'abnormal_results'     => $abnormalQuery->count(),
        ];
    }

    // ── Report Volume (daily counts) ────────────────────────────

    private function getReportVolume($dateFilter): array
    {
        $query = LabReport::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )->groupBy('date')->orderBy('date');

        if ($dateFilter) {
            $query->where('created_at', '>=', $dateFilter);
        }

        return $query->get()->map(fn ($row) => [
            'date'  => $row->date,
            'count' => (int) $row->count,
        ])->toArray();
    }

    // ── Test Category Distribution ──────────────────────────────

    private function getCategoryDistribution($dateFilter): array
    {
        $query = ExtractedData::select(
            'category',
            DB::raw('COUNT(*) as value')
        )->groupBy('category')->orderByDesc('value');

        if ($dateFilter) {
            $query->whereHas('labReport', fn ($q) => $q->where('created_at', '>=', $dateFilter));
        }

        return $query->get()->map(fn ($row) => [
            'name'  => $row->category,
            'value' => (int) $row->value,
        ])->toArray();
    }

    // ── Top Abnormal Tests ──────────────────────────────────────

    private function getTopAbnormalTests($dateFilter): array
    {
        $query = ExtractedData::select(
            'test_name',
            DB::raw("SUM(CASE WHEN flag = 'H' THEN 1 ELSE 0 END) as high"),
            DB::raw("SUM(CASE WHEN flag = 'L' THEN 1 ELSE 0 END) as low")
        )
        ->whereNotNull('flag')
        ->groupBy('test_name')
        ->orderByRaw("(SUM(CASE WHEN flag = 'H' THEN 1 ELSE 0 END) + SUM(CASE WHEN flag = 'L' THEN 1 ELSE 0 END)) DESC")
        ->limit(8);

        if ($dateFilter) {
            $query->whereHas('labReport', fn ($q) => $q->where('created_at', '>=', $dateFilter));
        }

        return $query->get()->map(fn ($row) => [
            'test_name' => $row->test_name,
            'high'      => (int) $row->high,
            'low'       => (int) $row->low,
        ])->toArray();
    }

    // ── Patient Demographics ────────────────────────────────────

    private function getPatientDemographics($dateFilter): array
    {
        // Gender breakdown
        $genderQuery = Patient::select('gender', DB::raw('COUNT(*) as count'))->groupBy('gender');
        if ($dateFilter) {
            $genderQuery->where('created_at', '>=', $dateFilter);
        }
        $gender = $genderQuery->pluck('count', 'gender')->toArray();

        // Age groups — parse "38 Y, 0 M, 0 D" format
        $patientsQuery = Patient::select('age');
        if ($dateFilter) {
            $patientsQuery->where('created_at', '>=', $dateFilter);
        }

        $ageGroups = ['0-17' => 0, '18-30' => 0, '31-45' => 0, '46-60' => 0, '60+' => 0];
        foreach ($patientsQuery->get() as $patient) {
            $years = $this->parseAgeYears($patient->age);
            if ($years === null) continue;

            if ($years <= 17)      $ageGroups['0-17']++;
            elseif ($years <= 30)  $ageGroups['18-30']++;
            elseif ($years <= 45)  $ageGroups['31-45']++;
            elseif ($years <= 60)  $ageGroups['46-60']++;
            else                   $ageGroups['60+']++;
        }

        return [
            'gender'     => $gender,
            'age_groups' => array_map(
                fn ($range, $count) => ['range' => $range, 'count' => $count],
                array_keys($ageGroups),
                array_values($ageGroups)
            ),
        ];
    }

    // ── OCR Confidence by Category ──────────────────────────────

    private function getConfidenceByCategory($dateFilter): array
    {
        $query = ExtractedData::select(
            'category',
            DB::raw('ROUND(AVG(confidence_score) * 100, 1) as avg_confidence')
        )
        ->whereNotNull('confidence_score')
        ->groupBy('category');

        if ($dateFilter) {
            $query->whereHas('labReport', fn ($q) => $q->where('created_at', '>=', $dateFilter));
        }

        return $query->get()->map(fn ($row) => [
            'category'       => $row->category,
            'avg_confidence' => (float) $row->avg_confidence,
        ])->toArray();
    }

    // ── Technician Activity ─────────────────────────────────────

    private function getTechnicianActivity($dateFilter): array
    {
        // Uploads per user
        $uploadQuery = LabReport::select('uploaded_by', DB::raw('COUNT(*) as count'))
            ->whereNotNull('uploaded_by')
            ->groupBy('uploaded_by');
        if ($dateFilter) {
            $uploadQuery->where('created_at', '>=', $dateFilter);
        }
        $uploadCounts = $uploadQuery->pluck('count', 'uploaded_by');

        // Verifications per user
        $verifyQuery = LabReport::select('verified_by', DB::raw('COUNT(*) as count'))
            ->whereNotNull('verified_by')
            ->groupBy('verified_by');
        if ($dateFilter) {
            $verifyQuery->where('created_at', '>=', $dateFilter);
        }
        $verifyCounts = $verifyQuery->pluck('count', 'verified_by');

        // Merge user IDs and fetch names
        $userIds = $uploadCounts->keys()->merge($verifyCounts->keys())->unique()->filter();
        $users   = User::whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds->map(fn ($id) => [
            'id'            => (int) $id,
            'name'          => $users[$id] ?? 'Unknown',
            'uploads'       => (int) ($uploadCounts[$id] ?? 0),
            'verifications' => (int) ($verifyCounts[$id] ?? 0),
        ])
        ->sortByDesc(fn ($u) => $u['uploads'] + $u['verifications'])
        ->values()
        ->take(5)
        ->toArray();
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Extract the year component from age strings like "38 Y, 0 M, 0 D".
     */
    private function parseAgeYears(?string $age): ?int
    {
        if (!$age) return null;

        // "38 Y, 0 M, 0 D" or "38Y"
        if (preg_match('/(\d+)\s*[yY]/', $age, $m)) {
            return (int) $m[1];
        }

        // Plain number
        if (is_numeric(trim($age))) {
            return (int) trim($age);
        }

        return null;
    }
}
