const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

class ApiError extends Error {
  constructor(public status: number, message: string, public errors?: any) {
    super(message)
    this.name = 'ApiError'
  }
}

export const apiClient = {
  async request(endpoint: string, options: RequestInit = {}) {
    const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null

    const config: RequestInit = {
      headers: {
        'Accept': 'application/json',
        ...(token && { 'Authorization': `Bearer ${token}` }),
        ...options.headers,
      },
      ...options,
    }

    // Only set Content-Type for non-FormData requests
    if (!(options.body instanceof FormData)) {
      (config.headers as Record<string, string>)['Content-Type'] = 'application/json'
    }

    try {
      const response = await fetch(`${API_BASE_URL}${endpoint}`, config)
      
      // Handle different response types
      const contentType = response.headers.get('content-type')
      let result
      
      if (contentType && contentType.includes('application/json')) {
        result = await response.json()
      } else {
        result = { message: await response.text() }
      }

      if (!response.ok) {
        throw new ApiError(
          response.status,
          result.message || `HTTP ${response.status}: ${response.statusText}`,
          result.errors
        )
      }

      return result
    } catch (error) {
      if (error instanceof ApiError) {
        throw error
      }
      
      // Handle network errors
      if (error instanceof TypeError && error.message.includes('Failed to fetch')) {
        throw new Error(`Cannot connect to server. Please ensure the backend is running at ${API_BASE_URL}`)
      }
      
      // Check if error is an Error instance before accessing message property
      if (error instanceof Error) {
        throw new Error(`Network error: ${error.message}`)
      }
      
      // If it's some other type of error, convert to string
      throw new Error(`Network error: ${String(error)}`)
    }
  },

  async get(endpoint: string) {
    return this.request(endpoint, {
      method: 'GET',
    })
  },

  async post(endpoint: string, data?: any) {
    const body = data instanceof FormData ? data : JSON.stringify(data)
    
    return this.request(endpoint, {
      method: 'POST',
      body,
    })
  },

  async put(endpoint: string, data: any) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    })
  },

  async patch(endpoint: string, data: any) {
    return this.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
  },

  async delete(endpoint: string) {
    return this.request(endpoint, {
      method: 'DELETE',
    })
  }
}

// Export the ApiError class for use in components
export { ApiError }