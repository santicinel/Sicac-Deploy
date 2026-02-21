# SICAC Frontend

## Requisitos

- Node.js 20+
- Python 3 (modulo IA)
- PHP 8.2+ con Composer (para API Laravel en `../sicac_backend`)

## Primer arranque

```bash
npm install
npm run setup:api
```

`setup:api` ejecuta migraciones y seeders en el backend para dejar usuarios de prueba listos.

## Desarrollo

```bash
npm run dev
```

Este comando ahora levanta en paralelo:

- Frontend Vite
- API Laravel (`127.0.0.1:8001`)
- Servicio IA (`127.0.0.1:8002`)

## Credenciales de prueba

- Admin: `admin@sicac.com` / `admin123`
- Usuario: `juan@example.com` / `password123`
- Tecnico: `pedro.tech@example.com` / `password123`
