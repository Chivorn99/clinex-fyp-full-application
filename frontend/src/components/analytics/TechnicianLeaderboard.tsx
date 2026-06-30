'use client'

interface TechnicianLeaderboardProps {
    data: Array<{
        id: number
        name: string
        uploads: number
        verifications: number
    }>
}

const AVATAR_GRADIENTS = [
    'from-blue-600 to-indigo-600',
    'from-purple-600 to-pink-600',
    'from-emerald-600 to-teal-600',
    'from-amber-600 to-orange-600',
    'from-rose-600 to-red-600',
]

const RANK_BADGES = ['🥇', '🥈', '🥉']

export default function TechnicianLeaderboard({ data }: TechnicianLeaderboardProps) {
    if (!data.length) {
        return (
            <div className="flex items-center justify-center h-full text-slate-500 text-sm font-medium">
                No user activity recorded
            </div>
        )
    }

    const maxTotal = Math.max(...data.map(u => u.uploads + u.verifications), 1)

    return (
        <div className="space-y-3">
            {data.map((user, index) => {
                const total = user.uploads + user.verifications
                const uploadPct = total > 0 ? (user.uploads / maxTotal) * 100 : 0
                const verifyPct = total > 0 ? (user.verifications / maxTotal) * 100 : 0

                return (
                    <div
                        key={user.id}
                        className="flex items-center gap-3 p-3 bg-slate-800/40 border border-slate-800 rounded-xl hover:bg-slate-800/70 transition-colors"
                    >
                        {/* Rank badge */}
                        <span className="text-sm w-5 text-center flex-shrink-0">
                            {index < 3 ? RANK_BADGES[index] : <span className="text-slate-600 text-xs font-bold">{index + 1}</span>}
                        </span>

                        {/* Avatar */}
                        <div className={`w-8 h-8 bg-gradient-to-tr ${AVATAR_GRADIENTS[index % AVATAR_GRADIENTS.length]} rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm flex-shrink-0`}>
                            {user.name.charAt(0).toUpperCase()}
                        </div>

                        {/* Info */}
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-bold text-slate-200 truncate">{user.name}</p>
                            {/* Stacked bar */}
                            <div className="flex h-1.5 rounded-full overflow-hidden mt-1.5 bg-slate-800">
                                <div
                                    className="bg-blue-500 transition-all duration-500"
                                    style={{ width: `${uploadPct}%` }}
                                />
                                <div
                                    className="bg-emerald-500 transition-all duration-500"
                                    style={{ width: `${verifyPct}%` }}
                                />
                            </div>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-[10px] text-blue-400 font-semibold">{user.uploads} uploads</span>
                                <span className="text-slate-700 text-[10px]">•</span>
                                <span className="text-[10px] text-emerald-400 font-semibold">{user.verifications} verified</span>
                            </div>
                        </div>

                        {/* Total */}
                        <div className="text-right flex-shrink-0">
                            <span className="text-xs font-extrabold text-slate-300 bg-slate-800 px-2.5 py-1 rounded-md border border-slate-700">
                                {total}
                            </span>
                        </div>
                    </div>
                )
            })}
        </div>
    )
}
