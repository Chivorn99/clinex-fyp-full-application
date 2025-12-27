'use client'
import { useState, useEffect } from 'react'
import DashboardLayout from '@/components/layout/DashboardLayout'
import Link from 'next/link'
import { Calendar, Users, Clock, TrendingUp, Plus, X, Eye, FileText, RefreshCw } from 'lucide-react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

// Interface for dashboard statistics
interface DashboardStats {
    totalPatients: number
    todaysUploads: number
    pendingReports: number
    monthlyReports: number
    recentReports: Array<{
        id: number
        original_filename: string
        status: string
        created_at: string
        patient?: {
            name: string
        }
    }>
}

export default function HomePage() {
    const router = useRouter()
    const { user } = useAuth()
    const [showRecentReports, setShowRecentReports] = useState(false)
    const [dashboardStats, setDashboardStats] = useState<DashboardStats | null>(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')

    const mockUser = {
        name: 'Dr. Sarah Johnson',
        email: 'sarah@smithclinic.com',
        clinic: 'Smith Medical Clinic'
    }

    const currentUser = user ? {
        name: user.name,
        email: user.email,
        clinic: 'Smith Medical Clinic'
    } : mockUser

    // Fetch dashboard statistics
    useEffect(() => {
        fetchDashboardStats()
    }, [])

    const fetchDashboardStats = async () => {
        try {
            setLoading(true)
            setError('')
            
            console.log('🚀 Fetching dashboard statistics...')
            
            // Fetch all required data in parallel
            const [patientsResponse, reportsResponse, batchesResponse] = await Promise.all([
                apiClient.get('/patients?per_page=1'), 
                apiClient.get('/lab-reports?per_page=10&sort=created_at&order=desc'), 
                apiClient.get('/batches?per_page=50')
            ])

            console.log('✅ API Responses:', {
                patients: patientsResponse,
                reports: reportsResponse,
                batches: batchesResponse
            })

            // Calculate statistics
            const totalPatients = patientsResponse.data?.total || 0
            
            // Get today's uploads (reports created today)
            const today = new Date().toISOString().split('T')[0]
            const todaysUploads = reportsResponse.data?.data?.filter((report: any) => 
                report.created_at.startsWith(today)
            ).length || 0

            // Count pending reports (processed but not verified)
            const pendingReports = reportsResponse.data?.data?.filter((report: any) => 
                report.status === 'processed'
            ).length || 0

            // Calculate this month's reports
            const currentMonth = new Date().getMonth()
            const currentYear = new Date().getFullYear()
            const monthlyReports = reportsResponse.data?.data?.filter((report: any) => {
                const reportDate = new Date(report.created_at)
                return reportDate.getMonth() === currentMonth && reportDate.getFullYear() === currentYear
            }).length || 0

            // Get recent reports for the modal
            const recentReports = reportsResponse.data?.data?.slice(0, 5) || []

            setDashboardStats({
                totalPatients,
                todaysUploads,
                pendingReports,
                monthlyReports,
                recentReports
            })

            console.log('✅ Dashboard stats calculated:', {
                totalPatients,
                todaysUploads,
                pendingReports,
                monthlyReports,
                recentReportsCount: recentReports.length
            })

        } catch (err: any) {
            console.error('💥 Failed to fetch dashboard stats:', err)
            setError('Failed to load dashboard statistics')
            
            // Fallback to mock data
            setDashboardStats({
                totalPatients: 0,
                todaysUploads: 0,
                pendingReports: 0,
                monthlyReports: 0,
                recentReports: []
            })
        } finally {
            setLoading(false)
        }
    }

    // Generate stats array with real data
    const stats = dashboardStats ? [
        {
            name: 'Total Patients',
            value: dashboardStats.totalPatients.toLocaleString(),
            change: '+12%', // You can calculate this based on previous data
            changeType: 'increase' as const,
            icon: Users,
        },
        {
            name: "Today's Uploads",
            value: dashboardStats.todaysUploads.toString(),
            change: `+${dashboardStats.todaysUploads}`,
            changeType: 'increase' as const,
            icon: Calendar,
        },
        {
            name: 'Pending Reports',
            value: dashboardStats.pendingReports.toString(),
            change: dashboardStats.pendingReports > 0 ? `${dashboardStats.pendingReports} awaiting` : 'All clear',
            changeType: dashboardStats.pendingReports > 0 ? 'increase' as const : 'decrease' as const,
            icon: Clock,
        },
        {
            name: 'This Month',
            value: dashboardStats.monthlyReports.toString(),
            change: '+8.2%', // You can calculate this based on previous month
            changeType: 'increase' as const,
            icon: TrendingUp,
        },
    ] : []

    // Transform recent reports for the modal
    const recentReports = dashboardStats?.recentReports.map(report => ({
        id: report.id.toString(),
        fileName: report.original_filename,
        patientName: report.patient?.name || 'Unknown Patient',
        reportType: 'Laboratory Report',
        processedDate: report.created_at,
        status: report.status
    })) || []

    const handleRefreshStats = () => {
        fetchDashboardStats()
    }

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    return (
        <>
            <DashboardLayout>
                <div className="space-y-6">
                    {/* Welcome Header */}
                    <div className="bg-white shadow rounded-lg">
                        <div className="px-6 py-8">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h1 className="text-3xl font-bold text-gray-900">
                                        Welcome back, {currentUser.name.split(' ')[1] || currentUser.name}! 👋
                                    </h1>
                                    <p className="mt-2 text-gray-600">
                                        Here's what's happening at {currentUser.clinic} today
                                    </p>
                                    {error && (
                                        <p className="mt-2 text-sm text-red-600">
                                            {error} - showing cached data
                                        </p>
                                    )}
                                </div>
                                <div className="flex space-x-3">
                                    <button
                                        onClick={handleRefreshStats}
                                        disabled={loading}
                                        className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                                    >
                                        <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                                        Refresh
                                    </button>
                                    <button
                                        onClick={() => setShowRecentReports(true)}
                                        disabled={loading || recentReports.length === 0}
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                                    >
                                        <Calendar className="h-4 w-4 mr-2" />
                                        View Recent Reports ({recentReports.length})
                                    </button>
                                    <button
                                        className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        onClick={() => router.push('/main/upload')}
                                    >
                                        <Plus className="h-4 w-4 mr-2" />
                                        Upload Reports
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Statistics Grid */}
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {loading ? (
                            // Loading skeleton
                            Array.from({ length: 4 }).map((_, index) => (
                                <div key={index} className="bg-white shadow rounded-lg p-6 animate-pulse">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <div className="h-12 w-12 rounded-md bg-gray-200"></div>
                                        </div>
                                        <div className="ml-4 flex-1">
                                            <div className="h-4 bg-gray-200 rounded mb-2"></div>
                                            <div className="h-8 bg-gray-200 rounded"></div>
                                        </div>
                                    </div>
                                </div>
                            ))
                        ) : (
                            stats.map((stat) => {
                                const Icon = stat.icon
                                return (
                                    <div key={stat.name} className="bg-white shadow rounded-lg p-6 hover:shadow-md transition-shadow">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0">
                                                <div className="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                                    <Icon className="h-6 w-6" />
                                                </div>
                                            </div>
                                            <div className="ml-4 flex-1">
                                                <p className="text-sm font-medium text-gray-500 truncate">
                                                    {stat.name}
                                                </p>
                                                <div className="flex items-baseline">
                                                    <p className="text-2xl font-semibold text-gray-900">
                                                        {stat.value}
                                                    </p>
                                                    <p className={`ml-2 flex items-baseline text-sm font-semibold ${
                                                        stat.changeType === 'increase' ? 'text-green-600' : 'text-red-600'
                                                    }`}>
                                                        {stat.change}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )
                            })
                        )}
                    </div>

                    {/* Quick Actions & Recent Activity */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Quick Actions */}
                        <div className="bg-white shadow rounded-lg">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h2 className="text-lg font-medium text-gray-900">Quick Actions</h2>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-2 gap-4">
                                    <button 
                                        onClick={() => router.push('/main/patient')}
                                        className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        <Users className="h-8 w-8 text-gray-400 mb-2" />
                                        <span className="text-sm font-medium text-gray-900">View Patients</span>
                                        {dashboardStats && (
                                            <span className="text-xs text-gray-500 mt-1">{dashboardStats.totalPatients} total</span>
                                        )}
                                    </button>
                                    <button 
                                        onClick={() => router.push('/main/verification/monitoring')} 
                                        className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        <Clock className="h-8 w-8 text-gray-400 mb-2" />
                                        <span className="text-sm font-medium text-gray-900">Ready To Verify</span>
                                        {dashboardStats && (
                                            <span className="text-xs text-gray-500 mt-1">{dashboardStats.pendingReports} pending</span>
                                        )}
                                    </button>
                                    <button 
                                        onClick={() => router.push('/main/reports')}
                                        className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        <FileText className="h-8 w-8 text-gray-400 mb-2" />
                                        <span className="text-sm font-medium text-gray-900">View Reports</span>
                                        {dashboardStats && (
                                            <span className="text-xs text-gray-500 mt-1">{dashboardStats.monthlyReports} this month</span>
                                        )}
                                    </button>
                                    <button 
                                        onClick={() => router.push('/main/upload')}
                                        className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                    >
                                        <Plus className="h-8 w-8 text-gray-400 mb-2" />
                                        <span className="text-sm font-medium text-gray-900">Upload New</span>
                                        {dashboardStats && (
                                            <span className="text-xs text-gray-500 mt-1">{dashboardStats.todaysUploads} today</span>
                                        )}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Recent Activity */}
                        <div className="bg-white shadow rounded-lg">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h2 className="text-lg font-medium text-gray-900">Recent Activity</h2>
                            </div>
                            <div className="p-6">
                                {loading ? (
                                    <div className="space-y-4">
                                        {Array.from({ length: 3 }).map((_, index) => (
                                            <div key={index} className="animate-pulse flex space-x-3">
                                                <div className="h-8 w-8 bg-gray-200 rounded-full"></div>
                                                <div className="flex-1 space-y-2">
                                                    <div className="h-4 bg-gray-200 rounded w-3/4"></div>
                                                    <div className="h-3 bg-gray-200 rounded w-1/2"></div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : recentReports.length > 0 ? (
                                    <div className="flow-root">
                                        <ul className="-mb-8">
                                            {recentReports.slice(0, 3).map((report, index) => (
                                                <li key={report.id}>
                                                    <div className={`relative ${index < 2 ? 'pb-8' : ''}`}>
                                                        <div className="relative flex space-x-3">
                                                            <div>
                                                                <span className={`h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white ${
                                                                    report.status === 'verified' ? 'bg-green-500' : 
                                                                    report.status === 'processed' ? 'bg-blue-500' : 'bg-yellow-500'
                                                                }`}>
                                                                    <FileText className="h-4 w-4 text-white" />
                                                                </span>
                                                            </div>
                                                            <div className="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                                <div>
                                                                    <p className="text-sm text-gray-500">
                                                                        Report <span className="font-medium text-gray-900">{report.fileName}</span> 
                                                                        <span className={`ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                                            report.status === 'verified' ? 'bg-green-100 text-green-800' :
                                                                            report.status === 'processed' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'
                                                                        }`}>
                                                                            {report.status}
                                                                        </span>
                                                                    </p>
                                                                </div>
                                                                <div className="text-right text-sm whitespace-nowrap text-gray-500">
                                                                    <time>{formatDate(report.processedDate)}</time>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : (
                                    <div className="text-center text-gray-500">
                                        <FileText className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                        <p>No recent activity</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </DashboardLayout>

            {/* Recent Reports Modal */}
            {showRecentReports && (
                <div className="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div
                            className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                            aria-hidden="true"
                            onClick={() => setShowRecentReports(false)}
                        ></div>

                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div className="bg-white px-6 py-4 border-b border-gray-200">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900" id="modal-title">
                                        Recent Reports ({recentReports.length})
                                    </h3>
                                    <button
                                        type="button"
                                        onClick={() => setShowRecentReports(false)}
                                        className="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md p-1"
                                    >
                                        <span className="sr-only">Close</span>
                                        <X className="h-6 w-6" />
                                    </button>
                                </div>
                            </div>

                            <div className="bg-white px-6 py-4 max-h-96 overflow-y-auto">
                                {recentReports.length > 0 ? (
                                    <div className="space-y-3">
                                        {recentReports.map((report) => (
                                            <div
                                                key={report.id}
                                                className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                            >
                                                <div className="flex items-center space-x-3">
                                                    <div className="flex-shrink-0">
                                                        <FileText className="h-5 w-5 text-gray-400" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium text-gray-900 truncate">
                                                            {report.fileName}
                                                        </p>
                                                        <p className="text-sm text-gray-500 truncate">
                                                            {report.patientName} • {report.reportType}
                                                        </p>
                                                        <p className="text-xs text-gray-400">
                                                            {formatDate(report.processedDate)}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-2 flex-shrink-0">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                        report.status === 'verified' ? 'bg-green-100 text-green-800' :
                                                        report.status === 'processed' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'
                                                    }`}>
                                                        {report.status}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => router.push(`/main/reports/report-details?id=${report.id}`)}
                                                        className="text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md p-1"
                                                        title="View report"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <FileText className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                                        <p className="text-gray-500">No recent reports found</p>
                                    </div>
                                )}
                            </div>

                            <div className="bg-gray-50 px-6 py-4">
                                <div className="flex justify-between space-x-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowRecentReports(false)}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        Close
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowRecentReports(false)
                                            router.push('/main/reports')
                                        }}
                                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        View All Reports
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    )
}