'use client'
import { useState, useEffect } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { ArrowLeft, FileText, User, Calendar, Clock, Phone, CheckCircle, AlertTriangle, Download, Edit, Maximize, Minimize, FileDown } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

interface PatientInfo {
    name: string
    patientId: string
    age: string
    gender: string
    phone: string | null
}

interface LabInfo {
    labId: string
    requestedBy: string
    requestedDate: string
    collectedDate: string
    analysisDate: string
    validatedBy: string
}

interface TestResult {
    category: string
    testName: string
    result: string
    flag: string | null
    unit: string | null
    referenceRange: string | null
}

interface ReportData {
    patientInfo: PatientInfo
    labInfo: LabInfo
    testResults: TestResult[]
}

interface ReportMetadata {
    id: number
    originalFilename: string
    status: string
    verifiedAt: string | null
    verifiedBy: string | null
    notes: string | null
    batchName: string
    uploaderName: string
    batchId?: number
}

export default function ReportDetailsPage() {
    const router = useRouter()
    const searchParams = useSearchParams()
    const reportId = searchParams.get('id')
    const { user } = useAuth()

    const [reportData, setReportData] = useState<ReportData | null>(null)
    const [reportMetadata, setReportMetadata] = useState<ReportMetadata | null>(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState<string>('')
    const [isPreviewExpanded, setIsPreviewExpanded] = useState(false)
    const [isExportingCsv, setIsExportingCsv] = useState(false)
    const [pdfDataUrl, setPdfDataUrl] = useState<string>('')
    const [pdfLoading, setPdfLoading] = useState(true)
    const [pdfError, setPdfError] = useState<string>('')

    // Mock user data
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

    useEffect(() => {
        if (reportId) {
            fetchReportDetails()
            fetchPdfData()
        }
    }, [reportId])

    const fetchReportDetails = async () => {
        try {
            setLoading(true)
            setError('')

            console.log('🚀 Fetching report details for ID:', reportId)
            const response = await apiClient.get(`/lab-reports/${reportId}`)
            console.log('✅ API Response:', response)

            if (response.success && response.data?.lab_report) {
                const labReport = response.data.lab_report
                const extractedData = response.data.extracted_data

                const transformedReportData: ReportData = {
                    patientInfo: {
                        name: extractedData.patientInfo?.name || 'N/A',
                        patientId: extractedData.patientInfo?.patientId || 'N/A',
                        age: extractedData.patientInfo?.age || 'N/A',
                        gender: extractedData.patientInfo?.gender || 'N/A',
                        phone: extractedData.patientInfo?.phone || null
                    },
                    labInfo: {
                        labId: extractedData.labInfo?.labId || 'N/A',
                        requestedBy: extractedData.labInfo?.requestedBy || 'N/A',
                        requestedDate: extractedData.labInfo?.requestedDate || 'N/A',
                        collectedDate: extractedData.labInfo?.collectedDate || 'N/A',
                        analysisDate: extractedData.labInfo?.analysisDate || 'N/A',
                        validatedBy: extractedData.labInfo?.validatedBy || 'N/A'
                    },
                    testResults: extractedData.testResults || []
                }

                const transformedMetadata: ReportMetadata = {
                    id: labReport.id,
                    originalFilename: labReport.original_filename,
                    status: labReport.status,
                    verifiedAt: labReport.verified_at,
                    verifiedBy: labReport.verifier?.name || null,
                    notes: labReport.notes,
                    batchName: labReport.batch?.name || 'Unknown Batch',
                    uploaderName: labReport.uploader?.name || 'Unknown',
                    batchId: labReport.batch?.id
                }

                setReportData(transformedReportData)
                setReportMetadata(transformedMetadata)

                console.log('✅ Data transformed successfully:', {
                    patientName: transformedReportData.patientInfo.name,
                    labId: transformedReportData.labInfo.labId,
                    testResultsCount: transformedReportData.testResults.length
                })
            } else {
                throw new Error('Invalid response structure')
            }
        } catch (err: any) {
            console.error('💥 Failed to fetch report details:', err)
            let errorMessage = 'Failed to load report details'

            if (err.response?.status === 404) {
                errorMessage = 'Report not found'
            } else if (err.response?.status === 401) {
                errorMessage = 'Authentication failed. Please log in again.'
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

    const fetchPdfData = async () => {
        try {
            setPdfLoading(true)
            setPdfError('')

            console.log('🚀 Fetching PDF data for report ID:', reportId)
            const response = await apiClient.get(`/${reportId}/pdf-data`)
            console.log('✅ PDF API Response:', response)

            if (response.success && response.data?.base64_content) {
                const base64 = response.data.base64_content
                const dataUrl = `data:application/pdf;base64,${base64}`
                setPdfDataUrl(dataUrl)
            } else {
                throw new Error('Invalid PDF response structure')
            }
        } catch (err: any) {
            console.error('💥 Failed to fetch PDF data:', err)
            let errorMessage = 'Failed to load PDF preview'

            if (err.status === 404) {
                errorMessage = 'PDF file not found'
            } else if (err.status === 401) {
                errorMessage = 'Authentication failed. Please log in again.'
            } else if (err.message) {
                errorMessage = err.message
            }

            setPdfError(errorMessage)
        } finally {
            setPdfLoading(false)
        }
    }

    const handleDownloadPdf = () => {
        if (!pdfDataUrl || !reportMetadata) return

        const link = document.createElement('a')
        link.href = pdfDataUrl
        link.download = reportMetadata.originalFilename || `report_${reportId}.pdf`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
    }

    const handleExportCsv = async () => {
        try {
            setIsExportingCsv(true)
            console.log('🚀 Starting CSV export for report:', reportId)
            // Use fetch for blob response
            const token = localStorage.getItem('auth_token')
            const fetchResponse = await fetch(`http://localhost:8000/api/lab-reports/export/verified-csv?report_id=${reportId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'text/csv',
                    'Authorization': `Bearer ${token}`
                }
            })
            if (!fetchResponse.ok) {
                throw new Error('Failed to export CSV')
            }
            const blob = await fetchResponse.blob()
            if (blob.size === 0) {
                throw new Error('No verified data found for this report')
            }
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            const patientName = reportData?.patientInfo.name.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '') || 'unknown'
            const labId = reportData?.labInfo.labId || reportId
            const date = new Date().toISOString().split('T')[0]
            link.download = `verified_report_${labId}_${patientName}_${date}.csv`
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            window.URL.revokeObjectURL(url)
            console.log('✅ CSV export successful')
        } catch (error: any) {
            console.error('❌ CSV export failed:', error)
            let errorMessage = 'Failed to export CSV. Please try again.'
            if (error.message.includes('Failed to export CSV')) {
                errorMessage = 'No verified data found for this report.'
            } else if (error.message) {
                errorMessage = error.message
            }
            alert(errorMessage)
        } finally {
            setIsExportingCsv(false)
        }
    }

    const handleVerifyReport = () => {
        if (reportMetadata) {
            const batchId = reportMetadata.batchId || reportMetadata.batchName.match(/\d+/)?.[0] || reportMetadata.id
            router.push(`/main/verification?batchId=${batchId}&reportId=${reportId}`)
        }
    }

    const getTestResultFlag = (flag: string | null, result: string, referenceRange: string | null) => {
        if (flag === "HIGH" || flag === "H") {
            return (
                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    <AlertTriangle className="h-3 w-3 mr-1" />
                    High
                </span>
            )
        }
        if (flag === "LOW" || flag === "L") {
            return (
                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    <AlertTriangle className="h-3 w-3 mr-1" />
                    Low
                </span>
            )
        }
        if (flag === "CRITICAL" || flag === "C") {
            return (
                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-200 text-red-900">
                    <AlertTriangle className="h-3 w-3 mr-1" />
                    Critical
                </span>
            )
        }
        return (
            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <CheckCircle className="h-3 w-3 mr-1" />
                Normal
            </span>
        )
    }

    // Group test results by category
    const groupedResults = reportData?.testResults.reduce((acc, test) => {
        if (!acc[test.category]) {
            acc[test.category] = []
        }
        acc[test.category].push(test)
        return acc
    }, {} as Record<string, TestResult[]>)

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center min-h-96">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-3 text-gray-600">Loading report details...</span>
                </div>
            </DashboardLayout>
        )
    }

    if (error) {
        return (
            <DashboardLayout>
                <div className="text-center py-12">
                    <div className="text-red-600 text-lg font-medium mb-4">{error}</div>
                    <button
                        onClick={() => router.back()}
                        className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Go Back
                    </button>
                </div>
            </DashboardLayout>
        )
    }

    if (!reportData || !reportMetadata) {
        return (
            <DashboardLayout>
                <div className="text-center py-12">
                    <p className="text-gray-500">Report not found</p>
                    <button
                        onClick={() => router.back()}
                        className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Go Back
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
                        <button
                            onClick={() => router.back()}
                            className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Reports
                        </button>
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Report Details</h1>
                            <div className="mt-1 flex items-center space-x-4">
                                <p className="text-gray-600">Lab Report ID: {reportData.labInfo.labId}</p>
                                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${reportMetadata.status === 'verified'
                                        ? 'bg-green-100 text-green-800'
                                        : (reportMetadata.status === 'processing' || reportMetadata.status === 'processed')
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-yellow-100 text-yellow-800'
                                    }`}>
                                    {reportMetadata.status === 'processed' ? 'Needs Verification' : reportMetadata.status}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex space-x-3">
                        {(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') && (
                            <button
                                onClick={handleVerifyReport}
                                className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                            >
                                <CheckCircle className="h-4 w-4 mr-2" />
                                Verify Report
                            </button>
                        )}
                        {(reportMetadata.status === 'verified' || currentUser.name.includes('Dr.')) && (
                            <>
                                {reportMetadata.status === 'verified' && (
                                    <button
                                        onClick={handleExportCsv}
                                        disabled={isExportingCsv}
                                        className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {isExportingCsv ? (
                                            <>
                                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                Exporting...
                                            </>
                                        ) : (
                                            <>
                                                <FileDown className="h-4 w-4 mr-2" />
                                                Export CSV
                                            </>
                                        )}
                                    </button>
                                )}
                            </>
                        )}
                        <button
                            onClick={handleDownloadPdf}
                            disabled={!pdfDataUrl || pdfLoading}
                            className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Download className="h-4 w-4 mr-2" />
                            Download PDF
                        </button>
                    </div>
                </div>

                {/* Report Metadata */}
                <div className={`border rounded-lg p-4 ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed')
                        ? 'bg-blue-50 border-blue-200'
                        : reportMetadata.status === 'verified'
                            ? 'bg-green-50 border-green-200'
                            : 'bg-yellow-50 border-yellow-200'
                    }`}>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span className={`font-medium ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-900' :
                                    reportMetadata.status === 'verified' ? 'text-green-900' :
                                        'text-yellow-900'
                                }`}>Batch:</span>
                            <span className={`ml-2 ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-700' :
                                    reportMetadata.status === 'verified' ? 'text-green-700' :
                                        'text-yellow-700'
                                }`}>{reportMetadata.batchName}</span>
                        </div>
                        <div>
                            <span className={`font-medium ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-900' :
                                    reportMetadata.status === 'verified' ? 'text-green-900' :
                                        'text-yellow-900'
                                }`}>Uploaded by:</span>
                            <span className={`ml-2 ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-700' :
                                    reportMetadata.status === 'verified' ? 'text-green-700' :
                                        'text-yellow-700'
                                }`}>{reportMetadata.uploaderName}</span>
                        </div>
                        {reportMetadata.verifiedBy && (
                            <div>
                                <span className={`font-medium ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-900' :
                                        reportMetadata.status === 'verified' ? 'text-green-900' :
                                            'text-yellow-900'
                                    }`}>Verified by:</span>
                                <span className={`ml-2 ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-700' :
                                        reportMetadata.status === 'verified' ? 'text-green-700' :
                                            'text-yellow-700'
                                    }`}>{reportMetadata.verifiedBy}</span>
                            </div>
                        )}
                    </div>
                    {(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') && (
                        <div className="mt-2 pt-2 border-t border-blue-200">
                            <div className="flex items-center">
                                <Clock className="h-4 w-4 text-blue-600 mr-2" />
                                <span className="font-medium text-blue-900">Status:</span>
                                <span className="ml-2 text-blue-700">
                                    This report has been processed and data extracted. Click "Verify Report" to review and approve the extracted data.
                                </span>
                            </div>
                        </div>
                    )}
                    {reportMetadata.notes && (
                        <div className={`mt-2 pt-2 border-t ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'border-blue-200' :
                                reportMetadata.status === 'verified' ? 'border-green-200' :
                                    'border-yellow-200'
                            }`}>
                            <span className={`font-medium ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') ? 'text-blue-900' :
                                    reportMetadata.status === 'verified' ? 'text-green-900' :
                                        'text-yellow-900'
                                }`}>Notes:</span>
                            <p className={`mt-1 ${(reportMetadata.status === 'processing' || reportMetadata.status === 'processed')
                                    ? 'text-blue-700'
                                    : reportMetadata.status === 'verified'
                                        ? 'text-green-700'
                                        : 'text-yellow-700'
                                }`}>{reportMetadata.notes}</p>
                        </div>
                    )}
                </div>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    {/* Left Column - PDF Preview */}
                    <div className="xl:col-span-5">
                        <div className="bg-white shadow rounded-lg sticky top-6">
                            <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Original Report</h3>
                                <div className="flex items-center space-x-2">
                                    <button
                                        onClick={() => setIsPreviewExpanded(!isPreviewExpanded)}
                                        className="inline-flex items-center px-2 py-1 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        {isPreviewExpanded ? <Minimize className="h-3 w-3" /> : <Maximize className="h-3 w-3" />}
                                    </button>
                                </div>
                            </div>
                            <div className={`p-4 ${isPreviewExpanded ? 'fixed inset-0 z-50 bg-white' : ''}`}>
                                {pdfLoading ? (
                                    <div className="flex items-center justify-center h-96">
                                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                        <span className="ml-3 text-gray-600">Loading PDF...</span>
                                    </div>
                                ) : pdfError ? (
                                    <div className="text-center h-96 flex items-center justify-center">
                                        <div className="text-red-600">{pdfError}</div>
                                    </div>
                                ) : (
                                    <div className="border rounded-lg overflow-hidden" style={{ height: isPreviewExpanded ? '90vh' : '600px' }}>
                                        <iframe
                                            src={pdfDataUrl}
                                            className="w-full h-full"
                                            title="PDF Preview"
                                        />
                                    </div>
                                )}
                                {isPreviewExpanded && (
                                    <button
                                        onClick={() => setIsPreviewExpanded(false)}
                                        className="absolute top-4 right-4 inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        <Minimize className="h-4 w-4 mr-2" />
                                        Close
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right Column - Patient & Lab Info and Test Results */}
                    <div className="xl:col-span-7 space-y-6">
                        {(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') && (
                            <div className="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <AlertTriangle className="h-5 w-5 text-blue-400" />
                                    </div>
                                    <div className="ml-3">
                                        <h3 className="text-sm font-medium text-blue-800">
                                            Verification Required
                                        </h3>
                                        <div className="mt-2 text-sm text-blue-700">
                                            <p>
                                                This report has been processed and the data has been extracted successfully.
                                                Please review the extracted information below and click "Verify Report"
                                                to complete the verification process.
                                            </p>
                                        </div>
                                        <div className="mt-4">
                                            <button
                                                onClick={handleVerifyReport}
                                                className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                <CheckCircle className="h-4 w-4 mr-2" />
                                                Go to Verification Page
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Patient & Lab Information */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div className="bg-white shadow rounded-lg">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                        <User className="h-5 w-5 mr-2 text-blue-600" />
                                        Patient Information
                                        {(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') && (
                                            <span className="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Needs Review
                                            </span>
                                        )}
                                    </h3>
                                </div>
                                <div className="px-6 py-4 space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Patient Name</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.patientInfo.name || 'Not extracted'}</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Patient ID</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.patientInfo.patientId}</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Age</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.patientInfo.age}</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Gender</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.patientInfo.gender}</p>
                                        </div>
                                        <div className="col-span-2">
                                            <label className="block text-sm font-medium text-gray-500">Phone Number</label>
                                            <p className="mt-1 text-sm text-gray-900 flex items-center">
                                                <Phone className="h-4 w-4 mr-2 text-gray-400" />
                                                {reportData.patientInfo.phone || 'Not provided'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white shadow rounded-lg">
                                <div className="px-6 py-4 border-b border-gray-200">
                                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                        <FileText className="h-5 w-5 mr-2 text-green-600" />
                                        Laboratory Information
                                        {(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') && (
                                            <span className="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Needs Review
                                            </span>
                                        )}
                                    </h3>
                                </div>
                                <div className="px-6 py-4 space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Lab ID</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.labInfo.labId}</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Requested By</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.labInfo.requestedBy}</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Requested Date</label>
                                            <p className="mt-1 text-sm text-gray-900 flex items-center">
                                                <Calendar className="h-4 w-4 mr-2 text-gray-400" />
                                                {reportData.labInfo.requestedDate}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Collected Date</label>
                                            <p className="mt-1 text-sm text-gray-900 flex items-center">
                                                <Calendar className="h-4 w-4 mr-2 text-gray-400" />
                                                {reportData.labInfo.collectedDate}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Analysis Date</label>
                                            <p className="mt-1 text-sm text-gray-900 flex items-center">
                                                <Calendar className="h-4 w-4 mr-2 text-gray-400" />
                                                {reportData.labInfo.analysisDate}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-500">Validated By</label>
                                            <p className="mt-1 text-sm text-gray-900">{reportData.labInfo.validatedBy}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Test Results by Category */}
                        <div className="space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xl font-bold text-gray-900">Test Results</h3>
                                {(reportMetadata.status === 'processing' || reportMetadata.status === 'processed') && (
                                    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <AlertTriangle className="h-4 w-4 mr-2" />
                                        Needs Verification
                                    </span>
                                )}
                            </div>

                            {groupedResults && Object.entries(groupedResults).map(([category, tests]) => (
                                <div key={category} className="bg-white shadow rounded-lg">
                                    <div className="px-6 py-4 border-b border-gray-200">
                                        <h4 className="text-lg font-medium text-gray-900">{category}</h4>
                                    </div>
                                    <div className="px-6 py-4">
                                        <div className="overflow-hidden">
                                            <table className="min-w-full divide-y divide-gray-200">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test Name</th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference Range</th>
                                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flag</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white divide-y divide-gray-200">
                                                    {tests.map((test, index) => (
                                                        <tr key={index}>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{test.testName}</td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{test.result}</td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{test.unit || 'N/A'}</td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{test.referenceRange || 'N/A'}</td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                {getTestResultFlag(test.flag, test.result, test.referenceRange)}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            ))}

                            {(!groupedResults || Object.keys(groupedResults).length === 0) && (
                                <div className="bg-white shadow rounded-lg p-6">
                                    <div className="text-center">
                                        <FileText className="mx-auto h-12 w-12 text-gray-400" />
                                        <h3 className="mt-2 text-sm font-medium text-gray-900">No test results found</h3>
                                        <p className="mt-1 text-sm text-gray-500">Test results might not have been extracted properly.</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    )
}