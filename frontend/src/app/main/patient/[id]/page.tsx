'use client'
import { useState, useEffect, useCallback } from 'react'
import { useRouter, useParams } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { ArrowLeft, User, Phone, Calendar, FileText, Activity, Clock, CheckCircle, RefreshCw, AlertCircle, XCircle, Eye, ChevronRight } from 'lucide-react'
import { apiClient } from '@/lib/api'

interface Patient {
    id: number
    patient_id: string
    name: string
    age: string
    gender: string
    phone: string | null
    email: string | null
    created_at: string
    updated_at: string
    lab_reports: PatientLabReport[]
}

interface PatientStats {
    total_reports: number
    verified_reports: number
    pending_reports: number
    latest_report_date: string | null
    first_report_date: string | null
}

interface PatientLabReport {
    id: number
    original_filename: string
    status: string
    verified_at: string | null
    processed_at: string | null
    notes: string | null
    extracted_data: Array<{
        id: number
        category: string
        test_name: string
        result: string
        unit: string
        reference: string
        flag: string | null
    }>
    batch: { id: number; name: string; status: string }
    uploader: { id: number; name: string; email: string }
    verifier: { id: number; name: string } | null
    extracted_lab_info: {
        lab_id: string
        requested_by: string
        collected_date: string
        analysis_date: string
    } | null
}

interface PaginationData {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
}

type ExtractedTest = PatientLabReport['extracted_data'][number]

export default function PatientDetailPage() {
    const router = useRouter()
    const params = useParams()
    const patientId = params.id as string

    const [patient, setPatient] = useState<Patient | null>(null)
    const [stats, setStats] = useState<PatientStats | null>(null)
    const [reports, setReports] = useState<PatientLabReport[]>([])
    const [loading, setLoading] = useState(true)
    const [reportsLoading, setReportsLoading] = useState(true)
    const [error, setError] = useState('')
    const [reportsPagination, setReportsPagination] = useState<PaginationData | null>(null)
    const [statusFilter, setStatusFilter] = useState<string>('')
    const [expandedReportId, setExpandedReportId] = useState<number | null>(null)

    const fetchPatient = useCallback(async () => {
        try {
            setLoading(true)
            setError('')
            const response = await apiClient.get(`/patients/${patientId}`)
            if (response.success && response.data) {
                setPatient(response.data.patient)
                setStats(response.data.statistics)
            }
        } catch (err: unknown) {
            console.error('Failed to fetch patient:', err)
            setError('Failed to load patient details')
        } finally {
            setLoading(false)
        }
    }, [patientId])

    const fetchReports = useCallback(async (page: number = 1) => {
        try {
            setReportsLoading(true)
            let url = `/patients/${patientId}/lab-reports?page=${page}&per_page=10`
            if (statusFilter) url += `&status=${statusFilter}`
            const response = await apiClient.get(url)
            if (response.success && response.data) {
                setReports(response.data.data || [])
                setReportsPagination({
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    per_page: response.data.per_page,
                    total: response.data.total,
                    from: response.data.from,
                    to: response.data.to,
                })
            }
        } catch {
            setReports([])
        } finally {
            setReportsLoading(false)
        }
    }, [patientId, statusFilter])

    useEffect(() => { fetchPatient() }, [fetchPatient])
    useEffect(() => { fetchReports(1) }, [fetchReports])

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A'
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        })
    }

    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            verified: 'bg-green-100 text-green-800',
            processed: 'bg-blue-100 text-blue-800',
            processing: 'bg-yellow-100 text-yellow-800',
            failed: 'bg-red-100 text-red-800',
        }
        const icons: Record<string, React.ReactNode> = {
            verified: <CheckCircle className="h-3.5 w-3.5 mr-1" />,
            processed: <Clock className="h-3.5 w-3.5 mr-1" />,
            processing: <RefreshCw className="h-3.5 w-3.5 mr-1 animate-spin" />,
            failed: <XCircle className="h-3.5 w-3.5 mr-1" />,
        }
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[status] || 'bg-gray-100 text-gray-800'}`}>
                {icons[status] || <AlertCircle className="h-3.5 w-3.5 mr-1" />}
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        )
    }

    const groupTestsByCategory = (tests: ExtractedTest[]) => {
        return tests.reduce((acc, test) => {
            const category = test.category || 'UNCATEGORIZED'
            if (!acc[category]) acc[category] = []
            acc[category].push(test)
            return acc
        }, {} as Record<string, ExtractedTest[]>)
    }

    const getFlagColor = (flag: string | null) => {
        if (!flag) return ''
        switch (flag.toUpperCase()) {
            case 'H': case 'HIGH': return 'text-red-600 font-semibold'
            case 'L': case 'LOW': return 'text-amber-600 font-semibold'
            case 'C': case 'CRITICAL': return 'text-red-700 font-bold'
            default: return 'text-green-600'
        }
    }

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center min-h-96">
                    <RefreshCw className="h-8 w-8 animate-spin text-blue-600 mr-3" />
                    <span className="text-gray-600">Loading patient details...</span>
                </div>
            </DashboardLayout>
        )
    }

    if (error || !patient) {
        return (
            <DashboardLayout>
                <div className="text-center py-12">
                    <AlertCircle className="h-12 w-12 text-red-400 mx-auto mb-4" />
                    <div className="text-red-600 text-lg font-medium mb-4">{error || 'Patient not found'}</div>
                    <button onClick={() => router.back()} className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <ArrowLeft className="h-4 w-4 mr-2" /> Go Back
                    </button>
                </div>
            </DashboardLayout>
        )
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <button onClick={() => router.push('/main/patient')} className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <ArrowLeft className="h-4 w-4 mr-2" /> Back to Patients
                        </button>
                        <div className="flex items-center">
                            <div className="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                <User className="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900">{patient.name}</h1>
                                <p className="text-sm text-gray-500">Patient ID: {patient.patient_id}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Patient Info + Stats Cards */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Patient Demographics */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <User className="h-5 w-5 mr-2 text-blue-600" /> Patient Information
                        </h3>
                        <dl className="space-y-3">
                            {[
                                { label: 'Age', value: patient.age },
                                { label: 'Gender', value: patient.gender },
                                { label: 'Phone', value: patient.phone || 'Not provided', icon: <Phone className="h-4 w-4 mr-1 text-gray-400" /> },
                                { label: 'Email', value: patient.email || 'Not provided' },
                                { label: 'Registered', value: formatDate(patient.created_at), icon: <Calendar className="h-4 w-4 mr-1 text-gray-400" /> },
                            ].map(item => (
                                <div key={item.label} className="flex justify-between">
                                    <dt className="text-sm font-medium text-gray-500">{item.label}</dt>
                                    <dd className="text-sm text-gray-900 flex items-center">
                                        {item.icon}{item.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    {/* Reports Stats */}
                    <div className="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                        {[
                            { label: 'Total Reports', value: stats?.total_reports || 0, icon: FileText, color: 'blue' },
                            { label: 'Verified', value: stats?.verified_reports || 0, icon: CheckCircle, color: 'green' },
                            { label: 'Pending', value: stats?.pending_reports || 0, icon: Clock, color: 'amber' },
                            { label: 'History Since', value: stats?.first_report_date ? new Date(stats.first_report_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : 'N/A', icon: Activity, color: 'purple', isText: true },
                        ].map(stat => (
                            <div key={stat.label} className="bg-white shadow rounded-lg p-5">
                                <div className={`inline-flex p-2 rounded-lg bg-${stat.color}-100 text-${stat.color}-600 mb-3`}>
                                    <stat.icon className="h-5 w-5" />
                                </div>
                                <p className="text-sm font-medium text-gray-500">{stat.label}</p>
                                <p className={`${stat.isText ? 'text-lg' : 'text-2xl'} font-bold text-gray-900 mt-1`}>
                                    {stat.value}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Lab Reports Section */}
                <div className="bg-white shadow rounded-lg">
                    <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                            <FileText className="h-5 w-5 mr-2 text-blue-600" /> Lab Reports
                            {reportsPagination && <span className="ml-2 text-sm text-gray-500">({reportsPagination.total} total)</span>}
                        </h3>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="block px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="verified">Verified</option>
                            <option value="processed">Processed</option>
                            <option value="processing">Processing</option>
                        </select>
                    </div>

                    <div className="p-6">
                        {reportsLoading ? (
                            <div className="flex items-center justify-center py-8">
                                <RefreshCw className="h-6 w-6 animate-spin text-blue-600 mr-2" />
                                <span className="text-gray-600">Loading reports...</span>
                            </div>
                        ) : reports.length === 0 ? (
                            <div className="text-center py-8">
                                <FileText className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 mb-1">No Lab Reports</h3>
                                <p className="text-sm text-gray-500">
                                    {statusFilter ? 'No reports match the selected filter.' : 'This patient doesn\'t have any lab reports yet.'}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {reports.map((report) => (
                                    <div key={report.id} className="border border-gray-200 rounded-lg overflow-hidden hover:border-blue-300 transition-colors">
                                        {/* Report Header - always visible */}
                                        <div
                                            className="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50"
                                            onClick={() => setExpandedReportId(expandedReportId === report.id ? null : report.id)}
                                        >
                                            <div className="flex items-center space-x-3">
                                                <FileText className="h-5 w-5 text-gray-400" />
                                                <div>
                                                    <h5 className="text-sm font-medium text-gray-900">{report.original_filename}</h5>
                                                    <p className="text-xs text-gray-500">
                                                        Batch: {report.batch.name} • Uploaded by: {report.uploader.name}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-3">
                                                {getStatusBadge(report.status)}
                                                <ChevronRight className={`h-4 w-4 text-gray-400 transition-transform ${expandedReportId === report.id ? 'rotate-90' : ''}`} />
                                            </div>
                                        </div>

                                        {/* Expandable Content */}
                                        {expandedReportId === report.id && (
                                            <div className="border-t border-gray-200 bg-gray-50 p-4 space-y-4">
                                                {/* Lab Info */}
                                                {report.extracted_lab_info && (
                                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                                        <div><span className="font-medium text-gray-500">Lab ID:</span> <span className="text-gray-900">{report.extracted_lab_info.lab_id}</span></div>
                                                        <div><span className="font-medium text-gray-500">Requested By:</span> <span className="text-gray-900">{report.extracted_lab_info.requested_by}</span></div>
                                                        <div><span className="font-medium text-gray-500">Collected:</span> <span className="text-gray-900">{formatDate(report.extracted_lab_info.collected_date)}</span></div>
                                                        <div><span className="font-medium text-gray-500">Analyzed:</span> <span className="text-gray-900">{formatDate(report.extracted_lab_info.analysis_date)}</span></div>
                                                    </div>
                                                )}

                                                {/* Test Results Table */}
                                                {report.extracted_data.length > 0 && (
                                                    <div className="space-y-3">
                                                        {Object.entries(groupTestsByCategory(report.extracted_data)).map(([category, tests]) => (
                                                            <div key={category} className="bg-white rounded-md border border-gray-200">
                                                                <div className="bg-gray-100 px-4 py-2 border-b border-gray-200">
                                                                    <span className="text-xs font-semibold text-gray-600 uppercase">{category}</span>
                                                                </div>
                                                                <table className="min-w-full">
                                                                    <thead>
                                                                        <tr className="text-xs text-gray-500">
                                                                            <th className="px-4 py-2 text-left font-medium">Test</th>
                                                                            <th className="px-4 py-2 text-left font-medium">Result</th>
                                                                            <th className="px-4 py-2 text-left font-medium">Unit</th>
                                                                            <th className="px-4 py-2 text-left font-medium">Reference</th>
                                                                            <th className="px-4 py-2 text-left font-medium">Flag</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody className="divide-y divide-gray-100">
                                                                        {tests.map((test) => (
                                                                            <tr key={test.id} className="text-sm">
                                                                                <td className="px-4 py-2 font-medium text-gray-900">{test.test_name}</td>
                                                                                <td className={`px-4 py-2 ${getFlagColor(test.flag)}`}>{test.result}</td>
                                                                                <td className="px-4 py-2 text-gray-500">{test.unit || '—'}</td>
                                                                                <td className="px-4 py-2 text-gray-500">{test.reference || '—'}</td>
                                                                                <td className="px-4 py-2">
                                                                                    {test.flag ? (
                                                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                                                                            test.flag === 'H' ? 'bg-red-100 text-red-800' :
                                                                                            test.flag === 'L' ? 'bg-amber-100 text-amber-800' :
                                                                                            test.flag === 'C' ? 'bg-red-200 text-red-900' :
                                                                                            'bg-green-100 text-green-800'
                                                                                        }`}>
                                                                                            {test.flag}
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="text-gray-400">—</span>
                                                                                    )}
                                                                                </td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}

                                                {/* Action buttons */}
                                                <div className="flex items-center justify-between pt-2">
                                                    <div className="text-xs text-gray-500">
                                                        {report.verifier && <span>Verified by: {report.verifier.name} • {formatDate(report.verified_at)}</span>}
                                                    </div>
                                                    <button
                                                        onClick={() => router.push(`/main/reports/report-details?id=${report.id}`)}
                                                        className="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-md transition-colors"
                                                    >
                                                        <Eye className="h-4 w-4 mr-1" /> View Full Report
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}

                                {/* Pagination */}
                                {reportsPagination && reportsPagination.last_page > 1 && (
                                    <div className="flex items-center justify-between pt-4 border-t border-gray-200">
                                        <p className="text-sm text-gray-700">
                                            Showing {reportsPagination.from} to {reportsPagination.to} of {reportsPagination.total}
                                        </p>
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => fetchReports(reportsPagination.current_page - 1)}
                                                disabled={reportsPagination.current_page === 1}
                                                className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >Previous</button>
                                            <button
                                                onClick={() => fetchReports(reportsPagination.current_page + 1)}
                                                disabled={reportsPagination.current_page === reportsPagination.last_page}
                                                className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >Next</button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    )
}
