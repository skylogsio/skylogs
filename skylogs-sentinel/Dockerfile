# ---------- Build stage ----------
FROM golang:1.23-alpine AS builder

WORKDIR /app

# Install CA certs (needed for HTTPS)
RUN apk add --no-cache ca-certificates

COPY go.mod go.sum ./
RUN go mod download

COPY . .

# Build static binary
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -o skylogs-sentinel ./cmd/sentinel

# ---------- Runtime stage ----------
FROM gcr.io/distroless/base-debian12

WORKDIR /app

COPY --from=builder /app/skylogs-sentinel /app/skylogs-sentinel
COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

USER nonroot:nonroot

ENTRYPOINT ["/app/skylogs-sentinel"]

