# Clinex Frontend

The web interface for the Clinex clinical lab report management system, built with **Next.js 15** and **React 19**.

## Tech Stack

- **Framework**: Next.js 15 (App Router)
- **UI**: React 19, Tailwind CSS 4, Lucide Icons
- **Charts**: Recharts
- **Real-time**: Laravel Echo + Pusher
- **Auth**: Token-based (Laravel Sanctum)

## Features

- 🏠 Dashboard with real-time statistics
- 📄 PDF upload with batch support (up to 20 files)
- ✅ Side-by-side verification (original PDF vs extracted data)
- 👥 Patient management with lab report history
- 📊 Analytics dashboard with configurable time ranges
- 🔒 Role-based access (Admin / Lab Technician)
- 🌙 Dark mode / Light mode support
- 📱 Responsive design

## Pages

| Route | Description |
|-------|-------------|
| `/` | Landing page with splash screen |
| `/auth/login` | Authentication |
| `/main/homepage` | Dashboard |
| `/main/upload` | Upload lab reports |
| `/main/reports` | Reports management |
| `/main/verification` | AI data verification |
| `/main/patient` | Patient management |
| `/main/analytics` | Analytics dashboard |
| `/admin` | Admin panel |
| `/admin/users` | User management |
| `/admin/templates` | Template management |
| `/admin/system` | System health |

## Development

The frontend runs inside Docker. From the project root:

```bash
docker compose up -d frontend
```

Access at `http://localhost:3000`

## Environment Variables

Create a `.env.local` file:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```
