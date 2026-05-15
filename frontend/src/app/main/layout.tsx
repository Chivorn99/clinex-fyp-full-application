'use client'
import ProtectedRoute from '@/components/auth/ProtectedRoute'
import { ToastProvider } from '@/contexts/ToastContext'

export default function MainLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute>
      <ToastProvider>
        {children}
      </ToastProvider>
    </ProtectedRoute>
  )
}
