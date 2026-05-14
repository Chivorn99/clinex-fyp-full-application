'use client'
import { useEffect, useState, useCallback } from 'react'
import { useAuth, User } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'
import {
  Users,
  Search,
  Shield,
  Stethoscope,
  FlaskConical,
  Trash2,
  ChevronDown,
  ChevronUp,
  Check,
  AlertCircle,
  X,
} from 'lucide-react'

const AVAILABLE_PERMISSIONS = [
  { key: 'manage_users', label: 'Manage Users', description: 'Create, edit, delete users and change roles' },
  { key: 'manage_templates', label: 'Manage Templates', description: 'Create and edit OCR extraction templates' },
  { key: 'manage_reports', label: 'Manage Reports', description: 'Delete and reprocess reports in bulk' },
  { key: 'view_analytics', label: 'View Analytics', description: 'Access the admin dashboard overview' },
  { key: 'view_system_health', label: 'View System Health', description: 'Monitor Ollama, queue, and disk status' },
  { key: 'export_data', label: 'Export Data', description: 'Export reports and data as CSV' },
]

interface AdminUser {
  id: number
  name: string
  email: string
  role: string
  permissions: Record<string, boolean> | null
  phone_number?: string
  specialization?: string
  created_at: string
}

interface PaginatedResponse {
  data: AdminUser[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

export default function UsersPage() {
  const { user: currentUser, hasPermission } = useAuth()
  const [users, setUsers] = useState<AdminUser[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [total, setTotal] = useState(0)
  const [expandedUserId, setExpandedUserId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  const showToast = (type: 'success' | 'error', message: string) => {
    setToast({ type, message })
    setTimeout(() => setToast(null), 3000)
  }

  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '15' })
      if (roleFilter !== 'all') params.set('role', roleFilter)
      if (search) params.set('search', search)

      const data: PaginatedResponse = await apiClient.get(`/admin/users?${params}`)
      setUsers(data.data)
      setTotalPages(data.last_page)
      setTotal(data.total)
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Failed to load users')
    } finally {
      setLoading(false)
    }
  }, [page, roleFilter, search])

  useEffect(() => {
    if (hasPermission('manage_users')) {
      fetchUsers()
    } else {
      setLoading(false)
    }
  }, [hasPermission, fetchUsers])

  const handleRoleChange = async (userId: number, newRole: string) => {
    setSaving(true)
    try {
      await apiClient.patch(`/admin/users/${userId}/role`, { role: newRole })
      setUsers((prev) => prev.map((u) => (u.id === userId ? { ...u, role: newRole } : u)))
      showToast('success', 'Role updated')
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Role update failed')
    } finally {
      setSaving(false)
    }
  }

  const handlePermissionToggle = async (userId: number, permKey: string, currentVal: boolean) => {
    const targetUser = users.find((u) => u.id === userId)
    if (!targetUser) return

    const newPerms = { ...(targetUser.permissions || {}), [permKey]: !currentVal }

    try {
      await apiClient.patch(`/admin/users/${userId}/permissions`, { permissions: newPerms })
      setUsers((prev) =>
        prev.map((u) => (u.id === userId ? { ...u, permissions: newPerms } : u))
      )
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Permission update failed')
    }
  }

  const handleDelete = async (userId: number, userName: string) => {
    if (!confirm(`Delete user "${userName}"? This cannot be undone.`)) return
    try {
      await apiClient.delete(`/admin/users/${userId}`)
      setUsers((prev) => prev.filter((u) => u.id !== userId))
      setTotal((prev) => prev - 1)
      showToast('success', 'User deleted')
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Delete failed')
    }
  }

  const roleIcon = (role: string) => {
    switch (role) {
      case 'admin':
        return <Shield className="h-3.5 w-3.5" />
      case 'doctor':
        return <Stethoscope className="h-3.5 w-3.5" />
      case 'lab_technician':
        return <FlaskConical className="h-3.5 w-3.5" />
      default:
        return null
    }
  }

  const roleColor = (role: string) => {
    switch (role) {
      case 'admin':
        return 'bg-purple-100 text-purple-700 border-purple-200'
      case 'doctor':
        return 'bg-blue-100 text-blue-700 border-blue-200'
      case 'lab_technician':
        return 'bg-emerald-100 text-emerald-700 border-emerald-200'
      default:
        return 'bg-gray-100 text-gray-700 border-gray-200'
    }
  }

  if (!hasPermission('manage_users')) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Access Restricted</h2>
          <p className="text-gray-500">
            You need the <code className="bg-gray-100 px-2 py-0.5 rounded text-sm">manage_users</code> permission.
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="p-6 lg:p-8 max-w-6xl mx-auto space-y-6">
      {/* Toast */}
      {toast && (
        <div
          className={`fixed top-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium ${
            toast.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              : 'bg-red-50 text-red-800 border border-red-200'
          }`}
        >
          {toast.type === 'success' ? <Check className="h-4 w-4" /> : <AlertCircle className="h-4 w-4" />}
          {toast.message}
        </div>
      )}

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <Users className="h-6 w-6 text-indigo-500" />
          User Management
        </h1>
        <p className="text-gray-500 mt-1">
          {total} user{total !== 1 ? 's' : ''} total. Manage roles and granular permissions.
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Search by name or email..."
            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
          />
        </div>
        <select
          value={roleFilter}
          onChange={(e) => {
            setRoleFilter(e.target.value)
            setPage(1)
          }}
          className="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"
        >
          <option value="all">All Roles</option>
          <option value="admin">Admin</option>
          <option value="doctor">Doctor</option>
          <option value="lab_technician">Lab Technician</option>
        </select>
      </div>

      {/* Users Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {loading ? (
          <div className="p-8 space-y-4">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex items-center gap-4 animate-pulse">
                <div className="h-10 w-10 bg-gray-200 rounded-full" />
                <div className="flex-1">
                  <div className="h-4 w-48 bg-gray-200 rounded mb-2" />
                  <div className="h-3 w-32 bg-gray-100 rounded" />
                </div>
              </div>
            ))}
          </div>
        ) : users.length === 0 ? (
          <div className="p-12 text-center">
            <Users className="h-12 w-12 text-gray-300 mx-auto mb-4" />
            <h3 className="text-lg font-semibold text-gray-900">No Users Found</h3>
            <p className="text-gray-500 text-sm mt-1">Try adjusting your search or filters.</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {users.map((u) => {
              const isExpanded = expandedUserId === u.id
              const isSelf = String(u.id) === String(currentUser?.id)
              return (
                <div key={u.id} className="hover:bg-gray-50/50 transition-colors duration-150">
                  {/* User Row */}
                  <div className="p-4 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                      {/* Avatar */}
                      <div className="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        {u.name?.charAt(0).toUpperCase()}
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-medium text-gray-900">{u.name}</span>
                          {isSelf && (
                            <span className="text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded-full">
                              You
                            </span>
                          )}
                        </div>
                        <p className="text-sm text-gray-500">{u.email}</p>
                      </div>
                    </div>

                    <div className="flex items-center gap-3">
                      {/* Role Badge */}
                      <span
                        className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border ${roleColor(
                          u.role
                        )}`}
                      >
                        {roleIcon(u.role)}
                        {u.role?.replace('_', ' ')}
                      </span>

                      {/* Permission count badge */}
                      {u.role !== 'admin' && u.permissions && (
                        <span className="text-xs text-gray-400">
                          {Object.values(u.permissions).filter(Boolean).length} perms
                        </span>
                      )}

                      {/* Expand */}
                      <button
                        onClick={() => setExpandedUserId(isExpanded ? null : u.id)}
                        className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                      >
                        {isExpanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                      </button>

                      {/* Delete */}
                      {!isSelf && (
                        <button
                          onClick={() => handleDelete(u.id, u.name)}
                          className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                          title="Delete user"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Expanded Panel */}
                  {isExpanded && (
                    <div className="px-4 pb-5 pt-1 bg-gray-50/80 border-t border-gray-100">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Role Change */}
                        <div>
                          <h4 className="text-sm font-semibold text-gray-700 mb-3">Role</h4>
                          <select
                            value={u.role}
                            onChange={(e) => handleRoleChange(u.id, e.target.value)}
                            disabled={isSelf || saving}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white disabled:opacity-50"
                          >
                            <option value="admin">Admin</option>
                            <option value="doctor">Doctor</option>
                            <option value="lab_technician">Lab Technician</option>
                          </select>
                          {isSelf && (
                            <p className="text-xs text-gray-400 mt-1">You cannot change your own role.</p>
                          )}
                          <div className="mt-3 text-xs text-gray-400 space-y-1">
                            <p>Joined: {new Date(u.created_at).toLocaleDateString()}</p>
                            {u.specialization && <p>Specialization: {u.specialization}</p>}
                            {u.phone_number && <p>Phone: {u.phone_number}</p>}
                          </div>
                        </div>

                        {/* Permissions Panel */}
                        <div>
                          <h4 className="text-sm font-semibold text-gray-700 mb-3">
                            Permissions
                            {u.role === 'admin' && (
                              <span className="text-xs font-normal text-gray-400 ml-2">
                                (all granted by admin role)
                              </span>
                            )}
                          </h4>
                          <div className="space-y-2">
                            {AVAILABLE_PERMISSIONS.map((perm) => {
                              const isGranted = u.role === 'admin' || !!u.permissions?.[perm.key]
                              return (
                                <label
                                  key={perm.key}
                                  className={`flex items-center justify-between p-2.5 rounded-lg border cursor-pointer transition-colors duration-150 ${
                                    isGranted
                                      ? 'bg-indigo-50/50 border-indigo-200'
                                      : 'bg-white border-gray-200 hover:border-gray-300'
                                  } ${u.role === 'admin' ? 'opacity-60 cursor-not-allowed' : ''}`}
                                >
                                  <div>
                                    <span className="text-sm font-medium text-gray-900">{perm.label}</span>
                                    <p className="text-[11px] text-gray-400">{perm.description}</p>
                                  </div>
                                  <div className="relative">
                                    <input
                                      type="checkbox"
                                      checked={isGranted}
                                      onChange={() => handlePermissionToggle(u.id, perm.key, isGranted)}
                                      disabled={u.role === 'admin'}
                                      className="sr-only peer"
                                    />
                                    <div
                                      className={`w-9 h-5 rounded-full transition-colors duration-200 ${
                                        isGranted ? 'bg-indigo-600' : 'bg-gray-300'
                                      } ${u.role === 'admin' ? '' : 'cursor-pointer'}`}
                                      onClick={() => {
                                        if (u.role !== 'admin') handlePermissionToggle(u.id, perm.key, isGranted)
                                      }}
                                    >
                                      <div
                                        className={`h-4 w-4 rounded-full bg-white shadow-sm transform transition-transform duration-200 mt-0.5 ${
                                          isGranted ? 'translate-x-[18px]' : 'translate-x-0.5'
                                        }`}
                                      />
                                    </div>
                                  </div>
                                </label>
                              )
                            })}
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-500">
            Page {page} of {totalPages}
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Previous
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page === totalPages}
              className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
