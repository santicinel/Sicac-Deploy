# SICAC - Deploy publico (demo)

Este proyecto ya esta preparado para desplegar:

- `frontend` Vue (`sicac_frontend`)
- `backend` Laravel (`sicac_backend`)
- `IA` FastAPI (`sicac_frontend/IA`)

Todo sale por un unico dominio con HTTPS automatico via Caddy.

## Estado actual (hecho por Codex y validado)

1. Deploy Docker completo creado y funcional:
- `deploy/docker-compose.yml`
- `deploy/Caddyfile`
- `deploy/.env.example`

2. Imagenes y arranque listos:
- `sicac_backend/docker/Dockerfile`
- `sicac_backend/docker/start.sh`
- `sicac_frontend/docker/Dockerfile`
- `sicac_frontend/docker/nginx.conf`
- `sicac_frontend/IA/docker/Dockerfile`

3. Ajustes para produccion aplicados:
- Frontend usa defaults de produccion (`/api` en mismo dominio y IA en `/ai`).
- Backend confiando proxy reverso (`trustProxies`).
- Backend forzando `APP_URL` y `https` en produccion.

4. Errores reales corregidos durante pruebas:
- Fallo de build de extensiones PHP en backend.
- Seeder de catalogo sin ruta valida dentro de contenedor.
- BOM en `TechnicianRequestSeeder.php` que rompia namespace.

5. Verificacion funcional hecha en Docker local:
- `frontend` responde en `https://localhost/`
- `backend` responde en `https://localhost/api/products`
- `login` funciona (`admin@sicac.com / admin123`)
- `IA` responde en `https://localhost/ai/`

## Estructura de deploy

- `deploy/docker-compose.yml`: orquestacion de servicios
- `deploy/Caddyfile`: proxy y TLS
- `deploy/.env.example`: variables a completar
- `sicac_backend/docker/*`: imagen y arranque backend
- `sicac_frontend/docker/*`: build y servido SPA
- `sicac_frontend/IA/docker/*`: imagen IA

## Requisitos del servidor

- VPS Linux publico (Ubuntu 22/24 recomendado)
- Docker + Docker Compose plugin
- Dominio apuntando al VPS (registro DNS `A`)
- Puertos 80 y 443 abiertos en firewall

## DuckDNS (recomendado para este caso)

- No usar `nip.io` en produccion: Let's Encrypt lo rate-limitea a nivel global y puede romper HTTPS.
- Usar un dominio real, por ejemplo `sicac.duckdns.org`.

Pasos rapidos con DuckDNS:

1. Crear `sicac.duckdns.org` en DuckDNS.
2. Apuntarlo a la IP publica del servidor (ejemplo: `216.238.105.160`).
3. Configurar en `deploy/.env`:
   - `DOMAIN=sicac.duckdns.org`
   - `FRONTEND_URLS=https://sicac.duckdns.org`
   - `SANCTUM_STATEFUL_DOMAINS=sicac.duckdns.org`

Si el DNS todavia no propago o ACME falla al principio:

- Poner `CADDYFILE=Caddyfile.http` para levantar en HTTP-only temporal.
- Cuando DNS/certificados esten bien, volver a `CADDYFILE=Caddyfile`.

## Lo que te falta a vos (obligatorio)

1. Subir estos cambios a tu repo:

```bash
git add .
git commit -m "chore: prepare full public docker deploy"
git push
```

2. En el VPS, clonar o actualizar repo:

```bash
git clone <TU_REPO_GIT>
cd "PF - deploy"
# o si ya existe:
# git pull
```

3. Crear y editar variables de deploy:

```bash
cp deploy/.env.example deploy/.env
```

Configurar en `deploy/.env` al menos:
- `DOMAIN=tu-dominio.com`
- `SANCTUM_STATEFUL_DOMAINS=tu-dominio.com`
- `FRONTEND_URLS=https://tu-dominio.com`
- `CADDYFILE=Caddyfile` (HTTPS normal)
- `CADDY_EMAIL=tuemail@algo.com`
- `APP_KEY=...` (ver paso siguiente)
- `GPT_API_KEY` o `OPENAI_API_KEY` (opcional, si queres IA LLM completa)
- `MAIL_MAILER=smtp`
- `MAIL_HOST=...`
- `MAIL_PORT=...`
- `MAIL_USERNAME=...`
- `MAIL_PASSWORD=...`
- `MAIL_FROM_ADDRESS=...`
- `MAIL_FROM_NAME=...`

4. Generar `APP_KEY` real en el VPS:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env run --rm backend php artisan key:generate --show
```

Copiar el valor `base64:...` y pegarlo en `APP_KEY` de `deploy/.env`.

5. Publicar:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build
```

6. Validar:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env ps
docker compose -f deploy/docker-compose.yml --env-file deploy/.env logs -f
```

## Notificaciones por email (solicitudes/reclamos)

Si cambias estados y no salen emails, normalmente es configuracion SMTP faltante.

Verificacion rapida en VPS:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env exec -T backend php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$kernel=\$app->make(Illuminate\\Contracts\\Console\\Kernel::class); \$kernel->bootstrap(); echo 'MAIL_DEFAULT=' . config('mail.default') . PHP_EOL;"
```

Debe devolver `MAIL_DEFAULT=smtp`.

Si no, revisar `deploy/.env` y volver a recrear backend:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build --force-recreate backend
```

## Datos demo iniciales

Con `SEED_DEMO_DATA=true`, el backend siembra una vez por volumen:

- Admin: `admin@sicac.com` / `admin123`
- Usuario: `juan@example.com` / `password123`
- Tecnico: `pedro.tech@example.com` / `password123`

Si ya sembraste una vez y no queres repetir:
- `SEED_DEMO_DATA=false`

## Actualizar deploy despues

```bash
git pull
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build
```

## Rebuild puntual para cambios de IA

Si cambias solo prompts, respuestas o rutas Python de la IA:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build ai
```

Si ademas cambias textos visibles del chat en Vue (por ejemplo el encabezado "Asistente IA Gustavo"):

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build frontend ai
```

## Notas operativas

- Base de datos: SQLite persistida en volumen Docker (`backend_data`).
- Archivos de presupuestos: persistidos en volumen (`backend_storage`).
- El backend toma el catalogo desde `sicac_frontend/IA/kb/catalogo_sielse_normalizado.json` montado por Compose.
- Manten la estructura de carpetas del repo para que seeders y servicios funcionen.
