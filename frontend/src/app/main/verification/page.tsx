'use client'
import { useState, useEffect, useMemo } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import DashboardLayout from '@/components/layout/DashboardLayout'
import {ArrowLeft,FileText,User,Eye,Maximize,Minimize,CheckCircle,Clock as ClockIcon,Plus,Trash2,AlertTriangle} from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

// Interfaces
interface PatientInfo {
    name: string
    patientId: string
    age: string
    gender: string
    phone: string
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
    id: string
    category: string
    testName: string
    result: string
    unit: string
    referenceRange: string
    flag: 'high' | 'low' | 'critical' | 'normal' | null
}

interface ProcessedReport {
    id: string
    fileName: string
    status: 'processing' | 'completed' | 'verified' | 'error'
    processingProgress: number
    pdfUrl: string // Will be updated dynamically with data URL
    patientInfo: PatientInfo
    labInfo: LabInfo
    testResults: TestResult[]
    extracted_data?: any
    original_filename?: string
    uploader?: any
}

interface BatchInfo {
    id: number
    name: string
    status: string
}

export default function VerificationPage() {
    const router = useRouter()
    const searchParams = useSearchParams()
    const batchId = searchParams.get('batchId')
    const reportId = searchParams.get('reportId')
    const { user } = useAuth()

    const [isProcessing, setIsProcessing] = useState(true)
    const [reports, setReports] = useState<ProcessedReport[]>([])
    const [selectedReport, setSelectedReport] = useState<ProcessedReport | null>(null)
    const [editingField, setEditingField] = useState<string | null>(null)
    const [isPreviewExpanded, setIsPreviewExpanded] = useState(false)
    const [processingAnimation, setProcessingAnimation] = useState(true)
    const [batchInfo, setBatchInfo] = useState<BatchInfo | null>(null)
    const [error, setError] = useState('')
    const [pdfDataUrl, setPdfDataUrl] = useState<string>('')
    const [pdfLoading, setPdfLoading] = useState(false)
    const [pdfError, setPdfError] = useState('')

    // Modal states
    const [showCategoryModal, setShowCategoryModal] = useState(false)
    const [newCategoryName, setNewCategoryName] = useState('')
    const [isSubmitting, setIsSubmitting] = useState(false)

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

    // Group test results by category - use useMemo to avoid recalculation
    const groupedTestResults = useMemo(() => {
        if (!selectedReport) return {}

        return selectedReport.testResults.reduce((acc, test) => {
            const category = test.category || 'UNCATEGORIZED'
            if (!acc[category]) {
                acc[category] = []
            }
            acc[category].push(test)
            return acc
        }, {} as Record<string, TestResult[]>)
    }, [selectedReport])

    useEffect(() => {
        if (reportId) {
            fetchSingleReport(reportId)
        } else if (batchId) {
            fetchBatchReports()
        } else {
            fetchAllReports()
        }
    }, [batchId, reportId])

    const fetchPdfData = async (reportId: string) => {
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

    const fetchSingleReport = async (id: string) => {
        try {
            setIsProcessing(true)
            setError('')

            console.log('🚀 Fetching single report:', id)

            const response = await apiClient.get(`/lab-reports/${id}`)
            console.log('✅ Single report API Response:', response)

            if (response.success && response.data?.lab_report) {
                const labReport = response.data.lab_report
                const extractedData = response.data.extracted_data

                console.log('📊 Lab Report Data:', labReport)
                console.log('📋 Extracted Data:', extractedData)

                const transformedReport = transformLabReportToProcessedReport(labReport, extractedData)

                setReports([transformedReport])
                setSelectedReport(transformedReport)

                // Fetch PDF data for the selected report
                await fetchPdfData(id)

                if (labReport.batch) {
                    setBatchInfo({
                        id: labReport.batch.id,
                        name: labReport.batch.name,
                        status: labReport.batch.status
                    })
                }

                console.log('✅ Single report loaded successfully')
            } else {
                throw new Error('Invalid response structure')
            }
        } catch (err: any) {
            console.error('💥 Failed to fetch single report:', err)
            handleFetchError(err, `report ${id}`)
        } finally {
            setIsProcessing(false)
            setProcessingAnimation(false)
        }
    }

    const fetchBatchReports = async () => {
        try {
            setIsProcessing(true)
            setError('')

            console.log('🚀 Fetching reports for batch:', batchId)

            const response = await apiClient.get(`/batches/${batchId}/reports-for-verification`)
            console.log('✅ Batch reports API Response:', response)

            const apiResponse = response.data

            if (!apiResponse || !apiResponse.batch || !apiResponse.reports_to_verify?.data) {
                throw new Error('Invalid API response structure')
            }

            setBatchInfo(apiResponse.batch)

            const reportsData = apiResponse.reports_to_verify.data
            const transformedReports = reportsData.map((report: any) =>
                transformLabReportToProcessedReport(report, report.extracted_data)
            )

            setReports(transformedReports)

            if (transformedReports.length > 0) {
                const targetReport = reportId
                    ? transformedReports.find((r: ProcessedReport) => r.id === reportId)
                    : transformedReports[0]
                setSelectedReport(targetReport || transformedReports[0])
                if (targetReport) {
                    await fetchPdfData(targetReport.id)
                }
            }

            console.log('✅ Batch reports loaded successfully')
        } catch (err: any) {
            console.error('💥 Failed to fetch batch reports:', err)
            handleFetchError(err, `batch ${batchId}`)
        } finally {
            setIsProcessing(false)
            setProcessingAnimation(false)
        }
    }

    const transformLabReportToProcessedReport = (labReport: any, extractedData: any): ProcessedReport => {
        console.log('🔄 Transforming lab report:', labReport.id)

        const transformed = {
            id: labReport.id.toString(),
            fileName: labReport.original_filename || `Report ${labReport.id}`,
            status: labReport.status === 'verified' ? 'verified' as const : 
                    labReport.status === 'processed' ? 'completed' as const : 'completed' as const,
            processingProgress: 100,
            pdfUrl: '', 
            patientInfo: {
                name: extractedData?.patientInfo?.name || '',
                patientId: extractedData?.patientInfo?.patientId || '',
                age: extractedData?.patientInfo?.age || '',
                gender: extractedData?.patientInfo?.gender || '',
                phone: extractedData?.patientInfo?.phone || ''
            },
            labInfo: {
                labId: extractedData?.labInfo?.labId || '',
                requestedBy: extractedData?.labInfo?.requestedBy || '',
                requestedDate: extractedData?.labInfo?.requestedDate || '',
                collectedDate: extractedData?.labInfo?.collectedDate || '',
                analysisDate: extractedData?.labInfo?.analysisDate || '',
                validatedBy: extractedData?.labInfo?.validatedBy || ''
            },
            testResults: (extractedData?.testResults || []).map((test: any, index: number) => ({
                id: `${labReport.id}_${index}`,
                category: test.category || '',
                testName: test.testName || '',
                result: test.result || '',
                unit: test.unit || '',
                referenceRange: test.referenceRange || '',
                flag: mapBackendFlag(test.flag)
            })),
            extracted_data: extractedData,
            original_filename: labReport.original_filename,
            uploader: labReport.uploader
        }

        console.log('✅ Transformed report:', {
            id: transformed.id,
            fileName: transformed.fileName,
            testResultsCount: transformed.testResults.length
        })

        return transformed
    }

    const mapBackendFlag = (flag: string | null): TestResult['flag'] => {
        if (!flag) return null
        switch (flag.toUpperCase()) {
            case 'H': return 'high'
            case 'L': return 'low'
            case 'C': return 'critical'
            default: return 'normal'
        }
    }

    const mapFrontendFlag = (flag: TestResult['flag']): string | null => {
        if (!flag || flag === 'normal') return null
        switch (flag) {
            case 'high': return 'H'
            case 'low': return 'L'
            case 'critical': return 'C'
            default: return null
        }
    }

    const handleFetchError = (err: any, context: string) => {
        let errorMessage = `Failed to load ${context}`

        if (err.response?.status === 404) {
            errorMessage = `${context} not found or has no reports ready for verification`
        } else if (err.response?.status === 401) {
            errorMessage = 'Authentication failed. Please log in again.'
        } else if (err.response?.status === 403) {
            errorMessage = 'You do not have permission to access this resource'
        } else if (err.response?.data?.message) {
            errorMessage = err.response.data.message
        } else if (err.message) {
            errorMessage = err.message
        }

        setError(errorMessage)
    }

    // Helper functions for updating data
    const updatePatientInfo = (field: keyof PatientInfo, value: string) => {
        if (!selectedReport) return

        const updatedReport = {
            ...selectedReport,
            patientInfo: {
                ...selectedReport.patientInfo,
                [field]: value
            }
        }

        setSelectedReport(updatedReport)
        setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))
    }

    const updateLabInfo = (field: keyof LabInfo, value: string) => {
        if (!selectedReport) return

        const updatedReport = {
            ...selectedReport,
            labInfo: {
                ...selectedReport.labInfo,
                [field]: value
            }
        }

        setSelectedReport(updatedReport)
        setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))
    }

    const updateTestResult = (testId: string, field: keyof TestResult, value: string | null) => {
        if (!selectedReport) return

        const updatedReport = {
            ...selectedReport,
            testResults: selectedReport.testResults.map(test =>
                test.id === testId ? { ...test, [field]: value } : test
            )
        }

        setSelectedReport(updatedReport)
        setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))
    }

    const addNewCategory = () => {
        setShowCategoryModal(true)
    }

    const handleCategorySubmit = () => {
        if (!newCategoryName.trim() || !selectedReport) return

        const newTest: TestResult = {
            id: `test_${Date.now()}`,
            category: newCategoryName.toUpperCase(),
            testName: '',
            result: '',
            unit: '',
            referenceRange: '',
            flag: null
        }

        const updatedReport = {
            ...selectedReport,
            testResults: [...selectedReport.testResults, newTest]
        }

        setSelectedReport(updatedReport)
        setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))
        setNewCategoryName('')
        setShowCategoryModal(false)
    }

    const addTestResult = (category: string) => {
        if (!selectedReport) return

        const newTest: TestResult = {
            id: `test_${Date.now()}`,
            category,
            testName: '',
            result: '',
            unit: '',
            referenceRange: '',
            flag: null
        }

        const updatedReport = {
            ...selectedReport,
            testResults: [...selectedReport.testResults, newTest]
        }

        setSelectedReport(updatedReport)
        setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))
    }

    const removeTestResult = (testId: string) => {
        if (!selectedReport) return

        const updatedReport = {
            ...selectedReport,
            testResults: selectedReport.testResults.filter(test => test.id !== testId)
        }

        setSelectedReport(updatedReport)
        setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))
    }

    const handleSubmitVerification = async () => {
        if (!selectedReport) return

        setIsSubmitting(true)

        try {
            console.log('🚀 Starting verification submission for report:', selectedReport.id)

            const verifiedData = {
                verified_data: {
                    patientInfo: {
                        name: selectedReport.patientInfo.name,
                        patientId: selectedReport.patientInfo.patientId,
                        age: selectedReport.patientInfo.age,
                        gender: selectedReport.patientInfo.gender,
                        phone: selectedReport.patientInfo.phone
                    },
                    labInfo: {
                        labId: selectedReport.labInfo.labId,
                        requestedBy: selectedReport.labInfo.requestedBy,
                        requestedDate: selectedReport.labInfo.requestedDate,
                        collectedDate: selectedReport.labInfo.collectedDate,
                        analysisDate: selectedReport.labInfo.analysisDate,
                        validatedBy: selectedReport.labInfo.validatedBy
                    },
                    testResults: selectedReport.testResults.map(test => ({
                        category: test.category,
                        testName: test.testName,
                        result: test.result,
                        unit: test.unit,
                        referenceRange: test.referenceRange,
                        flag: mapFrontendFlag(test.flag)
                    }))
                },
                notes: `Verified by ${currentUser.name} on ${new Date().toLocaleDateString()}`
            }

            console.log('📦 Sending verification data:', verifiedData)

            const response = await apiClient.post(`/lab-reports/${selectedReport.id}/verify`, verifiedData)

            console.log('✅ Verification API Response:', response)

            if (response.success) {
                console.log('🎉 Report verified successfully!')

                const updatedReport = {
                    ...selectedReport,
                    status: 'verified' as const
                }

                setSelectedReport(updatedReport)
                setReports(prev => prev.map(r => r.id === selectedReport.id ? updatedReport : r))

                if (batchId) {
                    const remainingReports = reports.filter(r =>
                        r.id !== selectedReport.id && r.status === 'completed'
                    )

                    if (remainingReports.length > 0) {
                        setSelectedReport(remainingReports[0])
                        console.log('👆 Auto-selected next report for verification:', remainingReports[0].fileName)

                        const newUrl = `/main/verification?batchId=${batchId}&reportId=${remainingReports[0].id}`
                        window.history.replaceState(null, '', newUrl)

                        // Fetch PDF for the next report
                        await fetchPdfData(remainingReports[0].id)
                    } else {
                        console.log('🏁 All reports in batch verified')
                        router.push('/main/verification/monitoring')
                    }
                } else {
                    router.push('/main/reports?status=verified')
                }
            } else {
                throw new Error(response.message || 'Verification failed')
            }
        } catch (err: any) {
            console.error('💥 Verification submission failed:', err)

            let errorMessage = 'Failed to verify report'

            if (err.response?.status === 422) {
                const validationErrors = err.response.data?.errors
                if (validationErrors) {
                    errorMessage = 'Validation failed: ' + Object.values(validationErrors).flat().join(', ')
                } else {
                    errorMessage = err.response.data?.message || 'Validation failed'
                }
            } else if (err.response?.status === 401) {
                errorMessage = 'Authentication failed. Please log in again.'
            } else if (err.response?.status === 404) {
                errorMessage = 'Report not found'
            } else if (err.response?.data?.message) {
                errorMessage = err.response.data.message
            } else if (err.message) {
                errorMessage = err.message
            }

            alert(`Verification Error: ${errorMessage}`)
            setError(errorMessage)
        } finally {
            setIsSubmitting(false)
        }
    }

    const handleVerifyLater = async () => {
        if (!selectedReport) return

        setIsSubmitting(true)

        try {
            console.log('💾 Saving report for later verification:', selectedReport.id)

            if (batchId) {
                router.push('/main/verification/monitoring')
            } else {
                router.push('/main/reports?status=processed')
            }
        } catch (err: any) {
            console.error('💥 Failed to save for later:', err)
            alert('Failed to save report for later verification')
        } finally {
            setIsSubmitting(false)
        }
    }

    const renderFlagDropdown = (test: TestResult) => (
        <select
            value={test.flag || ''}
            onChange={(e) => {
                const value = e.target.value === '' ? null : e.target.value
                updateTestResult(test.id, 'flag', value as TestResult['flag'])
            }}
            className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900"
        >
            <option value="">Normal</option>
            <option value="H">High (H)</option>
            <option value="L">Low (L)</option>
        </select>
    )

    const fetchAllReports = () => {
        const mockReports: ProcessedReport[] = [
            {
                id: 'rpt_001',
                fileName: 'lab_report_john_doe.pdf',
                status: 'completed',
                processingProgress: 100,
                pdfUrl: '', // Placeholder for mock data
                patientInfo: {
                    name: "សាន សេងយាន",
                    patientId: "PT001871",
                    age: "72 Y",
                    gender: "Female",
                    phone: "069366717"
                },
                labInfo: {
                    labId: "LT001235",
                    requestedBy: "Dr. CHHORN Sophy",
                    requestedDate: "17/03/2024 12:57",
                    collectedDate: "17/03/2024 13:36",
                    analysisDate: "17/03/2024 13:36",
                    validatedBy: "SREYNEANG - B.Sc"
                },
                testResults: [
                    {
                        id: '1',
                        category: "BIOCHEMISTRY",
                        testName: "Glucose",
                        result: "6.5",
                        unit: "mmol/L",
                        referenceRange: "(3.9-6.1)",
                        flag: "high"
                    }
                ]
            }
        ]

        setTimeout(() => {
            setProcessingAnimation(false)
            setReports(mockReports)
            setIsProcessing(false)
            const firstCompleted = mockReports.find(r => r.status === 'completed')
            if (firstCompleted) {
                setSelectedReport(firstCompleted)
                // Skip PDF fetching for mock data
                setPdfDataUrl('')
                setPdfError('PDF preview not available for mock data')
            }
        }, 3000)
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <button
                            onClick={() => {
                                if (reportId && !batchId) {
                                    router.push('/main/reports')
                                } else {
                                    router.push('/main/verification/monitoring')
                                }
                            }}
                            className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            {reportId && !batchId ? 'Back to Reports' : 'Back to Monitoring'}
                        </button>
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">
                                {batchId ? `Batch Verification - ${batchInfo?.name || `Batch ${batchId}`}` : 'Data Verification'}
                            </h1>
                            <p className="mt-1 text-gray-600">
                                {batchId
                                    ? `Review and verify ${reports.length} reports from this batch`
                                    : reportId
                                        ? 'Review and verify this report'
                                        : 'Review and verify extracted data before submission'
                                }
                            </p>
                        </div>
                    </div>
                    <div className="flex space-x-3">
                        <button
                            onClick={handleVerifyLater}
                            disabled={!selectedReport || selectedReport.status !== 'completed' || isSubmitting}
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isSubmitting ? (
                                <>
                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-400 mr-2"></div>
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <ClockIcon className="h-4 w-4 mr-2" />
                                    Verify Later
                                </>
                            )}
                        </button>
                        <button
                            onClick={handleSubmitVerification}
                            disabled={!selectedReport || selectedReport.status !== 'completed' || isSubmitting}
                            className="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isSubmitting ? (
                                <>
                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                    Submitting...
                                </>
                            ) : (
                                <>
                                    <CheckCircle className="h-4 w-4 mr-2" />
                                    Submit Verification
                                </>
                            )}
                        </button>
                    </div>
                </div>

                {/* Batch Info Banner */}
                {batchInfo && (
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="flex items-center space-x-4">
                            <div className="flex-shrink-0">
                                <FileText className="h-8 w-8 text-blue-600" />
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-medium text-blue-900">{batchInfo.name}</h3>
                                <p className="text-blue-700">
                                    Verifying {reports.length} reports from this batch
                                </p>
                            </div>
                            <div className="flex-shrink-0">
                                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${batchInfo.status === 'completed'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-yellow-100 text-yellow-800'
                                    }`}>
                                    {batchInfo.status}
                                </span>
                            </div>
                        </div>
                    </div>
                )}

                {/* Main Content Grid - Redesigned Layout */}
                <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    {/* PDF Preview - Left Side */}
                    <div className="xl:col-span-5">
                        <div className="bg-white shadow rounded-lg sticky top-6">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                        <Eye className="h-5 w-5 mr-2 text-orange-600" />
                                        PDF Preview
                                    </h3>
                                    <div className="flex items-center space-x-2">
                                        <button
                                            onClick={() => window.open(pdfDataUrl, '_blank')}
                                            className="text-blue-600 hover:text-blue-800 font-medium text-sm"
                                            disabled={!pdfDataUrl}
                                        >
                                            Open Full PDF
                                        </button>
                                        <button
                                            onClick={() => setIsPreviewExpanded(!isPreviewExpanded)}
                                            className="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md p-1"
                                            title={isPreviewExpanded ? "Minimize" : "Expand"}
                                        >
                                            {isPreviewExpanded ? (
                                                <Minimize className="h-4 w-4" />
                                            ) : (
                                                <Maximize className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                </div>
                                {selectedReport && (
                                    <p className="text-sm text-gray-500 mt-1">{selectedReport.fileName}</p>
                                )}
                            </div>
                            <div className="p-4">
                                {selectedReport ? (
                                    pdfLoading ? (
                                        <div className="flex items-center justify-center h-96">
                                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                            <span className="ml-3 text-gray-600">Loading PDF...</span>
                                        </div>
                                    ) : pdfError ? (
                                        <div className="h-96 flex items-center justify-center bg-gray-50 rounded-md border border-gray-200">
                                            <div className="text-center">
                                                <FileText className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                                                <p className="text-sm text-red-600">{pdfError}</p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className={`${isPreviewExpanded ? 'h-[800px]' : 'h-[700px]'} transition-all duration-300`}>
                                            <iframe
                                                src={pdfDataUrl}
                                                className="w-full h-full rounded-md border border-gray-200 shadow-sm"
                                                title="PDF Preview"
                                            />
                                        </div>
                                    )
                                ) : (
                                    <div className="h-96 flex items-center justify-center bg-gray-50 rounded-md border border-gray-200">
                                        <div className="text-center">
                                            <FileText className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                                            <p className="text-sm text-gray-500">Select a report to preview</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Main Content Area - Middle */}
                    <div className="xl:col-span-5">
                        {selectedReport ? (
                            <div className="space-y-6">
                                {/* Patient Information */}
                                <div className="bg-white shadow rounded-lg">
                                    <div className="px-6 py-4 border-b border-gray-200">
                                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                            <User className="h-5 w-5 mr-2 text-blue-600" />
                                            Patient Information
                                        </h3>
                                    </div>
                                    <div className="p-6 grid grid-cols-2 gap-4">
                                        {Object.entries(selectedReport.patientInfo).map(([key, value]) => (
                                            <div key={key}>
                                                <label className="block text-sm font-medium text-gray-700 mb-1 capitalize">
                                                    {key.replace(/([A-Z])/g, ' $1').trim()}
                                                </label>
                                                <input
                                                    type="text"
                                                    value={value}
                                                    onChange={(e) => updatePatientInfo(key as keyof PatientInfo, e.target.value)}
                                                    className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                                    placeholder={`Enter ${key.replace(/([A-Z])/g, ' $1').trim().toLowerCase()}`}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Lab Information */}
                                <div className="bg-white shadow rounded-lg">
                                    <div className="px-6 py-4 border-b border-gray-200">
                                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                            <FileText className="h-5 w-5 mr-2 text-green-600" />
                                            Laboratory Information
                                        </h3>
                                    </div>
                                    <div className="p-6 grid grid-cols-2 gap-4">
                                        {Object.entries(selectedReport.labInfo).map(([key, value]) => (
                                            <div key={key}>
                                                <label className="block text-sm font-medium text-gray-700 mb-1 capitalize">
                                                    {key.replace(/([A-Z])/g, ' $1').trim()}
                                                </label>
                                                <input
                                                    type="text"
                                                    value={value}
                                                    onChange={(e) => updateLabInfo(key as keyof LabInfo, e.target.value)}
                                                    className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                                    placeholder={`Enter ${key.replace(/([A-Z])/g, ' $1').trim().toLowerCase()}`}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Test Results by Category */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xl font-bold text-gray-900">Test Results</h3>
                                        <button
                                            onClick={addNewCategory}
                                            className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Category
                                        </button>
                                    </div>

                                    {Object.entries(groupedTestResults).map(([category, tests]) => (
                                        <div key={category} className="bg-white shadow rounded-lg">
                                            <div className="px-6 py-4 border-b border-gray-200">
                                                <div className="flex items-center justify-between">
                                                    <h4 className="text-lg font-medium text-gray-900 uppercase tracking-wide">
                                                        {category}
                                                    </h4>
                                                    <button
                                                        onClick={() => addTestResult(category)}
                                                        className="inline-flex items-center px-2 py-1 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                                                    >
                                                        <Plus className="h-3 w-3 mr-1" />
                                                        Add Test
                                                    </button>
                                                </div>
                                            </div>
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-gray-200">
                                                    <thead className="bg-gray-50">
                                                        <tr>
                                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Test Name</th>
                                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference Range</th>
                                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flag</th>
                                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="bg-white divide-y divide-gray-200">
                                                        {tests.map((test) => (
                                                            <tr key={test.id} className="hover:bg-gray-50">
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        type="text"
                                                                        value={test.testName}
                                                                        onChange={(e) => updateTestResult(test.id, 'testName', e.target.value)}
                                                                        className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                                                        placeholder="Test name"
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        type="text"
                                                                        value={test.result}
                                                                        onChange={(e) => updateTestResult(test.id, 'result', e.target.value)}
                                                                        className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                                                        placeholder="Result"
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        type="text"
                                                                        value={test.unit}
                                                                        onChange={(e) => updateTestResult(test.id, 'unit', e.target.value)}
                                                                        className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                                                        placeholder="Unit"
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <input
                                                                        type="text"
                                                                        value={test.referenceRange}
                                                                        onChange={(e) => updateTestResult(test.id, 'referenceRange', e.target.value)}
                                                                        className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                                                        placeholder="Reference range"
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    {renderFlagDropdown(test)}
                                                                </td>
                                                                <td className="px-4 py-3">
                                                                    <button
                                                                        onClick={() => removeTestResult(test.id)}
                                                                        className="text-red-600 hover:text-red-800"
                                                                        title="Remove test"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ))}

                                    {Object.keys(groupedTestResults).length === 0 && (
                                        <div className="bg-white shadow rounded-lg p-8 text-center">
                                            <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No test results found</p>
                                            <button
                                                onClick={addNewCategory}
                                                className="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                                            >
                                                <Plus className="h-4 w-4 mr-2" />
                                                Add First Category
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="bg-white shadow rounded-lg p-12 text-center">
                                <FileText className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 mb-2">Select a Report</h3>
                                <p className="text-gray-500">Choose a completed report from the list to start verification</p>
                            </div>
                        )}
                    </div>

                    {/* Reports List - Right Side */}
                    <div className="xl:col-span-2">
                        <div className="bg-white shadow rounded-lg">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h3 className="text-lg font-medium text-gray-900">Reports ({reports.length})</h3>
                            </div>
                            <div className="p-4 space-y-3 max-h-[700px] overflow-y-auto">
                                {reports.map((report) => (
                                    <div
                                        key={report.id}
                                        onClick={() => {
                                            if (report.status === 'completed') {
                                                setSelectedReport(report)
                                                fetchPdfData(report.id)
                                            }
                                        }}
                                        className={`p-3 rounded-lg border cursor-pointer transition-colors ${selectedReport?.id === report.id
                                            ? 'border-blue-300 bg-blue-50'
                                            : report.status === 'completed'
                                                ? 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                                : 'border-gray-200 bg-gray-50 cursor-not-allowed'
                                            }`}
                                    >
                                        <div className="flex items-center space-x-2">
                                            <FileText className="h-4 w-4 text-gray-400 flex-shrink-0" />
                                            <span className="text-sm font-medium text-gray-900 truncate">
                                                {report.fileName}
                                            </span>
                                        </div>
                                        <div className="mt-2 flex items-center justify-between">
                                            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${report.status === 'completed'
                                                ? 'bg-green-100 text-green-800'
                                                : report.status === 'processing'
                                                    ? 'bg-yellow-100 text-yellow-800'
                                                    : 'bg-gray-100 text-gray-800'
                                                }`}>
                                                {report.status}
                                            </span>
                                            {report.status === 'processing' && (
                                                <span className="text-xs text-gray-500">{report.processingProgress}%</span>
                                            )}
                                        </div>
                                        {report.status === 'processing' && (
                                            <div className="mt-2 w-full bg-gray-200 rounded-full h-1">
                                                <div
                                                    className="bg-yellow-600 h-1 rounded-full transition-all duration-300"
                                                    style={{ width: `${report.processingProgress}%` }}
                                                ></div>
                                            </div>
                                        )}
                                        {report.uploader && (
                                            <div className="mt-2 text-xs text-gray-500">
                                                Uploaded by: {report.uploader.name}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Add Category Modal */}
                {showCategoryModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                        <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div
                                className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                aria-hidden="true"
                                onClick={() => setShowCategoryModal(false)}
                            ></div>
                            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true"></span>
                            <div className="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                                    <div className="sm:flex sm:items-start">
                                        <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <Plus className="h-6 w-6 text-blue-600" aria-hidden="true" />
                                        </div>
                                    <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 className="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                Add New Category
                                            </h3>
                                            <div className="mt-2">
                                                <p className="text-sm text-gray-500">
                                                Enter a name for the new test results category.
                                                </p>
                                            </div>
                                        <div className="mt-4">
                                            <input
                                                type="text"
                                                value={newCategoryName}
                                                onChange={(e) => setNewCategoryName(e.target.value)}
                                                placeholder="Category name (e.g., BIOCHEMISTRY)"
                                                className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                onKeyPress={(e) => {
                                                    if (e.key === 'Enter') {
                                                        handleCategorySubmit()
                                                    }
                                                }}
                                            />
                                        </div>
                                        </div>
                                    </div>
                                <div className="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                    <button
                                        type="button"
                                        onClick={handleCategorySubmit}
                                        disabled={!newCategoryName.trim()}
                                        className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Add Category
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowCategoryModal(false)}
                                        className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    )
}