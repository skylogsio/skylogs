# ---------- Build stage ----------
FROM nexus.qcluster.org/golang:1.26-alpine AS builder

WORKDIR /app

RUN sed -i 's|https://dl-cdn.alpinelinux.org/alpine/|https://nexus.qcluster.org/repository/apk-proxy/alpine/|g' /etc/apk/repositories

RUN apk add --no-cache ca-certificates

ENV GOPROXY=https://nexus.qcluster.org/repository/go-proxy/
COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    GOPROXY=https://nexus.qcluster.org/repository/go-proxy/ \
    go build -o skylogs-raft ./cmd/server

# ---------- Runtime stage ----------
FROM nexus.qcluster.org/buildpack-deps:bookworm-curl

# Point apt at the internal Nexus proxies

RUN cat > /etc/apt/sources.list.d/debian.sources <<'EOF'
Types: deb
URIs: https://nexus.qcluster.org/repository/apt-proxy-bookworm/
Suites: bookworm bookworm-updates
Components: main
Trusted: yes

Types: deb
URIs: https://nexus.qcluster.org/repository/apt-proxy-bookworm-security/
Suites: bookworm-security
Components: main
Trusted: yes
EOF

RUN rm -f /etc/apt/sources.list /etc/apt/sources.list.d/debian.sources.bak 2>/dev/null || true

RUN apt-get update && \
    apt-get install -y --no-install-recommends ca-certificates curl gosu && \
    rm -rf /var/lib/apt/lists/*

RUN useradd -u 10001 -r -s /usr/sbin/nologin raft
WORKDIR /app

COPY --from=builder /app/skylogs-raft .
COPY docker-entrypoint.sh /

RUN sed -i 's/\r$//' /docker-entrypoint.sh && chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
