'use client'
import { useAuth } from '@/contexts/AuthContext'
import Navbar from './Navbar'

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const { user, logout, isLoading } = useAuth()

  // Fallback mock user in case auth fails
  const mockUser = {
    id: 'mock_001',
    name: 'Dr. Sarah Johnson',
    email: 'sarah@smithclinic.com',
    role: 'doctor'
  }

  // Use auth user if available, otherwise fallback to mock
  const currentUser = user || mockUser

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        {children}
      </main>
    </div>
  )
}