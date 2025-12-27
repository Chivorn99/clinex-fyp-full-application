'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { CheckCircle, XCircle, Clock, FileText, AlertTriangle, RefreshCw, Download, Eye, CheckSquare, User, Calendar, ChevronDown, ChevronUp } from 'lucide-react'
import { apiClient } from '@/lib/api'

interface BatchStatus {
    id: number
    name: string
    status: 'pending' | 'processing' | 'completed' | 'failed'
    total_reports: number
    processed_reports: number
    failed_reports: number
    verified_reports?: number
    created_at: string
    processing_started_at?: string
    processing_completed_at?: string
    files?: FileStatus[]
}

interface FileStatus {
    id: number
    filename: string
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'processed'
    processed_at?: string
    error_message?: string
    extracted_data?: any
}

interface ReportForVerification {
    id: number
    original_filename: string
    processed_at: string
    processing_time: number
    extracted_data_summary: {
        has_patient_info: boolean
        has_lab_info: boolean
        test_count: number
        categories: string[]
    }
    patient: {
        id: number
        name: string
        patient_id: string
    } | null
    batch: {
        id: number
        name: string
    }
    uploader: string
    verification_url: string
    verify_url: string
}

interface WrappedVerificationResponse {
    success: boolean
    data: {
        data: ReportForVerification[]
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
    summary: {
        total_pending: number
        per_page: number
        current_page: number
    }
    message: string
}

interface LaravelPaginationResponse {
    current_page: number
    data: ReportForVerification[]
    first_page_url: string
    from: number
    last_page: number
    last_page_url: string
    next_page_url: string | null
    path: string
    per_page: number
    prev_page_url: string | null
    to: number
    total: number
}

type VerificationResponse = WrappedVerificationResponse | LaravelPaginationResponse

export default function VerificationPage() {
    const router = useRouter()

    const [reportsForVerification, setReportsForVerification] = useState<ReportForVerification[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [autoRefresh, setAutoRefresh] = useState(true)
    const [currentPage, setCurrentPage] = useState(1)
    const [totalPages, setTotalPages] = useState(1)
    const [totalPending, setTotalPending] = useState(0)
    const [searchTerm, setSearchTerm] = useState('')
    const [selectedBatch, setSelectedBatch] = useState<string>('')
    const [expandedBatches, setExpandedBatches] = useState<Set<number>>(new Set())

    useEffect(() => {
        fetchReportsForVerification()

        // Set up polling for real-time updates
        let interval: NodeJS.Timeout
        if (autoRefresh) {
            interval = setInterval(() => {
                fetchReportsForVerification()
            }, 5000) // Refresh every 5 seconds
        }

        return () => {
            if (interval) clearInterval(interval)
        }
    }, [autoRefresh, currentPage, searchTerm, selectedBatch])

    // Helper type guards
    const isWrappedResponse = (data: any): data is WrappedVerificationResponse => {
        return data && typeof data === 'object' && 'success' in data && data.success === true
    }

    const isLaravelPaginationResponse = (data: any): data is LaravelPaginationResponse => {
        return data && typeof data === 'object' && 'data' in data && Array.isArray(data.data) && 'last_page' in data
    }

    const fetchReportsForVerification = async () => {
        try {
            const params = new URLSearchParams({
                page: currentPage.toString(),
                per_page: '50' // Get more results to show all batches
            })

            // Add search filter if provided
            if (searchTerm) {
                params.append('search', searchTerm)
            }

            // Add batch filter if selected
            if (selectedBatch) {
                params.append('batch_id', selectedBatch)
            }

            const response = await apiClient.get(`/reports-for-verification?${params.toString()}`)

            console.log('API Response:', response.data)

            const responseData = response.data

            if (isWrappedResponse(responseData)) {
                // WrappedVerificationResponse
                setReportsForVerification(responseData.data.data || [])
                setTotalPages(responseData.data.last_page || 1)
                setTotalPending(responseData.summary.total_pending || 0)
            } else if (isLaravelPaginationResponse(responseData)) {
                // LaravelPaginationResponse
                setReportsForVerification(responseData.data || [])
                setTotalPages(responseData.last_page || 1)
                setTotalPending(responseData.total || responseData.data.length)
            } else {
                console.warn('Unexpected response structure:', responseData)
                setReportsForVerification([])
                setTotalPages(1)
                setTotalPending(0)
            }

            setError('')
        } catch (err: any) {
            console.error('Failed to fetch reports for verification:', err)
            console.error('Error response:', err.response?.data)
            setError('Failed to fetch reports for verification')

            setReportsForVerification([])
            setTotalPages(1)
            setTotalPending(0)
        } finally {
            setLoading(false)
        }
    }

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString()
    }

    const formatProcessingTime = (seconds: number) => {
        if (seconds < 60) {
            return `${seconds}s`
        }
        const minutes = Math.floor(seconds / 60)
        const remainingSeconds = seconds % 60
        return `${minutes}m ${remainingSeconds}s`
    }

    // Group reports by batch for better organization
    const reportsByBatch = reportsForVerification.reduce((acc, report) => {
        const batchId = report.batch.id
        if (!acc[batchId]) {
            acc[batchId] = {
                batch: report.batch,
                reports: []
            }
        }
        acc[batchId].reports.push(report)
        return acc
    }, {} as Record<number, { batch: { id: number, name: string }, reports: ReportForVerification[] }>)

    // Get unique batches for filter dropdown
    const uniqueBatches = Object.values(reportsByBatch).map(item => item.batch)

    const toggleBatchExpansion = (batchId: number) => {
        const newExpanded = new Set(expandedBatches)
        if (newExpanded.has(batchId)) {
            newExpanded.delete(batchId)
        } else {
            newExpanded.add(batchId)
        }
        setExpandedBatches(newExpanded)
    }

    const expandAllBatches = () => {
        setExpandedBatches(new Set(Object.keys(reportsByBatch).map(Number)))
    }

    const collapseAllBatches = () => {
        setExpandedBatches(new Set())
    }

    if (loading && reportsForVerification.length === 0) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center min-h-96">
                    <div className="text-center">
                        <RefreshCw className="h-8 w-8 text-blue-600 animate-spin mx-auto mb-4" />
                        <p className="text-gray-600">Loading reports for verification...</p>
                    </div>
                </div>
            </DashboardLayout>
        )
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Verification Monitoring</h1>
                        <p className="mt-2 text-gray-600">
                            Monitor and manage all reports ready for verification organized by batch
                        </p>
                    </div>
                    <div className="flex space-x-3">
                        <button
                            onClick={() => setAutoRefresh(!autoRefresh)}
                            className={`px-4 py-2 border rounded-md text-sm font-medium ${autoRefresh
                                    ? 'bg-green-50 border-green-200 text-green-700'
                                    : 'bg-gray-50 border-gray-200 text-gray-700'
                                }`}
                        >
                            {autoRefresh ? 'Auto-refresh ON' : 'Auto-refresh OFF'}
                        </button>
                        <button
                            onClick={() => fetchReportsForVerification()}
                            disabled={loading}
                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </button>
                    </div>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-md p-4">
                        <p className="text-red-600">{error}</p>
                    </div>
                )}

                {/* Filters and Summary */}
                <div className="bg-white shadow rounded-lg p-6">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label htmlFor="search" className="block text-sm font-medium text-gray-700 mb-2">
                                Search Files
                            </label>
                            <input
                                type="text"
                                id="search"
                                placeholder="Search by filename..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label htmlFor="batch" className="block text-sm font-medium text-gray-700 mb-2">
                                Filter by Batch
                            </label>
                            <select
                                id="batch"
                                value={selectedBatch}
                                onChange={(e) => setSelectedBatch(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">All Batches</option>
                                {uniqueBatches.map((batch) => (
                                    <option key={batch.id} value={batch.id.toString()}>
                                        {batch.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <div className="bg-blue-50 p-4 rounded-md border border-blue-200 w-full">
                                <p className="text-sm font-medium text-blue-900">Total Batches</p>
                                <p className="text-2xl font-bold text-blue-600">{Object.keys(reportsByBatch).length}</p>
                                <p className="text-xs text-blue-700">with pending reports</p>
                            </div>
                        </div>
                        <div className="flex items-end">
                            <div className="bg-green-50 p-4 rounded-md border border-green-200 w-full">
                                <p className="text-sm font-medium text-green-900">Total Reports</p>
                                <p className="text-2xl font-bold text-green-600">{totalPending}</p>
                                <p className="text-xs text-green-700">ready for verification</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Batch Controls */}
                {Object.keys(reportsByBatch).length > 0 && (
                    <div className="flex justify-between items-center">
                        <div className="flex space-x-2">
                            <button
                                onClick={expandAllBatches}
                                className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Expand All
                            </button>
                            <button
                                onClick={collapseAllBatches}
                                className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Collapse All
                            </button>
                        </div>
                        {/* <button
                            onClick={() => router.push('/main/verification')}
                            className="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                        >
                            <CheckSquare className="h-5 w-5 mr-2" />
                            Start Global Verification
                        </button> */}
                    </div>
                )}

                {/* Batch-organized Reports */}
                {Object.keys(reportsByBatch).length > 0 ? (
                    <div className="space-y-4">
                        {Object.entries(reportsByBatch).map(([batchId, { batch, reports }]) => {
                            const isExpanded = expandedBatches.has(Number(batchId))

                            return (
                                <div key={batchId} className="bg-white shadow rounded-lg overflow-hidden">
                                    {/* Batch Header */}
                                    <div className="px-6 py-4 bg-gray-50 border-b border-gray-200">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-4">
                                                <button
                                                    onClick={() => toggleBatchExpansion(Number(batchId))}
                                                    className="flex items-center space-x-2 text-lg font-medium text-gray-900 hover:text-blue-600"
                                                >
                                                    {isExpanded ? (
                                                        <ChevronUp className="h-5 w-5" />
                                                    ) : (
                                                        <ChevronDown className="h-5 w-5" />
                                                    )}
                                                    <span>{batch.name}</span>
                                                </button>
                                                <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                    {reports.length} reports
                                                </span>
                                            </div>
                                            <button
                                                onClick={() => router.push(`/main/verification?batchId=${batch.id}`)}
                                                className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                                            >
                                                <CheckSquare className="h-4 w-4 mr-2" />
                                                Verify This Batch
                                            </button>
                                        </div>
                                    </div>

                                    {/* Batch Reports Table */}
                                    {isExpanded && (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            File
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Processed Info
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Extracted Data
                                                        </th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Data Quality
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white divide-y divide-gray-200">
                                                    {reports.map((report) => (
                                                        <tr key={report.id} className="hover:bg-gray-50">
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="flex items-center">
                                                                    <CheckCircle className="h-5 w-5 text-green-500 flex-shrink-0 mr-3" />
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="text-sm font-medium text-gray-900 truncate">
                                                                            {report.original_filename}
                                                                        </p>
                                                                        <p className="text-xs text-gray-500">
                                                                            ID: {report.id}
                                                                        </p>
                                                                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                                                            Ready for Verification
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="text-sm text-gray-900">
                                                                    <div className="flex items-center text-xs text-gray-500 mb-1">
                                                                        <Calendar className="h-3 w-3 mr-1" />
                                                                        {formatDate(report.processed_at)}
                                                                    </div>
                                                                    <div className="flex items-center text-xs text-gray-500 mb-1">
                                                                        <User className="h-3 w-3 mr-1" />
                                                                        {report.uploader}
                                                                    </div>
                                                                    <p className="text-xs text-gray-500">
                                                                        Processing: {formatProcessingTime(report.processing_time)}
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="text-sm text-gray-900">
                                                                    {report.extracted_data_summary.test_count > 0 ? (
                                                                        <>
                                                                            <p className="font-medium text-blue-600">
                                                                                {report.extracted_data_summary.test_count} tests extracted
                                                                            </p>
                                                                            {report.extracted_data_summary.categories.length > 0 && (
                                                                                <div className="mt-1">
                                                                                    {report.extracted_data_summary.categories.slice(0, 2).map((category, index) => (
                                                                                        <span key={index} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-1 mb-1">
                                                                                            {category}
                                                                                        </span>
                                                                                    ))}
                                                                                    {report.extracted_data_summary.categories.length > 2 && (
                                                                                        <span className="text-xs text-gray-500">
                                                                                            +{report.extracted_data_summary.categories.length - 2} more
                                                                                        </span>
                                                                                    )}
                                                                                </div>
                                                                            )}
                                                                        </>
                                                                    ) : (
                                                                        <p className="text-xs text-gray-500">No tests extracted</p>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <div className="flex flex-col space-y-1">
                                                                    {report.extracted_data_summary.has_patient_info && (
                                                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                            Patient Info ✓
                                                                        </span>
                                                                    )}
                                                                    {report.extracted_data_summary.has_lab_info && (
                                                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                                            Lab Info ✓
                                                                        </span>
                                                                    )}
                                                                    {!report.extracted_data_summary.has_patient_info && !report.extracted_data_summary.has_lab_info && (
                                                                        <span className="text-xs text-gray-500">No quality indicators</span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            )
                        })}
                    </div>
                ) : (
                    <div className="bg-white shadow rounded-lg p-12 text-center">
                        <CheckCircle className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No Reports Ready for Verification</h3>
                        <p className="text-gray-600 mb-6">
                            There are currently no reports ready for verification. Upload and process some files first.
                        </p>
                        <button
                            onClick={() => router.push('/main/upload')}
                            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            Upload Files
                        </button>
                    </div>
                )}
            </div>
        </DashboardLayout>
    )
}