'use client'
import { RadialBarChart, RadialBar, ResponsiveContainer, Tooltip } from 'recharts'

interface ConfidenceRadialChartProps {
    data: Array<{ category: string; avg_confidence: number }>
}

const RING_COLORS = ['#6366f1', '#22c55e', '#a855f7', '#eab308', '#ef4444', '#ec4899', '#14b8a6', '#f97316']

function CustomTooltip({ active, payload }: { active?: boolean; payload?: Array<{ payload: { category: string; avg_confidence: number } }> }) {
    if (!active || !payload?.length) return null
    const d = payload[0].payload
    return (
        <div className="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 shadow-xl">
            <p className="text-slate-400 text-[11px] font-medium">{d.category}</p>
            <p className="text-white text-sm font-bold">{d.avg_confidence}%</p>
        </div>
    )
}

export default function ConfidenceRadialChart({ data }: ConfidenceRadialChartProps) {
    if (!data.length) {
        return (
            <div className="flex items-center justify-center h-full text-slate-500 text-sm font-medium">
                No confidence data available
            </div>
        )
    }

    // Recharts RadialBarChart expects data sorted by value (innermost ring = first item)
    const chartData = data
        .map((d, i) => ({
            ...d,
            fill: RING_COLORS[i % RING_COLORS.length],
        }))
        .sort((a, b) => a.avg_confidence - b.avg_confidence)

    return (
        <div className="flex items-center gap-4 h-full">
            <div className="flex-shrink-0" style={{ width: 180, height: 180 }}>
                <ResponsiveContainer width="100%" height="100%">
                    <RadialBarChart
                        cx="50%"
                        cy="50%"
                        innerRadius="30%"
                        outerRadius="100%"
                        data={chartData}
                        startAngle={180}
                        endAngle={-180}
                        barSize={12}
                    >
                        <RadialBar
                            dataKey="avg_confidence"
                            cornerRadius={6}
                            background={{ fill: '#1e293b' }}
                        />
                        <Tooltip content={<CustomTooltip />} />
                    </RadialBarChart>
                </ResponsiveContainer>
            </div>

            {/* Legend */}
            <div className="flex-1 space-y-2.5 min-w-0">
                {data.map((item, index) => (
                    <div key={item.category} className="flex items-center gap-2.5">
                        <div
                            className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                            style={{ backgroundColor: RING_COLORS[index % RING_COLORS.length] }}
                        />
                        <span className="text-xs text-slate-300 font-medium truncate flex-1">{item.category}</span>
                        <span className="text-xs font-bold tabular-nums" style={{ color: RING_COLORS[index % RING_COLORS.length] }}>
                            {item.avg_confidence}%
                        </span>
                    </div>
                ))}
            </div>
        </div>
    )
}
