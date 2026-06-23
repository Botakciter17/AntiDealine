const API_BASE = '/api'

export async function api(endpoint, method = 'GET', body = null) {
  const token = localStorage.getItem('ad_token')
  
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
    },
  }

  if (token) {
    options.headers['Authorization'] = `Bearer ${token}`
  }

  if (body && method !== 'GET') {
    options.body = JSON.stringify(body)
  }

  const res = await fetch(`${API_BASE}${endpoint}`, options)
  const data = await res.json()

  if (!res.ok) {
    throw new Error(data.error || 'Request failed')
  }

  return data
}

export async function apiUpload(endpoint, formData) {
  const token = localStorage.getItem('ad_token')

  const options = {
    method: 'POST',
    headers: {},
    body: formData,
  }

  if (token) {
    options.headers['Authorization'] = `Bearer ${token}`
  }

  const res = await fetch(`${API_BASE}${endpoint}`, options)
  const data = await res.json()

  if (!res.ok) {
    throw new Error(data.error || 'Upload failed')
  }

  return data
}
