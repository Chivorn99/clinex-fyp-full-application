"use client";
import { useEffect, useState, useCallback } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { apiClient } from "@/lib/api";
import {
  FileText,
  Search,
  Trash2,
  RefreshCw,
  Check,
  AlertCircle,
  CheckCircle2,
  Clock,
  XCircle,
  Filter,
} from "lucide-react";

interface Report {
  id: number;
  original_filename: string;
  status: string;
  verified_at: string | null;
  verified_by: number | null;
  created_at: string;
  patient?: { name: string; id: number };
}

interface PaginatedResponse {
  data: Report[];
  current_page: number;
  last_page: number;
  total: number;
}

export default function ReportsPage() {
  const { hasPermission } = useAuth();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [verifiedFilter, setVerifiedFilter] = useState<string>("all");
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);
  const [toast, setToast] = useState<{
    type: "success" | "error";
    message: string;
  } | null>(null);

  const showToast = (type: "success" | "error", message: string) => {
    setToast({ type, message });
    setTimeout(() => setToast(null), 3000);
  };

  const fetchReports = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: "20",
      });
      if (statusFilter !== "all") params.set("status", statusFilter);
      if (verifiedFilter !== "all") params.set("is_verified", verifiedFilter);
      if (search) params.set("search", search);

      const data: PaginatedResponse = await apiClient.get(
        `/admin/reports?${params}`,
      );
      setReports(data.data);
      setTotalPages(data.last_page);
      setTotal(data.total);
    } catch (err) {
      showToast(
        "error",
        err instanceof Error ? err.message : "Failed to load reports",
      );
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, verifiedFilter, search]);

  useEffect(() => {
    if (hasPermission("manage_reports")) {
      fetchReports();
    } else {
      setLoading(false);
    }
  }, [hasPermission, fetchReports]);

  const toggleSelect = (id: number) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selected.size === reports.length) {
      setSelected(new Set());
    } else {
      setSelected(new Set(reports.map((r) => r.id)));
    }
  };

  const handleBulkDelete = async () => {
    if (selected.size === 0) return;
    if (!confirm(`Delete ${selected.size} report(s)? This cannot be undone.`))
      return;
    setBulkLoading(true);
    try {
      const result = await apiClient.post("/admin/reports/bulk-delete", {
        report_ids: Array.from(selected),
      });
      showToast(
        "success",
        (result as { message?: string }).message || "Reports deleted",
      );
      setSelected(new Set());
      fetchReports();
    } catch (err) {
      showToast(
        "error",
        err instanceof Error ? err.message : "Bulk delete failed",
      );
    } finally {
      setBulkLoading(false);
    }
  };

  const handleBulkReprocess = async () => {
    if (selected.size === 0) return;
    if (!confirm(`Reprocess ${selected.size} report(s)?`)) return;
    setBulkLoading(true);
    try {
      const result = await apiClient.post("/admin/reports/bulk-reprocess", {
        report_ids: Array.from(selected),
      });
      showToast(
        "success",
        (result as { message?: string }).message || "Reports queued",
      );
      setSelected(new Set());
      fetchReports();
    } catch (err) {
      showToast(
        "error",
        err instanceof Error ? err.message : "Bulk reprocess failed",
      );
    } finally {
      setBulkLoading(false);
    }
  };

  const statusBadge = (status: string) => {
    const styles: Record<string, string> = {
      completed: "bg-emerald-100 text-emerald-700",
      processed: "bg-emerald-100 text-emerald-700",
      processing: "bg-blue-100 text-blue-700",
      pending: "bg-amber-100 text-amber-700",
      failed: "bg-red-100 text-red-700",
    };
    const icons: Record<string, React.ReactNode> = {
      completed: <CheckCircle2 className="h-3 w-3" />,
      processed: <CheckCircle2 className="h-3 w-3" />,
      processing: <RefreshCw className="h-3 w-3 animate-spin" />,
      pending: <Clock className="h-3 w-3" />,
      failed: <XCircle className="h-3 w-3" />,
    };
    return (
      <span
        className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${styles[status] || "bg-gray-100 text-gray-600"}`}
      >
        {icons[status]} {status}
      </span>
    );
  };

  if (!hasPermission("manage_reports")) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">
            Access Restricted
          </h2>
          <p className="text-gray-500">
            You need the{" "}
            <code className="bg-gray-100 px-2 py-0.5 rounded text-sm">
              manage_reports
            </code>{" "}
            permission.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
      {/* Toast */}
      {toast && (
        <div
          className={`fixed top-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium ${
            toast.type === "success"
              ? "bg-emerald-50 text-emerald-800 border border-emerald-200"
              : "bg-red-50 text-red-800 border border-red-200"
          }`}
        >
          {toast.type === "success" ? (
            <Check className="h-4 w-4" />
          ) : (
            <AlertCircle className="h-4 w-4" />
          )}
          {toast.message}
        </div>
      )}

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <FileText className="h-6 w-6 text-indigo-500" />
          Report Oversight
        </h1>
        <p className="text-gray-500 mt-1">
          {total} total report{total !== 1 ? "s" : ""}. Filter, inspect, and
          manage in bulk.
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Search by filename or patient name..."
            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
          />
        </div>
        <div className="flex gap-2">
          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="px-3 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none bg-white"
          >
            <option value="all">All Status</option>
            <option value="processed">Processed</option>
            <option value="processing">Processing</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
          </select>
          <select
            value={verifiedFilter}
            onChange={(e) => {
              setVerifiedFilter(e.target.value);
              setPage(1);
            }}
            className="px-3 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none bg-white"
          >
            <option value="all">All Verification</option>
            <option value="true">Verified</option>
            <option value="false">Unverified</option>
          </select>
        </div>
      </div>

      {/* Bulk Actions */}
      {selected.size > 0 && (
        <div className="bg-indigo-50 border border-indigo-200 rounded-xl p-3 flex items-center justify-between">
          <span className="text-sm text-indigo-700 font-medium">
            {selected.size} report(s) selected
          </span>
          <div className="flex gap-2">
            <button
              onClick={handleBulkReprocess}
              disabled={bulkLoading}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
              <RefreshCw
                className={`h-3.5 w-3.5 ${bulkLoading ? "animate-spin" : ""}`}
              />
              Reprocess
            </button>
            <button
              onClick={handleBulkDelete}
              disabled={bulkLoading}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors"
            >
              <Trash2 className="h-3.5 w-3.5" />
              Delete
            </button>
            <button
              onClick={() => setSelected(new Set())}
              className="px-3 py-1.5 text-gray-600 text-sm rounded-lg hover:bg-white transition-colors"
            >
              Clear
            </button>
          </div>
        </div>
      )}

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {loading ? (
          <div className="p-8 space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="h-12 bg-gray-100 rounded animate-pulse" />
            ))}
          </div>
        ) : reports.length === 0 ? (
          <div className="p-12 text-center">
            <Filter className="h-12 w-12 text-gray-300 mx-auto mb-4" />
            <h3 className="text-lg font-semibold text-gray-900">
              No Reports Found
            </h3>
            <p className="text-gray-500 text-sm mt-1">
              Try adjusting your filters.
            </p>
          </div>
        ) : (
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-100 bg-gray-50/80">
                <th className="px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    checked={
                      selected.size === reports.length && reports.length > 0
                    }
                    onChange={toggleSelectAll}
                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Filename
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Patient
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Verified
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Date
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {reports.map((r) => (
                <tr
                  key={r.id}
                  className={`hover:bg-gray-50/50 transition-colors ${selected.has(r.id) ? "bg-indigo-50/30" : ""}`}
                >
                  <td className="px-4 py-3">
                    <input
                      type="checkbox"
                      checked={selected.has(r.id)}
                      onChange={() => toggleSelect(r.id)}
                      className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                  </td>
                  <td
                    className="px-4 py-3 text-sm text-gray-900 font-medium max-w-50 truncate"
                    title={r.original_filename}
                  >
                    {r.original_filename}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600">
                    {r.patient?.name || (
                      <span className="text-gray-400 italic">Unlinked</span>
                    )}
                  </td>
                  <td className="px-4 py-3">{statusBadge(r.status)}</td>
                  <td className="px-4 py-3">
                    {r.verified_at ? (
                      <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                        <CheckCircle2 className="h-3.5 w-3.5" /> Yes
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 text-xs text-amber-600 font-medium">
                        <Clock className="h-3.5 w-3.5" /> No
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500">
                    {new Date(r.created_at).toLocaleDateString()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-500">
            Page {page} of {totalPages} ({total} total)
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
            >
              Previous
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page === totalPages}
              className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
