'use client'
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from 'recharts'

interface AbnormalTestsChartProps {
    data: Array<{ test_name: string; high: number; low: number }>
}

function CustomTooltip({ active, payload, label }: { active?: boolean; payload?: Array<{ dataKey: string; value: number; color: string }>; label?: string }) {
    if (!active || !payload?.length) return null
    return (
        <div className="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 shadow-xl">
            <p className="text-slate-300 text-xs font-semibold mb-1">{label}</p>
            {payload.map((entry) => (
                <div key={entry.dataKey} className="flex items-center gap-2 text-xs">
                    <div className="w-2 h-2 rounded-full" style={{ backgroundColor: entry.color }} />
                    <span className="text-slate-400 capitalize">{entry.dataKey}:</span>
                    <span className="text-white font-bold">{entry.value}</span>
                </div>
            ))}
        </div>
    )
}

// Color palette for the bars (alternating warm tones for High, cool tones for Low)
const HIGH_COLORS = ['#f43f5e', '#fb7185', '#e11d48', '#f97316', '#ef4444', '#dc2626', '#be123c', '#c2410c']
const LOW_COLORS  = ['#38bdf8', '#60a5fa', '#3b82f6', '#818cf8', '#6366f1', '#2563eb', '#4f46e5', '#0ea5e9']

export default function AbnormalTestsChart({ data }: AbnormalTestsChartProps) {
    if (!data.length) {
        return (
            <div className="flex items-center justify-center h-full text-slate-500 text-sm font-medium">
                No abnormal results recorded
            </div>
        )
    }

    // Sort by total flags desc
    const sorted = [...data].sort((a, b) => (b.high + b.low) - (a.high + a.low))

    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={sorted} layout="vertical" margin={{ top: 4, right: 12, left: 4, bottom: 4 }}>
                <XAxis
                    type="number"
                    tick={{ fill: '#64748b', fontSize: 11, fontWeight: 500 }}
                    axisLine={false}
                    tickLine={false}
                    allowDecimals={false}
                />
                <YAxis
                    type="category"
                    dataKey="test_name"
                    tick={{ fill: '#cbd5e1', fontSize: 11, fontWeight: 600 }}
                    axisLine={false}
                    tickLine={false}
                    width={100}
                />
                <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(148, 163, 184, 0.06)' }} />
                <Bar dataKey="high" stackId="flags" radius={[0, 0, 0, 0]} barSize={16}>
                    {sorted.map((_, index) => (
                        <Cell key={`high-${index}`} fill={HIGH_COLORS[index % HIGH_COLORS.length]} />
                    ))}
                </Bar>
                <Bar dataKey="low" stackId="flags" radius={[0, 4, 4, 0]} barSize={16}>
                    {sorted.map((_, index) => (
                        <Cell key={`low-${index}`} fill={LOW_COLORS[index % LOW_COLORS.length]} />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    )
}
