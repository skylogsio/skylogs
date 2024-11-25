# Stage 1: Build the Go binary
FROM golang:1.23.2-alpine AS builder

# Set the working directory in the container
WORKDIR /app

# Copy go.mod and go.sum files to the workspace
COPY ../../apps/backend/go.mod ./
# Download all dependencies
RUN go mod download

# Copy the rest of the source code
COPY ../../apps/backend .
COPY ../../config/config.yaml .

# Build the Go app
RUN go build -o main .

# Stage 2: Create a small image for the Go app
FROM alpine:latest

# Set the working directory in the container
WORKDIR /root/

# Copy the binary from the builder image
#COPY --from=builder /app/jwt_secret.pem .
COPY --from=builder /app/config.yaml .
COPY --from=builder /app/main .

# Expose the application on port 8080 (if needed)
EXPOSE 8080

# Command to run the executable
CMD ["./main" ,"serve"]
#CMD ["sleep", "infinity"]
