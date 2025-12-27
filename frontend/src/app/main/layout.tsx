import ProtectedRoute from '@/components/auth/ProtectedRoute'

export default function MainLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute>
      {children}
    </ProtectedRoute>
  )
}