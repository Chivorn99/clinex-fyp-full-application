'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import {
  LayoutDashboard,
  Users,
  FileText,
  Brain,
  Activity,
  ArrowLeft,
  Shield,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react'
import { useState } from 'react'

interface NavItem {
  label: string
  href: string
  icon: React.ReactNode
  permission?: string
}

const navItems: NavItem[] = [
  {
    label: 'Overview',
    href: '/admin',
    icon: <LayoutDashboard className="h-5 w-5" />,
    permission: 'view_analytics',
  },
  {
    label: 'Users',
    href: '/admin/users',
    icon: <Users className="h-5 w-5" />,
    permission: 'manage_users',
  },
  {
    label: 'Reports',
    href: '/admin/reports',
    icon: <FileText className="h-5 w-5" />,
    permission: 'manage_reports',
  },
  {
    label: 'OCR Templates',
    href: '/admin/templates',
    icon: <Brain className="h-5 w-5" />,
    permission: 'manage_templates',
  },
  {
    label: 'System',
    href: '/admin/system',
    icon: <Activity className="h-5 w-5" />,
    permission: 'view_system_health',
  },
]

export default function AdminSidebar() {
  const pathname = usePathname()
  const { user, hasPermission } = useAuth()
  const [collapsed, setCollapsed] = useState(false)

  const filteredItems = navItems.filter(
    (item) => !item.permission || hasPermission(item.permission)
  )

  const isActive = (href: string) => {
    if (href === '/admin') return pathname === '/admin'
    return pathname.startsWith(href)
  }

  return (
    <aside
      className={`${
        collapsed ? 'w-[72px]' : 'w-[280px]'
      } min-h-screen bg-gradient-to-b from-slate-900 via-slate-850 to-slate-800 border-r border-slate-700/50 flex flex-col transition-all duration-300 ease-in-out`}
    >
      {/* Header */}
      <div className="p-4 border-b border-slate-700/50">
        <div className="flex items-center justify-between">
          <div className={`flex items-center gap-3 ${collapsed ? 'justify-center w-full' : ''}`}>
            <div className="h-9 w-9 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-indigo-500/20">
              <Shield className="h-5 w-5 text-white" />
            </div>
            {!collapsed && (
              <div className="overflow-hidden">
                <h2 className="text-sm font-semibold text-white tracking-wide">Admin Panel</h2>
                <p className="text-[11px] text-slate-400 truncate">ClineX Management</p>
              </div>
            )}
          </div>
          <button
            onClick={() => setCollapsed(!collapsed)}
            className={`p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700/50 transition-all duration-200 ${collapsed ? 'hidden' : ''}`}
          >
            <ChevronLeft className="h-4 w-4" />
          </button>
        </div>
      </div>

      {/* Expand button when collapsed */}
      {collapsed && (
        <button
          onClick={() => setCollapsed(false)}
          className="mx-auto mt-2 p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700/50 transition-all duration-200"
        >
          <ChevronRight className="h-4 w-4" />
        </button>
      )}

      {/* Navigation */}
      <nav className="flex-1 py-4 px-3 space-y-1">
        {!collapsed && (
          <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider px-3 mb-3">
            Navigation
          </p>
        )}
        {filteredItems.map((item) => {
          const active = isActive(item.href)
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 group relative ${
                active
                  ? 'bg-indigo-600/20 text-white border-l-[3px] border-indigo-400 ml-0 pl-[9px]'
                  : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border-l-[3px] border-transparent'
              } ${collapsed ? 'justify-center px-0' : ''}`}
              title={collapsed ? item.label : undefined}
            >
              <span className={`flex-shrink-0 ${active ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300'}`}>
                {item.icon}
              </span>
              {!collapsed && <span>{item.label}</span>}
              {/* Tooltip for collapsed mode */}
              {collapsed && (
                <div className="absolute left-full ml-2 px-2 py-1 bg-slate-800 text-white text-xs rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-200 whitespace-nowrap z-50 shadow-lg border border-slate-700">
                  {item.label}
                </div>
              )}
            </Link>
          )
        })}
      </nav>

      {/* User Info & Back Button */}
      <div className="border-t border-slate-700/50 p-4 space-y-3">
        {!collapsed && user && (
          <div className="flex items-center gap-3 px-2">
            <div className="h-8 w-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
              {user.name?.charAt(0).toUpperCase()}
            </div>
            <div className="min-w-0">
              <p className="text-sm text-white font-medium truncate">{user.name}</p>
              <p className="text-[11px] text-slate-400 truncate capitalize">{user.role?.replace('_', ' ')}</p>
            </div>
          </div>
        )}
        <Link
          href="/main/homepage"
          className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-white hover:bg-slate-700/40 transition-all duration-200 ${
            collapsed ? 'justify-center' : ''
          }`}
          title={collapsed ? 'Back to Dashboard' : undefined}
        >
          <ArrowLeft className="h-4 w-4 flex-shrink-0" />
          {!collapsed && <span>Back to Dashboard</span>}
        </Link>
      </div>
    </aside>
  )
}
