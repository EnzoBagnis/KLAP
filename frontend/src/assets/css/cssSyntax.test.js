import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

/*
 * Garde-fou : une accolade oubliée dans un fichier CSS (une résolution de conflit, par exemple)
 * imbrique toutes les règles suivantes. Au build, les fichiers sont mis bout à bout : l'erreur
 * casse alors les autres écrans (la navigation de la fiche n'avait plus aucun style).
 */
const DIR = join(process.cwd(), 'src/assets/css');
const files = readdirSync(DIR).filter((name) => name.endsWith('.css'));

function unbalanced(css) {
  const code = css.replace(/\/\*[\s\S]*?\*\//g, '').replace(/"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/g, '""');
  let depth = 0;
  let line = 1;
  for (const char of code) {
    if (char === '\n') line += 1;
    if (char === '{') depth += 1;
    if (char === '}') depth -= 1;
    if (depth < 0) return `accolade fermante en trop, ligne ${line}`;
  }
  return depth === 0 ? null : `${depth} accolade(s) non fermée(s)`;
}

describe('Feuilles de style', () => {
  it.each(files)('%s a des accolades équilibrées', (name) => {
    expect(unbalanced(readFileSync(join(DIR, name), 'utf8'))).toBeNull();
  });

  it('repère une accolade oubliée', () => {
    expect(unbalanced('.a { color: red;\n.b { color: blue; }')).toBe('1 accolade(s) non fermée(s)');
    expect(unbalanced('.a { content: "}"; }')).toBeNull();
  });
});
