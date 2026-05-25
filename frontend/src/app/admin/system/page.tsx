"use client";
import { useEffect, useState, useCallback } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { apiClient } from "@/lib/api";
import {
  Activity,
  Database,
  Brain,
  HardDrive,
  Clock,
  RefreshCw,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Cpu,
} from "lucide-react";

interface OllamaModel {
  name: string;
  size: number;
}

interface SystemHealth {
  database: {
    status: string;
    type?: string;
    error?: string;
  };
  ollama: {
    status: string;
    host?: string;
    models?: OllamaModel[];
    model_count?: number;
    error?: string;
  };
  queue: {
    pending_jobs: number;
    failed_jobs: number;
    processing_jobs: number;
  };
  disk: {
    uploads_size_bytes: number;
    uploads_size_human: string;
    uploads_file_count: number;
  };
}

export default function SystemPage() {
  const { hasPermission } = useAuth();
  const [health, setHealth] = useState<SystemHealth | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [lastChecked, setLastChecked] = useState<string>("");

  const fetchHealth = useCallback(async (showRefresh = false, isPolling = false) => {
    if (showRefresh) setRefreshing(true);
    else if (!isPolling) setLoading(true);
    try {
      const data = await apiClient.get("/admin/system-health");
      setHealth(data);
      setLastChecked(new Date().toLocaleString());
      if (!isPolling) setError(null);
    } catch (err) {
      if (!isPolling) {
        setError(
          err instanceof Error ? err.message : "Failed to load system health",
        );
      }
    } finally {
      if (!isPolling) setLoading(false);
      if (showRefresh) setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    if (hasPermission("view_system_health")) {
      fetchHealth();
      const interval = setInterval(() => {
        fetchHealth(false, true);
      }, 5000);
      return () => clearInterval(interval);
    } else {
      setLoading(false);
    }
  }, [hasPermission, fetchHealth]);

  const statusIcon = (status: string) => {
    switch (status) {
      case "healthy":
        return <CheckCircle2 className="h-5 w-5 text-emerald-500" />;
      case "unhealthy":
        return <XCircle className="h-5 w-5 text-red-500" />;
      default:
        return <AlertTriangle className="h-5 w-5 text-amber-500" />;
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case "healthy":
        return "border-emerald-200 bg-emerald-50/50";
      case "unhealthy":
        return "border-red-200 bg-red-50/50";
      default:
        return "border-amber-200 bg-amber-50/50";
    }
  };

  const formatBytes = (bytes: number): string => {
    if (bytes === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  };

  if (!hasPermission("view_system_health")) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">
            Access Restricted
          </h2>
          <p className="text-gray-500">
            You need the{" "}
            <code className="bg-gray-100 px-2 py-0.5 rounded text-sm">
              view_system_health
            </code>{" "}
            permission.
          </p>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="p-8 space-y-6">
        <div className="h-8 w-48 bg-gray-200 rounded animate-pulse" />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className="bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-pulse"
            >
              <div className="h-6 w-32 bg-gray-200 rounded mb-4" />
              <div className="h-4 w-48 bg-gray-100 rounded" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
          <p className="font-medium">Error</p>
          <p className="text-sm mt-1">{error}</p>
          <button
            onClick={() => fetchHealth()}
            className="mt-3 text-sm text-red-600 underline hover:text-red-800"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 lg:p-8 max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <Activity className="h-6 w-6 text-indigo-500" />
            System Health
          </h1>
          <p className="text-gray-500 mt-1">
            Real-time monitoring of all ClineX services.
          </p>
        </div>
        <button
          onClick={() => fetchHealth(true)}
          disabled={refreshing}
          className="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors shadow-sm"
        >
          <RefreshCw
            className={`h-4 w-4 ${refreshing ? "animate-spin" : ""}`}
          />
          Refresh
        </button>
      </div>

      {/* Service Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        {/* Database */}
        <div
          className={`rounded-xl p-5 border-2 ${statusColor(health?.database?.status || "unknown")} transition-colors`}
        >
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-white rounded-lg shadow-sm">
                <Database className="h-5 w-5 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">Database</h3>
                <p className="text-xs text-gray-500">
                  {health?.database?.type || "MySQL"}
                </p>
              </div>
            </div>
            {statusIcon(health?.database?.status || "unknown")}
          </div>
          <div className="flex items-center gap-2">
            <span
              className={`text-sm font-medium ${health?.database?.status === "healthy" ? "text-emerald-700" : "text-red-700"}`}
            >
              {health?.database?.status === "healthy"
                ? "Connected"
                : "Disconnected"}
            </span>
          </div>
          {health?.database?.error && (
            <p className="text-xs text-red-600 mt-2 bg-red-50 rounded p-2">
              {health.database.error}
            </p>
          )}
        </div>

        {/* Ollama */}
        <div
          className={`rounded-xl p-5 border-2 ${statusColor(health?.ollama?.status || "unknown")} transition-colors`}
        >
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-white rounded-lg shadow-sm">
                <Brain className="h-5 w-5 text-purple-600" />
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">Ollama LLM</h3>
                <p className="text-xs text-gray-500 font-mono">
                  {health?.ollama?.host || "N/A"}
                </p>
              </div>
            </div>
            {statusIcon(health?.ollama?.status || "unknown")}
          </div>
          {health?.ollama?.status === "healthy" ? (
            <div className="space-y-2">
              <span className="text-sm font-medium text-emerald-700">
                {health.ollama.model_count} model(s) loaded
              </span>
              {health.ollama.models && health.ollama.models.length > 0 && (
                <div className="space-y-1">
                  {health.ollama.models.map((m, idx) => (
                    <div
                      key={idx}
                      className="flex items-center justify-between bg-white/60 rounded-lg px-3 py-1.5"
                    >
                      <span className="text-xs font-medium text-gray-800 font-mono">
                        {m.name}
                      </span>
                      <span className="text-xs text-gray-500">
                        {formatBytes(m.size)}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ) : (
            <div>
              <span className="text-sm font-medium text-red-700">
                Not Available
              </span>
              {health?.ollama?.error && (
                <p className="text-xs text-red-600 mt-2 bg-red-50 rounded p-2">
                  {health.ollama.error}
                </p>
              )}
            </div>
          )}
        </div>

        {/* Queue Worker */}
        <div className="rounded-xl p-5 border-2 border-gray-200 bg-white transition-colors">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-gray-50 rounded-lg shadow-sm">
                <Cpu className="h-5 w-5 text-indigo-600" />
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">Queue Worker</h3>
                <p className="text-xs text-gray-500">Laravel job processing</p>
              </div>
            </div>
            {health?.queue &&
            (health.queue.pending_jobs > 0 ||
              health.queue.processing_jobs > 0) ? (
              <div className="flex items-center gap-1">
                <RefreshCw className="h-4 w-4 text-blue-500 animate-spin" />
                <span className="text-xs text-blue-600 font-medium">
                  Active
                </span>
              </div>
            ) : (
              <CheckCircle2 className="h-5 w-5 text-emerald-500" />
            )}
          </div>
          <div className="grid grid-cols-3 gap-3">
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500 mb-1">Pending</p>
              <p className="text-xl font-bold text-gray-900">
                {health?.queue?.pending_jobs ?? 0}
              </p>
            </div>
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500 mb-1">Processing</p>
              <p className="text-xl font-bold text-blue-600">
                {health?.queue?.processing_jobs ?? 0}
              </p>
            </div>
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500 mb-1">Failed</p>
              <p
                className={`text-xl font-bold ${(health?.queue?.failed_jobs ?? 0) > 0 ? "text-red-600" : "text-gray-900"}`}
              >
                {health?.queue?.failed_jobs ?? 0}
              </p>
            </div>
          </div>
        </div>

        {/* Disk */}
        <div className="rounded-xl p-5 border-2 border-gray-200 bg-white transition-colors">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-gray-50 rounded-lg shadow-sm">
                <HardDrive className="h-5 w-5 text-teal-600" />
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">Disk Storage</h3>
                <p className="text-xs text-gray-500">Uploads directory</p>
              </div>
            </div>
            <CheckCircle2 className="h-5 w-5 text-emerald-500" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500 mb-1">Total Size</p>
              <p className="text-xl font-bold text-gray-900">
                {health?.disk?.uploads_size_human ?? "0 B"}
              </p>
            </div>
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500 mb-1">Files</p>
              <p className="text-xl font-bold text-gray-900">
                {health?.disk?.uploads_file_count ?? 0}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Timestamp */}
      <div className="flex items-center justify-center gap-2 text-xs text-gray-400">
        <Clock className="h-3 w-3" />
        Last checked: {lastChecked || "—"}
      </div>
    </div>
  );
}
