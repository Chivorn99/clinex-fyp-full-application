'use client'
import Link from 'next/link'
import { useState, useEffect } from 'react'
import DashboardLayout from '@/components/layout/DashboardLayout'
import { User, Building2, Mail, Phone, MapPin, Calendar, Edit3, Save, X, Camera, Shield, Bell, Globe } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/api'

export default function ProfilePage() {
    const { user: authUser } = useAuth()
    const [isEditing, setIsEditing] = useState(false)
    const [activeTab, setActiveTab] = useState<'profile' | 'clinic' | 'security'>('profile')
    const [loading, setLoading] = useState(true)

    const [userData, setUserData] = useState({
        name: 'Dr. Sarah Johnson',
        email: 'sarah@smithclinic.com',
        phone: '+1 (555) 123-4567',
        specialization: 'General Practitioner',
        role: 'Lab Technician',
        joinDate: '2023-01-15',
        profileImage: '/api/placeholder/150/150'
    })

    useEffect(() => {
        const fetchUserProfile = async () => {
            try {
                setLoading(true)
                const response = await apiClient.get('/profile')
                if (response.success && response.data && response.data.user) {
                    const user = response.data.user

                    setUserData({
                        name: user.name || 'Dr. Sarah Johnson',
                        email: user.email || 'sarah@smithclinic.com',
                        phone: user.phone_number || '+1 (555) 123-4567',
                        specialization: user.specialization || 'General Practitioner',
                        role: user.role || 'Lab Technician',
                        joinDate: user.created_at || '2023-01-15',
                        profileImage: response.data.profile_picture_url || '/api/placeholder/150/150'
                    })
                } else {
                    setUserData({
                        name: authUser?.name || 'Dr. Sarah Johnson',
                        email: authUser?.email || 'sarah@smithclinic.com',
                        phone: authUser?.phone_number || '+1 (555) 123-4567',
                        specialization: authUser?.specialization || 'General Practitioner',
                        role: authUser?.role || 'Lab Technician',
                        joinDate: authUser?.created_at || '2023-01-15',
                        profileImage: authUser?.profile_picture_url || '/api/placeholder/150/150'
                    })
                }
            } catch (error) {
                console.error('Failed to fetch user profile:', error)
                setUserData({
                    name: authUser?.name || 'Dr. Sarah Johnson',
                    email: authUser?.email || 'sarah@smithclinic.com',
                    phone: authUser?.phone_number || '+1 (555) 123-4567',
                    specialization: authUser?.specialization || 'General Practitioner',
                    role: authUser?.role || 'Lab Technician',
                    joinDate: authUser?.created_at || '2023-01-15',
                    profileImage: authUser?.profile_picture_url || '/api/placeholder/150/150'
                })
            } finally {
                setLoading(false)
            }
        }

        if (authUser) {
            fetchUserProfile()
        } else {
            setLoading(false)
        }
    }, [authUser])

    const [clinicData, setClinicData] = useState({
        name: 'Smith Medical Clinic',
        address: '123 Healthcare Ave, Medical District',
        city: 'New York',
        state: 'NY',
        zipCode: '10001',
        phone: '+1 (555) 987-6543',
        email: 'info@smithclinic.com',
        website: 'www.smithclinic.com',
        establishedYear: '2020',
        specialties: ['General Medicine', 'Pediatrics', 'Cardiology']
    })

    const [securitySettings, setSecuritySettings] = useState({
        twoFactorEnabled: true,
        loginNotifications: true,
        sessionTimeout: '30'
    })


    const [selectedFile, setSelectedFile] = useState<File | null>(null)

    const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0]
        if (file) {
            setSelectedFile(file)
            const reader = new FileReader()
            reader.onload = (e) => {
                setUserData({ ...userData, profileImage: e.target?.result as string })
            }
            reader.readAsDataURL(file)
        }
    }

    const handleSave = async () => {
        try {
            setLoading(true)
            const formData = new FormData()
            formData.append('name', userData.name)
            formData.append('email', userData.email)
            formData.append('phone_number', userData.phone)
            formData.append('specialization', userData.specialization)
            
            if (selectedFile) {
                formData.append('profile_pic', selectedFile)
            }

            const response = await apiClient.request('/profile/update', {
                method: 'POST',
                body: formData,
            })

            if (response.success) {
                setIsEditing(false)
                setSelectedFile(null)
                
                if (response.data && response.data.user) {
                    const updatedUser = response.data.user
                    setUserData({
                        name: updatedUser.name || userData.name,
                        email: updatedUser.email || userData.email,
                        phone: updatedUser.phone_number || userData.phone,
                        specialization: updatedUser.specialization || userData.specialization,
                        role: updatedUser.role || userData.role,
                        joinDate: updatedUser.created_at || userData.joinDate,
                        profileImage: response.data.profile_picture_url || userData.profileImage
                    })
                }
                
                console.log('Profile updated successfully')
            }
        } catch (error) {
            console.error('Failed to update profile:', error)
        } finally {
            setLoading(false)
        }
    }

    const handleCancel = () => {
        setIsEditing(false)
    }

    if (loading) {
        return (
            <DashboardLayout>
                <div className="flex items-center justify-center min-h-96">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            </DashboardLayout>
        )
    }

    return (
        <DashboardLayout>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Profile Settings</h1>
                        <p className="mt-2 text-gray-600">Manage your account and clinic information</p>
                    </div>
                    {!isEditing ? (
                        <button
                            onClick={() => setIsEditing(true)}
                            className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <Edit3 className="h-4 w-4 mr-2" />
                            Edit Profile
                        </button>
                    ) : (
                        <div className="flex space-x-3">
                            <button
                                onClick={handleCancel}
                                className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                <X className="h-4 w-4 mr-2" />
                                Cancel
                            </button>
                            <button
                                onClick={handleSave}
                                disabled={loading}
                                className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                            >
                                {loading ? (
                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                ) : (
                                    <Save className="h-4 w-4 mr-2" />
                                )}
                                Save Changes
                            </button>
                        </div>
                    )}
                </div>

                {/* Tab Navigation */}
                <div className="bg-white shadow rounded-lg">
                    <div className="border-b border-gray-200">
                        <nav className="-mb-px flex space-x-8 px-6">
                            {[
                                { key: 'profile', label: 'Personal Info', icon: User },
                                // { key: 'clinic', label: 'Clinic Details', icon: Building2 },
                                { key: 'security', label: 'Security', icon: Shield },
                            ].map((tab) => {
                                const Icon = tab.icon
                                return (
                                    <button
                                        key={tab.key}
                                        onClick={() => setActiveTab(tab.key as any)}
                                        className={`${activeTab === tab.key
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                            } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center`}
                                    >
                                        <Icon className="h-4 w-4 mr-2" />
                                        {tab.label}
                                    </button>
                                )
                            })}
                        </nav>
                    </div>

                    {/* Tab Content */}
                    <div className="p-6">
                        {activeTab === 'profile' && (
                            <div className="space-y-6">
                                <div className="flex items-center space-x-6">
                                    <div className="relative">
                                        <img
                                            className="h-24 w-24 rounded-full object-cover"
                                            src={userData.profileImage}
                                            alt="Profile"
                                        />
                                        {isEditing && (
                                            <>
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleFileSelect}
                                                    className="hidden"
                                                    id="profile-upload"
                                                />
                                                <label
                                                    htmlFor="profile-upload"
                                                    className="absolute bottom-0 right-0 h-8 w-8 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 cursor-pointer"
                                                >
                                                    <Camera className="h-4 w-4" />
                                                </label>
                                            </>
                                        )}
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-medium text-gray-900">{userData.name}</h3>
                                        <p className="text-sm text-gray-500">{userData.specialization}</p>
                                        <p className="text-sm text-gray-500">Joined {new Date(userData.joinDate).toLocaleDateString()}</p>
                                    </div>
                                </div>

                                {/* Personal Information Form */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Full Name
                                        </label>
                                        <div className="relative">
                                            <User className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                                            <input
                                                type="text"
                                                value={userData.name}
                                                onChange={(e) => setUserData({ ...userData, name: e.target.value })}
                                                disabled={!isEditing}
                                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Email Address
                                        </label>
                                        <div className="relative">
                                            <Mail className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                                            <input
                                                type="email"
                                                value={userData.email}
                                                onChange={(e) => setUserData({ ...userData, email: e.target.value })}
                                                disabled={!isEditing}
                                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Phone Number
                                        </label>
                                        <div className="relative">
                                            <Phone className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                                            <input
                                                type="tel"
                                                value={userData.phone}
                                                onChange={(e) => setUserData({ ...userData, phone: e.target.value })}
                                                disabled={!isEditing}
                                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Specialization
                                        </label>
                                        <input
                                            type="text"
                                            value={userData.specialization}
                                            onChange={(e) => setUserData({ ...userData, specialization: e.target.value })}
                                            disabled={!isEditing}
                                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Role
                                        </label>
                                        <input
                                            type="text"
                                            value={userData.role}
                                            onChange={(e) => setUserData({ ...userData, role: e.target.value })}
                                            disabled={!isEditing}
                                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Join Date
                                        </label>
                                        <div className="relative">
                                            <Calendar className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                                            <input
                                                type="date"
                                                value={userData.joinDate}
                                                disabled={true}
                                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Security Tab */}
                        {activeTab === 'security' && (
                            <div className="space-y-6">
                                <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                    <div className="flex">
                                        <Shield className="h-5 w-5 text-yellow-400" />
                                        <div className="ml-3">
                                            <h3 className="text-sm font-medium text-yellow-800">
                                                Security Settings
                                            </h3>
                                            <p className="mt-1 text-sm text-yellow-700">
                                                These settings help protect your account and clinic data.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    {/* Change Password Option */}
                                    <div className="border-b border-gray-200 pb-4">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <label className="text-sm font-medium text-gray-900">
                                                    Password
                                                </label>
                                                <p className="text-sm text-gray-500">
                                                    Update your password to keep your account secure
                                                </p>
                                            </div>
                                            <Link
                                                href="/auth/reset-password"
                                                className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                Change Password
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    )
}