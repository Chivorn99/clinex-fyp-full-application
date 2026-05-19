export const dynamic = 'force-static'

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value))

export function GET(
  _request: Request,
  { params }: { params: { width: string; height: string } },
) {
  const widthRaw = Number.parseInt(params.width, 10)
  const heightRaw = Number.parseInt(params.height, 10)

  const width = clamp(Number.isFinite(widthRaw) ? widthRaw : 150, 16, 2048)
  const height = clamp(Number.isFinite(heightRaw) ? heightRaw : 150, 16, 2048)

  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
  <rect width="100%" height="100%" fill="#F3F4F6"/>
  <circle cx="${width / 2}" cy="${height / 2}" r="${Math.min(width, height) * 0.22}" fill="#E5E7EB"/>
  <path d="M ${width / 2} ${height / 2 - Math.min(width, height) * 0.08} a ${Math.min(width, height) * 0.08} ${Math.min(width, height) * 0.08} 0 1 0 0.01 0 Z" fill="#9CA3AF"/>
  <path d="M ${width / 2 - Math.min(width, height) * 0.16} ${height / 2 + Math.min(width, height) * 0.16} c ${Math.min(width, height) * 0.08} -${Math.min(width, height) * 0.1} ${Math.min(width, height) * 0.24} -${Math.min(width, height) * 0.1} ${Math.min(width, height) * 0.32} 0" fill="none" stroke="#9CA3AF" stroke-width="${Math.max(2, Math.min(width, height) * 0.02)}" stroke-linecap="round"/>
</svg>`

  return new Response(svg, {
    headers: {
      'Content-Type': 'image/svg+xml; charset=utf-8',
      'Cache-Control': 'public, max-age=31536000, immutable',
    },
  })
}
