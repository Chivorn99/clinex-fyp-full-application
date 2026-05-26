'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { Search, Eye, CheckCircle, Clock, FileText, Users } from 'lucide-react'
import { apiClient } from '@/lib/api'

interface ExtractedData {
    patientInfo?: {
        name?: string
    }
}

interface ApiBatch {
    id?: number
    name?: string
}

interface ApiReport {
    id: number | string
    status?: string
    original_filename?: string
    filename?: string
    patient_name?: string
    report_type?: string
    created_at?: string
    updated_at?: string
    extracted_data?: ExtractedData
    verification_status?: string
    verified_by?: string
    batch_id?: number | string
    batch?: ApiBatch
    uploader?: string
}

interface ApiError {
    response?: {
        status?: number
        data?: {
            message?: string
        }
    }
    message?: string
}

const isApiError = (err: unknown): err is ApiError => typeof err === 'object' && err !== null

interface Report {
    id: string
    fileName: string
    patientName: string
    reportType: string
    uploadDate: string
    processedDate: string
    status: 'verified' | 'unverified' | 'processing'
    batchId: string
    extractedData?: ExtractedData
    verifiedBy?: string
    priority: 'low' | 'medium' | 'high'
    original_filename?: string
    uploader?: string
    created_at?: string
    updated_at?: string
    verification_status?: string
    canVerify: boolean
    batch?: {
        id: number
        name: string
    }
}


export default function ReportsPage() {
    const [activeTab, setActiveTab] = useState<'all' | 'verified' | 'unverified'>('all')
    const [searchQuery, setSearchQuery] = useState('')
    const [filterBatch, setFilterBatch] = useState('all') // Changed from filterType to filterBatch
    const [reports, setReports] = useState<Report[]>([])
    const [availableBatches, setAvailableBatches] = useState<{ id: string, name: string }[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const router = useRouter()

    // Fetch reports from API
    const fetchReports = async () => {
        try {
            setLoading(true)
            setError('')

            const response = await apiClient.get('/lab-reports')

            // Handle response structure
            let reportsData: ApiReport[] = []
            if (Array.isArray(response.data)) {
                reportsData = response.data
            } else if (response.data?.data && Array.isArray(response.data.data)) {
                reportsData = response.data.data
            } else if (response.data?.reports && Array.isArray(response.data.reports)) {
                reportsData = response.data.reports
            } else {
                console.warn('Unexpected response structure:', response.data)
                reportsData = []
            }


            // Transform API data to match our interface
            const transformedReports: Report[] = reportsData.map((report: ApiReport) => {

                // Extract patient name from extracted data or use fallback
                const patientName = report.extracted_data?.patientInfo?.name ||
                    report.patient_name ||
                    `Patient ${report.id}`

                const backendStatus = (report.status || '').toLowerCase()
                const verificationStatus = (report.verification_status || '').toLowerCase()

                // Determine high-level UI status
                let status: 'verified' | 'unverified' | 'processing' = 'unverified'
                if (backendStatus === 'verified' || verificationStatus === 'verified' || report.verified_by) {
                    status = 'verified'
                } else if (
                    backendStatus === 'processing' ||
                    backendStatus === 'uploaded'
                ) {
                    status = 'processing'
                }
                // 'processed' and 'failed' remain as 'unverified' (ready for verification)

                // Only processed, not-yet-verified reports should expose Verify actions.
                const canVerify =
                    status === 'unverified' &&
                    backendStatus === 'processed'

                // Extract batch info
                const batchId = report.batch?.id?.toString() ||
                    report.batch_id?.toString() ||
                    'unknown'

                const batchName = report.batch?.name || `Batch ${batchId}`

                return {
                    id: report.id.toString(),
                    fileName: report.original_filename || report.filename || `Report ${report.id}`,
                    patientName: patientName,
                    reportType: report.report_type || 'Medical Report',
                    uploadDate: report.created_at || new Date().toISOString(),
                    processedDate: report.updated_at || report.created_at || new Date().toISOString(),
                    status: status,
                    batchId: batchId,
                    extractedData: report.extracted_data,
                    verifiedBy: report.verified_by || undefined,
                    priority: 'medium' as const,
                    original_filename: report.original_filename,
                    uploader: report.uploader,
                    created_at: report.created_at,
                    updated_at: report.updated_at,
                    verification_status: report.verification_status,
                    canVerify,
                    batch: {
                        id: parseInt(batchId),
                        name: batchName
                    }
                }
            })

            setReports(transformedReports)

            // Extract unique batches for filter dropdown
            const uniqueBatches = transformedReports.reduce((acc: { id: string, name: string }[], report) => {
                const existingBatch = acc.find(b => b.id === report.batchId)
                if (!existingBatch && report.batch) {
                    acc.push({
                        id: report.batchId,
                        name: report.batch.name
                    })
                }
                return acc
            }, [])

            setAvailableBatches(uniqueBatches)

        } catch (err: unknown) {
            console.error('Failed to fetch reports:', err)

            let errorMessage = 'Failed to load reports'
            if (isApiError(err) && err.response?.status === 401) {
                errorMessage = 'Authentication failed. Please log in again.'
            } else if (isApiError(err) && err.response?.status === 403) {
                errorMessage = 'You do not have permission to view reports'
            } else if (isApiError(err) && err.response?.data?.message) {
                errorMessage = err.response.data.message
            } else if (isApiError(err) && err.message) {
                errorMessage = err.message
            }

            setError(errorMessage)
        } finally {
            setLoading(false)
        }
    }

    // Fetch reports on component mount and set up polling
    useEffect(() => {
        fetchReports()
    }, [])

    // Poll for updates if any report is processing
    useEffect(() => {
        const hasProcessing = reports.some(r => r.status === 'processing')
        
        if (hasProcessing) {
            const interval = setInterval(() => {
                fetchReports()
            }, 5000) // Poll every 5 seconds
            
            return () => clearInterval(interval)
        }
    }, [reports])

    const filteredReports = reports.filter(report => {
        const matchesSearch = report.fileName.toLowerCase().includes(searchQuery.toLowerCase()) ||
            report.patientName.toLowerCase().includes(searchQuery.toLowerCase())
        const matchesBatch = filterBatch === 'all' || report.batchId === filterBatch
        const matchesTab = activeTab === 'all' ||
            (activeTab === 'verified' && report.status === 'verified') ||
            (activeTab === 'unverified' && report.status === 'unverified')

        return matchesSearch && matchesBatch && matchesTab
    })

    const formatDate = (dateString: string) => {
        if (!dateString) return 'Processing...'
        return new Date(dateString).toLocaleString()
    }

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'verified':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Verified
                    </span>
                )
            case 'unverified':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Unverified
                    </span>
                )
            case 'processing':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Processing
                    </span>
                )
            default:
                return null
        }
    }


    const stats = {
        total: reports.length,
        verified: reports.filter(r => r.status === 'verified').length,
        unverified: reports.filter(r => r.status === 'unverified').length,
        processing: reports.filter(r => r.status === 'processing').length
    }

    // Handle view report details
    const handleViewReport = (reportId: string) => {
        router.push(`/main/reports/report-details?id=${reportId}`)
    }

    const handleVerifyReport = (report: Report) => {
        const queryBatchId = report.batch?.id || report.batchId
        router.push(`/main/verification?batchId=${queryBatchId}&reportId=${report.id}`)
    }

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    <span className="ml-3 text-gray-600">Loading reports...</span>
                </div>
            </DashboardLayout>
        )
    }

    if (error) {
        return (
            <DashboardLayout>
                <div className="text-center py-12">
                    <div className="text-red-600 text-lg font-medium">{error}</div>
                    <button
                        onClick={fetchReports}
                        className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        Retry
                    </button>
                </div>
            </DashboardLayout>
        )
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Reports Management</h1>
                    <p className="mt-2 text-gray-600">
                        View and manage all processed medical reports and batch classifications
                    </p>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    {loading ? (
                        Array.from({ length: 4 }).map((_, i) => (
                            <div key={i} className="bg-white p-6 rounded-lg shadow animate-pulse">
                                <div className="flex items-center">
                                    <div className="h-8 w-8 bg-gray-200 rounded" />
                                    <div className="ml-4 flex-1">
                                        <div className="h-4 bg-gray-200 rounded w-24 mb-2" />
                                        <div className="h-7 bg-gray-200 rounded w-12" />
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                    <>
                    <div className="bg-white p-6 rounded-lg shadow">
                        <div className="flex items-center">
                            <FileText className="h-8 w-8 text-blue-600" />
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Total Reports</p>
                                <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-lg shadow">
                        <div className="flex items-center">
                            <CheckCircle className="h-8 w-8 text-green-600" />
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Verified</p>
                                <p className="text-2xl font-bold text-gray-900">{stats.verified}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-lg shadow">
                        <div className="flex items-center">
                            <Clock className="h-8 w-8 text-yellow-600" />
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Unverified</p>
                                <p className="text-2xl font-bold text-gray-900">{stats.unverified}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-lg shadow">
                        <div className="flex items-center">
                            <Users className="h-8 w-8 text-blue-600" />
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Processing</p>
                                <p className="text-2xl font-bold text-gray-900">{stats.processing}</p>
                            </div>
                        </div>
                    </div>
                    </>
                    )}
                </div>

                {/* Tabs and Filters */}
                <div className="bg-white shadow rounded-lg">
                    <div className="border-b border-gray-200">
                        <nav className="-mb-px flex space-x-8 px-6">
                            {([
                                { key: 'all', label: 'All Reports', count: stats.total },
                                { key: 'verified', label: 'Verified', count: stats.verified },
                                { key: 'unverified', label: 'Unverified', count: stats.unverified },
                            ] as Array<{ key: 'all' | 'verified' | 'unverified'; label: string; count: number }>).map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`${activeTab === tab.key
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
                                >
                                    {tab.label} ({tab.count})
                                </button>
                            ))}
                        </nav>
                    </div>

                    {/* Search and Filter Bar */}
                    <div className="p-6 border-b border-gray-200">
                        <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                            <div className="flex flex-1 space-x-4">
                                <div className="relative flex-1 max-w-md">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search className="h-5 w-5 text-gray-400" />
                                    </div>
                                    <input
                                        type="text"
                                        placeholder="Search reports..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-gray-900"
                                    />
                                </div>
                                <select
                                    value={filterBatch}
                                    onChange={(e) => setFilterBatch(e.target.value)}
                                    className="block px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-gray-800"
                                >
                                    <option value="all">All Batches</option>
                                    {availableBatches.map((batch) => (
                                        <option key={batch.id} value={batch.id}>
                                            {batch.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Reports Table */}
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Report Details
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Patient
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Processed Date
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {loading ? (
                                    Array.from({ length: 5 }).map((_, i) => (
                                        <tr key={i} className="animate-pulse">
                                            <td className="px-6 py-4"><div className="flex items-center"><div className="h-5 w-5 bg-gray-200 rounded mr-3" /><div><div className="h-4 bg-gray-200 rounded w-40 mb-1" /><div className="h-3 bg-gray-200 rounded w-24" /></div></div></td>
                                            <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-28" /></td>
                                            <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-20" /></td>
                                            <td className="px-6 py-4"><div className="h-5 bg-gray-200 rounded-full w-16" /></td>
                                            <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-24" /></td>
                                            <td className="px-6 py-4"><div className="h-8 bg-gray-200 rounded w-16" /></td>
                                        </tr>
                                    ))
                                ) : filteredReports.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center">
                                            <FileText className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                                            <h3 className="text-lg font-medium text-gray-900 mb-1">No Reports Found</h3>
                                            <p className="text-sm text-gray-500">Try adjusting your filters or search query.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredReports.map((report) => (
                                        <tr
                                            key={report.id}
                                            className="hover:bg-gray-50 cursor-pointer"
                                            onClick={() => handleViewReport(report.id)}
                                        >
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <FileText className="h-5 w-5 text-gray-400 mr-3" />
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {report.fileName}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            Batch: {report.batch?.name || report.batchId}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {report.patientName}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {report.reportType}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {getStatusBadge(report.status)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDate(report.processedDate)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation()
                                                            handleViewReport(report.id)
                                                        }}
                                                        className="text-blue-600 hover:text-blue-900"
                                                        title="View Details"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </button>
                                                    {report.canVerify && (
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation()
                                                                handleVerifyReport(report)
                                                            }}
                                                            className="text-green-600 hover:text-green-900"
                                                            title="Verify Report"
                                                        >
                                                            <CheckCircle className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    )
}
