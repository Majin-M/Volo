# --- Etape 1 : build du frontend ---
FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

# Vite fige les variables VITE_* au build : elles arrivent en build arg.
ARG VITE_STRIPE_PUBLIC_KEY
ENV VITE_STRIPE_PUBLIC_KEY=${VITE_STRIPE_PUBLIC_KEY}

# L'API est servie sur la même origine (/api via Nginx) : pas d'URL à configurer.
RUN npm run build

# --- Etape 2 : servir les fichiers statiques ---
FROM nginx:alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
