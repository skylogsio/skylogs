FROM golang:1.23.2-alpine AS builder

WORKDIR /app

COPY ./ ./

WORKDIR /app/apps/backend

RUN go mod download


RUN go build -o main .

FROM alpine:latest

WORKDIR /root/

COPY --from=builder /app/config.yaml .
COPY --from=builder /app/apps/backend/main .

EXPOSE 8080

CMD ["./main" ,"serve"]
