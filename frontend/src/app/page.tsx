'use client'
import { useRouter } from 'next/navigation'
import { useEffect, useState } from 'react'

/* ─── Splash Screen (3 seconds) ──────────────────────────────────── */
function SplashScreen({ onFinish }: { onFinish: () => void }) {
  useEffect(() => {
    const timer = setTimeout(onFinish, 3000)
    return () => clearTimeout(timer)
  }, [onFinish])

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex items-center justify-center">
      <div className="text-center">
        {/* Animated Logo */}
        <div className="relative mb-8 mx-auto w-32 h-32">
          <div className="absolute inset-0 w-32 h-32 border-4 border-blue-200 dark:border-blue-800 rounded-full splash-spin" />
          <div className="absolute inset-2 w-28 h-28 border-4 border-green-200 dark:border-emerald-800 rounded-full splash-reverse-spin" />
          <div className="relative w-32 h-32 bg-gradient-to-br from-blue-600 to-green-600 rounded-full flex items-center justify-center animate-pulse shadow-2xl shadow-blue-500/25">
            <span className="text-4xl font-bold text-white animate-bounce">C</span>
          </div>
          <div className="absolute -top-4 -left-4 w-2 h-2 bg-blue-400 rounded-full animate-ping" />
          <div className="absolute -top-2 -right-6 w-1 h-1 bg-green-400 rounded-full animate-ping splash-delay-300" />
          <div className="absolute -bottom-4 -right-4 w-2 h-2 bg-blue-500 rounded-full animate-ping splash-delay-500" />
          <div className="absolute -bottom-2 -left-6 w-1 h-1 bg-green-500 rounded-full animate-ping splash-delay-700" />
        </div>

        <div className="mb-4">
          <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-green-600 bg-clip-text text-transparent splash-fade-in">
            Clinex
          </h1>
          <p className="text-lg text-gray-600 dark:text-gray-400 mt-2 splash-fade-in-delay">
            Clinical Management System
          </p>
        </div>

        <div className="w-64 mx-auto mb-6">
          <div className="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-1">
            <div className="bg-gradient-to-r from-blue-600 to-green-600 h-1 rounded-full splash-loading-bar" />
          </div>
        </div>

        <p className="text-sm text-gray-500 dark:text-gray-500 animate-pulse">
          Loading your clinic dashboard...
        </p>
      </div>

      <style jsx>{`
        @keyframes splash-spin-kf { to { transform: rotate(360deg); } }
        @keyframes splash-reverse-spin-kf { to { transform: rotate(-360deg); } }
        @keyframes splash-fade-in-kf { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes splash-loading-kf { from { width: 0%; } to { width: 100%; } }
        .splash-spin { animation: splash-spin-kf 3s linear infinite; }
        .splash-reverse-spin { animation: splash-reverse-spin-kf 3s linear infinite; }
        .splash-fade-in { animation: splash-fade-in-kf 1s ease-out; }
        .splash-fade-in-delay { animation: splash-fade-in-kf 1s ease-out 0.5s both; }
        .splash-loading-bar { animation: splash-loading-kf 3s ease-out; }
        .splash-delay-300 { animation-delay: 0.3s; }
        .splash-delay-500 { animation-delay: 0.5s; }
        .splash-delay-700 { animation-delay: 0.7s; }
      `}</style>
    </div>
  )
}

/* ─── Landing Page ───────────────────────────────────────────────── */
function LandingContent() {
  const router = useRouter()
  const [scrollY, setScrollY] = useState(0)

  useEffect(() => {
    const handleScroll = () => setScrollY(window.scrollY)
    window.addEventListener('scroll', handleScroll)
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  const features = [
    { icon: '📄', title: 'Smart PDF Upload', desc: 'Batch upload up to 20 lab report PDFs at once. Automatic duplicate detection and hash-based file tracking.', color: 'blue' },
    { icon: '🤖', title: 'AI Data Extraction', desc: 'Local LLM (Phi-3 via Ollama) with Tesseract OCR extracts patient info, lab data, and test results automatically.', color: 'purple' },
    { icon: '✅', title: 'Human Verification', desc: 'Side-by-side view of original PDF and extracted data. Lab technicians review, correct, and verify before finalizing.', color: 'emerald' },
    { icon: '👥', title: 'Patient Management', desc: 'Auto-create patient records from verified reports. Track complete lab report history per patient.', color: 'cyan' },
    { icon: '📊', title: 'Analytics Dashboard', desc: 'Operational insights on processing rates, verification progress, and report trends over configurable time ranges.', color: 'orange' },
    { icon: '🔒', title: 'Role-Based Access', desc: 'Admin and Lab Technician roles with 6 granular permissions. Sanctum token-based API authentication.', color: 'red' },
  ]

  const steps = [
    { step: '01', title: 'Upload Lab Reports', desc: 'Lab technicians upload scanned PDF reports through the web interface. The system accepts single or batch uploads with automatic duplicate detection.' },
    { step: '02', title: 'AI Processing', desc: 'Tesseract OCR extracts raw text from the PDF. Then our local Phi-3 LLM uses hospital-specific templates with few-shot prompting to structure the data.' },
    { step: '03', title: 'Human Verification', desc: 'A qualified lab technician reviews the AI-extracted data side-by-side with the original document. They can correct any errors and approve the data.' },
    { step: '04', title: 'Data Stored & Accessible', desc: 'Verified data is stored in the database. Patient records are created automatically. Reports can be exported to Excel for further analysis.' },
  ]

  const techStack = [
    { name: 'Next.js', role: 'Frontend', icon: '⚡' },
    { name: 'Laravel 12', role: 'Backend API', icon: '🔧' },
    { name: 'Phi-3 + Ollama', role: 'Local AI/LLM', icon: '🧠' },
    { name: 'Tesseract', role: 'OCR Engine', icon: '👁️' },
    { name: 'MySQL 8', role: 'Database', icon: '🗄️' },
    { name: 'Docker', role: 'Containers', icon: '🐳' },
    { name: 'Nginx', role: 'Reverse Proxy', icon: '🌐' },
    { name: 'Pest PHP', role: 'Testing', icon: '🧪' },
  ]

  return (
    <div className="min-h-screen bg-white dark:bg-[#0a0f1c] text-gray-900 dark:text-white overflow-x-hidden transition-colors duration-300">
      {/* ─── Navbar ─────────────────────────────────────────── */}
      <nav className={`fixed top-0 w-full z-50 transition-all duration-300 ${
        scrollY > 50
          ? 'bg-white/80 dark:bg-[#0a0f1c]/90 backdrop-blur-xl shadow-lg shadow-gray-200/50 dark:shadow-blue-900/10'
          : 'bg-transparent'
      }`}>
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-emerald-400 flex items-center justify-center font-bold text-lg text-white shadow-lg shadow-blue-500/25">
              C
            </div>
            <span className="text-xl font-bold tracking-tight">Clinex</span>
          </div>
          <div className="hidden md:flex items-center gap-8 text-sm text-gray-500 dark:text-gray-400">
            <a href="#features" className="hover:text-gray-900 dark:hover:text-white transition-colors">Features</a>
            <a href="#workflow" className="hover:text-gray-900 dark:hover:text-white transition-colors">How It Works</a>
            <a href="#tech" className="hover:text-gray-900 dark:hover:text-white transition-colors">Technology</a>
          </div>
          <button
            onClick={() => router.push('/auth/login')}
            className="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-emerald-500 rounded-xl text-sm font-semibold text-white hover:shadow-lg hover:shadow-blue-500/25 transition-all duration-300 hover:scale-105 cursor-pointer"
          >
            Sign In →
          </button>
        </div>
      </nav>

      {/* ─── Hero ───────────────────────────────────────────── */}
      <section className="relative min-h-screen flex items-center justify-center px-6">
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute top-1/4 -left-32 w-96 h-96 bg-blue-400/10 dark:bg-blue-600/10 rounded-full blur-3xl animate-pulse" />
          <div className="absolute bottom-1/4 -right-32 w-96 h-96 bg-emerald-400/10 dark:bg-emerald-600/10 rounded-full blur-3xl animate-pulse" style={{ animationDelay: '1s' }} />
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-purple-300/5 dark:bg-purple-600/5 rounded-full blur-3xl animate-pulse" style={{ animationDelay: '2s' }} />
          <div className="absolute inset-0 opacity-[0.04] dark:opacity-[0.03]" style={{
            backgroundImage: 'linear-gradient(rgba(128,128,128,0.2) 1px, transparent 1px), linear-gradient(90deg, rgba(128,128,128,0.2) 1px, transparent 1px)',
            backgroundSize: '60px 60px',
          }} />
        </div>

        <div className="relative max-w-5xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400 text-sm mb-8 landing-fade-in">
            <span className="w-2 h-2 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse" />
            AI-Powered Clinical Lab Report System
          </div>

          <h1 className="text-5xl sm:text-6xl lg:text-7xl font-bold leading-tight mb-6 landing-fade-in-up">
            Digitize Lab Reports
            <br />
            <span className="bg-gradient-to-r from-blue-600 via-cyan-500 to-emerald-500 bg-clip-text text-transparent">
              with AI Precision
            </span>
          </h1>

          <p className="text-lg sm:text-xl text-gray-500 dark:text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed landing-fade-in-up-delay">
            Clinex transforms paper-based lab reports into structured digital data using 
            OCR and local AI. Built for Cambodian hospitals and clinics to streamline 
            clinical workflows with human-in-the-loop verification.
          </p>

          <div className="flex flex-col sm:flex-row gap-4 justify-center landing-fade-in-up-delay-2">
            <button
              onClick={() => router.push('/auth/login')}
              className="px-8 py-4 bg-gradient-to-r from-blue-600 to-emerald-500 rounded-2xl text-lg font-semibold text-white hover:shadow-2xl hover:shadow-blue-500/25 transition-all duration-300 hover:scale-105 cursor-pointer"
            >
              Get Started →
            </button>
            <a
              href="#features"
              className="px-8 py-4 bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl text-lg font-semibold hover:bg-gray-200 dark:hover:bg-white/10 transition-all duration-300 cursor-pointer"
            >
              Learn More
            </a>
          </div>

          <div className="grid grid-cols-3 gap-6 mt-20 max-w-lg mx-auto landing-fade-in-up-delay-2">
            {[
              { value: '118', label: 'Tests Passed' },
              { value: '100%', label: 'Pass Rate' },
              { value: '<6s', label: 'Test Runtime' },
            ].map((stat, i) => (
              <div key={i} className="text-center">
                <div className="text-2xl sm:text-3xl font-bold bg-gradient-to-r from-blue-600 to-emerald-500 bg-clip-text text-transparent">
                  {stat.value}
                </div>
                <div className="text-xs text-gray-400 dark:text-gray-500 mt-1">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>

        <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
          <svg className="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
        </div>
      </section>

      {/* ─── Features ───────────────────────────────────────── */}
      <section id="features" className="py-32 px-6">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-20">
            <p className="text-blue-600 dark:text-blue-400 text-sm font-semibold tracking-widest uppercase mb-3">Features</p>
            <h2 className="text-4xl sm:text-5xl font-bold">Everything You Need</h2>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map((f, i) => (
              <div key={i} className="group p-8 rounded-3xl bg-gray-50 dark:bg-white/[0.03] border border-gray-200 dark:border-white/[0.06] hover:shadow-xl dark:hover:bg-white/[0.06] hover:scale-[1.02] transition-all duration-300">
                <div className="text-4xl mb-4">{f.icon}</div>
                <h3 className="text-xl font-bold mb-2">{f.title}</h3>
                <p className="text-gray-500 dark:text-gray-400 text-sm leading-relaxed">{f.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── How It Works ───────────────────────────────────── */}
      <section id="workflow" className="py-32 px-6 bg-gradient-to-b from-transparent via-blue-50/50 dark:via-blue-950/20 to-transparent">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-20">
            <p className="text-emerald-600 dark:text-emerald-400 text-sm font-semibold tracking-widest uppercase mb-3">Workflow</p>
            <h2 className="text-4xl sm:text-5xl font-bold">How It Works</h2>
          </div>

          <div className="space-y-0">
            {steps.map((item, i) => (
              <div key={i} className="flex gap-8 items-start group">
                <div className="flex flex-col items-center flex-shrink-0">
                  <div className="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 flex items-center justify-center font-bold text-lg text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                    {item.step}
                  </div>
                  {i < 3 && (
                    <div className="w-px h-20 bg-gradient-to-b from-blue-400 dark:from-blue-500 to-transparent opacity-30 mt-2" />
                  )}
                </div>
                <div className="pb-12">
                  <h3 className="text-xl font-bold mb-2">{item.title}</h3>
                  <p className="text-gray-500 dark:text-gray-400 leading-relaxed max-w-lg">{item.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Tech Stack ─────────────────────────────────────── */}
      <section id="tech" className="py-32 px-6">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-20">
            <p className="text-cyan-600 dark:text-cyan-400 text-sm font-semibold tracking-widest uppercase mb-3">Technology</p>
            <h2 className="text-4xl sm:text-5xl font-bold">Built With Modern Stack</h2>
          </div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {techStack.map((tech, i) => (
              <div key={i} className="p-5 rounded-2xl bg-gray-50 dark:bg-white/[0.03] border border-gray-200 dark:border-white/[0.06] hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-all text-center group">
                <div className="text-3xl mb-2 group-hover:scale-110 transition-transform">{tech.icon}</div>
                <div className="font-semibold text-sm">{tech.name}</div>
                <div className="text-xs text-gray-400 dark:text-gray-500 mt-1">{tech.role}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── CTA ────────────────────────────────────────────── */}
      <section className="py-32 px-6">
        <div className="max-w-3xl mx-auto text-center">
          <div className="relative p-12 rounded-3xl bg-gradient-to-br from-blue-50 via-purple-50 to-emerald-50 dark:from-blue-600/10 dark:via-purple-600/10 dark:to-emerald-600/10 border border-gray-200 dark:border-white/10">
            <h2 className="text-3xl sm:text-4xl font-bold mb-4">Ready to Digitize Your Lab?</h2>
            <p className="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
              Start transforming paper-based lab reports into structured digital records with AI-powered precision.
            </p>
            <button
              onClick={() => router.push('/auth/login')}
              className="px-10 py-4 bg-gradient-to-r from-blue-600 to-emerald-500 rounded-2xl text-lg font-semibold text-white hover:shadow-2xl hover:shadow-blue-500/25 transition-all duration-300 hover:scale-105 cursor-pointer"
            >
              Sign In to Dashboard →
            </button>
          </div>
        </div>
      </section>

      {/* ─── Footer ─────────────────────────────────────────── */}
      <footer className="py-8 px-6 border-t border-gray-200 dark:border-white/5">
        <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-400 dark:text-gray-500">
          <div className="flex items-center gap-2">
            <div className="w-6 h-6 rounded-lg bg-gradient-to-br from-blue-500 to-emerald-400 flex items-center justify-center text-xs font-bold text-white">C</div>
            <span>Clinex © 2026</span>
          </div>
          <p>Final Year Project — AI-Powered Clinical Lab Report Management</p>
        </div>
      </footer>

      {/* ─── Animations ─────────────────────────────────────── */}
      <style jsx>{`
        @keyframes landing-fade-in-kf { from { opacity: 0; } to { opacity: 1; } }
        @keyframes landing-fade-in-up-kf { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .landing-fade-in { animation: landing-fade-in-kf 1s ease-out; }
        .landing-fade-in-up { animation: landing-fade-in-up-kf 1s ease-out; }
        .landing-fade-in-up-delay { animation: landing-fade-in-up-kf 1s ease-out 0.3s both; }
        .landing-fade-in-up-delay-2 { animation: landing-fade-in-up-kf 1s ease-out 0.6s both; }
      `}</style>
    </div>
  )
}

/* ─── Main Component: Splash → Landing ──────────────────────────── */
export default function HomePage() {
  const [showSplash, setShowSplash] = useState(true)

  if (showSplash) {
    return <SplashScreen onFinish={() => setShowSplash(false)} />
  }

  return <LandingContent />
}