'use client'
import { useState, useEffect, useRef } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { Mail, ArrowLeft, RefreshCw } from 'lucide-react'
import { apiClient } from '@/lib/api'

export default function VerifyOTPPage() {
  const searchParams = useSearchParams()
  const router = useRouter()
  const email = searchParams.get('email') || ''
  
  const [otp, setOtp] = useState(['', '', '', '', '', ''])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState('')
  const [timeLeft, setTimeLeft] = useState(600)
  const [canResend, setCanResend] = useState(false)
  const [isResending, setIsResending] = useState(false)
  
  const inputRefs = useRef<(HTMLInputElement | null)[]>([])

  useEffect(() => {
    const timer = setInterval(() => {
      setTimeLeft((prevTime) => {
        if (prevTime <= 1) {
          setCanResend(true)
          return 0
        }
        return prevTime - 1
      })
    }, 1000)

    return () => clearInterval(timer)
  }, [])

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
  }

  const handleOtpChange = (index: number, value: string) => {
    if (value.length > 1) return
    
    const newOtp = [...otp]
    newOtp[index] = value
    setOtp(newOtp)

    if (value && index < 5) {
      inputRefs.current[index + 1]?.focus()
    }

    if (value && index === 5 && newOtp.every(digit => digit !== '')) {
      handleSubmit(newOtp.join(''))
    }
  }

  const handleKeyDown = (index: number, e: React.KeyboardEvent) => {
    if (e.key === 'Backspace' && !otp[index] && index > 0) {
      inputRefs.current[index - 1]?.focus()
    }
  }

  const handleSubmit = async (otpCode?: string) => {
    const code = otpCode || otp.join('')
    if (code.length !== 6) {
      setError('Please enter all 6 digits')
      return
    }

    setIsLoading(true)
    setError('')

    try {
      const response = await apiClient.post('/password/otp-verify-only', {
        email,
        code,
      })

      if (response.success) {
        router.push(`/auth/new-password?email=${encodeURIComponent(email)}&code=${code}`)
      } else {
        setError(response.message || 'Invalid OTP code')
        setOtp(['', '', '', '', '', ''])
        inputRefs.current[0]?.focus()
      }
    } catch (err: any) {
      setError(err.message || 'Invalid OTP code. Please try again.')
      setOtp(['', '', '', '', '', ''])
      inputRefs.current[0]?.focus()
    } finally {
      setIsLoading(false)
    }
  }

  const handleResendOtp = async () => {
    setIsResending(true)
    setError('')

    try {
      const response = await apiClient.post('/password/otp-request', {
        email,
      })

      if (response.success) {
        setTimeLeft(600)
        setCanResend(false)
        setOtp(['', '', '', '', '', ''])
        inputRefs.current[0]?.focus()
      } else {
        setError(response.message || 'Failed to resend OTP')
      }
    } catch (err: any) {
      setError(err.message || 'Failed to resend OTP. Please try again.')
    } finally {
      setIsResending(false)
    }
  }

  if (!email) {
    router.push('/auth/reset-password')
    return null
  }

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <div className="mx-auto h-16 w-16 bg-blue-600 rounded-xl flex items-center justify-center mb-6">
            <Mail className="h-8 w-8 text-white" />
          </div>
          <h2 className="text-3xl font-bold text-gray-900 mb-2">
            Verify Your Email
          </h2>
          <p className="text-gray-600">
            Enter the 6-digit code sent to
          </p>
          <p className="font-medium text-blue-600">{email}</p>
        </div>

        <div className="bg-white py-8 px-6 shadow-lg rounded-xl border border-gray-100">
          <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); handleSubmit(); }}>
            {error && (
              <div className="bg-red-50 border border-red-200 rounded-md p-3">
                <p className="text-sm text-red-600">{error}</p>
              </div>
            )}

            {/* OTP Input Fields */}
            <div>
              <label className="block text-sm font-medium text-gray-800 mb-4 text-center">
                Verification Code
              </label>
              <div className="flex justify-center space-x-3">
                {otp.map((digit, index) => (
                  <input
                    key={index}
                    ref={(el) => { inputRefs.current[index] = el }}
                    type="text"
                    inputMode="numeric"
                    pattern="[0-9]"
                    maxLength={1}
                    value={digit}
                    onChange={(e) => handleOtpChange(index, e.target.value)}
                    onKeyDown={(e) => handleKeyDown(index, e)}
                    className="w-12 h-12 text-center text-lg font-semibold border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    disabled={isLoading}
                  />
                ))}
              </div>
            </div>

            {/* Timer */}
            <div className="text-center">
              {timeLeft > 0 ? (
                <p className="text-sm text-gray-600">
                  Code expires in <span className="font-medium text-red-600">{formatTime(timeLeft)}</span>
                </p>
              ) : (
                <p className="text-sm text-red-600">
                  Code has expired. Please request a new one.
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={isLoading || otp.some(digit => digit === '') || timeLeft === 0}
              className="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition duration-200"
            >
              {isLoading ? (
                <div className="flex items-center">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Verifying...
                </div>
              ) : (
                'Verify Code'
              )}
            </button>

            {/* Resend OTP */}
            {canResend && (
              <button
                type="button"
                onClick={handleResendOtp}
                disabled={isResending}
                className="w-full flex justify-center items-center py-2 px-4 text-sm font-medium text-blue-600 hover:text-blue-700 focus:outline-none transition duration-200"
              >
                {isResending ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
                    Resending...
                  </>
                ) : (
                  <>
                    <RefreshCw className="h-4 w-4 mr-1" />
                    Resend Code
                  </>
                )}
              </button>
            )}
          </form>
        </div>

        <div className="text-center">
          <Link
            href="/auth/reset-password"
            className="inline-flex items-center text-sm text-blue-600 hover:text-blue-500 font-medium"
          >
            <ArrowLeft className="h-4 w-4 mr-1" />
            Back to Email
          </Link>
        </div>
      </div>
    </div>
  )
}