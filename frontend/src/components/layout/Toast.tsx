'use client'
import { useEffect, useState } from 'react'
import { X, CheckCircle, AlertTriangle, AlertCircle, Info } from 'lucide-react'
import { useToast, ToastType } from '@/contexts/ToastContext'

const TOAST_CONFIG: Record<ToastType, {
    icon: typeof CheckCircle
    bg: string
    border: string
    text: string
    progress: string
}> = {
    success: {
        icon: CheckCircle,
        bg: 'bg-green-50',
        border: 'border-green-200',
        text: 'text-green-800',
        progress: 'bg-green-500',
    },
    error: {
        icon: AlertCircle,
        bg: 'bg-red-50',
        border: 'border-red-200',
        text: 'text-red-800',
        progress: 'bg-red-500',
    },
    warning: {
        icon: AlertTriangle,
        bg: 'bg-amber-50',
        border: 'border-amber-200',
        text: 'text-amber-800',
        progress: 'bg-amber-500',
    },
    info: {
        icon: Info,
        bg: 'bg-blue-50',
        border: 'border-blue-200',
        text: 'text-blue-800',
        progress: 'bg-blue-500',
    },
}

function ToastItem({ id, message, type, duration = 5000 }: {
    id: string
    message: string
    type: ToastType
    duration?: number
}) {
    const { removeToast } = useToast()
    const [isVisible, setIsVisible] = useState(false)
    const [isLeaving, setIsLeaving] = useState(false)
    const config = TOAST_CONFIG[type]
    const Icon = config.icon

    useEffect(() => {
        // Trigger enter animation
        requestAnimationFrame(() => setIsVisible(true))
    }, [])

    const handleDismiss = () => {
        setIsLeaving(true)
        setTimeout(() => removeToast(id), 300)
    }

    return (
        <div
            className={`
                relative overflow-hidden rounded-lg border shadow-lg
                ${config.bg} ${config.border}
                transition-all duration-300 ease-out
                ${isVisible && !isLeaving
                    ? 'translate-x-0 opacity-100'
                    : 'translate-x-full opacity-0'
                }
                max-w-sm w-full pointer-events-auto
            `}
        >
            <div className="flex items-start gap-3 p-4">
                <Icon className={`h-5 w-5 shrink-0 mt-0.5 ${config.text}`} />
                <p className={`text-sm font-medium flex-1 ${config.text}`}>
                    {message}
                </p>
                <button
                    onClick={handleDismiss}
                    className={`shrink-0 ${config.text} opacity-60 hover:opacity-100 transition-opacity`}
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            {/* Auto-dismiss progress bar */}
            {duration > 0 && (
                <div className="h-1 w-full bg-black/5">
                    <div
                        className={`h-full ${config.progress} opacity-40`}
                        style={{
                            animation: `toast-progress ${duration}ms linear forwards`,
                        }}
                    />
                </div>
            )}
        </div>
    )
}

export default function ToastContainer() {
    const { toasts } = useToast()

    if (toasts.length === 0) return null

    return (
        <>
            {/* CSS animation for progress bar */}
            <style jsx global>{`
                @keyframes toast-progress {
                    from { width: 100%; }
                    to { width: 0%; }
                }
            `}</style>
            <div className="fixed bottom-4 right-4 z-9999 flex flex-col gap-3 pointer-events-none">
                {toasts.map(toast => (
                    <ToastItem
                        key={toast.id}
                        id={toast.id}
                        message={toast.message}
                        type={toast.type}
                        duration={toast.duration}
                    />
                ))}
            </div>
        </>
    )
}
