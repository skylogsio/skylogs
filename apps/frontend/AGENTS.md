# AGENTS.md — Skylogs Frontend

Guidance for AI coding agents working in this repository.

## Product context

**Skylogs** is an open-source alert and incident management platform. It consolidates alerts from observability tools (Prometheus, Grafana, Zabbix, Datadog, Splunk, Sentry, etc.), routes them intelligently, and notifies teams through SMS, email, Slack, Teams, Telegram, and other channels.

This directory (`apps/frontend`) is the **Next.js web UI** for Skylogs. The full monorepo may also include a Laravel backend and Docker infrastructure; this app talks to the backend via REST (`BASE_URL`).

---

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | Next.js 16 (App Router) |
| UI | React 19, MUI 9, Emotion |
| Language | TypeScript (strict) |
| Data fetching | TanStack Query v5, server actions in `src/api/*` |
| Tables | TanStack Table v8 (`SmartTable`) |
| Forms | React Hook Form + Zod |
| Auth | next-auth v4 (credentials + JWT refresh) |
| i18n | next-international (`en`, `fa`) with RTL support |
| HTTP | axios (`src/lib/axios.ts`) |
| Animation | framer-motion (auth & sidebar) |
| Icons | react-icons |

Path alias: `@/*` → `src/*`

---

## Project structure

```
apps/frontend/
├── src/
│   ├── app/
│   │   ├── [locale]/          # Locale-routed pages (URL rewrite hides locale prefix)
│   │   │   ├── alert-rule/    # Core alert rule CRUD & detail
│   │   │   ├── auth/signIn/   # Login page
│   │   │   ├── admin-area/    # Owner-only system settings
│   │   │   ├── debugging/     # Alert timeline debugging
│   │   │   ├── endpoints/     # Notification endpoints
│   │   │   ├── users/ teams/ data-source/ clusters/ ...
│   │   │   └── layout.tsx     # Root shell: fonts, providers, Wrapper
│   │   └── api/
│   │       ├── route.ts       # ⚠️ Exposes session token — do not extend
│   │       └── v1/[...slug]/  # Webhook proxy to backend
│   ├── api/                   # Server actions ("use server") → backend REST
│   ├── @types/                # Shared TypeScript interfaces
│   ├── components/            # Reusable UI (Wrapper, Table, AlertRule, Modal, …)
│   ├── context/               # React context (SideBar, Zone)
│   ├── features/              # Feature modules (e.g. Debugging/)
│   ├── hooks/                 # Shared hooks (useRole, useCurrentTheme, …)
│   ├── lib/                   # axios instance, utilities
│   ├── locales/               # en/ fa translations
│   ├── provider/              # App providers (MUI, auth, query, RTL)
│   ├── services/next-auth/    # authOptions, session types
│   ├── utils/                 # Domain helpers (alertRule, dataSource, userUtils)
│   └── proxy.ts               # Middleware: i18n + auth gate
├── public/static/             # Images, icons, fonts
├── skylogs-readme.md          # Product overview
└── IMPROVEMENT_CHECKLIST.md   # Known gaps & roadmap
```

---

## App shell & routing

- **`Wrapper`** (`src/components/Wrapper/`) wraps all authenticated pages: sidebar, topbar, RBAC redirects, zone selection.
- Auth pages under `/auth` render **without** the shell.
- Default post-login route: `/alert-rule`.
- Sidebar nav and roles are defined in `SideBar.tsx`; admin nav in `AdminSideBar.tsx`.

### Roles (RBAC)

Roles: `member` | `manager` | `owner` (see `src/utils/userUtils.ts`).

| Area | Roles |
|------|-------|
| Users, Data Sources, Settings/Telegram | `owner`, `manager` |
| Clusters, Profile Services, Settings | `owner` |
| Admin Area | `owner` |

Client checks live in `Wrapper`; **server/middleware enforcement is incomplete** — do not rely on client-only guards for security fixes.

### Zones (clusters)

Selected zone is stored in `X-Cluster` cookie via `ZoneContext`. Switching zone reloads to `/alert-rule`. API calls attach the zone header in `axios` interceptors.

---

## Conventions

### Components

- Use **functional components** and `"use client"` only when needed (hooks, events, browser APIs).
- Prefer **MUI `sx`** and theme tokens from `MuiProvider` — warm sand/copper palette, light + dark schemes.
- Match existing UI patterns: glass/filled inputs on auth, compact topbar pills (`GlassPillButton`), sidebar gradient active states.
- **Minimize diff scope** — do not refactor unrelated code in task-focused PRs.

### API layer

- Backend calls go in `src/api/<domain>.ts` with `"use server"` at the top.
- Use the shared `axios` instance; it injects `Authorization: Bearer …` and `X-Cluster`.
- Return typed responses using `@types/global` (`ServerResponse`, etc.).
- Avoid empty `try/catch { throw error }` blocks when adding new code.

### Forms & validation

- Define Zod schemas colocated with forms.
- Use `@hookform/resolvers/zod` + `react-hook-form`.
- Surface errors via MUI `TextField` `error`/`helperText` or toasts (`react-toastify`).

### i18n

- Locales: **en** (LTR), **fa** (RTL, Vazir font).
- Client: `useScopedI18n("namespace")` from `@/locales/client`.
- Add strings to **both** `src/locales/en/` and `src/locales/fa/`.
- Test RTL layout when changing horizontal spacing or icons.

### Tables

- List pages use **`SmartTable`** with server-side pagination/filtering via `fetchTableData.ts`.
- Follow existing column/action patterns (`ActionColumn`, `DeleteModal`).

### Theming

- Use `useCurrentTheme()` for resolved light/dark (not raw `palette.mode` alone when system preference matters).
- Theme preference persists in cookies (`theme`, `mui-mode`).

---

## Commands

```bash
npm run dev          # Start dev server
npm run build        # Production build
npm run start        # Start production server
npm run lint         # ESLint
npm run prettier     # Format staged paths (pass file list)
npx tsc --noEmit     # Typecheck (add script if missing)
```

---

## Environment

Required variables (document in `.env.example` when adding):

- `BASE_URL` — Backend API base URL
- `NEXTAUTH_SECRET` — Session encryption
- `NEXTAUTH_URL` — App URL for next-auth callbacks

---

## Security — do not regress

1. **Never expose raw tokens** — `src/app/api/route.ts` returns session tokens; prefer removing or hardening, not expanding.
2. **Do not commit secrets** — no credentials in docker-compose or source.
3. **Do not commit** unless explicitly asked.
4. **RBAC** — treat `Wrapper` redirects as UX only; prefer middleware/server checks for real authorization.
5. **Webhook proxy** — keep `allowedRoutes` allowlist tight in `api/v1/[...slug]/route.ts`.
6. **axios 401 retry** — use `error.config`, not `error.request.config`.

See `IMPROVEMENT_CHECKLIST.md` for the full prioritized backlog.

---

## Adding a new page

1. Create `src/app/[locale]/<route>/page.tsx`.
2. Add sidebar entry in `SideBar.tsx` (and `role` if restricted).
3. Add API functions in `src/api/` if needed.
4. Add types in `src/@types/`.
5. Add i18n keys in `locales/en` and `locales/fa`.
6. If the route is protected, confirm middleware coverage in `src/proxy.ts`.

---

## Key files reference

| Concern | Location |
|---------|----------|
| Auth config | `src/services/next-auth/authOptions.tsx` |
| Middleware | `src/proxy.ts` |
| App providers | `src/provider/index.tsx` |
| MUI theme | `src/provider/MuiProvider.tsx` |
| Layout shell | `src/app/[locale]/layout.tsx` |
| Role hook | `src/hooks/index.ts` → `useRole` |
| Alert rule types | `src/@types/alertRule.ts` |
| Topbar / sidebar | `src/components/Wrapper/` |

---

## Design notes

- **Sign-in page** (`auth/signIn/page.tsx`) is the visual reference: radial gradients, glass pills, filled inputs, gradient CTA buttons.
- **Primary palette**: copper/sand tones (`#C4A07A` primary, `#F3EEE6` background in light mode).
- Prefer accessible contrast, `aria-*` on interactive controls, and `useReducedMotion` where animations exist.

---

## Out of scope for agents (unless asked)

- Creating git commits or pushing branches
- Adding markdown/docs files beyond what the task requires
- Large refactors (SmartTable split, axios client/server split) without explicit request
- Adding tests/CI — tracked in checklist but not yet set up

---

## Related docs

- Improvement roadmap: [`IMPROVEMENT_CHECKLIST.md`](./IMPROVEMENT_CHECKLIST.md)
- Upstream repo docs: `docs/` in the Skylogs monorepo (installation, API, integrations)
