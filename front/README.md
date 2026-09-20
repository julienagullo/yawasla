# Yawasla — Front

React + TypeScript, compilé avec Vite.

```
cp .env.example .env.local
npm install
npm run dev      # serveur de dev, /api proxifié vers le back PHP
npm run build    # build de production dans dist/
npm run lint
```

## Structure

- `src/app/` : point d'entrée de l'app et routeur
- `src/api/` : client HTTP vers le back (`/api`)
- `src/layouts/` : layouts public et back-office
- `src/pages/public/` : front-office (newsroom, médias)
- `src/pages/admin/` : back-office (connexion, tableau de bord)
- `src/styles/` : styles globaux
