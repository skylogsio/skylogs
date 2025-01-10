# Use Node.js LTS version as base image
FROM node:18-alpine AS builder

# Set working directory
WORKDIR /usr/src/app

# Copy package.json and lock files
COPY package.json package-lock.json ./

# Install dependencies
RUN npm install --force

# Copy the rest of the application files
COPY . .

# Build the Next.js application
RUN npm run build

# Use a minimal Node.js runtime for production
FROM node:18-alpine AS runner

# Set working directory
WORKDIR /usr/src/app

# Copy only the necessary files from the builder
COPY --from=builder /usr/src/app/package.json ./
COPY --from=builder /usr/src/app/node_modules ./node_modules
COPY --from=builder /usr/src/app/.next ./.next
COPY --from=builder /usr/src/app/public ./public

# Expose port
EXPOSE 3000

# Command to run the application
CMD ["npm", "run", "start"]