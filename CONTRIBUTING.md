# Contributing to Skylogs

Thanks for your interest in contributing! 🎉

The full contributor documentation lives in
[docs/contributing.md](docs/contributing.md) and the
[Developer Guide](docs/developer-guide.md).

## Quick summary

1. **Discuss first** for larger changes — open an issue or a
   [Discussion](https://github.com/skylogsio/skylogs/discussions) before
   investing significant time.
2. **Fork and branch** from `main`: `feat/short-description` or
   `fix/short-description`.
3. **Follow the code style** of each app:
   - Backend (Laravel): `vendor/bin/pint`
   - Frontend (Next.js): `npm run lint`
   - Sentinel (Go): `gofmt` / `golangci-lint`
4. **Write tests** for new behavior and make sure CI passes.
5. **Update docs and CHANGELOG.md** when behavior or configuration changes.
6. **Open a PR** — the template will guide you through the checklist.

## Development setup

```bash
git clone https://github.com/<your-fork>/skylogs.git
cd skylogs
cp .env.example .env
make dev
```

See the [Developer Guide](docs/developer-guide.md) for details.

## Code of Conduct

This project follows our [Code of Conduct](CODE_OF_CONDUCT.md). Be kind.
