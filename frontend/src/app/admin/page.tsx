"use client";
import { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { apiClient } from "@/lib/api";
import {
  Users,
  FileText,
  CheckCircle2,
  AlertTriangle,
  TrendingUp,
  Clock,
  Activity,
  UserPlus,
} from "lucide-react";

interface DashboardStats {
  total_users: number;
  total_reports: number;
  total_patients: number;
  total_batches: number;
  verified_reports: number;
  unverified_reports: number;
  processing_reports: number;
  failed_reports: number;
  users_by_role: Record<string, number>;
  reports_this_week: number;
  reports_today: number;
}

export default function AdminDashboardPage() {
  const { user, hasPermission } = useAuth();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const data = await apiClient.get("/admin/dashboard");
        setStats(data);
      } catch (err) {
        setError(
          err instanceof Error ? err.message : "Failed to load dashboard",
        );
      } finally {
        setLoading(false);
      }
    };

    if (hasPermission("view_analytics")) {
      fetchStats();
    } else {
      setLoading(false);
    }
  }, [hasPermission]);

  if (!hasPermission("view_analytics")) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">
            Access Restricted
          </h2>
          <p className="text-gray-500">
            You need the{" "}
            <code className="bg-gray-100 px-2 py-0.5 rounded text-sm">
              view_analytics
            </code>{" "}
            permission to see this page.
          </p>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="p-8 space-y-6">
        <div className="h-8 w-64 bg-gray-200 rounded-lg animate-pulse" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {Array.from({ length: 8 }).map((_, i) => (
            <div
              key={i}
              className="bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-pulse"
            >
              <div className="h-4 w-24 bg-gray-200 rounded mb-3" />
              <div className="h-8 w-16 bg-gray-200 rounded" />
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
          <p className="font-medium">Error loading dashboard</p>
          <p className="text-sm mt-1">{error}</p>
        </div>
      </div>
    );
  }

  const statCards = [
    {
      label: "Total Users",
      value: stats?.total_users ?? 0,
      icon: <Users className="h-5 w-5" />,
      gradient: "from-blue-500 to-blue-600",
      bgLight: "bg-blue-50",
      textColor: "text-blue-600",
    },
    {
      label: "Total Reports",
      value: stats?.total_reports ?? 0,
      icon: <FileText className="h-5 w-5" />,
      gradient: "from-indigo-500 to-indigo-600",
      bgLight: "bg-indigo-50",
      textColor: "text-indigo-600",
    },
    {
      label: "Verified",
      value: stats?.verified_reports ?? 0,
      icon: <CheckCircle2 className="h-5 w-5" />,
      gradient: "from-emerald-500 to-emerald-600",
      bgLight: "bg-emerald-50",
      textColor: "text-emerald-600",
    },
    {
      label: "Unverified",
      value: stats?.unverified_reports ?? 0,
      icon: <Clock className="h-5 w-5" />,
      gradient: "from-amber-500 to-amber-600",
      bgLight: "bg-amber-50",
      textColor: "text-amber-600",
    },
    {
      label: "Processing",
      value: stats?.processing_reports ?? 0,
      icon: <Activity className="h-5 w-5" />,
      gradient: "from-cyan-500 to-cyan-600",
      bgLight: "bg-cyan-50",
      textColor: "text-cyan-600",
    },
    {
      label: "Failed",
      value: stats?.failed_reports ?? 0,
      icon: <AlertTriangle className="h-5 w-5" />,
      gradient: "from-red-500 to-red-600",
      bgLight: "bg-red-50",
      textColor: "text-red-600",
    },
    {
      label: "Reports Today",
      value: stats?.reports_today ?? 0,
      icon: <TrendingUp className="h-5 w-5" />,
      gradient: "from-violet-500 to-violet-600",
      bgLight: "bg-violet-50",
      textColor: "text-violet-600",
    },
    {
      label: "This Week",
      value: stats?.reports_this_week ?? 0,
      icon: <TrendingUp className="h-5 w-5" />,
      gradient: "from-fuchsia-500 to-fuchsia-600",
      bgLight: "bg-fuchsia-50",
      textColor: "text-fuchsia-600",
    },
  ];

  return (
    <div className="p-6 lg:p-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Dashboard Overview</h1>
        <p className="text-gray-500 mt-1">
          Welcome back,{" "}
          <span className="font-medium text-gray-700">{user?.name}</span>.
          Here&apos;s your system summary.
        </p>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {statCards.map((card) => (
          <div
            key={card.label}
            className="bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200 group"
          >
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm font-medium text-gray-500">
                {card.label}
              </span>
              <div
                className={`${card.bgLight} p-2 rounded-lg ${card.textColor} group-hover:scale-110 transition-transform duration-200`}
              >
                {card.icon}
              </div>
            </div>
            <p className="text-3xl font-bold text-gray-900">
              {card.value.toLocaleString()}
            </p>
          </div>
        ))}
      </div>

      {/* Users by Role */}
      {stats?.users_by_role && Object.keys(stats.users_by_role).length > 0 && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <UserPlus className="h-5 w-5 text-indigo-500" />
            Users by Role
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {Object.entries(stats.users_by_role).map(([role, count]) => {
              const roleColors: Record<string, string> = {
                admin: "bg-purple-100 text-purple-700 border-purple-200",
                doctor: "bg-blue-100 text-blue-700 border-blue-200",
                lab_technician:
                  "bg-emerald-100 text-emerald-700 border-emerald-200",
              };
              const colorClass =
                roleColors[role] || "bg-gray-100 text-gray-700 border-gray-200";
              return (
                <div
                  key={role}
                  className={`rounded-xl p-4 border ${colorClass} flex items-center justify-between`}
                >
                  <span className="font-medium capitalize">
                    {role.replace("_", " ")}
                  </span>
                  <span className="text-2xl font-bold">{count}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Quick Info */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Quick Info</h2>
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
          <div className="p-3 bg-gray-50 rounded-lg">
            <span className="text-gray-500">Total Patients</span>
            <p className="text-xl font-bold text-gray-900 mt-1">
              {stats?.total_patients ?? 0}
            </p>
          </div>
          <div className="p-3 bg-gray-50 rounded-lg">
            <span className="text-gray-500">Total Batches</span>
            <p className="text-xl font-bold text-gray-900 mt-1">
              {stats?.total_batches ?? 0}
            </p>
          </div>
          <div className="p-3 bg-gray-50 rounded-lg">
            <span className="text-gray-500">Verification Rate</span>
            <p className="text-xl font-bold text-gray-900 mt-1">
              {stats && stats.total_reports > 0
                ? Math.round(
                    (stats.verified_reports / stats.total_reports) * 100,
                  )
                : 0}
              %
            </p>
          </div>
          <div className="p-3 bg-gray-50 rounded-lg">
            <span className="text-gray-500">Failure Rate</span>
            <p className="text-xl font-bold text-gray-900 mt-1">
              {stats && stats.total_reports > 0
                ? Math.round((stats.failed_reports / stats.total_reports) * 100)
                : 0}
              %
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
