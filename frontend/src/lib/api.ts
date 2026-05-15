const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

type JsonValue = string | number | boolean | null | JsonObject | JsonValue[]
type JsonObject = { [key: string]: JsonValue }

const extractApiErrorMessage = (result: unknown, fallback: string): string => {
  if (typeof result !== 'object' || result === null) {
    return fallback
  }

  const maybeMessage = (result as { message?: unknown }).message
  if (typeof maybeMessage === 'string' && maybeMessage.trim().length > 0) {
    return maybeMessage
  }

  const maybeErrors = (result as { errors?: unknown }).errors
  if (typeof maybeErrors === 'object' && maybeErrors !== null) {
    const firstFieldErrors = Object.values(maybeErrors as Record<string, unknown>)[0]
    if (Array.isArray(firstFieldErrors) && typeof firstFieldErrors[0] === 'string') {
      return firstFieldErrors[0]
    }
  }

  return fallback
}

class ApiError extends Error {
  constructor(public status: number, message: string, public errors?: unknown) {
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
        // Handle expired session — silently redirect to login
        if (response.status === 401 && typeof window !== 'undefined') {
          localStorage.removeItem('auth_token')
          localStorage.removeItem('user')
          window.location.href = '/auth/login'
          // Return a never-resolving promise so no error UI flashes
          return new Promise(() => {})
        }

        const fallback = `HTTP ${response.status}: ${response.statusText}`
        throw new ApiError(
          response.status,
          extractApiErrorMessage(result, fallback),
          typeof result === 'object' && result !== null ? (result as { errors?: unknown }).errors : undefined
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

  async post(endpoint: string, data?: JsonObject | FormData) {
    const body = data instanceof FormData ? data : JSON.stringify(data)
    
    return this.request(endpoint, {
      method: 'POST',
      body,
    })
  },

  async put(endpoint: string, data: JsonObject) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    })
  },

  async patch(endpoint: string, data: JsonObject) {
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