# --- Etape 1 : build du frontend ---
FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

# Vite fige les variables VITE_* AU MOMENT DU BUILD, pas au demarrage du
# conteneur : les passer en `environment:` serait sans effet, elles doivent
# arriver ici en build arg. Sans cela, loadStripe() recoit undefined et le
# paiement est mort en production.
ARG VITE_STRIPE_PUBLIC_KEY
ENV VITE_STRIPE_PUBLIC_KEY=${VITE_STRIPE_PUBLIC_KEY}

# L'URL de l'API en prod : Nginx reverse-proxifie /api vers PHP-FPM,
# donc le frontend appelle la meme origine (pas de CORS).
RUN npm run build

# --- Etape 2 : servir les fichiers statiques ---
FROM nginx:alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
