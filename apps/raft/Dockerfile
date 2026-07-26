# ---------- Build stage ----------
FROM golang:1.26-alpine AS builder

WORKDIR /app

RUN apk add --no-cache ca-certificates

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -o skylogs-raft .

# ---------- Runtime stage ----------
FROM debian:bookworm-slim

RUN apt-get update && \
    apt-get install -y ca-certificates curl gosu && \
    rm -rf /var/lib/apt/lists/*

RUN useradd -u 10001 -r -s /usr/sbin/nologin raft

WORKDIR /app

COPY --from=builder /app/skylogs-raft .
COPY docker-entrypoint.sh /

RUN sed -i 's/\r$//' /docker-entrypoint.sh && chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
