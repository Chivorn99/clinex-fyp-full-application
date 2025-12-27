'use client'
import { useEffect } from 'react'
import { useRouter } from 'next/navigation'

export default function SplashPage() {
  const router = useRouter()

  useEffect(() => {
    const timer = setTimeout(() => {
      router.push('/auth/login')
    }, 3000)

    return () => clearTimeout(timer)
  }, [router])

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50 flex items-center justify-center">
      <div className="text-center">
        {/* Animated Logo */}
        <div className="relative mb-8">
          <div className="absolute inset-0 w-32 h-32 border-4 border-blue-200 rounded-full animate-spin"></div>
          <div className="absolute inset-2 w-28 h-28 border-4 border-green-200 rounded-full animate-reverse-spin"></div>
          <div className="relative w-32 h-32 bg-gradient-to-br from-blue-600 to-green-600 rounded-full flex items-center justify-center animate-pulse shadow-2xl">
            <span className="text-4xl font-bold text-white animate-bounce">C</span>
          </div>

          {/* Floating particles effect */}
          <div className="absolute -top-4 -left-4 w-2 h-2 bg-blue-400 rounded-full animate-ping"></div>
          <div className="absolute -top-2 -right-6 w-1 h-1 bg-green-400 rounded-full animate-ping animation-delay-300"></div>
          <div className="absolute -bottom-4 -right-4 w-2 h-2 bg-blue-500 rounded-full animate-ping animation-delay-500"></div>
          <div className="absolute -bottom-2 -left-6 w-1 h-1 bg-green-500 rounded-full animate-ping animation-delay-700"></div>
        </div>

        {/* Animated Title */}
        <div className="mb-4">
          <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-green-600 bg-clip-text text-transparent animate-fade-in">
            Clinex
          </h1>
          <p className="text-lg text-gray-600 mt-2 animate-fade-in-delay">
            Clinical Management System
          </p>
        </div>

        <div className="w-64 mx-auto mb-6">
          <div className="w-full bg-gray-200 rounded-full h-1">
            <div className="bg-gradient-to-r from-blue-600 to-green-600 h-1 rounded-full animate-loading-bar"></div>
          </div>
        </div>

        <p className="text-sm text-gray-500 animate-pulse">
          Loading your clinic dashboard...
        </p>

        <div className="fixed inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-64 h-64 bg-blue-100 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-float"></div>
          <div className="absolute top-3/4 right-1/4 w-64 h-64 bg-green-100 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-float-delay"></div>
          <div className="absolute bottom-1/4 left-1/3 w-64 h-64 bg-purple-100 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-float-slow"></div>
        </div>
      </div>

      <style jsx>{`
        @keyframes reverse-spin {
          from {
            transform: rotate(360deg);
          }
          to {
            transform: rotate(0deg);
          }
        }
        
        @keyframes fade-in {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        @keyframes loading-bar {
          from {
            width: 0%;
          }
          to {
            width: 100%;
          }
        }
        
        @keyframes float {
          0%, 100% {
            transform: translateY(0px);
          }
          50% {
            transform: translateY(-20px);
          }
        }
        
        .animate-reverse-spin {
          animation: reverse-spin 3s linear infinite;
        }
        
        .animate-fade-in {
          animation: fade-in 1s ease-out;
        }
        
        .animate-fade-in-delay {
          animation: fade-in 1s ease-out 0.5s both;
        }
        
        .animate-loading-bar {
          animation: loading-bar 3s ease-out;
        }
        
        .animate-float {
          animation: float 6s ease-in-out infinite;
        }
        
        .animate-float-delay {
          animation: float 6s ease-in-out infinite 2s;
        }
        
        .animate-float-slow {
          animation: float 8s ease-in-out infinite 1s;
        }
        
        .animation-delay-300 {
          animation-delay: 0.3s;
        }
        
        .animation-delay-500 {
          animation-delay: 0.5s;
        }
        
        .animation-delay-700 {
          animation-delay: 0.7s;
        }
      `}</style>
    </div>
  )
}