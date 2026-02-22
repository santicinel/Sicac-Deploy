# SICAC - Deploy publico (demo)

Este repo queda preparado para publicar:

- `frontend` Vue (`sicac_frontend`)
- `backend` Laravel (`sicac_backend`)
- `IA` FastAPI (`sicac_frontend/IA`)

Todo sale por un unico dominio con HTTPS automatico usando Caddy.

## Estructura de deploy agregada

- `deploy/docker-compose.yml`: orquestacion de servicios
- `deploy/Caddyfile`: proxy y TLS
- `deploy/.env.example`: variables a completar
- `sicac_backend/docker/*`: imagen y arranque backend
- `sicac_frontend/docker/*`: build y servido SPA
- `sicac_frontend/IA/docker/*`: imagen IA

## Requisitos del servidor

- VPS Linux publico (Ubuntu 22/24 recomendado)
- Docker + Docker Compose plugin
- Dominio apuntando al VPS (registro `A`)
- Puertos 80 y 443 abiertos en firewall

## Pasos exactos

1. Clonar repo en el VPS:

```bash
git clone <TU_REPO_GIT>
cd "PF - deploy"
```

2. Crear archivo de variables:

```bash
cp deploy/.env.example deploy/.env
```

3. Editar `deploy/.env` con tu dominio real (`DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URLS`).

4. Generar `APP_KEY` de Laravel:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env run --rm backend php artisan key:generate --show
```

Copiar el resultado `base64:...` dentro de `APP_KEY` en `deploy/.env`.

5. Levantar en produccion:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build
```

6. Ver logs si queres validar:

```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env logs -f
```

## Datos demo

Con `SEED_DEMO_DATA=true`, el backend siembra datos iniciales una sola vez por volumen:

- Admin: `admin@sicac.com` / `admin123`
- Usuario: `juan@example.com` / `password123`
- Tecnico: `pedro.tech@example.com` / `password123`

Si ya sembraste y no queres repetir, podes dejar `SEED_DEMO_DATA=false`.

## Actualizar deploy

```bash
git pull
docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d --build
```

## Notas operativas

- Base de datos: SQLite persistida en volumen Docker (`backend_data`).
- Archivos de presupuestos: persistidos en volumen (`backend_storage`).
- Para endpoints IA con LLM, configura `GPT_API_KEY` (y/o `OPENAI_API_KEY`) en `deploy/.env`.
- El backend toma el catalogo desde `sicac_frontend/IA/kb/catalogo_sielse_normalizado.json` (montado por Compose), por eso hay que mantener la estructura de carpetas del repo.

