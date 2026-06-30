'use client'
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from 'recharts'

interface CategoryDonutChartProps {
    data: Array<{ name: string; value: number }>
}

const COLORS = ['#6366f1', '#22c55e', '#a855f7', '#eab308', '#ef4444', '#ec4899', '#14b8a6', '#f97316']

function CustomTooltip({ active, payload }: { active?: boolean; payload?: Array<{ name: string; value: number; payload: { name: string; value: number } }> }) {
    if (!active || !payload?.length) return null
    const item = payload[0]
    return (
        <div className="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 shadow-xl">
            <p className="text-slate-400 text-[11px] font-medium">{item.payload.name}</p>
            <p className="text-white text-sm font-bold">{item.value} tests</p>
        </div>
    )
}

export default function CategoryDonutChart({ data }: CategoryDonutChartProps) {
    if (!data.length) {
        return (
            <div className="flex items-center justify-center h-full text-slate-500 text-sm font-medium">
                No category data available
            </div>
        )
    }

    const total = data.reduce((sum, d) => sum + d.value, 0)

    return (
        <div className="flex items-center gap-4 h-full">
            {/* Chart */}
            <div className="relative flex-shrink-0" style={{ width: 180, height: 180 }}>
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={data}
                            cx="50%"
                            cy="50%"
                            innerRadius={55}
                            outerRadius={80}
                            paddingAngle={3}
                            dataKey="value"
                            strokeWidth={0}
                        >
                            {data.map((_, index) => (
                                <Cell key={index} fill={COLORS[index % COLORS.length]} />
                            ))}
                        </Pie>
                        <Tooltip content={<CustomTooltip />} />
                    </PieChart>
                </ResponsiveContainer>
                {/* Center label */}
                <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span className="text-2xl font-extrabold text-white tracking-tight">{total}</span>
                    <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Tests</span>
                </div>
            </div>

            {/* Legend */}
            <div className="flex-1 space-y-2 min-w-0">
                {data.slice(0, 5).map((item, index) => {
                    const pct = total > 0 ? Math.round((item.value / total) * 100) : 0
                    return (
                        <div key={item.name} className="flex items-center gap-2.5">
                            <div
                                className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                style={{ backgroundColor: COLORS[index % COLORS.length] }}
                            />
                            <span className="text-xs text-slate-300 font-medium truncate flex-1">{item.name}</span>
                            <span className="text-xs text-slate-400 font-semibold tabular-nums">{pct}%</span>
                        </div>
                    )
                })}
            </div>
        </div>
    )
}
