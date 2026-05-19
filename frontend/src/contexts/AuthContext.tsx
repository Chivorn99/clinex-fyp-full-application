'use client'
import React, { createContext, useContext, useEffect, useState, useCallback } from 'react'
import { useRouter } from 'next/navigation'

export interface User {
  id: string
  name: string
  email: string
  role: string
  permissions?: Record<string, boolean>
  phone_number?: string
  specialization?: string
  created_at?: string
  profile_pic?: string
  profile_picture_url?: string
}

interface AuthContextType {
  user: User | null
  login: (token: string, userData: User) => void
  updateUser: (updates: Partial<User>) => void
  logout: () => void
  isLoading: boolean
  hasPermission: (permission: string) => boolean
  hasAnyPermission: (permissions: string[]) => boolean
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const router = useRouter()

  const logout = useCallback(() => {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user')
    
    // Clear both cookies
    document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT'
    document.cookie = 'user_role=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT'
    
    setUser(null)
    router.push('/auth/login')
  }, [router])

  const checkAuth = useCallback(() => {
    try {
      const token = localStorage.getItem('auth_token')
      const userData = localStorage.getItem('user')
      
      if (token && userData) {
        const parsedUser = JSON.parse(userData)
        setUser(parsedUser)
        document.cookie = `auth_token=${token}; path=/; max-age=86400`
        document.cookie = `user_role=${parsedUser.role}; path=/; max-age=86400`
      }
    } catch (error) {
      console.error('Auth check failed:', error)
      logout()
    } finally {
      setIsLoading(false)
    }
  }, [logout])

  useEffect(() => {
    checkAuth()
  }, [checkAuth])

  const login = (token: string, userData: User) => {
    localStorage.setItem('auth_token', token)
    localStorage.setItem('user', JSON.stringify(userData))

    document.cookie = `auth_token=${token}; path=/; max-age=86400`
    document.cookie = `user_role=${userData.role}; path=/; max-age=86400`
    
    setUser(userData)
  }

  const updateUser = useCallback((updates: Partial<User>) => {
    setUser((prev) => {
      if (!prev) return prev

      const next = { ...prev, ...updates }
      localStorage.setItem('user', JSON.stringify(next))
      document.cookie = `user_role=${next.role}; path=/; max-age=86400`
      return next
    })
  }, [])

  /**
   * Check if the current user has a specific permission.
   * Admin role automatically has ALL permissions.
   */
  const hasPermission = useCallback((permission: string): boolean => {
    if (!user) return false
    if (user.role === 'admin') return true
    return !!user.permissions?.[permission]
  }, [user])

  /**
   * Check if the current user has ANY of the given permissions.
   */
  const hasAnyPermission = useCallback((permissions: string[]): boolean => {
    return permissions.some(p => hasPermission(p))
  }, [hasPermission])

  return (
    <AuthContext.Provider value={{ user, login, updateUser, logout, isLoading, hasPermission, hasAnyPermission }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}