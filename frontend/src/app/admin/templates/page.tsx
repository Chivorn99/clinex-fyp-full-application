'use client'
import { useEffect, useState, useCallback } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'
import {
  Brain,
  Plus,
  ToggleLeft,
  ToggleRight,
  Edit3,
  Trash2,
  Save,
  X,
  ChevronDown,
  ChevronUp,
  AlertCircle,
  Check,
} from 'lucide-react'

interface ReportTemplate {
  id: number
  name: string
  hospital_code: string
  is_active: boolean
  llm_model: string
  schema: Record<string, unknown>
  few_shot_examples: Array<Record<string, unknown>>
  created_at: string
  updated_at: string
}

export default function TemplatesPage() {
  const { hasPermission } = useAuth()
  const [templates, setTemplates] = useState<ReportTemplate[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [editForm, setEditForm] = useState<Partial<ReportTemplate>>({})
  const [showCreateForm, setShowCreateForm] = useState(false)
  const [saving, setSaving] = useState(false)
  const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  const showToast = (type: 'success' | 'error', message: string) => {
    setToast({ type, message })
    setTimeout(() => setToast(null), 3000)
  }

  const fetchTemplates = useCallback(async () => {
    try {
      const data = await apiClient.get('/admin/templates')
      setTemplates(data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load templates')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    if (hasPermission('manage_templates')) {
      fetchTemplates()
    } else {
      setLoading(false)
    }
  }, [hasPermission, fetchTemplates])

  const handleToggle = async (template: ReportTemplate) => {
    try {
      await apiClient.post(`/admin/templates/${template.id}/toggle`, {})
      setTemplates((prev) =>
        prev.map((t) =>
          t.id === template.id ? { ...t, is_active: !t.is_active } : t
        )
      )
      showToast('success', `Template ${template.is_active ? 'deactivated' : 'activated'}`)
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Toggle failed')
    }
  }

  const handleEdit = (template: ReportTemplate) => {
    setEditingId(template.id)
    setEditForm({
      name: template.name,
      hospital_code: template.hospital_code,
      llm_model: template.llm_model,
      schema: template.schema,
      few_shot_examples: template.few_shot_examples,
    })
  }

  const handleSaveEdit = async () => {
    if (!editingId) return
    setSaving(true)
    try {
      const payload: Record<string, unknown> = { ...editForm }
      if (typeof payload.schema === 'string') {
        payload.schema = JSON.parse(payload.schema as string)
      }
      if (typeof payload.few_shot_examples === 'string') {
        payload.few_shot_examples = JSON.parse(payload.few_shot_examples as string)
      }
      await apiClient.patch(`/admin/templates/${editingId}`, payload as Record<string, string | number | boolean | null>)
      showToast('success', 'Template updated successfully')
      setEditingId(null)
      fetchTemplates()
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (template: ReportTemplate) => {
    if (!confirm(`Delete template "${template.name}"? This cannot be undone.`)) return
    try {
      await apiClient.delete(`/admin/templates/${template.id}`)
      setTemplates((prev) => prev.filter((t) => t.id !== template.id))
      showToast('success', 'Template deleted')
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Delete failed')
    }
  }

  const handleCreate = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setSaving(true)
    try {
      const formData = new FormData(e.currentTarget)
      const payload: Record<string, unknown> = {
        name: formData.get('name') as string,
        hospital_code: formData.get('hospital_code') as string,
        llm_model: formData.get('llm_model') as string || 'phi3:mini',
        schema: JSON.parse(formData.get('schema') as string || '{}'),
        few_shot_examples: JSON.parse(formData.get('few_shot_examples') as string || '[]'),
        is_active: true,
      }
      await apiClient.post('/admin/templates', payload as Record<string, string | number | boolean | null>)
      showToast('success', 'Template created successfully')
      setShowCreateForm(false)
      fetchTemplates()
    } catch (err) {
      showToast('error', err instanceof Error ? err.message : 'Create failed')
    } finally {
      setSaving(false)
    }
  }

  if (!hasPermission('manage_templates')) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Access Restricted</h2>
          <p className="text-gray-500">
            You need the <code className="bg-gray-100 px-2 py-0.5 rounded text-sm">manage_templates</code> permission.
          </p>
        </div>
      </div>
    )
  }

  if (loading) {
    return (
      <div className="p-8 space-y-4">
        <div className="h-8 w-48 bg-gray-200 rounded animate-pulse" />
        {[1, 2].map((i) => (
          <div key={i} className="bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-pulse">
            <div className="h-6 w-48 bg-gray-200 rounded mb-4" />
            <div className="h-4 w-96 bg-gray-100 rounded" />
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className="p-6 lg:p-8 max-w-5xl mx-auto space-y-6">
      {/* Toast */}
      {toast && (
        <div
          className={`fixed top-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium transition-all duration-300 ${
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
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <Brain className="h-6 w-6 text-indigo-500" />
            OCR Templates
          </h1>
          <p className="text-gray-500 mt-1">Manage report extraction templates and LLM configurations.</p>
        </div>
        <button
          onClick={() => setShowCreateForm(!showCreateForm)}
          className="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 text-sm font-medium shadow-sm"
        >
          <Plus className="h-4 w-4" />
          New Template
        </button>
      </div>

      {/* Error */}
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
          <p className="font-medium">Error</p>
          <p className="text-sm mt-1">{error}</p>
        </div>
      )}

      {/* Create Form */}
      {showCreateForm && (
        <form onSubmit={handleCreate} className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
          <h3 className="text-lg font-semibold text-gray-900">Create New Template</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
              <input
                name="name"
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="e.g., Hospital ABC Default"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Hospital Code</label>
              <input
                name="hospital_code"
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="e.g., HOSP_ABC"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">LLM Model</label>
              <select
                name="llm_model"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"
              >
                <option value="phi3:mini">phi3:mini (3.8B — recommended)</option>
                <option value="llama3:8b">llama3:8b (8B — better quality)</option>
                <option value="mistral:7b">mistral:7b (7B — fast)</option>
              </select>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Schema (JSON)</label>
            <textarea
              name="schema"
              rows={4}
              required
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
              defaultValue={JSON.stringify(
                {
                  fields: [
                    { name: 'patient_name', type: 'string' },
                    { name: 'age', type: 'integer' },
                    { name: 'gender', type: 'string' },
                    { name: 'phone', type: 'string' },
                  ],
                  test_result_fields: ['test_name', 'result', 'unit', 'reference_range', 'flag'],
                },
                null,
                2
              )}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Few-shot Examples (JSON array)</label>
            <textarea
              name="few_shot_examples"
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
              defaultValue="[]"
              placeholder="[]"
            />
          </div>
          <div className="flex items-center gap-3 pt-2">
            <button
              type="submit"
              disabled={saving}
              className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium disabled:opacity-50"
            >
              <Save className="h-4 w-4" />
              {saving ? 'Creating...' : 'Create Template'}
            </button>
            <button
              type="button"
              onClick={() => setShowCreateForm(false)}
              className="px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors text-sm"
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      {/* Templates List */}
      {templates.length === 0 && !error ? (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
          <Brain className="h-12 w-12 text-gray-300 mx-auto mb-4" />
          <h3 className="text-lg font-semibold text-gray-900 mb-1">No Templates</h3>
          <p className="text-gray-500 text-sm">Create your first OCR template to enable LLM-powered extraction.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {templates.map((template) => (
            <div
              key={template.id}
              className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200"
            >
              {/* Template Header */}
              <div className="p-5 flex items-center justify-between">
                <div className="flex items-center gap-4">
                  {/* Toggle */}
                  <button
                    onClick={() => handleToggle(template)}
                    className="flex-shrink-0"
                    title={template.is_active ? 'Deactivate' : 'Activate'}
                  >
                    {template.is_active ? (
                      <ToggleRight className="h-7 w-7 text-emerald-500 hover:text-emerald-600 transition-colors" />
                    ) : (
                      <ToggleLeft className="h-7 w-7 text-gray-400 hover:text-gray-500 transition-colors" />
                    )}
                  </button>
                  <div>
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      {template.name}
                      <span
                        className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${
                          template.is_active
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-gray-100 text-gray-500'
                        }`}
                      >
                        {template.is_active ? 'ACTIVE' : 'INACTIVE'}
                      </span>
                    </h3>
                    <p className="text-sm text-gray-500 mt-0.5">
                      <span className="font-mono text-xs bg-gray-50 px-1.5 py-0.5 rounded">
                        {template.hospital_code}
                      </span>
                      <span className="mx-2 text-gray-300">·</span>
                      Model: <span className="font-medium text-gray-700">{template.llm_model}</span>
                      <span className="mx-2 text-gray-300">·</span>
                      {template.few_shot_examples?.length ?? 0} example(s)
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handleEdit(template)}
                    className="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                    title="Edit"
                  >
                    <Edit3 className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(template)}
                    className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Delete"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => setExpandedId(expandedId === template.id ? null : template.id)}
                    className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-lg transition-colors"
                    title="Expand"
                  >
                    {expandedId === template.id ? (
                      <ChevronUp className="h-4 w-4" />
                    ) : (
                      <ChevronDown className="h-4 w-4" />
                    )}
                  </button>
                </div>
              </div>

              {/* Edit Form (inline) */}
              {editingId === template.id && (
                <div className="border-t border-gray-100 bg-gray-50 p-5 space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                      <input
                        value={(editForm.name as string) ?? ''}
                        onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Hospital Code</label>
                      <input
                        value={(editForm.hospital_code as string) ?? ''}
                        onChange={(e) => setEditForm({ ...editForm, hospital_code: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">LLM Model</label>
                      <select
                        value={(editForm.llm_model as string) ?? 'phi3:mini'}
                        onChange={(e) => setEditForm({ ...editForm, llm_model: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white"
                      >
                        <option value="phi3:mini">phi3:mini (3.8B)</option>
                        <option value="llama3:8b">llama3:8b (8B)</option>
                        <option value="mistral:7b">mistral:7b (7B)</option>
                      </select>
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Schema (JSON)</label>
                    <textarea
                      rows={6}
                      value={typeof editForm.schema === 'string' ? editForm.schema : JSON.stringify(editForm.schema, null, 2)}
                      onChange={(e) => setEditForm({ ...editForm, schema: e.target.value as unknown as Record<string, unknown> })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Few-shot Examples (JSON array)</label>
                    <textarea
                      rows={6}
                      value={
                        typeof editForm.few_shot_examples === 'string'
                          ? editForm.few_shot_examples
                          : JSON.stringify(editForm.few_shot_examples, null, 2)
                      }
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          few_shot_examples: e.target.value as unknown as Array<Record<string, unknown>>,
                        })
                      }
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                    />
                  </div>
                  <div className="flex items-center gap-3">
                    <button
                      onClick={handleSaveEdit}
                      disabled={saving}
                      className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium disabled:opacity-50"
                    >
                      <Save className="h-4 w-4" />
                      {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                    <button
                      onClick={() => setEditingId(null)}
                      className="flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors text-sm"
                    >
                      <X className="h-4 w-4" />
                      Cancel
                    </button>
                  </div>
                </div>
              )}

              {/* Expanded View */}
              {expandedId === template.id && editingId !== template.id && (
                <div className="border-t border-gray-100 bg-gray-50 p-5 space-y-4">
                  <div>
                    <h4 className="text-sm font-semibold text-gray-700 mb-2">Schema</h4>
                    <pre className="bg-slate-800 text-slate-100 rounded-lg p-4 text-xs overflow-x-auto">
                      {JSON.stringify(template.schema, null, 2)}
                    </pre>
                  </div>
                  <div>
                    <h4 className="text-sm font-semibold text-gray-700 mb-2">
                      Few-shot Examples ({template.few_shot_examples?.length ?? 0})
                    </h4>
                    <pre className="bg-slate-800 text-slate-100 rounded-lg p-4 text-xs overflow-x-auto max-h-96">
                      {JSON.stringify(template.few_shot_examples, null, 2)}
                    </pre>
                  </div>
                  <div className="text-xs text-gray-400">
                    Created: {new Date(template.created_at).toLocaleString()} · Updated:{' '}
                    {new Date(template.updated_at).toLocaleString()}
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
