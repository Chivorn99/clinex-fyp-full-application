"use client";
import { useState, useEffect } from "react";
import { AuthProvider } from "@/contexts/AuthContext";
import AdminSidebar from "@/components/layout/AdminSidebar";
import ProtectedRoute from "@/components/auth/ProtectedRoute";

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // Prevent hydration mismatch: render nothing on the server,
  // show a loading skeleton until the client has mounted.
  if (!mounted) {
    return (
      <div className="flex min-h-screen bg-gray-50">
        {/* Sidebar placeholder */}
        <div className="w-70 min-h-screen bg-slate-900" />
        <main className="flex-1 flex items-center justify-center">
          <div className="animate-spin rounded-full h-10 w-10 border-2 border-indigo-500 border-t-transparent" />
        </main>
      </div>
    );
  }

  return (
    <AuthProvider>
      <ProtectedRoute>
        <div className="flex min-h-screen bg-gray-50">
          <AdminSidebar />
          <main className="flex-1 overflow-y-auto">{children}</main>
        </div>
      </ProtectedRoute>
    </AuthProvider>
  );
}
