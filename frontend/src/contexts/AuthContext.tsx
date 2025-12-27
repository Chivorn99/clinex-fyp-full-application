'use client'
import React, { createContext, useContext, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'

interface User {
  id: string
  name: string
  email: string
  role: string
  phone_number?: string
  specialization?: string
  created_at?: string
  profile_pic?: string
  profile_picture_url?: string
}

interface AuthContextType {
  user: User | null
  login: (token: string, userData: User) => void
  logout: () => void
  isLoading: boolean
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const router = useRouter()

  useEffect(() => {
    checkAuth()
  }, [])

  const checkAuth = () => {
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
  }

  const login = (token: string, userData: User) => {
    localStorage.setItem('auth_token', token)
    localStorage.setItem('user', JSON.stringify(userData))

    document.cookie = `auth_token=${token}; path=/; max-age=86400`
    document.cookie = `user_role=${userData.role}; path=/; max-age=86400`
    
    setUser(userData)
  }

  const logout = () => {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user')
    
    // Clear both cookies
    document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT'
    document.cookie = 'user_role=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT'
    
    setUser(null)
    router.push('/auth/login')
  }

  return (
    <AuthContext.Provider value={{ user, login, logout, isLoading }}>
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