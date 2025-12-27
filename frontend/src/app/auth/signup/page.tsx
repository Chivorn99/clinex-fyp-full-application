'use client'
import { useState } from 'react'
import Link from 'next/link'
import { Eye, EyeOff, Mail, Lock, User, Building2, Check, X } from 'lucide-react'
import { useRouter } from 'next/navigation'
import { apiClient } from '@/lib/api'

export default function SignUpPage() {
  const router = useRouter()
  const [formData, setFormData] = useState({
    fullName: '',
    clinicName: '',
    email: '',
    password: '',
  })
  const [showPassword, setShowPassword] = useState(false)
  const [agreedToTerms, setAgreedToTerms] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  // Password strength validation
  const passwordStrength = {
    minLength: formData.password.length >= 8,
    hasUppercase: /[A-Z]/.test(formData.password),
    hasLowercase: /[a-z]/.test(formData.password),
    hasNumber: /\d/.test(formData.password),
    hasSpecialChar: /[!@#$%^&*(),.?":{}|<>]/.test(formData.password),
  }

  const isPasswordStrong = Object.values(passwordStrength).every(Boolean)

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  const handleSignUp = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!agreedToTerms || !isPasswordStrong) return
    
    setIsLoading(true)
    setError('')
    setSuccess('')

    try {
      const response = await apiClient.post('/register', {
        name: formData.fullName,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.password,
      })

      setSuccess('Account created successfully! Redirecting to login...')
      
      setTimeout(() => {
        router.push('/auth/login')
      }, 2000)

    } catch (err: any) {
      setError(err.message || 'Registration failed. Please try again.')
    } finally {
      setIsLoading(false)
    }
  }

  const isFormValid = formData.fullName && formData.email && isPasswordStrong && agreedToTerms

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
      <div className="max-w-md w-full space-y-8">
        {/* Logo and Header */}
        <div className="text-center">
          <div className="mx-auto h-16 w-16 bg-green-600 rounded-xl flex items-center justify-center mb-6">
            <span className="text-white text-2xl font-bold">C</span>
          </div>
          <h2 className="text-3xl font-bold text-gray-900 mb-2">
            Create your Clinex account
          </h2>
          <p className="text-gray-600">
            Set up your clinic management system in minutes
          </p>
        </div>

        {/* Sign Up Form */}
        <div className="bg-white py-8 px-6 shadow-lg rounded-xl border border-gray-100">
          <form className="space-y-6" onSubmit={handleSignUp}>
            {/* Error and Success Messages */}
            {error && (
              <div className="bg-red-50 border border-red-200 rounded-md p-3">
                <p className="text-sm text-red-600">{error}</p>
              </div>
            )}

            {success && (
              <div className="bg-green-50 border border-green-200 rounded-md p-3">
                <p className="text-sm text-green-600">{success}</p>
              </div>
            )}

            {/* Full Name Field */}
            <div>
              <label htmlFor="fullName" className="block text-sm font-medium text-gray-700 mb-2">
                Full Name
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <User className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  id="fullName"
                  name="fullName"
                  type="text"
                  required
                  value={formData.fullName}
                  onChange={handleInputChange}
                  className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                  placeholder="Enter your full name"
                />
              </div>
            </div>

            {/* Clinic Name Field */}
            <div>
              <label htmlFor="clinicName" className="block text-sm font-medium text-gray-700 mb-2">
                Clinic Name
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Building2 className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  id="clinicName"
                  name="clinicName"
                  type="text"
                  required
                  value={formData.clinicName}
                  onChange={handleInputChange}
                  className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                  placeholder="Enter your clinic name"
                />
              </div>
            </div>

            {/* Work Email Field */}
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                Work Email
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  value={formData.email}
                  onChange={handleInputChange}
                  className="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                  placeholder="Enter your work email"
                />
              </div>
            </div>

            {/* Password Field */}
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                Password
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Lock className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  required
                  value={formData.password}
                  onChange={handleInputChange}
                  className="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200"
                  placeholder="Create a strong password"
                />
                <button
                  type="button"
                  className="absolute inset-y-0 right-0 pr-3 flex items-center"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? (
                    <EyeOff className="h-5 w-5 text-gray-400 hover:text-gray-600" />
                  ) : (
                    <Eye className="h-5 w-5 text-gray-400 hover:text-gray-600" />
                  )}
                </button>
              </div>

              {/* Password Strength Indicators */}
              {formData.password && (
                <div className="mt-3 space-y-1">
                  <div className="text-xs text-gray-600 mb-2">Password requirements:</div>
                  {Object.entries({
                    'At least 8 characters': passwordStrength.minLength,
                    'One uppercase letter': passwordStrength.hasUppercase,
                    'One lowercase letter': passwordStrength.hasLowercase,
                    'One number': passwordStrength.hasNumber,
                    'One special character': passwordStrength.hasSpecialChar,
                  }).map(([requirement, met]) => (
                    <div key={requirement} className="flex items-center text-xs">
                      {met ? (
                        <Check className="h-3 w-3 text-green-500 mr-2" />
                      ) : (
                        <X className="h-3 w-3 text-gray-400 mr-2" />
                      )}
                      <span className={met ? 'text-green-600' : 'text-gray-500'}>
                        {requirement}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Terms Agreement */}
            <div className="flex items-start">
              <input
                id="agree-terms"
                name="agree-terms"
                type="checkbox"
                checked={agreedToTerms}
                onChange={(e) => setAgreedToTerms(e.target.checked)}
                className="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-1"
              />
              <div className="ml-3 text-sm">
                <label htmlFor="agree-terms" className="text-gray-700">
                  I agree to the{' '}
                  <Link href="/terms" className="text-green-600 hover:text-green-500 font-medium">
                    Terms of Service
                  </Link>{' '}
                  and{' '}
                  <Link href="/privacy" className="text-green-600 hover:text-green-500 font-medium">
                    Privacy Policy
                  </Link>
                </label>
              </div>
            </div>

            {/* Create Account Button */}
            <button
              type="submit"
              disabled={!isFormValid || isLoading}
              className="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition duration-200"
            >
              {isLoading ? (
                <div className="flex items-center">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Creating account...
                </div>
              ) : (
                'Create Account'
              )}
            </button>
          </form>
        </div>

        {/* Sign In Link */}
        <div className="text-center">
          <p className="text-sm text-gray-600">
            Already have an account?{' '}
            <Link
              href="/auth/login"
              className="font-medium text-green-600 hover:text-green-500"
            >
              Sign in here
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}