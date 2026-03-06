const BASE = '/api'

export async function listWorlds() {
  const r = await fetch(`${BASE}/worlds`)
  return r.json()
}

export async function getWorld(name) {
  const r = await fetch(`${BASE}/worlds/${name}`)
  if (!r.ok) throw new Error(`World "${name}" not found`)
  return r.json()
}

export async function saveWorld(name, data) {
  const r = await fetch(`${BASE}/worlds/${name}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  if (!r.ok) throw new Error('Failed to save world')
  return r.json()
}

export async function deleteWorld(name) {
  const r = await fetch(`${BASE}/worlds/${name}`, { method: 'DELETE' })
  if (!r.ok) throw new Error('Failed to delete world')
  return r.json()
}

export async function getConfig() {
  const r = await fetch(`${BASE}/config`)
  return r.json()
}
