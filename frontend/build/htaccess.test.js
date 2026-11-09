import { apiOrigin, contentSecurityPolicy, htaccess } from './htaccess.js';

describe('.htaccess du frontend (#203)', () => {
  it('ne garde que l’origine de l’API', () => {
    expect(apiOrigin('https://api-klap.alwaysdata.net/api')).toBe('https://api-klap.alwaysdata.net');
    expect(apiOrigin('http://localhost:8090/api/')).toBe('http://localhost:8090');
    expect(apiOrigin(undefined)).toBe('http://localhost:8080');
  });

  it('se contente du site lui-même pour une adresse vide ou relative', () => {
    expect(apiOrigin('')).toBeNull();
    expect(apiOrigin('/api')).toBeNull();
    expect(contentSecurityPolicy('/api')).toContain("connect-src 'self';");
  });

  it('n’autorise les appels réseau que vers le site et l’API', () => {
    const csp = contentSecurityPolicy('https://api-klap.alwaysdata.net/api');
    expect(csp).toContain("connect-src 'self' https://api-klap.alwaysdata.net;");
    expect(csp).toContain("script-src 'self';");
    expect(csp).not.toContain('unsafe');
  });

  it('renvoie les routes React vers index.html et pose les en-têtes', () => {
    const file = htaccess('https://api-klap.alwaysdata.net/api');
    expect(file).toContain('RewriteRule ^ index.html [L]');
    expect(file).toContain('Header always set Content-Security-Policy');
    expect(file).toContain('X-Content-Type-Options "nosniff"');
  });
});
