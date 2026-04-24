# Cancel Hansel – Pre-Deploy Checklist

Run these steps locally before deploying to Render.

---

## 1. Verify services/api has a `start` script

Open `services/api/package.json`.  The `scripts` block must contain:

```json
"scripts": {
  "build": "tsc",
  "start": "node dist/index.js"
}
```

If `start` is missing, add it.  The entry point (`dist/index.js`) should match
whatever TypeScript compiles to.  Check `tsconfig.json` → `outDir`.

---

## 2. Verify PORT is read from the environment

The API server must bind to `process.env.PORT`, not a hard-coded value:

```ts
const port = Number(process.env.PORT) || 8080;
app.listen(port, () => console.log(`Listening on ${port}`));
```

---

## 3. Add CORS origin for the dashboard

After the dashboard is deployed you will know its Render URL
(`https://cancel-hansel-dashboard.onrender.com` by default).

Add that URL to the allowed origins in `services/api/src/app.ts`:

```ts
const allowedOrigins = [
  process.env.CORS_ORIGIN,            // set this in Render env vars
  'http://localhost:5173',            // local dev
].filter(Boolean);

app.use(cors({ origin: allowedOrigins }));
```

Set `CORS_ORIGIN=https://cancel-hansel-dashboard.onrender.com` in the
Render API service environment variables after the dashboard is deployed.

---

## 4. Local build check before pushing

```powershell
cd C:\Users\Reboot\cancel-hansel
git pull origin main

# API
cd services\api
npm install
npm run build      # must exit 0

# Frontend
cd ..\..\apps\web
npm install
npm run build      # must exit 0
```

---

## 5. Render deployment order

1. **Create PostgreSQL** – `cancel-hansel-db` (free tier, Internal URL).
2. **Deploy API** – `cancel-hansel-api` web service.  Copy the auto-generated
   `API_KEY` value from the Environment tab after first deploy.
3. **Deploy Dashboard** – `cancel-hansel-dashboard` static site.  If
   `VITE_API_BASE_URL` was not resolved automatically from the Blueprint, set
   it manually to `https://cancel-hansel-api.onrender.com`.
4. **Run migrations** (only if needed outside the build step):
   ```
   npx prisma migrate deploy
   ```
5. **Verify** – open the dashboard URL, paste the API key, confirm metrics load.

---

## 6. Secrets must NOT be committed

- `apps/web/.env.local` is git-ignored – keep it that way.
- Never commit `DATABASE_URL` or `API_KEY`.
- All secrets live only in Render environment variables.
