"use client";
import { useState, useEffect } from "react";
import { apiClient } from "@/lib/api";
import { Plus, Edit2, Trash2, Database, BookOpen } from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";

interface Template {
  id: number;
  name: string;
}

interface VerifiedExample {
  id: number;
  template_id: number;
  raw_text: string;
  corrected_json: Record<string, unknown>;
  is_active: boolean;
  created_at: string;
  template?: Template;
}

export default function TrainingDataPage() {
  const { hasPermission } = useAuth();
  const [examples, setExamples] = useState<VerifiedExample[]>([]);
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingExample, setEditingExample] = useState<VerifiedExample | null>(null);
  
  const [formData, setFormData] = useState({
    template_id: "",
    raw_text: "",
    corrected_json: "",
    is_active: true
  });

  useEffect(() => {
    if (hasPermission('manage_templates')) {
      fetchTemplates();
      fetchExamples();
    } else {
      setLoading(false);
    }
  }, [hasPermission]);

  const fetchTemplates = async () => {
    try {
      const res = await apiClient.get('/admin/templates');
      setTemplates(res.data || []);
    } catch (e) {
      console.error("Failed to fetch templates", e);
    }
  };

  const fetchExamples = async () => {
    try {
      setLoading(true);
      const res = await apiClient.get('/admin/training-data');
      setExamples(res.data.data || res.data || []);
    } catch (e) {
      console.error("Failed to fetch examples", e);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (example?: VerifiedExample) => {
    if (example) {
      setEditingExample(example);
      setFormData({
        template_id: example.template_id.toString(),
        raw_text: example.raw_text,
        corrected_json: JSON.stringify(example.corrected_json, null, 2),
        is_active: example.is_active
      });
    } else {
      setEditingExample(null);
      setFormData({
        template_id: templates[0]?.id?.toString() || "",
        raw_text: "",
        corrected_json: "{\n  \n}",
        is_active: true
      });
    }
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingExample(null);
  };

  const handleSave = async () => {
    try {
      let parsedJson = {};
      try {
        parsedJson = JSON.parse(formData.corrected_json);
      } catch {
        alert("Invalid JSON format in Corrected Data");
        return;
      }

      const payload = {
        template_id: parseInt(formData.template_id),
        raw_text: formData.raw_text,
        corrected_json: parsedJson,
        is_active: formData.is_active
      };

      if (editingExample) {
        await apiClient.put(`/admin/training-data/${editingExample.id}`, payload);
      } else {
        await apiClient.post('/admin/training-data', payload);
      }
      
      handleCloseModal();
      fetchExamples();
    } catch (e) {
      alert("Failed to save example");
      console.error(e);
    }
  };

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this training example?")) {
      try {
        await apiClient.delete(`/admin/training-data/${id}`);
        fetchExamples();
      } catch {
        alert("Failed to delete example");
      }
    }
  };

  if (!hasPermission('manage_templates')) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Access Restricted</h2>
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="p-8 space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 flex items-center">
              <Database className="h-8 w-8 mr-3 text-blue-600" />
              LLM Training Data
            </h1>
            <p className="mt-2 text-gray-600">
              Manage verified examples to fine-tune and improve OCR extraction accuracy.
            </p>
          </div>
          <button
            onClick={() => handleOpenModal()}
            className="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            <Plus className="h-5 w-5 mr-2" />
            Add Example
          </button>
        </div>

        <div className="bg-white shadow rounded-lg overflow-hidden">
          {loading ? (
            <div className="p-8 text-center text-gray-500">Loading examples...</div>
          ) : examples.length === 0 ? (
            <div className="p-12 text-center">
              <BookOpen className="h-12 w-12 text-gray-300 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-1">No Training Data</h3>
              <p className="text-gray-500">Add manual examples or verify reports to populate training data.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preview Text</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {examples.map((ex) => (
                    <tr key={ex.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {ex.template?.name || `Template ${ex.template_id}`}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-500">
                        <div className="max-w-md truncate">
                          {ex.raw_text.substring(0, 100)}...
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${ex.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {ex.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onClick={() => handleOpenModal(ex)} className="text-blue-600 hover:text-blue-900 mr-4">
                          <Edit2 className="h-4 w-4" />
                        </button>
                        <button onClick={() => handleDelete(ex.id)} className="text-red-600 hover:text-red-900">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-4xl w-full max-h-[90vh] flex flex-col">
            <h2 className="text-xl font-bold mb-4">{editingExample ? "Edit Example" : "Add New Example"}</h2>
            
            <div className="flex-1 overflow-y-auto space-y-4 pr-2">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Template</label>
                <select
                  value={formData.template_id}
                  onChange={(e) => setFormData({...formData, template_id: e.target.value})}
                  className="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="" disabled>Select Template</option>
                  {templates.map(t => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 h-96">
                <div className="flex flex-col">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Raw OCR Text</label>
                  <textarea
                    value={formData.raw_text}
                    onChange={(e) => setFormData({...formData, raw_text: e.target.value})}
                    className="flex-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                    placeholder="Paste raw text here..."
                  />
                </div>
                <div className="flex flex-col">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Corrected JSON Data</label>
                  <textarea
                    value={formData.corrected_json}
                    onChange={(e) => setFormData({...formData, corrected_json: e.target.value})}
                    className="flex-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                    placeholder="{\n  &quot;key&quot;: &quot;value&quot;\n}"
                  />
                </div>
              </div>

              <div className="flex items-center">
                <input
                  type="checkbox"
                  id="isActive"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({...formData, is_active: e.target.checked})}
                  className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label htmlFor="isActive" className="ml-2 block text-sm text-gray-900">
                  Active (Used for LLM Training)
                </label>
              </div>
            </div>

            <div className="mt-6 flex justify-end space-x-3 pt-4 border-t">
              <button
                onClick={handleCloseModal}
                className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={handleSave}
                disabled={!formData.template_id || !formData.raw_text || !formData.corrected_json}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                Save Example
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
