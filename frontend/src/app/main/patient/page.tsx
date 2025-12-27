'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import {User,Phone,Calendar,FileText,Search,Filter,Eye,ChevronRight,Users,Activity,Clock,UserCheck,Download,RefreshCw,AlertCircle,CheckCircle,XCircle} from 'lucide-react'
import { apiClient } from '@/lib/api'

// Interfaces
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
    lab_reports_count: number
    latest_report_date: string | null
}

interface PaginationData {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    next_page_url: string | null
    prev_page_url: string | null
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
    batch: {
        id: number
        name: string
        status: string
    }
    uploader: {
        id: number
        name: string
        email: string
    }
    verifier: {
        id: number
        name: string
    } | null
    extracted_lab_info: {
        lab_id: string
        requested_by: string
        collected_date: string
        analysis_date: string
    } | null
}

export default function PatientPage() {
    const router = useRouter()

    // State management
    const [patients, setPatients] = useState<Patient[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [searchTerm, setSearchTerm] = useState('')
    const [selectedGender, setSelectedGender] = useState<string>('')
    const [currentPage, setCurrentPage] = useState(1)
    const [pagination, setPagination] = useState<PaginationData | null>(null)

    // Patient details modal state
    const [selectedPatient, setSelectedPatient] = useState<Patient | null>(null)
    const [patientReports, setPatientReports] = useState<PatientLabReport[]>([])
    const [loadingReports, setLoadingReports] = useState(false)
    const [showPatientModal, setShowPatientModal] = useState(false)
    const [reportsPagination, setReportsPagination] = useState<PaginationData | null>(null)
    const [currentReportsPage, setCurrentReportsPage] = useState(1)

    useEffect(() => {
        fetchPatients()
    }, [currentPage, searchTerm, selectedGender])

    const fetchPatients = async () => {
        try {
            setLoading(true)
            setError('')

            // Build query parameters
            const params = new URLSearchParams({
                page: currentPage.toString(),
                per_page: '15'
            })

            if (searchTerm.trim()) {
                params.append('search', searchTerm.trim())
            }

            if (selectedGender) {
                params.append('gender', selectedGender)
            }

            console.log('🚀 Fetching patients with params:', params.toString())

            const response = await apiClient.get(`/patients?${params.toString()}`)
            console.log('✅ Patients API Response:', response)

            if (response.success && response.data) {
                setPatients(response.data.data || [])
                setPagination({
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    per_page: response.data.per_page,
                    total: response.data.total,
                    from: response.data.from,
                    to: response.data.to,
                    next_page_url: response.data.next_page_url,
                    prev_page_url: response.data.prev_page_url
                })

                console.log(`✅ Loaded ${response.data.data?.length || 0} patients`)
            } else {
                throw new Error('Invalid response structure')
            }

        } catch (err: any) {
            console.error('💥 Failed to fetch patients:', err)
            setError(err.response?.data?.message || 'Failed to fetch patients')
        } finally {
            setLoading(false)
        }
    }

    const fetchPatientReports = async (patientId: number, page: number = 1) => {
        try {
            setLoadingReports(true)

            console.log('🚀 Fetching reports for patient:', patientId)

            const response = await apiClient.get(`/patients/${patientId}/lab-reports?page=${page}&per_page=10`)
            console.log('✅ Patient reports API Response:', response)

            if (response.success && response.data) {
                setPatientReports(response.data.data || [])
                setReportsPagination({
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    per_page: response.data.per_page,
                    total: response.data.total,
                    from: response.data.from,
                    to: response.data.to,
                    next_page_url: response.data.next_page_url,
                    prev_page_url: response.data.prev_page_url
                })

                console.log(`✅ Loaded ${response.data.data?.length || 0} reports for patient ${patientId}`)
            } else {
                throw new Error('Invalid response structure')
            }

        } catch (err: any) {
            console.error('💥 Failed to fetch patient reports:', err)
            // Don't show error for no reports, just show empty state
            setPatientReports([])
            setReportsPagination(null)
        } finally {
            setLoadingReports(false)
        }
    }

    const handlePatientClick = async (patient: Patient) => {
        setSelectedPatient(patient)
        setCurrentReportsPage(1)
        setShowPatientModal(true)
        await fetchPatientReports(patient.id, 1)
    }

    const handlePageChange = (page: number) => {
        setCurrentPage(page)
    }

    const handleReportsPageChange = async (page: number) => {
        if (selectedPatient) {
            setCurrentReportsPage(page)
            await fetchPatientReports(selectedPatient.id, page)
        }
    }

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault()
        setCurrentPage(1)
        fetchPatients()
    }

    const handleRefresh = () => {
        setCurrentPage(1)
        setSearchTerm('')
        setSelectedGender('')
        fetchPatients()
    }

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A'
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'verified':
                return <CheckCircle className="h-4 w-4 text-green-600" />
            case 'processed':
                return <Clock className="h-4 w-4 text-blue-600" />
            case 'processing':
                return <RefreshCw className="h-4 w-4 text-yellow-600 animate-spin" />
            case 'failed':
                return <XCircle className="h-4 w-4 text-red-600" />
            default:
                return <AlertCircle className="h-4 w-4 text-gray-600" />
        }
    }

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'verified':
                return 'bg-green-100 text-green-800'
            case 'processed':
                return 'bg-blue-100 text-blue-800'
            case 'processing':
                return 'bg-yellow-100 text-yellow-800'
            case 'failed':
                return 'bg-red-100 text-red-800'
            default:
                return 'bg-gray-100 text-gray-800'
        }
    }

    // Group test results by category
    const groupTestsByCategory = (tests: any[]) => {
        return tests.reduce((acc, test) => {
            const category = test.category || 'UNCATEGORIZED'
            if (!acc[category]) {
                acc[category] = []
            }
            acc[category].push(test)
            return acc
        }, {} as Record<string, any[]>)
    }

    const renderPagination = (paginationData: PaginationData, onPageChange: (page: number) => void) => {
        if (!paginationData || paginationData.last_page <= 1) return null

        const { current_page, last_page } = paginationData
        const pages = []

        // Add page numbers
        for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
            pages.push(i)
        }

        return (
            <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                <div className="flex flex-1 justify-between sm:hidden">
                    <button
                        onClick={() => onPageChange(current_page - 1)}
                        disabled={current_page === 1}
                        className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <button
                        onClick={() => onPageChange(current_page + 1)}
                        disabled={current_page === last_page}
                        className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
                <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm text-gray-700">
                            Showing <span className="font-medium">{paginationData.from}</span> to{' '}
                            <span className="font-medium">{paginationData.to}</span> of{' '}
                            <span className="font-medium">{paginationData.total}</span> results
                        </p>
                    </div>
                    <div>
                        <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <button
                                onClick={() => onPageChange(current_page - 1)}
                                disabled={current_page === 1}
                                className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Previous
                            </button>
                            {pages.map(page => (
                                <button
                                    key={page}
                                    onClick={() => onPageChange(page)}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${page === current_page
                                        ? 'z-10 bg-blue-600 text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                        : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'
                                        }`}
                                >
                                    {page}
                                </button>
                            ))}
                            <button
                                onClick={() => onPageChange(current_page + 1)}
                                disabled={current_page === last_page}
                                className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        )
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Patient Management</h1>
                        <p className="mt-1 text-gray-600">
                            Manage patient records and view their lab reports
                        </p>
                    </div>
                    <div className="flex space-x-3">
                        <button
                            onClick={handleRefresh}
                            disabled={loading}
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div className="bg-white rounded-lg shadow p-6">
                        <div className="flex items-center">
                            <div className="p-3 rounded-full bg-blue-100 text-blue-600">
                                <Users className="h-6 w-6" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Total Patients</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {pagination?.total || 0}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <div className="flex items-center">
                            <div className="p-3 rounded-full bg-green-100 text-green-600">
                                <UserCheck className="h-6 w-6" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">With Reports</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {patients.filter(p => p.lab_reports_count > 0).length}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <div className="flex items-center">
                            <div className="p-3 rounded-full bg-purple-100 text-purple-600">
                                <Activity className="h-6 w-6" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Total Reports</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {patients.reduce((sum, p) => sum + p.lab_reports_count, 0)}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <div className="flex items-center">
                            <div className="p-3 rounded-full bg-orange-100 text-orange-600">
                                <Clock className="h-6 w-6" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-500">Recent Activity</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {patients.filter(p => p.latest_report_date &&
                                        new Date(p.latest_report_date) > new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
                                    ).length}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Search and Filters */}
                <div className="bg-white shadow rounded-lg">
                    <div className="p-6 border-b border-gray-200">
                        <div className="flex flex-col sm:flex-row gap-4">
                            <form onSubmit={handleSearch} className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                    <input
                                        type="text"
                                        placeholder="Search patients by name, ID, or phone..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </form>

                            <div className="flex gap-2">
                                <div className="relative">
                                    <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                    <select
                                        value={selectedGender}
                                        onChange={(e) => setSelectedGender(e.target.value)}
                                        className="block w-full pl-10 pr-8 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="">All Genders</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>

                                <button
                                    type="submit"
                                    onClick={handleSearch}
                                    disabled={loading}
                                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                                >
                                    Search
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Patients Table */}
                    <div className="overflow-hidden">
                        {loading ? (
                            <div className="flex items-center justify-center py-12">
                                <div className="flex items-center space-x-2">
                                    <RefreshCw className="h-6 w-6 animate-spin text-blue-600" />
                                    <span className="text-gray-600">Loading patients...</span>
                                </div>
                            </div>
                        ) : error ? (
                            <div className="flex items-center justify-center py-12">
                                <div className="text-center">
                                    <AlertCircle className="h-12 w-12 text-red-400 mx-auto mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">Error Loading Patients</h3>
                                    <p className="text-gray-500 mb-4">{error}</p>
                                    <button
                                        onClick={handleRefresh}
                                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                                    >
                                        Try Again
                                    </button>
                                </div>
                            </div>
                        ) : patients.length === 0 ? (
                            <div className="flex items-center justify-center py-12">
                                <div className="text-center">
                                    <Users className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">No Patients Found</h3>
                                    <p className="text-gray-500">
                                        {searchTerm || selectedGender
                                            ? 'Try adjusting your search filters'
                                            : 'No patients have been added to the system yet'
                                        }
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Patient Info
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Contact
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Lab Reports
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Last Report
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {patients.map((patient) => (
                                                <tr
                                                    key={patient.id}
                                                    className="hover:bg-gray-50 cursor-pointer"
                                                    onClick={() => handlePatientClick(patient)}
                                                >
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="flex items-center">
                                                            <div className="flex-shrink-0 h-10 w-10">
                                                                <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                                    <User className="h-5 w-5 text-blue-600" />
                                                                </div>
                                                            </div>
                                                            <div className="ml-4">
                                                                <div className="text-sm font-medium text-gray-900">
                                                                    {patient.name}
                                                                </div>
                                                                <div className="text-sm text-gray-500">
                                                                    ID: {patient.patient_id}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm text-gray-900">
                                                            {patient.gender} • {patient.age}
                                                        </div>
                                                        <div className="text-sm text-gray-500 flex items-center">
                                                            <Phone className="h-3 w-3 mr-1" />
                                                            {patient.phone || 'No phone'}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="flex items-center">
                                                            <FileText className="h-4 w-4 text-gray-400 mr-2" />
                                                            <span className="text-sm font-medium text-gray-900">
                                                                {patient.lab_reports_count}
                                                            </span>
                                                            <span className="text-sm text-gray-500 ml-1">
                                                                report{patient.lab_reports_count !== 1 ? 's' : ''}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div className="flex items-center">
                                                            <Calendar className="h-3 w-3 mr-1" />
                                                            {formatDate(patient.latest_report_date)}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation()
                                                                handlePatientClick(patient)
                                                            }}
                                                            className="text-blue-600 hover:text-blue-900 inline-flex items-center"
                                                        >
                                                            <Eye className="h-4 w-4 mr-1" />
                                                            View Details
                                                            <ChevronRight className="h-4 w-4 ml-1" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination */}
                                {pagination && renderPagination(pagination, handlePageChange)}
                            </>
                        )}
                    </div>
                </div>

                {/* Patient Details Modal */}
                {showPatientModal && selectedPatient && (
                    <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                        <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            {/* Background overlay */}
                            <div
                                className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                aria-hidden="true"
                                onClick={() => setShowPatientModal(false)}
                            ></div>

                            {/* This element is to trick the browser into centering the modal contents. */}
                            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                            {/* Modal panel */}
                            <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                                <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div className="sm:flex sm:items-start">
                                        <div className="w-full">
                                            {/* Modal Header */}
                                            <div className="flex items-center justify-between border-b border-gray-200 pb-4 mb-6">
                                                <div className="flex items-center">
                                                    <div className="flex-shrink-0 h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <User className="h-6 w-6 text-blue-600" />
                                                    </div>
                                                    <div className="ml-4">
                                                        <h3 className="text-lg leading-6 font-medium text-gray-900">
                                                            {selectedPatient.name}
                                                        </h3>
                                                        <p className="text-sm text-gray-500">
                                                            Patient ID: {selectedPatient.patient_id}
                                                        </p>
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => setShowPatientModal(false)}
                                                    className="rounded-md bg-white text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <span className="sr-only">Close</span>
                                                    <XCircle className="h-6 w-6" />
                                                </button>
                                            </div>

                                            {/* Patient Info */}
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                                <div className="bg-gray-50 rounded-lg p-4">
                                                    <h4 className="text-sm font-medium text-gray-700 mb-2">Personal Information</h4>
                                                    <div className="space-y-2 text-sm">
                                                        <div><span className="font-medium text-gray-700">Age:</span> <span className="text-gray-900">{selectedPatient.age}</span></div>
                                                        <div><span className="font-medium text-gray-700">Gender:</span> <span className="text-gray-900">{selectedPatient.gender}</span></div>
                                                        <div><span className="font-medium text-gray-700">Phone:</span> <span className="text-gray-900">{selectedPatient.phone || 'Not provided'}</span></div>
                                                        <div><span className="font-medium text-gray-700">Email:</span> <span className="text-gray-900">{selectedPatient.email || 'Not provided'}</span></div>
                                                    </div>
                                                </div>

                                                <div className="bg-gray-50 rounded-lg p-4">
                                                    <h4 className="text-sm font-medium text-gray-700 mb-2">Account Information</h4>
                                                    <div className="space-y-2 text-sm">
                                                        <div><span className="font-medium text-gray-700">Created:</span> <span className="text-gray-900">{formatDate(selectedPatient.created_at)}</span></div>
                                                        <div><span className="font-medium text-gray-700">Updated:</span> <span className="text-gray-900">{formatDate(selectedPatient.updated_at)}</span></div>
                                                    </div>
                                                </div>

                                                <div className="bg-gray-50 rounded-lg p-4">
                                                    <h4 className="text-sm font-medium text-gray-700 mb-2">Lab Reports Summary</h4>
                                                    <div className="space-y-2 text-sm">
                                                        <div><span className="font-medium text-gray-700">Total Reports:</span> <span className="text-gray-900">{selectedPatient.lab_reports_count}</span></div>
                                                        <div><span className="font-medium text-gray-700">Latest Report:</span> <span className="text-gray-900">{formatDate(selectedPatient.latest_report_date)}</span></div>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Lab Reports Section */}
                                            <div>
                                                <h4 className="text-lg font-medium text-gray-900 mb-4">Lab Reports</h4>

                                                {loadingReports ? (
                                                    <div className="flex items-center justify-center py-8">
                                                        <RefreshCw className="h-6 w-6 animate-spin text-blue-600 mr-2" />
                                                        <span className="text-gray-600">Loading reports...</span>
                                                    </div>
                                                ) : patientReports.length === 0 ? (
                                                    <div className="text-center py-8">
                                                        <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                                        <h3 className="text-lg font-medium text-gray-900 mb-2">No Lab Reports</h3>
                                                        <p className="text-gray-500">This patient doesn't have any lab reports yet.</p>
                                                    </div>
                                                ) : (
                                                    <div className="space-y-4">
                                                        {patientReports.map((report) => (
                                                            <div key={report.id} className="border border-gray-200 rounded-lg p-4">
                                                                <div className="flex items-center justify-between mb-4">
                                                                    <div className="flex items-center">
                                                                        <FileText className="h-5 w-5 text-gray-400 mr-2" />
                                                                        <div>
                                                                            <h5 className="text-sm font-medium text-gray-900">
                                                                                {report.original_filename}
                                                                            </h5>
                                                                            <p className="text-xs text-gray-500">
                                                                                Batch: {report.batch.name}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <div className="flex items-center space-x-2">
                                                                        {getStatusIcon(report.status)}
                                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(report.status)}`}>
                                                                            {report.status}
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                {/* Lab Info */}
                                                                {report.extracted_lab_info && (
                                                                    <div className="mb-4 p-3 bg-gray-50 rounded-md">
                                                                        <h6 className="text-xs font-medium text-gray-700 mb-2">LAB INFORMATION</h6>
                                                                        <div className="grid grid-cols-2 gap-2 text-xs">
                                                                            <div><span className="font-medium text-gray-700">Lab ID:</span> <span className="text-gray-900">{report.extracted_lab_info.lab_id}</span></div>
                                                                            <div><span className="font-medium text-gray-700">Requested By:</span> <span className="text-gray-900">{report.extracted_lab_info.requested_by}</span></div>
                                                                            <div><span className="font-medium text-gray-700">Collected:</span> <span className="text-gray-900">{formatDate(report.extracted_lab_info.collected_date)}</span></div>
                                                                            <div><span className="font-medium text-gray-700">Analyzed:</span> <span className="text-gray-900">{formatDate(report.extracted_lab_info.analysis_date)}</span></div>
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                {/* Test Results */}
                                                                {report.extracted_data.length > 0 && (
                                                                    <div>
                                                                        <h6 className="text-xs font-medium text-gray-500 mb-2">TEST RESULTS ({report.extracted_data.length})</h6>
                                                                        <div className="space-y-3">
                                                                            {Object.entries(groupTestsByCategory(report.extracted_data)).map(([category, tests]) => (
                                                                                <div key={category} className="border border-gray-100 rounded-md">
                                                                                    <div className="bg-gray-50 px-3 py-2 border-b border-gray-100">
                                                                                        <span className="text-xs font-medium text-gray-700 uppercase">{category}</span>
                                                                                    </div>
                                                                                    <div className="p-3">
                                                                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                                                                            {(tests as any[]).map((test: any) => (
                                                                                                <div key={test.id} className="text-xs">
                                                                                                    <div className="font-medium text-gray-900">{test.test_name}</div>
                                                                                                    <div className="text-gray-600">
                                                                                                        {test.result} {test.unit}
                                                                                                        {test.flag && (
                                                                                                            <span className="ml-1 text-red-600 font-medium">({test.flag})</span>
                                                                                                        )}
                                                                                                    </div>
                                                                                                    {test.reference && (
                                                                                                        <div className="text-gray-400">Ref: {test.reference}</div>
                                                                                                    )}
                                                                                                </div>
                                                                                            ))}
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                )}

                                                                {/* Report metadata */}
                                                                <div className="mt-4 pt-3 border-t border-gray-100">
                                                                    <div className="flex items-center justify-between text-xs text-gray-500">
                                                                        <div>
                                                                            <span>Uploaded by: {report.uploader.name}</span>
                                                                            {report.verifier && (
                                                                                <span className="ml-4">Verified by: {report.verifier.name}</span>
                                                                            )}
                                                                        </div>
                                                                        <div className="flex items-center space-x-2">
                                                                            <button
                                                                                onClick={() => router.push(`/main/reports/report-details?id=${report.id}`)}
                                                                                className="text-blue-600 hover:text-blue-800 font-medium"
                                                                            >
                                                                                View Details
                                                                            </button>
                                                                            <button className="text-gray-600 hover:text-gray-800">
                                                                                <Download className="h-3 w-3" />
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))}

                                                        {/* Reports Pagination */}
                                                        {reportsPagination && renderPagination(reportsPagination, handleReportsPageChange)}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    )
}