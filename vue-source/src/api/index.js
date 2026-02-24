/**
 * REST API wrapper for the CIG Vue SPA.
 *
 * In WordPress (production):  window.cigGlobal.restUrl + window.cigGlobal.nonce are set
 *                              by wp_localize_script() in class-cig-core.php.
 * In local dev (demo mode):   window.cigGlobal is undefined — all calls return null
 *                              and the store falls back to demo data from @/data.
 */

export const isWordPress = () =>
  typeof window !== 'undefined' &&
  !!window.cigGlobal?.restUrl

function getBase() {
  return (window.cigGlobal?.restUrl || '').replace(/\/?$/, '/')
}

function getNonce() {
  return window.cigGlobal?.nonce || ''
}

async function request(method, path, body = null) {
  if (!isWordPress()) return null

  const url = getBase() + path.replace(/^\//, '')
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': getNonce(),
    },
    credentials: 'same-origin',
  }
  if (body !== null) {
    opts.body = JSON.stringify(body)
  }

  const res = await fetch(url, opts)

  // Refresh nonce from response header if WordPress rotated it
  const fresh = res.headers.get('X-WP-Nonce')
  if (fresh && window.cigGlobal) window.cigGlobal.nonce = fresh

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: res.statusText }))
    const msg = err.message || err.code || `HTTP ${res.status}`
    throw new Error(msg)
  }

  return res.json()
}

export const api = {
  isWordPress,
  get:   (path)        => request('GET',    path),
  post:  (path, body)  => request('POST',   path, body),
  put:   (path, body)  => request('PUT',    path, body),
  patch: (path, body)  => request('PATCH',  path, body),
  del:   (path)        => request('DELETE', path),
}
