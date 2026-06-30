'use client'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

interface ReportVolumeChartProps {
    data: Array<{ date: string; count: number }>
}

// Custom tooltip matching the dark slate theme
function CustomTooltip({ active, payload, label }: { active?: boolean; payload?: Array<{ value: number }>; label?: string }) {
    if (!active || !payload?.length) return null
    return (
        <div className="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 shadow-xl">
            <p className="text-slate-400 text-[11px] font-medium">{label}</p>
            <p className="text-white text-sm font-bold">{payload[0].value} reports</p>
        </div>
    )
}

export default function ReportVolumeChart({ data }: ReportVolumeChartProps) {
    if (!data.length) {
        return (
            <div className="flex items-center justify-center h-full text-slate-500 text-sm font-medium">
                No report data available
            </div>
        )
    }

    // Format dates for display (keep only month/day)
    const formatted = data.map(d => ({
        ...d,
        label: new Date(d.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    }))

    return (
        <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={formatted} margin={{ top: 8, right: 8, left: -20, bottom: 0 }}>
                <defs>
                    <linearGradient id="volumeGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#6366f1" stopOpacity={0.35} />
                        <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" vertical={false} />
                <XAxis
                    dataKey="label"
                    tick={{ fill: '#64748b', fontSize: 11, fontWeight: 500 }}
                    axisLine={{ stroke: '#1e293b' }}
                    tickLine={false}
                    interval="preserveStartEnd"
                />
                <YAxis
                    tick={{ fill: '#64748b', fontSize: 11, fontWeight: 500 }}
                    axisLine={false}
                    tickLine={false}
                    allowDecimals={false}
                />
                <Tooltip content={<CustomTooltip />} />
                <Area
                    type="monotone"
                    dataKey="count"
                    stroke="#818cf8"
                    strokeWidth={2.5}
                    fill="url(#volumeGradient)"
                    dot={false}
                    activeDot={{ r: 5, fill: '#818cf8', stroke: '#0f172a', strokeWidth: 2 }}
                />
            </AreaChart>
        </ResponsiveContainer>
    )
}
