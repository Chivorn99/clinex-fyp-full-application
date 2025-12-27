'use client'
import { useState, useEffect } from 'react'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { BarChart3, TrendingUp, Users, FileText, Calendar, Activity,PieChart,Download,RefreshCw,Filter,ChevronDown,ChevronUp,CheckCircle,Clock,AlertTriangle,Target,Zap,Award,Eye} from 'lucide-react'
import { apiClient } from '@/lib/api'

// Interfaces
interface AnalyticsData {
    overview: {
        totalPatients: number
        totalReports: number
        verifiedReports: number
        pendingReports: number
        failedReports: number
        averageProcessingTime: number
    }
    trends: {
        daily: Array<{
            date: string
            reports: number
            patients: number
            verified: number
        }>
        monthly: Array<{
            month: string
            reports: number
            patients: number
            verified: number
        }>
    }
    testCategories: Array<{
        category: string
        count: number
        percentage: number
    }>
    topTests: Array<{
        testName: string
        count: number
        category: string
    }>
    userActivity: Array<{
        userId: number
        userName: string
        uploadsCount: number
        verificationsCount: number
    }>
    processingStats: {
        averageTime: number
        fastestTime: number
        slowestTime: number
        successRate: number
    }
}

export default function AnalyticsPage() {
    const [analyticsData, setAnalyticsData] = useState<AnalyticsData | null>(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [timeRange, setTimeRange] = useState('30') // days
    const [selectedMetric, setSelectedMetric] = useState('reports')
    const [showDetails, setShowDetails] = useState<string | null>(null)

    useEffect(() => {
        fetchAnalyticsData()
    }, [timeRange])

    const fetchAnalyticsData = async () => {
        try {
            setLoading(true)
            setError('')
            
            console.log('🚀 Fetching analytics data...')
            
            // Fetch data from multiple endpoints
            const [patientsResponse, reportsResponse, batchesResponse] = await Promise.all([
                apiClient.get('/patients?per_page=1000'),
                apiClient.get('/lab-reports?per_page=1000'),
                apiClient.get('/batches?per_page=100')
            ])

            console.log('✅ Raw API data:', {
                patients: patientsResponse.data,
                reports: reportsResponse.data,
                batches: batchesResponse.data
            })

            // Process the data
            const patients = patientsResponse.data?.data || []
            const reports = reportsResponse.data?.data || []
            const batches = batchesResponse.data?.data || []

            // Calculate overview stats
            const totalPatients = patients.length
            const totalReports = reports.length
            const verifiedReports = reports.filter((r: any) => r.status === 'verified').length
            const pendingReports = reports.filter((r: any) => r.status === 'processed').length
            const failedReports = reports.filter((r: any) => r.status === 'failed').length

            // Calculate processing time (mock for now)
            const averageProcessingTime = 2.5 // minutes

            // Generate daily trends for the last 30 days
            const daily = generateDailyTrends(reports, patients, parseInt(timeRange))
            
            // Generate monthly trends for the last 6 months
            const monthly = generateMonthlyTrends(reports, patients)

            // Process test categories from extracted data
            const testCategories = processTestCategories(reports)
            
            // Get top tests
            const topTests = getTopTests(reports)

            // User activity stats
            const userActivity = processUserActivity(reports)

            // Processing stats
            const processingStats = {
                averageTime: 2.5,
                fastestTime: 0.8,
                slowestTime: 15.2,
                successRate: totalReports > 0 ? ((verifiedReports + pendingReports) / totalReports) * 100 : 0
            }

            setAnalyticsData({
                overview: {
                    totalPatients,
                    totalReports,
                    verifiedReports,
                    pendingReports,
                    failedReports,
                    averageProcessingTime
                },
                trends: {
                    daily,
                    monthly
                },
                testCategories,
                topTests,
                userActivity,
                processingStats
            })

            console.log('✅ Analytics data processed successfully')

        } catch (err: any) {
            console.error('💥 Failed to fetch analytics data:', err)
            setError('Failed to load analytics data')
        } finally {
            setLoading(false)
        }
    }

    // Helper functions
    const generateDailyTrends = (reports: any[], patients: any[], days: number) => {
        const trends = []
        const now = new Date()
        
        for (let i = days - 1; i >= 0; i--) {
            const date = new Date(now)
            date.setDate(date.getDate() - i)
            const dateStr = date.toISOString().split('T')[0]
            
            const dayReports = reports.filter(r => r.created_at.startsWith(dateStr))
            const dayPatients = patients.filter(p => p.created_at.startsWith(dateStr))
            const dayVerified = dayReports.filter(r => r.status === 'verified')
            
            trends.push({
                date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
                reports: dayReports.length,
                patients: dayPatients.length,
                verified: dayVerified.length
            })
        }
        
        return trends
    }

    const generateMonthlyTrends = (reports: any[], patients: any[]) => {
        const trends = []
        const now = new Date()
        
        for (let i = 5; i >= 0; i--) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1)
            const monthStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
            
            const monthReports = reports.filter(r => r.created_at.startsWith(monthStr))
            const monthPatients = patients.filter(p => p.created_at.startsWith(monthStr))
            const monthVerified = monthReports.filter(r => r.status === 'verified')
            
            trends.push({
                month: date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }),
                reports: monthReports.length,
                patients: monthPatients.length,
                verified: monthVerified.length
            })
        }
        
        return trends
    }

    const processTestCategories = (reports: any[]) => {
        const categories: Record<string, number> = {}
        let totalTests = 0

        reports.forEach(report => {
            if (report.extracted_data && Array.isArray(report.extracted_data)) {
                report.extracted_data.forEach((test: any) => {
                    const category = test.category || 'UNCATEGORIZED'
                    categories[category] = (categories[category] || 0) + 1
                    totalTests++
                })
            }
        })

        return Object.entries(categories).map(([category, count]) => ({
            category,
            count,
            percentage: totalTests > 0 ? Math.round((count / totalTests) * 100) : 0
        })).sort((a, b) => b.count - a.count)
    }

    const getTopTests = (reports: any[]) => {
        const tests: Record<string, { count: number; category: string }> = {}

        reports.forEach(report => {
            if (report.extracted_data && Array.isArray(report.extracted_data)) {
                report.extracted_data.forEach((test: any) => {
                    const testName = test.test_name || 'Unknown Test'
                    const category = test.category || 'UNCATEGORIZED'
                    
                    if (!tests[testName]) {
                        tests[testName] = { count: 0, category }
                    }
                    tests[testName].count++
                })
            }
        })

        return Object.entries(tests)
            .map(([testName, data]) => ({
                testName,
                count: data.count,
                category: data.category
            }))
            .sort((a, b) => b.count - a.count)
            .slice(0, 10)
    }

    const processUserActivity = (reports: any[]) => {
        const users: Record<number, { name: string; uploads: number; verifications: number }> = {}

        reports.forEach(report => {
            // Count uploads
            if (report.uploader) {
                const userId = report.uploader.id
                if (!users[userId]) {
                    users[userId] = { name: report.uploader.name, uploads: 0, verifications: 0 }
                }
                users[userId].uploads++
            }

            // Count verifications
            if (report.verifier) {
                const userId = report.verifier.id
                if (!users[userId]) {
                    users[userId] = { name: report.verifier.name, uploads: 0, verifications: 0 }
                }
                users[userId].verifications++
            }
        })

        return Object.entries(users).map(([userId, data]) => ({
            userId: parseInt(userId),
            userName: data.name,
            uploadsCount: data.uploads,
            verificationsCount: data.verifications
        })).sort((a, b) => (b.uploadsCount + b.verificationsCount) - (a.uploadsCount + a.verificationsCount))
    }

    const getMaxValue = (data: any[], key: string) => {
        return Math.max(...data.map(item => item[key]), 0)
    }

    const formatNumber = (num: number) => {
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K'
        }
        return num.toString()
    }

    const getCategoryColor = (index: number) => {
        const colors = [
            'bg-blue-500',
            'bg-green-500',
            'bg-purple-500',
            'bg-yellow-500',
            'bg-red-500',
            'bg-indigo-500',
            'bg-pink-500',
            'bg-teal-500'
        ]
        return colors[index % colors.length]
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Information Dashboard</h1>
                        <p className="mt-1 text-gray-600">
                            Comprehensive insights into your lab report processing system
                        </p>
                    </div>
                    <div className="flex space-x-3">
                        <select
                            value={timeRange}
                            onChange={(e) => setTimeRange(e.target.value)}
                            className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                        <button
                            onClick={fetchAnalyticsData}
                            disabled={loading}
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </button>
                    </div>
                </div>

                {loading ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {Array.from({ length: 8 }).map((_, i) => (
                            <div key={i} className="bg-white rounded-xl shadow-sm p-6 animate-pulse">
                                <div className="h-4 bg-gray-200 rounded mb-4"></div>
                                <div className="h-8 bg-gray-200 rounded mb-2"></div>
                                <div className="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        ))}
                    </div>
                ) : error ? (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <AlertTriangle className="h-12 w-12 text-red-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-red-800 mb-2">Error Loading Analytics</h3>
                        <p className="text-red-600 mb-4">{error}</p>
                        <button
                            onClick={fetchAnalyticsData}
                            className="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Try Again
                        </button>
                    </div>
                ) : analyticsData ? (
                    <>
                        {/* Overview Stats */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-blue-100 text-sm font-medium">Total Patients</p>
                                        <p className="text-3xl font-bold">{formatNumber(analyticsData.overview.totalPatients)}</p>
                                        <p className="text-blue-100 text-sm mt-1">
                                            <TrendingUp className="h-4 w-4 inline mr-1" />
                                            Active in system
                                        </p>
                                    </div>
                                    <Users className="h-12 w-12 text-blue-200" />
                                </div>
                            </div>

                            <div className="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-green-100 text-sm font-medium">Total Reports</p>
                                        <p className="text-3xl font-bold">{formatNumber(analyticsData.overview.totalReports)}</p>
                                        <p className="text-green-100 text-sm mt-1">
                                            <FileText className="h-4 w-4 inline mr-1" />
                                            Processed overall
                                        </p>
                                    </div>
                                    <FileText className="h-12 w-12 text-green-200" />
                                </div>
                            </div>

                            <div className="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-purple-100 text-sm font-medium">Verified Reports</p>
                                        <p className="text-3xl font-bold">{formatNumber(analyticsData.overview.verifiedReports)}</p>
                                        <p className="text-purple-100 text-sm mt-1">
                                            <CheckCircle className="h-4 w-4 inline mr-1" />
                                            {analyticsData.overview.totalReports > 0 
                                                ? Math.round((analyticsData.overview.verifiedReports / analyticsData.overview.totalReports) * 100) + '%'
                                                : '0%'
                                            } completion rate
                                        </p>
                                    </div>
                                    <Award className="h-12 w-12 text-purple-200" />
                                </div>
                            </div>

                            <div className="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-orange-100 text-sm font-medium">Avg. Processing</p>
                                        <p className="text-3xl font-bold">{analyticsData.processingStats.averageTime}min</p>
                                        <p className="text-orange-100 text-sm mt-1">
                                            <Zap className="h-4 w-4 inline mr-1" />
                                            {analyticsData.processingStats.successRate.toFixed(1)}% success rate
                                        </p>
                                    </div>
                                    <Activity className="h-12 w-12 text-orange-200" />
                                </div>
                            </div>
                        </div>

                        {/* Detailed Metrics */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-400">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-gray-600 text-sm font-medium">Pending Reports</p>
                                        <p className="text-2xl font-bold text-yellow-600">{analyticsData.overview.pendingReports}</p>
                                    </div>
                                    <Clock className="h-8 w-8 text-yellow-400" />
                                </div>
                            </div>

                            <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-400">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-gray-600 text-sm font-medium">Failed Reports</p>
                                        <p className="text-2xl font-bold text-red-600">{analyticsData.overview.failedReports}</p>
                                    </div>
                                    <AlertTriangle className="h-8 w-8 text-red-400" />
                                </div>
                            </div>

                            <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-400">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-gray-600 text-sm font-medium">Fastest Processing</p>
                                        <p className="text-2xl font-bold text-green-600">{analyticsData.processingStats.fastestTime}min</p>
                                    </div>
                                    <Target className="h-8 w-8 text-green-400" />
                                </div>
                            </div>

                            <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-400">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-gray-600 text-sm font-medium">Test Categories</p>
                                        <p className="text-2xl font-bold text-blue-600">{analyticsData.testCategories.length}</p>
                                    </div>
                                    <PieChart className="h-8 w-8 text-blue-400" />
                                </div>
                            </div>
                        </div>

                        {/* Charts Section */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Trends Chart */}
                            <div className="bg-white rounded-xl shadow-sm">
                                <div className="p-6 border-b border-gray-200">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-lg font-semibold text-gray-900">Report Trends</h3>
                                        <div className="flex space-x-2">
                                            {['reports', 'patients', 'verified'].map((metric) => (
                                                <button
                                                    key={metric}
                                                    onClick={() => setSelectedMetric(metric)}
                                                    className={`px-3 py-1 rounded-full text-xs font-medium ${
                                                        selectedMetric === metric 
                                                            ? 'bg-blue-100 text-blue-800' 
                                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                                    }`}
                                                >
                                                    {metric.charAt(0).toUpperCase() + metric.slice(1)}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-4">
                                        {analyticsData.trends.daily.map((day, index) => {
                                            const value = day[selectedMetric as keyof typeof day] as number
                                            const maxValue = getMaxValue(analyticsData.trends.daily, selectedMetric)
                                            const percentage = maxValue > 0 ? (value / maxValue) * 100 : 0
                                            
                                            return (
                                                <div key={index} className="flex items-center space-x-3">
                                                    <div className="w-16 text-xs text-gray-600 font-medium">
                                                        {day.date}
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="bg-gray-200 rounded-full h-2">
                                                            <div 
                                                                className="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300"
                                                                style={{ width: `${percentage}%` }}
                                                            ></div>
                                                        </div>
                                                    </div>
                                                    <div className="w-8 text-xs text-gray-900 font-semibold text-right">
                                                        {value}
                                                    </div>
                                                </div>
                                            )
                                        })}
                                    </div>
                                </div>
                            </div>

                            {/* Test Categories */}
                            <div className="bg-white rounded-xl shadow-sm">
                                <div className="p-6 border-b border-gray-200">
                                    <h3 className="text-lg font-semibold text-gray-900">Test Categories Distribution</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-4">
                                        {analyticsData.testCategories.slice(0, 6).map((category, index) => (
                                            <div key={category.category} className="flex items-center justify-between">
                                                <div className="flex items-center space-x-3">
                                                    <div className={`w-4 h-4 rounded-full ${getCategoryColor(index)}`}></div>
                                                    <span className="text-sm font-medium text-gray-900">{category.category}</span>
                                                </div>
                                                <div className="flex items-center space-x-2">
                                                    <span className="text-sm text-gray-600">{category.count}</span>
                                                    <span className="text-xs text-gray-500">({category.percentage}%)</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Additional Insights */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Top Tests */}
                            <div className="bg-white rounded-xl shadow-sm">
                                <div className="p-6 border-b border-gray-200">
                                    <h3 className="text-lg font-semibold text-gray-900">Most Common Tests</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        {analyticsData.topTests.slice(0, 8).map((test, index) => (
                                            <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{test.testName}</p>
                                                    <p className="text-xs text-gray-600">{test.category}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-semibold text-blue-600">{test.count}</p>
                                                    <p className="text-xs text-gray-500">tests</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* User Activity */}
                            <div className="bg-white rounded-xl shadow-sm">
                                <div className="p-6 border-b border-gray-200">
                                    <h3 className="text-lg font-semibold text-gray-900">User Activity</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        {analyticsData.userActivity.slice(0, 6).map((user, index) => (
                                            <div key={user.userId} className="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg">
                                                <div className="flex items-center space-x-3">
                                                    <div className="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                                        {user.userName.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{user.userName}</p>
                                                        <p className="text-xs text-gray-600">
                                                            {user.uploadsCount} uploads • {user.verificationsCount} verifications
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-semibold text-purple-600">
                                                        {user.uploadsCount + user.verificationsCount}
                                                    </p>
                                                    <p className="text-xs text-gray-500">total</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Performance Metrics */}
                        <div className="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-xl shadow-lg p-8 text-white">
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div className="text-center">
                                    <div className="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                                        <Target className="h-8 w-8" />
                                    </div>
                                    <p className="text-2xl font-bold">{analyticsData.processingStats.successRate.toFixed(1)}%</p>
                                    <p className="text-sm opacity-90">Success Rate</p>
                                </div>
                                <div className="text-center">
                                    <div className="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                                        <Zap className="h-8 w-8" />
                                    </div>
                                    <p className="text-2xl font-bold">{analyticsData.processingStats.averageTime}min</p>
                                    <p className="text-sm opacity-90">Avg. Processing</p>
                                </div>
                                <div className="text-center">
                                    <div className="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                                        <Award className="h-8 w-8" />
                                    </div>
                                    <p className="text-2xl font-bold">{analyticsData.processingStats.fastestTime}min</p>
                                    <p className="text-sm opacity-90">Fastest Time</p>
                                </div>
                                <div className="text-center">
                                    <div className="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-3">
                                        <Activity className="h-8 w-8" />
                                    </div>
                                    <p className="text-2xl font-bold">{analyticsData.overview.totalReports}</p>
                                    <p className="text-sm opacity-90">Total Processed</p>
                                </div>
                            </div>
                        </div>
                    </>
                ) : null}
            </div>
        </DashboardLayout>
    )
}