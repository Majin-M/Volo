# --- Etape 1 : build du frontend ---
FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

# L'URL de l'API en prod : Nginx reverse-proxifie /api vers PHP-FPM,
# donc le frontend appelle la meme origine (pas de CORS).
RUN npm run build

# --- Etape 2 : servir les fichiers statiques ---
FROM nginx:alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
