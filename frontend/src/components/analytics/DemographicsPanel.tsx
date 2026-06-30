'use client'
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from 'recharts'

interface DemographicsPanelProps {
    gender: Record<string, number>
    ageGroups: Array<{ range: string; count: number }>
}

const GENDER_COLORS: Record<string, string> = {
    Male: '#3b82f6',
    Female: '#ec4899',
}

function GenderTooltip({ active, payload }: { active?: boolean; payload?: Array<{ name: string; value: number; payload: { name: string; value: number } }> }) {
    if (!active || !payload?.length) return null
    return (
        <div className="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 shadow-xl">
            <p className="text-white text-sm font-bold">{payload[0].payload.name}: {payload[0].value}</p>
        </div>
    )
}

export default function DemographicsPanel({ gender, ageGroups }: DemographicsPanelProps) {
    const genderData = Object.entries(gender).map(([name, value]) => ({ name, value }))
    const genderTotal = genderData.reduce((s, d) => s + d.value, 0)
    const maxAge = Math.max(...ageGroups.map(g => g.count), 1)

    return (
        <div className="flex flex-col h-full gap-5">
            {/* Gender Donut - Compact */}
            <div className="flex items-center gap-4">
                <div className="relative flex-shrink-0" style={{ width: 100, height: 100 }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={genderData}
                                cx="50%"
                                cy="50%"
                                innerRadius={30}
                                outerRadius={45}
                                paddingAngle={4}
                                dataKey="value"
                                strokeWidth={0}
                            >
                                {genderData.map((entry) => (
                                    <Cell key={entry.name} fill={GENDER_COLORS[entry.name] || '#6366f1'} />
                                ))}
                            </Pie>
                            <Tooltip content={<GenderTooltip />} />
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span className="text-sm font-extrabold text-white">{genderTotal}</span>
                    </div>
                </div>

                {/* Gender Legend */}
                <div className="space-y-2">
                    {genderData.map(entry => {
                        const pct = genderTotal > 0 ? Math.round((entry.value / genderTotal) * 100) : 0
                        return (
                            <div key={entry.name} className="flex items-center gap-2">
                                <div className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: GENDER_COLORS[entry.name] || '#6366f1' }} />
                                <span className="text-xs text-slate-300 font-medium">{entry.name}</span>
                                <span className="text-xs text-slate-500 font-semibold">{pct}%</span>
                            </div>
                        )
                    })}
                </div>
            </div>

            {/* Age Distribution Bars */}
            <div className="flex-1 space-y-2.5">
                <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Age Distribution</p>
                {ageGroups.map((group) => {
                    const widthPct = maxAge > 0 ? Math.max((group.count / maxAge) * 100, 4) : 4
                    return (
                        <div key={group.range} className="flex items-center gap-3">
                            <span className="text-xs text-slate-400 font-semibold w-10 text-right tabular-nums">{group.range}</span>
                            <div className="flex-1 bg-slate-800/60 h-4 rounded-full overflow-hidden">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-indigo-600 to-purple-500 transition-all duration-700"
                                    style={{ width: `${widthPct}%` }}
                                />
                            </div>
                            <span className="text-xs text-slate-400 font-bold tabular-nums w-6">{group.count}</span>
                        </div>
                    )
                })}
            </div>
        </div>
    )
}
