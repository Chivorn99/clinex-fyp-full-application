import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export function middleware(request: NextRequest) {
  const path = request.nextUrl.pathname
  const isPublicPath = path === '/auth/login' ||
    path === '/auth/signup' ||
    path === '/auth/reset-password' ||    
    path === '/auth/verify-otp' ||      
    path === '/auth/new-password' ||      
    path === '/'
  const token = request.cookies.get('auth_token')?.value || ''
  const userRole = request.cookies.get('user_role')?.value || ''

  if (isPublicPath && token) {
    return NextResponse.redirect(new URL('/main/homepage', request.nextUrl))
  }

  if (!isPublicPath && !token) {
    return NextResponse.redirect(new URL('/auth/login', request.nextUrl))
  }

  // Future: If you ever need admin-only sections
  // const isAdminOnlyPath = path.startsWith('/admin-panel')
  // if (isAdminOnlyPath && userRole !== 'admin') {
  //   return NextResponse.redirect(new URL('/main/homepage', request.nextUrl))
  // }

  // Future: If you ever need lab-tech-only sections  
  // const isLabOnlyPath = path.startsWith('/lab-management')
  // if (isLabOnlyPath && userRole !== 'lab_technician' && userRole !== 'admin') {
  //   return NextResponse.redirect(new URL('/main/homepage', request.nextUrl))
  // }

  return NextResponse.next()
}

export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|favicon.ico).*)',
  ],
}