'use client'
import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Menu, X, Bell, User, LogOut, Settings } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

export default function Navbar() {
    const [isMenuOpen, setIsMenuOpen] = useState(false)
    const [isProfileOpen, setIsProfileOpen] = useState(false)
    const [isLoggingOut, setIsLoggingOut] = useState(false)
    const { user, logout } = useAuth()
    const router = useRouter()

    const handleLogout = async () => {
        setIsLoggingOut(true)

        try {
            await apiClient.post('/logout', {})
        } catch (error) {
            console.error('Backend logout failed:', error)
        } finally {
            setTimeout(() => {
                logout()
                setIsLoggingOut(false)
            }, 500)
        }
    }

    return (
        <nav className="bg-white shadow-sm border-b border-gray-200">
            {isLoggingOut && (
                <div className="fixed inset-0 bg-opacity-50 z-50 flex items-center justify-center transition-opacity duration-300">
                    <div className="bg-white rounded-lg p-6 flex items-center space-x-3">
                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        <span className="text-gray-700 font-medium">Signing out...</span>
                    </div>
                </div>
            )}

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between h-16">
                    {/* Logo and Main Navigation */}
                    <div className="flex">
                        {/* Logo */}
                        <div className="flex-shrink-0 flex items-center">
                            <Link href="/main/homepage" className="flex items-center">
                                <div className="h-8 w-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                                    <span className="text-white font-bold text-lg">C</span>
                                </div>
                                <span className="text-xl font-bold text-gray-900">Clinex</span>
                            </Link>
                        </div>

                        {/* Desktop Navigation */}
                        <div className="hidden md:ml-8 md:flex md:space-x-8">
                            <Link
                                href="/main/homepage"
                                className="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            >
                                Dashboard
                            </Link>
                            <Link
                                href="/main/patient"
                                className="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            >
                                Patients
                            </Link>
                            <Link
                                href="/main/reports"
                                className="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            >
                                Reports
                            </Link>
                            <Link
                                href="/main/verification/monitoring"
                                className="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            >
                                Batch
                            </Link>
                            <Link
                                href="/main/analytics"
                                className="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            >
                                Information
                            </Link>
                            <Link
                                href="/main/upload"
                                className="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            >
                                Upload
                            </Link>
                        </div>
                    </div>

                    {/* Right side - Notifications and Profile */}
                    <div className="flex items-center">

                        {/* Profile Dropdown */}
                        <div className="ml-3 relative">
                            <button
                                onClick={() => setIsProfileOpen(!isProfileOpen)}
                                className="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 hover:ring-2 hover:ring-blue-300"
                            >
                                <div className="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                    <User className="h-5 w-5 text-gray-600" />
                                </div>
                            </button>

                            {/* Profile Dropdown Menu */}
                            {isProfileOpen && (
                                <div className="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 transform transition-all duration-200 ease-out scale-100 opacity-100">
                                    <div className="py-1">
                                        {user && (
                                            <div className="px-4 py-2 border-b border-gray-100">
                                                <p className="text-sm font-medium text-gray-900">{user.name}</p>
                                                <p className="text-sm text-gray-500">{user.email}</p>
                                            </div>
                                        )}
                                        <Link
                                            href="/main/profile"
                                            className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                        >
                                            <User className="h-4 w-4 mr-3" />
                                            Profile
                                        </Link>
                                        {/* <Link
                                            href="/settings"
                                            className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                        >
                                            <Settings className="h-4 w-4 mr-3" />
                                            Settings
                                        </Link> */}
                                        <button
                                            onClick={handleLogout}
                                            disabled={isLoggingOut}
                                            className="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed group"
                                        >
                                            {isLoggingOut ? (
                                                <>
                                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-red-600 mr-3"></div>
                                                    Signing out...
                                                </>
                                            ) : (
                                                <>
                                                    <LogOut className="h-4 w-4 mr-3 group-hover:text-red-700 transition-colors duration-150" />
                                                    Sign out
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Mobile menu button */}
                        <div className="ml-2 md:hidden">
                            <button
                                onClick={() => setIsMenuOpen(!isMenuOpen)}
                                className="bg-white inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 transition-all duration-200"
                            >
                                <div className="relative">
                                    <Menu className={`h-6 w-6 transition-all duration-300 ${isMenuOpen ? 'opacity-0 rotate-90' : 'opacity-100 rotate-0'}`} />
                                    <X className={`h-6 w-6 absolute inset-0 transition-all duration-300 ${isMenuOpen ? 'opacity-100 rotate-0' : 'opacity-0 -rotate-90'}`} />
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Mobile Navigation */}
            <div className={`md:hidden transition-all duration-300 ease-in-out ${isMenuOpen ? 'max-h-64 opacity-100' : 'max-h-0 opacity-0 overflow-hidden'}`}>
                <div className="pt-2 pb-3 space-y-1 sm:px-3">
                    <Link
                        href="/main/homepage"
                        className="text-gray-500 hover:text-gray-700 block px-3 py-2 text-base font-medium transition-colors duration-200 hover:bg-gray-50 rounded-md"
                    >
                        Dashboard
                    </Link>
                    <Link
                        href="/patients"
                        className="text-gray-500 hover:text-gray-700 block px-3 py-2 text-base font-medium transition-colors duration-200 hover:bg-gray-50 rounded-md"
                    >
                        Patients
                    </Link>
                    <Link
                        href="/main/reports"
                        className="text-gray-500 hover:text-gray-700 block px-3 py-2 text-base font-medium transition-colors duration-200 hover:bg-gray-50 rounded-md"
                    >
                        Reports
                    </Link>
                    <Link
                        href="/main/upload"
                        className="text-gray-500 hover:text-gray-700 block px-3 py-2 text-base font-medium transition-colors duration-200 hover:bg-gray-50 rounded-md"
                    >
                        Upload
                    </Link>
                </div>
            </div>
        </nav>
    )
}