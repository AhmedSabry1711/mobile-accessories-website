/**
 * Mobi Boost — API client.
 * Thin fetch wrapper: JSON bodies, Bearer admin token, error unwrapping.
 */
const BASE = import.meta.env.VITE_API_URL || '';

async function request(path, options = {}) {
  const { method = 'GET', body, form, headers = {} } = options;

  const token = localStorage.getItem('mb_admin_token');
  const auth = token ? { Authorization: `Bearer ${token}` } : {};
  // Multipart uploads: let the browser set the boundary — never force
  // Content-Type — but DO send the admin token (was previously dropped).
  const headersBag = form
    ? { ...auth, ...headers }
    : { 'Content-Type': 'application/json', ...auth, ...headers };

  const res = await fetch(BASE + path, {
    method,
    headers: headersBag,
    body: form ? form : body !== undefined ? JSON.stringify(body) : undefined,
  });

  let data = null;
  try {
    data = await res.json();
  } catch {
    // empty body
  }

  if (!res.ok) {
    const message = data?.message || `Request failed (${res.status})`;
    const error = new Error(message);
    error.status = res.status;
    error.data = data;
    throw error;
  }
  return data?.data ?? null;
}

export const api = {
  get: (path) => request(path),
  post: (path, body) => request(path, { method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  del: (path) => request(path, { method: 'DELETE' }),
  upload: (path, form) => request(path, { method: 'POST', form }),
};

export const money = (value) => Number(value ?? 0).toFixed(2);
