'use client'
import { useState, useEffect, useCallback } from 'react'
import { useRouter } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import {User,Phone,Calendar,FileText,Search,Filter,Eye,ChevronRight,Users,Activity,Clock,UserCheck,RefreshCw,AlertCircle} from 'lucide-react'
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

interface ApiError {
    response?: {
        data?: {
            message?: string
        }
    }
}

const isApiError = (err: unknown): err is ApiError => typeof err === 'object' && err !== null

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



    const fetchPatients = useCallback(async () => {
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
            const response = await apiClient.get(`/patients?${params.toString()}`)
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
            } else {
                throw new Error('Invalid response structure')
            }

        } catch (err: unknown) {
            console.error('Failed to fetch patients:', err)
            if (isApiError(err) && err.response?.data?.message) {
                setError(err.response.data.message)
            } else {
                setError('Failed to fetch patients')
            }
        } finally {
            setLoading(false)
        }
    }, [currentPage, searchTerm, selectedGender])

    useEffect(() => {
        fetchPatients()
    }, [fetchPatients])

    const handlePatientClick = (patient: Patient) => {
        router.push(`/main/patient/${patient.id}`)
    }

    const handlePageChange = (page: number) => {
        setCurrentPage(page)
    }

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault()
        setCurrentPage(1)
    }

    const handleRefresh = () => {
        setCurrentPage(1)
        setSearchTerm('')
        setSelectedGender('')
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
                    {loading ? (
                        Array.from({ length: 4 }).map((_, i) => (
                            <div key={i} className="bg-white rounded-lg shadow p-6 animate-pulse">
                                <div className="flex items-center">
                                    <div className="p-3 rounded-full bg-gray-200 h-12 w-12" />
                                    <div className="ml-4 flex-1">
                                        <div className="h-4 bg-gray-200 rounded w-24 mb-2" />
                                        <div className="h-7 bg-gray-200 rounded w-12" />
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                    <>
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
                    </>
                    )}
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
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient Info</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reports</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {Array.from({ length: 6 }).map((_, i) => (
                                            <tr key={i} className="animate-pulse">
                                                <td className="px-6 py-4"><div className="flex items-center"><div className="h-10 w-10 bg-gray-200 rounded-full mr-3" /><div><div className="h-4 bg-gray-200 rounded w-32 mb-1" /><div className="h-3 bg-gray-200 rounded w-20" /></div></div></td>
                                                <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-24 mb-1" /><div className="h-3 bg-gray-200 rounded w-32" /></td>
                                                <td className="px-6 py-4"><div className="h-6 bg-gray-200 rounded-full w-8" /></td>
                                                <td className="px-6 py-4"><div className="h-4 bg-gray-200 rounded w-28" /></td>
                                                <td className="px-6 py-4"><div className="h-8 bg-gray-200 rounded w-20" /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
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

            </div>
        </DashboardLayout>
    )
}
