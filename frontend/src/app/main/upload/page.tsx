'use client'
import { useState, useRef } from 'react'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { Upload, FileText, Trash2, Eye, AlertCircle, CheckCircle, X } from 'lucide-react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

interface UploadedFile {
    id: string
    name: string
    size: number
    file: File
    status: 'ready' | 'uploading' | 'completed' | 'error'
    progress: number
}

interface BatchResponse {
    id: number
    name: string
    status: string
    total_reports: number
    processed_reports: number
    failed_reports: number
    created_at: string
    uploaded_files?: any[]
}

export default function UploadPage() {
    const { user } = useAuth()
    const [uploadedFiles, setUploadedFiles] = useState<UploadedFile[]>([])
    const [selectedFilePreview, setSelectedFilePreview] = useState<string | null>(null)
    const [previewUrl, setPreviewUrl] = useState<string | null>(null)
    const [isUploading, setIsUploading] = useState(false)
    const [isDragOver, setIsDragOver] = useState(false)
    const [currentBatch, setCurrentBatch] = useState<BatchResponse | null>(null)
    const [error, setError] = useState<string>('')
    const [autoProcess, setAutoProcess] = useState(true)
    const fileInputRef = useRef<HTMLInputElement>(null)
    const router = useRouter()

    const handleFileSelect = (files: FileList | null) => {
        if (!files) return

        setError('')
        const pdfFiles = Array.from(files).filter(file => file.type === 'application/pdf')

        if (pdfFiles.length === 0) {
            setError('Please select only PDF files')
            return
        }

        if (pdfFiles.length > 20) {
            setError('Maximum 20 files allowed per batch')
            return
        }

        // Add files to UI
        const newFiles: UploadedFile[] = pdfFiles.map(file => ({
            id: Math.random().toString(36).substr(2, 9),
            name: file.name,
            size: file.size,
            file: file,
            status: 'ready',
            progress: 0
        }))

        setUploadedFiles(prev => [...prev, ...newFiles])
    }

    const handleBatchUpload = async () => {
        if (uploadedFiles.length === 0) {
            setError('Please select files to upload')
            return
        }

        setIsUploading(true)
        setError('')

        // Update all files to uploading status
        setUploadedFiles(prev => 
            prev.map(f => ({ ...f, status: 'uploading' as const, progress: 0 }))
        )

        try {
            // Create FormData with files array (matching backend expectation)
            const formData = new FormData()
            
            uploadedFiles.forEach((fileItem) => {
                formData.append('files[]', fileItem.file)
            })
            
            // Add auto_process flag
            formData.append('auto_process', autoProcess ? '1' : '0')

            // Simulate progress updates
            const progressInterval = setInterval(() => {
                setUploadedFiles(prev =>
                    prev.map(f => {
                        if (f.status === 'uploading') {
                            const newProgress = Math.min(f.progress + Math.random() * 15, 90)
                            return { ...f, progress: newProgress }
                        }
                        return f
                    })
                )
            }, 300)

            // Upload to backend - DON'T SET CONTENT-TYPE FOR FORMDATA
            const response = await apiClient.post('/batches', formData)

            clearInterval(progressInterval)

            console.log('Full response:', response) // Debug log

            // Handle different response structures
            let batch: BatchResponse
            if (response.success && response.data?.batch) {
                batch = response.data.batch
            } else if (response.data && response.data.id) {
                batch = response.data
            } else if (response.id) {
                batch = response
            } else {
                throw new Error('Invalid response structure')
            }

            setCurrentBatch(batch)

            // Update all files to completed
            setUploadedFiles(prev =>
                prev.map(f => ({ 
                    ...f, 
                    status: 'completed' as const, 
                    progress: 100 
                }))
            )

            // If auto-processing is enabled, redirect to monitoring
            if (autoProcess) {
                setTimeout(() => {
                    router.push(`/main/verification/monitoring`)
                }, 1500)
            } else {
                console.log('Batch uploaded successfully. Ready for manual processing.')
            }

        } catch (error: any) {
            console.error('Full error details:', {
                message: error.message,
                status: error.status,
                errors: error.errors,
                stack: error.stack
            })
            
            let errorMessage = 'Failed to upload files. Please try again.'
            
            if (error.status === 401) {
                errorMessage = 'Authentication failed. Please log in again.'
            } else if (error.status === 422) {
                errorMessage = `Validation failed: ${
                    error.errors 
                        ? Object.values(error.errors).flat().join(', ') 
                        : error.message
                }`
            } else if (error.status === 500) {
                errorMessage = 'Server error. Please try again later.'
            } else if (error.message) {
                errorMessage = error.message
            }
            
            setError(errorMessage)
            
            // Update files to error status
            setUploadedFiles(prev =>
                prev.map(f => ({ 
                    ...f, 
                    status: 'error' as const, 
                    progress: 0 
                }))
            )
        } finally {
            setIsUploading(false)
        }
    }

    const handleManualProcess = async () => {
        if (!currentBatch) return

        try {
            setIsUploading(true)
            const response = await apiClient.post(`/batches/${currentBatch.id}/process`)
            
            // Handle different response structures
            if (response.success || response.data || response.id) {
                router.push(`/main/verification/monitoring`)
            } else {
                setError('Failed to start processing')
            }
        } catch (error: any) {
            console.error('Processing error:', error)
            setError(error.message || 'Failed to start processing')
        } finally {
            setIsUploading(false)
        }
    }

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault()
        setIsDragOver(false)
        handleFileSelect(e.dataTransfer.files)
    }

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault()
        setIsDragOver(true)
    }

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault()
        setIsDragOver(false)
    }

    const removeFile = (fileId: string) => {
        setUploadedFiles(prev => prev.filter(file => file.id !== fileId))
        if (selectedFilePreview === fileId) {
            setSelectedFilePreview(null)
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl)
                setPreviewUrl(null)
            }
        }
    }

    const handleFilePreview = (fileId: string) => {
        const file = uploadedFiles.find(f => f.id === fileId)
        if (file) {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl)
            }

            const url = URL.createObjectURL(file.file)
            setPreviewUrl(url)
            setSelectedFilePreview(fileId)
        }
    }

    const resetBatch = () => {
        setCurrentBatch(null)
        setUploadedFiles([])
        setSelectedFilePreview(null)
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl)
            setPreviewUrl(null)
        }
        setError('')
    }

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes'
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB', 'GB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }

    const completedFiles = uploadedFiles.filter(file => file.status === 'completed')
    const overallProgress = uploadedFiles.length > 0
        ? uploadedFiles.reduce((acc, file) => acc + file.progress, 0) / uploadedFiles.length
        : 0

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Lab Report Batch Upload</h1>
                    <p className="mt-2 text-gray-600">
                        Upload multiple lab report PDFs for automated processing
                    </p>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-md p-4">
                        <div className="flex">
                            <AlertCircle className="h-5 w-5 text-red-400" />
                            <div className="ml-3">
                                <p className="text-sm text-red-600">{error}</p>
                            </div>
                            <button
                                onClick={() => setError('')}
                                className="ml-auto text-red-400 hover:text-red-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* Current Batch Info */}
                {currentBatch && (
                    <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-medium text-green-800">
                                    ✅ Batch Uploaded Successfully
                                </h3>
                                <p className="text-sm text-green-600">
                                    {currentBatch.name} • {currentBatch.total_reports} files
                                </p>
                                {autoProcess && (
                                    <p className="text-xs text-green-600 mt-1">
                                        Auto-processing enabled - redirecting to monitoring...
                                    </p>
                                )}
                            </div>
                            <button
                                onClick={resetBatch}
                                className="text-green-600 hover:text-green-800 text-sm font-medium"
                            >
                                Upload New Batch
                            </button>
                        </div>
                    </div>
                )}

                {/* Processing Options */}
                <div className="bg-white shadow rounded-lg p-6">
                    <div className="flex items-center space-x-4">
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                checked={autoProcess}
                                onChange={(e) => setAutoProcess(e.target.checked)}
                                disabled={isUploading || currentBatch !== null}
                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <span className="ml-2 text-sm text-gray-700">
                                Auto-process after upload
                            </span>
                        </label>
                        <div className="text-xs text-gray-500">
                            {autoProcess 
                                ? 'Files will be processed automatically after upload' 
                                : 'Manual processing required after upload'
                            }
                        </div>
                    </div>
                </div>

                {/* Upload Progress */}
                {uploadedFiles.length > 0 && (
                    <div className="bg-white shadow rounded-lg p-6">
                        <div className="flex items-center justify-between mb-2">
                            <h3 className="text-lg font-medium text-gray-900">Upload Progress</h3>
                            <span className="text-sm text-gray-500">
                                {Math.round(overallProgress)}% Complete
                            </span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-2">
                            <div
                                className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                style={{ width: `${overallProgress}%` }}
                            ></div>
                        </div>
                        <p className="mt-2 text-sm text-gray-600">
                            {completedFiles.length} of {uploadedFiles.length} files ready
                        </p>
                    </div>
                )}

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* PDF Preview Container */}
                    <div className="bg-white shadow rounded-lg">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h2 className="text-lg font-medium text-gray-900">PDF Preview</h2>
                        </div>
                        <div className="p-6">
                            {selectedFilePreview && previewUrl ? (
                                <div className="aspect-[3/4] bg-gray-100 rounded-lg border-2 border-gray-300 overflow-hidden">
                                    <iframe
                                        src={previewUrl}
                                        className="w-full h-full rounded-lg"
                                        title="PDF Preview"
                                    />
                                </div>
                            ) : (
                                <div
                                    className={`aspect-[3/4] border-2 border-dashed rounded-lg flex flex-col items-center justify-center transition-colors ${isDragOver
                                        ? 'border-blue-400 bg-blue-50'
                                        : 'border-gray-300 bg-gray-50 hover:bg-gray-100'
                                        }`}
                                    onDrop={handleDrop}
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                >
                                    <Upload className="h-12 w-12 text-gray-400 mb-4" />
                                    <p className="text-lg font-medium text-gray-900 mb-2">
                                        {uploadedFiles.length > 0 ? 'Select a file to preview' : 'Upload Lab Report PDFs'}
                                    </p>
                                    <p className="text-sm text-gray-500 text-center mb-4">
                                        {uploadedFiles.length > 0
                                            ? 'Click on any file to view it here'
                                            : 'Drag and drop PDF files here, or click to select (Max 20 files)'
                                        }
                                    </p>
                                    {uploadedFiles.length === 0 && (
                                        <button
                                            onClick={() => fileInputRef.current?.click()}
                                            disabled={isUploading}
                                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                                        >
                                            <Upload className="h-4 w-4 mr-2" />
                                            Choose Files
                                        </button>
                                    )}
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        multiple
                                        accept=".pdf"
                                        onChange={(e) => handleFileSelect(e.target.files)}
                                        className="hidden"
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Uploaded Files List */}
                    <div className="bg-white shadow rounded-lg flex flex-col">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-medium text-gray-900">
                                    Selected Files ({uploadedFiles.length}/20)
                                </h2>
                                <button
                                    onClick={() => fileInputRef.current?.click()}
                                    disabled={isUploading || uploadedFiles.length >= 20}
                                    className="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium text-sm rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <Upload className="h-4 w-4 mr-2" />
                                    Add Files
                                </button>
                            </div>
                        </div>
                        <div className="flex-1 p-6">
                            {uploadedFiles.length === 0 ? (
                                <div className="text-center py-12">
                                    <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                    <p className="text-gray-500">No files selected yet</p>
                                    <p className="text-sm text-gray-400 mt-1">
                                        Upload PDF lab reports to see them here
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-3 max-h-96 overflow-y-auto">
                                    {uploadedFiles.map((file) => (
                                        <div
                                            key={file.id}
                                            className={`p-4 border rounded-lg transition-colors ${selectedFilePreview === file.id
                                                ? 'border-blue-300 bg-blue-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                                }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center space-x-2">
                                                        <FileText className="h-5 w-5 text-gray-400 flex-shrink-0" />
                                                        <button
                                                            onClick={() => handleFilePreview(file.id)}
                                                            className="text-sm font-medium truncate text-blue-600 hover:text-blue-800 cursor-pointer"
                                                        >
                                                            {file.name}
                                                        </button>
                                                    </div>
                                                    <div className="flex items-center space-x-2 mt-1">
                                                        <span className="text-xs text-gray-500">{formatFileSize(file.size)}</span>
                                                        {file.status === 'completed' && (
                                                            <CheckCircle className="h-4 w-4 text-green-500" />
                                                        )}
                                                        {file.status === 'error' && (
                                                            <AlertCircle className="h-4 w-4 text-red-500" />
                                                        )}
                                                        {file.status === 'ready' && (
                                                            <span className="text-xs text-blue-600">Ready</span>
                                                        )}
                                                    </div>
                                                    {file.status === 'uploading' && (
                                                        <div className="mt-2">
                                                            <div className="w-full bg-gray-200 rounded-full h-1">
                                                                <div
                                                                    className="bg-blue-600 h-1 rounded-full transition-all duration-300"
                                                                    style={{ width: `${file.progress}%` }}
                                                                ></div>
                                                            </div>
                                                            <span className="text-xs text-gray-500">{Math.round(file.progress)}%</span>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex items-center space-x-2 ml-4">
                                                    <button
                                                        onClick={() => handleFilePreview(file.id)}
                                                        className="p-1 text-gray-400 hover:text-blue-600"
                                                        title="Preview"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => removeFile(file.id)}
                                                        disabled={file.status === 'uploading'}
                                                        className="p-1 text-gray-400 hover:text-red-600 disabled:opacity-50"
                                                        title="Remove"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Upload/Process Buttons */}
                        {uploadedFiles.length > 0 && (
                            <div className="px-6 py-4 border-t border-gray-200">
                                <div className="flex justify-end space-x-3">
                                    {!currentBatch ? (
                                        <button
                                            onClick={handleBatchUpload}
                                            disabled={uploadedFiles.length === 0 || isUploading}
                                            className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {isUploading ? (
                                                <>
                                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                    Uploading...
                                                </>
                                            ) : (
                                                <>
                                                    <Upload className="h-4 w-4 mr-2" />
                                                    Upload Batch ({uploadedFiles.length})
                                                </>
                                            )}
                                        </button>
                                    ) : (
                                        !autoProcess && (
                                            <button
                                                onClick={handleManualProcess}
                                                disabled={isUploading}
                                                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                                            >
                                                {isUploading ? (
                                                    <>
                                                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                        Processing...
                                                    </>
                                                ) : (
                                                    <>
                                                        <FileText className="h-4 w-4 mr-2" />
                                                        Process Documents
                                                    </>
                                                )}
                                            </button>
                                        )
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    )
}