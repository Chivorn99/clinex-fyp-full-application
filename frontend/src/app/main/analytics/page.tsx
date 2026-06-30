'use client'
import { useState, useEffect, useCallback } from 'react'
import DashboardLayout from '@/components/layout/DashboardLayout'
import {
    TrendingUp, FileText, Clock, AlertTriangle,
    RefreshCw, Activity, Users, Beaker, ShieldCheck,
    BarChart3
} from 'lucide-react'
import { apiClient } from '@/lib/api'

// Chart Components
import ReportVolumeChart from '@/components/analytics/ReportVolumeChart'
import CategoryDonutChart from '@/components/analytics/CategoryDonutChart'
import AbnormalTestsChart from '@/components/analytics/AbnormalTestsChart'
import DemographicsPanel from '@/components/analytics/DemographicsPanel'
import ConfidenceRadialChart from '@/components/analytics/ConfidenceRadialChart'
import TechnicianLeaderboard from '@/components/analytics/TechnicianLeaderboard'

// ── Types ────────────────────────────────────────────────────

interface AnalyticsDashboard {
    stats: {
        total_reports: number
        pending_verification: number
        avg_processing_time: number
        abnormal_results: number
    }
    report_volume: Array<{ date: string; count: number }>
    category_distribution: Array<{ name: string; value: number }>
    top_abnormal_tests: Array<{ test_name: string; high: number; low: number }>
    patient_demographics: {
        gender: Record<string, number>
        age_groups: Array<{ range: string; count: number }>
    }
    confidence_by_category: Array<{ category: string; avg_confidence: number }>
    technician_activity: Array<{
        id: number
        name: string
        uploads: number
        verifications: number
    }>
}

type TimeRange = '7' | '14' | '30' | '0'

const TIME_RANGE_LABELS: Record<TimeRange, string> = {
    '7': '7d',
    '14': '14d',
    '30': '30d',
    '0': 'All Time',
}

// ── Helpers ──────────────────────────────────────────────────

function formatNumber(num: number): string {
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
    return num.toString()
}

function formatSeconds(seconds: number): string {
    if (seconds < 60) return `${seconds.toFixed(1)}s`
    const mins = Math.floor(seconds / 60)
    const secs = Math.round(seconds % 60)
    return `${mins}m ${secs}s`
}

// ── Stat Card ────────────────────────────────────────────────

interface StatCardProps {
    title: string
    value: string
    subtitle: string
    icon: React.ReactNode
    accentColor: string // e.g. "blue", "purple", "emerald", "rose"
}

function StatCard({ title, value, subtitle, icon, accentColor }: StatCardProps) {
    const colorMap: Record<string, { glow: string; iconBg: string; iconBorder: string; iconText: string; badge: string; badgeText: string }> = {
        blue:    { glow: 'bg-blue-500/10',    iconBg: 'bg-blue-500/10',    iconBorder: 'border-blue-500/20',    iconText: 'text-blue-400',    badge: 'bg-blue-500/10',    badgeText: 'text-blue-400' },
        purple:  { glow: 'bg-purple-500/10',  iconBg: 'bg-purple-500/10',  iconBorder: 'border-purple-500/20',  iconText: 'text-purple-400',  badge: 'bg-purple-500/10',  badgeText: 'text-purple-400' },
        amber:   { glow: 'bg-amber-500/10',   iconBg: 'bg-amber-500/10',   iconBorder: 'border-amber-500/20',   iconText: 'text-amber-400',   badge: 'bg-amber-500/10',   badgeText: 'text-amber-400' },
        rose:    { glow: 'bg-rose-500/10',    iconBg: 'bg-rose-500/10',    iconBorder: 'border-rose-500/20',    iconText: 'text-rose-400',    badge: 'bg-rose-500/10',    badgeText: 'text-rose-400' },
        emerald: { glow: 'bg-emerald-500/10', iconBg: 'bg-emerald-500/10', iconBorder: 'border-emerald-500/20', iconText: 'text-emerald-400', badge: 'bg-emerald-500/10', badgeText: 'text-emerald-400' },
    }
    const c = colorMap[accentColor] || colorMap.blue

    return (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl relative overflow-hidden group hover:border-slate-700 transition-all duration-300">
            <div className={`absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 ${c.glow} rounded-full blur-xl group-hover:opacity-150 transition-all duration-500`} />
            <div className="flex items-center justify-between relative z-10">
                <div>
                    <p className="text-slate-400 text-xs font-semibold tracking-wider uppercase">{title}</p>
                    <p className="text-3xl font-extrabold text-white mt-2 tracking-tight">{value}</p>
                    <div className={`flex items-center mt-3 text-xs font-medium ${c.badgeText} ${c.badge} px-2.5 py-1 rounded-full w-fit`}>
                        <span>{subtitle}</span>
                    </div>
                </div>
                <div className={`p-3.5 ${c.iconBg} border ${c.iconBorder} rounded-xl ${c.iconText}`}>
                    {icon}
                </div>
            </div>
        </div>
    )
}

// ── Section Card Wrapper ─────────────────────────────────────

function SectionCard({ title, subtitle, children, className = '' }: {
    title: string
    subtitle: string
    children: React.ReactNode
    className?: string
}) {
    return (
        <div className={`bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden flex flex-col ${className}`}>
            <div className="p-5 border-b border-slate-800/80 bg-slate-950/40">
                <h3 className="text-base font-bold text-white">{title}</h3>
                <p className="text-xs text-slate-400 mt-0.5">{subtitle}</p>
            </div>
            <div className="p-5 flex-1">
                {children}
            </div>
        </div>
    )
}

// ── Loading Skeleton ─────────────────────────────────────────

function LoadingSkeleton() {
    return (
        <div className="space-y-6">
            {/* Stat cards skeleton */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 animate-pulse">
                        <div className="h-3 bg-slate-800 rounded w-24 mb-4" />
                        <div className="h-8 bg-slate-800 rounded w-20 mb-3" />
                        <div className="h-5 bg-slate-800 rounded w-32" />
                    </div>
                ))}
            </div>
            {/* Chart sections skeleton */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {Array.from({ length: 2 }).map((_, i) => (
                    <div key={i} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 animate-pulse">
                        <div className="h-4 bg-slate-800 rounded w-40 mb-2" />
                        <div className="h-3 bg-slate-800 rounded w-56 mb-6" />
                        <div className="h-48 bg-slate-800/50 rounded-lg" />
                    </div>
                ))}
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {Array.from({ length: 2 }).map((_, i) => (
                    <div key={i} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 animate-pulse">
                        <div className="h-4 bg-slate-800 rounded w-40 mb-2" />
                        <div className="h-3 bg-slate-800 rounded w-56 mb-6" />
                        <div className="h-48 bg-slate-800/50 rounded-lg" />
                    </div>
                ))}
            </div>
        </div>
    )
}

// ── Main Page ────────────────────────────────────────────────

export default function AnalyticsPage() {
    const [data, setData] = useState<AnalyticsDashboard | null>(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [timeRange, setTimeRange] = useState<TimeRange>('30')

    const fetchData = useCallback(async () => {
        try {
            setLoading(true)
            setError('')
            const result = await apiClient.get(`/analytics/dashboard?days=${timeRange}`)
            setData(result as AnalyticsDashboard)
        } catch (err: unknown) {
            console.error('Failed to fetch analytics:', err)
            setError(err instanceof Error ? err.message : 'Failed to load analytics data')
        } finally {
            setLoading(false)
        }
    }, [timeRange])

    useEffect(() => {
        fetchData()
    }, [fetchData])

    return (
        <DashboardLayout>
            {/* Dark background wrapper — bleeds edge-to-edge inside DashboardLayout */}
            <div className="bg-slate-950 -mx-4 sm:-mx-6 lg:-mx-8 -my-6 px-4 sm:px-6 lg:px-8 py-6 min-h-[calc(100vh-4rem)]">
            <div className="space-y-6">
                {/* ── Header ─────────────────────────────── */}
                <div className="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-white">Analytics Dashboard</h1>
                        <p className="mt-1 text-slate-400">
                            Lab performance insights & clinical data overview
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Time Range Toggle */}
                        <div className="flex bg-slate-800 p-0.5 rounded-lg">
                            {(Object.keys(TIME_RANGE_LABELS) as TimeRange[]).map((range) => (
                                <button
                                    key={range}
                                    onClick={() => setTimeRange(range)}
                                    className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${
                                        timeRange === range
                                            ? 'bg-indigo-600 text-white shadow-sm'
                                            : 'text-slate-400 hover:text-white'
                                    }`}
                                >
                                    {TIME_RANGE_LABELS[range]}
                                </button>
                            ))}
                        </div>
                        {/* Refresh */}
                        <button
                            onClick={fetchData}
                            disabled={loading}
                            className="inline-flex items-center px-3.5 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-white transition-all disabled:opacity-50"
                        >
                            <RefreshCw className={`h-3.5 w-3.5 mr-1.5 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </button>
                    </div>
                </div>

                {/* ── Content ────────────────────────────── */}
                {loading ? (
                    <LoadingSkeleton />
                ) : error ? (
                    <div className="bg-red-950/50 border border-red-900/50 rounded-2xl p-8 text-center">
                        <AlertTriangle className="h-12 w-12 text-red-400 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold text-red-300 mb-2">Error Loading Analytics</h3>
                        <p className="text-red-400/80 text-sm mb-4 max-w-md mx-auto">{error}</p>
                        <button
                            onClick={fetchData}
                            className="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-500 transition-colors text-sm font-medium"
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Try Again
                        </button>
                    </div>
                ) : data ? (
                    <>
                        {/* ── Row 1: Stat Cards ──────────── */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <StatCard
                                title="Reports Processed"
                                value={formatNumber(data.stats.total_reports)}
                                subtitle="Total in selected range"
                                icon={<FileText className="h-6 w-6" />}
                                accentColor="blue"
                            />
                            <StatCard
                                title="Pending Verification"
                                value={formatNumber(data.stats.pending_verification)}
                                subtitle="Awaiting technician review"
                                icon={<Clock className="h-6 w-6" />}
                                accentColor="amber"
                            />
                            <StatCard
                                title="Avg Processing Time"
                                value={formatSeconds(data.stats.avg_processing_time)}
                                subtitle="OCR + LLM extraction"
                                icon={<Activity className="h-6 w-6" />}
                                accentColor="emerald"
                            />
                            <StatCard
                                title="Abnormal Results"
                                value={formatNumber(data.stats.abnormal_results)}
                                subtitle="Flagged High or Low"
                                icon={<AlertTriangle className="h-6 w-6" />}
                                accentColor="rose"
                            />
                        </div>

                        {/* ── Row 2: Volume & Categories ──── */}
                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <SectionCard
                                title="Report Volume"
                                subtitle="Daily report processing activity"
                                className="lg:col-span-7"
                            >
                                <div className="h-56">
                                    <ReportVolumeChart data={data.report_volume} />
                                </div>
                            </SectionCard>

                            <SectionCard
                                title="Test Category Distribution"
                                subtitle="Breakdown across lab disciplines"
                                className="lg:col-span-5"
                            >
                                <div className="h-56">
                                    <CategoryDonutChart data={data.category_distribution} />
                                </div>
                            </SectionCard>
                        </div>

                        {/* ── Row 3: Abnormal Tests & Demographics */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <SectionCard
                                title="Top Abnormal Biomarkers"
                                subtitle="Most frequently flagged test results (High / Low)"
                            >
                                <div className="h-64">
                                    <AbnormalTestsChart data={data.top_abnormal_tests} />
                                </div>
                            </SectionCard>

                            <SectionCard
                                title="Patient Demographics"
                                subtitle="Gender split & age distribution"
                            >
                                <div className="h-64">
                                    <DemographicsPanel
                                        gender={data.patient_demographics.gender}
                                        ageGroups={data.patient_demographics.age_groups}
                                    />
                                </div>
                            </SectionCard>
                        </div>

                        {/* ── Row 4: Confidence & Leaderboard */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <SectionCard
                                title="OCR Confidence by Category"
                                subtitle="Average extraction confidence per test discipline"
                            >
                                <div className="h-56">
                                    <ConfidenceRadialChart data={data.confidence_by_category} />
                                </div>
                            </SectionCard>

                            <SectionCard
                                title="Technician Activity"
                                subtitle="Top contributors by uploads & verifications"
                            >
                                <TechnicianLeaderboard data={data.technician_activity} />
                            </SectionCard>
                        </div>
                    </>
                ) : null}
            </div>
            </div>
        </DashboardLayout>
    )
}
