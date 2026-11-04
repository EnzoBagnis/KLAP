# Étape 1 : build de l'app React avec Vite
# Node 24 (LTS) : Node 20 n'est plus maintenu et jsdom exige Node 22 ou plus.
FROM node:24-alpine AS build

WORKDIR /app
COPY package.json package-lock.json ./
# npm ci installe exactement les versions du lockfile.
RUN npm ci

COPY . .
RUN npm run build

# Étape 2 : on sert le build (dist/) avec nginx
FROM nginx:alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
