'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { Search, Filter, Download, Eye, CheckCircle, XCircle, Clock, Calendar, FileText, Users, AlertTriangle } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

interface Report {
    id: string
    fileName: string
    patientName: string
    reportType: string
    uploadDate: string
    processedDate: string
    status: 'verified' | 'unverified' | 'processing'
    batchId: string
    extractedData?: any
    verifiedBy?: string
    priority: 'low' | 'medium' | 'high'
    original_filename?: string
    uploader?: string
    created_at?: string
    updated_at?: string
    verification_status?: string
    batch?: {
        id: number
        name: string
    }
}

interface Batch {
    id: string
    name: string
    createdAt: string
    reportCount: number
    verifiedCount: number
    status: 'completed' | 'processing' | 'pending'
    templateType: string
}

export default function ReportsPage() {
    const { user } = useAuth()
    const [activeTab, setActiveTab] = useState<'all' | 'verified' | 'unverified' | 'batches'>('all')
    const [searchQuery, setSearchQuery] = useState('')
    const [filterBatch, setFilterBatch] = useState('all') // Changed from filterType to filterBatch
    const [reports, setReports] = useState<Report[]>([])
    const [availableBatches, setAvailableBatches] = useState<{ id: string, name: string }[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const router = useRouter()

    // Fallback mock user data
    const mockUser = {
        name: 'Dr. Sarah Johnson',
        email: 'sarah@smithclinic.com',
        clinic: 'Smith Medical Clinic'
    }

    // Use auth user if available, otherwise fallback to mock
    const currentUser = user ? {
        name: user.name,
        email: user.email,
        clinic: 'Smith Medical Clinic'
    } : mockUser

    // Fetch reports from API
    const fetchReports = async () => {
        try {
            setLoading(true)
            setError('')

            console.log('🚀 Fetching lab reports...')
            const response = await apiClient.get('/lab-reports')
            console.log('✅ API Response:', response)
            console.log('📊 Response data:', response.data)

            // Handle response structure
            let reportsData = []
            if (Array.isArray(response.data)) {
                reportsData = response.data
            } else if (response.data?.data && Array.isArray(response.data.data)) {
                reportsData = response.data.data
            } else if (response.data?.reports && Array.isArray(response.data.reports)) {
                reportsData = response.data.reports
            } else {
                console.warn('⚠️ Unexpected response structure:', response.data)
                reportsData = []
            }

            console.log('📋 Reports data:', reportsData)

            // Transform API data to match our interface
            const transformedReports: Report[] = reportsData.map((report: any) => {
                console.log('🔄 Transforming report:', report)

                // Extract patient name from extracted data or use fallback
                const patientName = report.extracted_data?.patientInfo?.name ||
                    report.patient_name ||
                    `Patient ${report.id}`

                // Determine verification status
                let status: 'verified' | 'unverified' | 'processing' = 'unverified'
                if (report.verification_status === 'verified' || report.verified_by) {
                    status = 'verified'
                } else if (report.verification_status === 'processing') {
                    status = 'processing'
                }

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
                    priority: 'medium',
                    original_filename: report.original_filename,
                    uploader: report.uploader,
                    created_at: report.created_at,
                    updated_at: report.updated_at,
                    verification_status: report.verification_status,
                    batch: {
                        id: parseInt(batchId),
                        name: batchName
                    }
                }
            })

            console.log('✅ Transformed reports:', transformedReports)
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

            console.log('📦 Available batches:', uniqueBatches)
            setAvailableBatches(uniqueBatches)

        } catch (err: any) {
            console.error('💥 Failed to fetch reports:', err)
            console.error('📝 Error details:', err.response?.data)

            let errorMessage = 'Failed to load reports'
            if (err.response?.status === 401) {
                errorMessage = 'Authentication failed. Please log in again.'
            } else if (err.response?.status === 403) {
                errorMessage = 'You do not have permission to view reports'
            } else if (err.response?.data?.message) {
                errorMessage = err.response.data.message
            } else if (err.message) {
                errorMessage = err.message
            }

            setError(errorMessage)
        } finally {
            setLoading(false)
        }
    }

    // Fetch reports on component mount
    useEffect(() => {
        fetchReports()
    }, [])

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

    const getPriorityBadge = (priority: string) => {
        switch (priority) {
            case 'high':
                return (
                    <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                        <AlertTriangle className="h-3 w-3 mr-1" />
                        High
                    </span>
                )
            case 'medium':
                return (
                    <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                        Medium
                    </span>
                )
            case 'low':
                return (
                    <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                        Low
                    </span>
                )
            default:
                return null
        }
    }

    // Mock batches data for batch history tab
    const batches: Batch[] = [
        {
            id: 'batch_001',
            name: 'Morning Reports - Jan 15',
            createdAt: '2024-01-15T10:30:00Z',
            reportCount: 5,
            verifiedCount: 3,
            status: 'completed',
            templateType: 'Laboratory Report'
        },
        {
            id: 'batch_002',
            name: 'Afternoon Reports - Jan 15',
            createdAt: '2024-01-15T14:00:00Z',
            reportCount: 3,
            verifiedCount: 2,
            status: 'completed',
            templateType: 'Mixed Templates'
        },
        {
            id: 'batch_003',
            name: 'Evening Reports - Jan 16',
            createdAt: '2024-01-16T18:00:00Z',
            reportCount: 7,
            verifiedCount: 0,
            status: 'processing',
            templateType: 'X-Ray Report'
        }
    ]

    const getBatchStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Completed
                    </span>
                )
            case 'processing':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Processing
                    </span>
                )
            case 'pending':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Pending
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
        console.log('🔍 Viewing report details for ID:', reportId)
        router.push(`/main/reports/report-details?id=${reportId}`)
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
                </div>

                {/* Tabs and Filters */}
                <div className="bg-white shadow rounded-lg">
                    <div className="border-b border-gray-200">
                        <nav className="-mb-px flex space-x-8 px-6">
                            {[
                                { key: 'all', label: 'All Reports', count: stats.total },
                                { key: 'verified', label: 'Verified', count: stats.verified },
                                { key: 'unverified', label: 'Unverified', count: stats.unverified },
                                { key: 'batches', label: 'Batch History', count: batches.length }
                            ].map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setActiveTab(tab.key as any)}
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
                    {activeTab !== 'batches' && (
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
                    )}

                    {/* Reports Table */}
                    {activeTab !== 'batches' ? (
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
                                            Priority
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Processed Date
                                        </th>
                                        {/* <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th> */}
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {filteredReports.map((report) => (
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
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {getPriorityBadge(report.priority)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDate(report.processedDate)}
                                            </td>
                                            {/* <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
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
                                                    <button
                                                        onClick={(e) => e.stopPropagation()}
                                                        className="text-green-600 hover:text-green-900"
                                                        title="Download Report"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td> */}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {filteredReports.length === 0 && !loading && (
                                <div className="text-center py-12">
                                    <FileText className="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No reports found</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        {searchQuery || filterBatch !== 'all'
                                            ? 'Try adjusting your search or filter criteria.'
                                            : 'No reports have been uploaded yet.'
                                        }
                                    </p>
                                </div>
                            )}
                        </div>
                    ) : (
                        /* Batch History Table */
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Batch Details
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Template Type
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Reports
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Created Date
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {batches.map((batch) => (
                                        <tr key={batch.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <Calendar className="h-5 w-5 text-gray-400 mr-3" />
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {batch.name}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            ID: {batch.id}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {batch.templateType}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {batch.verifiedCount}/{batch.reportCount} verified
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {getBatchStatusBadge(batch.status)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDate(batch.createdAt)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div className="flex space-x-2">
                                                    <button className="text-blue-600 hover:text-blue-900">
                                                        <Eye className="h-4 w-4" />
                                                    </button>
                                                    <button className="text-green-600 hover:text-green-900">
                                                        <Download className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    )
}