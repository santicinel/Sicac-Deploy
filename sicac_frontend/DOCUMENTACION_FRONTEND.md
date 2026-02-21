# Documentacion Frontend SICAC + Especificacion Backend Laravel

Este documento describe el frontend actual y TODO lo necesario para conectar un backend Laravel con base de datos. Incluye arquitectura, contratos, endpoints, flujos y consideraciones operativas. No incluye nada de IA.

## Stack y configuracion (frontend)
- Framework: Vue 3 + Vite + TypeScript
- Routing: vue-router
- Estado global: Pinia
- HTTP: axios con CSRF tipo Sanctum y fetch
- UI: Tailwind CSS, componentes propios en `src/components`
- PDF: jsPDF + jspdf-autotable para presupuestos
- Configuracion: `VITE_APP_URL` y `VITE_API_URL` en `.env` (ver `src/config/app.ts`)

## Arquitectura de carpetas (frontend)
- `src/main.ts` inicia la app, `src/App.vue` y `src/DecideLayout.vue` seleccionan layouts.
- `src/layouts/*` define layouts (`AppLayout`, `AuthLayout`, `SimpleLayout`).
- `src/router/index.ts` define rutas y guards por rol.
- `src/store/*` maneja estado (auth, cart, admin).
- `src/services/*` contiene `authService` y `DataService` (mock de productos).
- `src/views/*` pantallas principales.
- `public/img/*` imagenes de productos.

## Rutas frontend
- Publicas: `/login`, `/terms`
- Usuario: `/home`, `/budget`, `/ai-recommendation`, `/about`, `/support`
- Tecnico: `/technician/claims`, `/technician/chat`
- Admin: `/admin/claims`, `/admin/technicians`, `/admin/products`, `/admin/ratings`, `/admin/labor-rate`

## Estado y datos en frontend (resumen)
- Auth (`src/store/authStore.ts`): `user` con `role` en `localStorage`.
- Cart (`src/store/cartStore.ts`): items, `includeTechnician`, `laborRate`.
- Admin products/technicians/settings: hoy en `localStorage`.
- Objetivo: reemplazar todo con API Laravel.

--------------------------------------------------------------------------------

# Backend Laravel: requisitos completos

## Stack recomendado
- Laravel 10 o 11
- Auth: Sanctum (SPA con cookies + CSRF)
- DB: MySQL o Postgres
- Cache: Redis (opcional)
- Queue: Redis/DB (para email y tareas)
- Mail: SMTP
- Storage: local o S3 (imagenes si aplica)

## Configuracion base
### .env clave
- `APP_URL=http://localhost:8000`
- `FRONTEND_URL=http://localhost:5173`
- `SESSION_DOMAIN=localhost` (ajustar en prod)
- `SANCTUM_STATEFUL_DOMAINS=localhost:5173`
- `SESSION_DRIVER=cookie`
- `SESSION_SECURE_COOKIE=false` (true en prod)
- `CORS_ALLOWED_ORIGINS=http://localhost:5173`

### CORS (config/cors.php)
- `allowed_origins`: `FRONTEND_URL`
- `supports_credentials`: true
- `allowed_headers`: `*`
- `allowed_methods`: `*`

### Sanctum (config/sanctum.php)
- `stateful`: `SANCTUM_STATEFUL_DOMAINS`

### Middleware sugeridos
- `auth:sanctum` para rutas privadas
- `role:admin|technician|user` (middleware custom)

## Modelos y tablas (minimo)
### users
- id, name, email, password, role, created_at, updated_at
- role: `admin | technician | user`

### products
- id (uuid o sku)
- name, brand (nullable), model_sku
- family, subfamily
- price_ars
- description (texto_rag)
- specs_json (JSON)
- tags (JSON array)
- image_url (nullable)
- active (bool)
- created_at, updated_at

### technicians
- id, user_id (opcional si tecnico es user)
- first_name, last_name, dni
- email, phone, address, city
- availability_date (date)
- created_at, updated_at

### claims (tickets)
- id, type (`technical` | `claim`)
- status (`open` | `in_progress` | `resolved` | `closed`)
- subject, description
- customer_id (user)
- created_at, updated_at
- assigned_technician_id (nullable)
- assignment_date (nullable)
- completion_notes (nullable)
- client_confirmed (bool)

### claim_messages (opcional)
- id, claim_id, author_id, role, message, created_at

### ratings
- id, claim_id (nullable), technician_id (nullable), product_id (nullable)
- rating (1-5), notes (nullable), created_at

### quotes (presupuestos)
- id, customer_id, status (`draft` | `sent` | `accepted`)
- include_technician (bool)
- labor_rate
- total
- created_at, updated_at

### quote_items
- id, quote_id, product_id, quantity, unit_price

### settings
- id, key, value (string/json)
- usar key: `labor_rate`

## Relaciones Eloquent
- User hasMany Claims
- Claim belongsTo User
- Claim belongsTo Technician (nullable)
- Claim hasMany Ratings (opcional) y Messages (opcional)
- Product hasMany Ratings
- Technician hasMany Claims
- Quote belongsTo User, hasMany QuoteItems

## Seeds recomendados
- Admin user, tecnico user, user generico
- Productos iniciales (desde JSON actual)
- Technicians de ejemplo

--------------------------------------------------------------------------------

# API REST (Laravel)
Base URL: `VITE_API_URL`
Todas las respuestas JSON y errores consistentes.

## Auth (Sanctum)
### GET /sanctum/csrf-cookie
Solicita cookie XSRF.

### POST /api/login
Request:
```json
{ "email": "user@sicac.com", "password": "secret", "remember": true }
```
Response 200:
```json
{ "user": { "id": 1, "name": "User", "email": "user@sicac.com", "role": "user" } }
```
Errores:
- 422 credenciales invalidas

### POST /api/logout
Response 200:
```json
{ "ok": true }
```

### GET /api/me
Response 200:
```json
{ "user": { "id": 1, "name": "User", "email": "user@sicac.com", "role": "user" } }
```

--------------------------------------------------------------------------------

## Productos
### GET /api/products
Query params:
- `page`, `per_page`
- `search`
- `family`, `subfamily`
- `min_price`, `max_price`
- `specs[key]=value` (opc)

Response 200:
```json
{
  "data": [
    {
      "id": "SKU-001",
      "name": "Camara IP X1",
      "brand": "Marca",
      "model_sku": "X1",
      "family": "CCTV",
      "subfamily": "Camaras IP",
      "price_ars": 120000,
      "description": "Texto...",
      "specs_json": { "resolucion": "2MP" },
      "tags": ["ip", "poe"],
      "image_url": "/storage/products/x1.png",
      "active": true
    }
  ],
  "meta": { "page": 1, "per_page": 12, "total": 120 }
}
```

### GET /api/products/categories
Response 200:
```json
{
  "families": {
    "CCTV": ["Camaras IP", "Grabadores"],
    "Alarmas": ["Sensores", "Sirenas"]
  }
}
```

### POST /api/products (admin)
Body:
```json
{
  "name": "Producto",
  "family": "Alarmas",
  "subfamily": "Central",
  "price_ars": 100000,
  "description": "Texto",
  "active": true
}
```

### PUT /api/products/{id}
### DELETE /api/products/{id}

--------------------------------------------------------------------------------

## Tecnicos (admin)
### GET /api/technicians
Response 200:
```json
{
  "data": [
    {
      "id": 1,
      "first_name": "Lucas",
      "last_name": "Fernandez",
      "dni": "30222333",
      "email": "lucas@sicac.com",
      "phone": "+54 ...",
      "address": "Mitre 123",
      "city": "Firmat",
      "availability_date": "2026-01-10"
    }
  ]
}
```

### POST /api/technicians
### PUT /api/technicians/{id}
### DELETE /api/technicians/{id}

--------------------------------------------------------------------------------

## Reclamos / Tickets
### GET /api/claims
Query params:
- `status`, `type`, `search`, `from`, `to`

Response:
```json
{
  "data": [
    {
      "id": "REC-1001",
      "type": "technical",
      "status": "open",
      "subject": "Camara sin conexion",
      "description": "Detalle...",
      "customer": { "id": 10, "name": "Juan Perez" },
      "created_at": "2026-01-10",
      "assigned_technician_id": null,
      "assignment_date": null
    }
  ]
}
```

### POST /api/claims
Body:
```json
{
  "type": "technical",
  "family": "CCTV",
  "subject": "Problema",
  "description": "Detalle",
  "visit_date": "2026-01-10",
  "visit_time": "morning"
}
```
Response 201:
```json
{ "id": "REC-1010", "status": "open" }
```

### PATCH /api/claims/{id}/status
Body:
```json
{ "status": "in_progress" }
```

### PATCH /api/claims/{id}/assign
Body:
```json
{ "assigned_technician_id": 1, "assignment_date": "2026-01-12" }
```

### PATCH /api/claims/{id}/close
Body:
```json
{ "completion_notes": "Resumen", "status": "resolved" }
```

--------------------------------------------------------------------------------

## Ratings
### GET /api/ratings
Query params:
- `type=technicians|products`
- `min_rating`, `category`

Response:
```json
{
  "data": [
    { "technician_id": 1, "average": 4.6, "total": 18 }
  ]
}
```

### POST /api/ratings
Body:
```json
{ "claim_id": "REC-1001", "technician_id": 1, "rating": 5, "notes": "Buen trabajo" }
```

--------------------------------------------------------------------------------

## Presupuestos
### POST /api/quotes
Body:
```json
{
  "include_technician": true,
  "labor_rate": 1500,
  "items": [
    { "product_id": "SKU-001", "quantity": 2, "unit_price": 120000 }
  ]
}
```
Response 201:
```json
{ "id": 10, "total": 241500 }
```

### GET /api/quotes/{id}
Response:
```json
{
  "id": 10,
  "items": [ { "product_id": "SKU-001", "quantity": 2, "unit_price": 120000 } ],
  "include_technician": true,
  "labor_rate": 1500,
  "total": 241500
}
```

--------------------------------------------------------------------------------

## Settings
### GET /api/settings/labor-rate
Response:
```json
{ "labor_rate": 1500 }
```

### PUT /api/settings/labor-rate
Body:
```json
{ "labor_rate": 1800 }
```

--------------------------------------------------------------------------------

# Mapeo vista -> endpoint
## LoginView
- `GET /sanctum/csrf-cookie`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

## HomeView
- `GET /api/products`
- `GET /api/products/categories`

## BudgetView / BudgetSummaryView
- `POST /api/quotes` (opcional)
- `GET /api/settings/labor-rate`

## SupportView
- `POST /api/claims`
- `GET /api/claims` (historial)
- `POST /api/ratings`

## AdminClaimsView
- `GET /api/claims`
- `PATCH /api/claims/{id}/status`
- `PATCH /api/claims/{id}/assign`

## AdminTechniciansView
- `GET /api/technicians`
- `POST/PUT/DELETE /api/technicians`

## AdminProductsView
- `GET /api/products`
- `POST/PUT/DELETE /api/products`

## AdminRatingsView
- `GET /api/ratings`

## AdminLaborRateView
- `GET /api/settings/labor-rate`
- `PUT /api/settings/labor-rate`

--------------------------------------------------------------------------------

# Errores y validaciones
Formato error recomendado:
```json
{ "message": "Error", "errors": { "field": ["Detalle"] } }
```
- 401: no autenticado
- 403: sin permiso
- 404: no encontrado
- 422: validacion
- 500: error interno

--------------------------------------------------------------------------------

# Checklist de integracion
1) Implementar auth Sanctum y roles en Laravel.
2) Reemplazar mocks del frontend por API real.
3) Implementar productos con filtros + paginacion.
4) Implementar reclamos y asignacion de tecnicos.
5) Implementar ratings y settings.
6) Verificar CORS y cookies.
7) Testear flujos completos por rol.
