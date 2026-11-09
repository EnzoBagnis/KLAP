/*
 * .htaccess du frontend, produit par le build (#203). Le frontend est servi par Apache (Alwaysdata)
 * en fichiers statiques : ce fichier renvoie les routes React vers index.html, envoie les en-têtes
 * de sécurité de l'audit (docs/AUDIT_SECURITE.md, section 5) et règle le cache.
 *
 * L'adresse de l'API vient de VITE_API_URL, comme dans ApiClient.js : la CSP n'autorise que
 * cette origine pour les appels réseau.
 */

const DEFAULT_API_URL = 'http://localhost:8080/api';

/**
 * Origine de l'API (schéma, hôte, port), sans chemin. Même règle que ApiClient.js : sans
 * variable, l'adresse locale par défaut ; une adresse vide ou relative (« /api ») vise le site
 * lui-même, déjà autorisé par 'self' : null.
 */
export function apiOrigin(apiUrl) {
  try {
    return new URL(apiUrl ?? DEFAULT_API_URL).origin;
  } catch {
    return null;
  }
}

export function contentSecurityPolicy(apiUrl) {
  return [
    "default-src 'self'",
    "script-src 'self'",
    "style-src 'self'",
    "img-src 'self' data: blob:",
    // Les polices sont intégrées au CSS en data:.
    "font-src 'self' data:",
    ['connect-src', "'self'", apiOrigin(apiUrl)].filter(Boolean).join(' '),
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join('; ');
}

export function htaccess(apiUrl) {
  return `# Produit par le build (frontend/build/htaccess.js, #203) : ne pas modifier à la main.

Options -Indexes

<IfModule mod_rewrite.c>
    RewriteEngine On
    # Routes React (/projets/1, /activation/…) : tout ce qui n'est pas un fichier va à index.html.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.html [L]
</IfModule>

<IfModule mod_headers.c>
    Header always set Content-Security-Policy "${contentSecurityPolicy(apiUrl)}"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
    Header always unset X-Powered-By

    # index.html toujours revalidé : il désigne les fichiers du dernier build.
    <FilesMatch "^index\\.html$">
        Header set Cache-Control "no-cache"
    </FilesMatch>
</IfModule>

# Fichiers du build : leur nom contient leur empreinte et change à chaque version.
<IfModule mod_headers.c>
    <If "%{REQUEST_URI} =~ m#^/assets/#">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </If>
</IfModule>
`;
}

/** Plugin Vite : ajoute .htaccess au dossier de build. */
export function htaccessPlugin(apiUrl) {
  return {
    name: 'klap-htaccess',
    apply: 'build',
    generateBundle() {
      this.emitFile({ type: 'asset', fileName: '.htaccess', source: htaccess(apiUrl) });
    },
  };
}
