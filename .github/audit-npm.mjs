// Audit npm de la CI : une faille haute ou critique fait echouer la
// compilation, sauf les avis listes ci-dessous, examines un par un.
//
// GHSA-vfj7-8cjw-p6xm : braces <= 3.0.3, deni de service par des motifs
// imbriques. Aucune version corrigee n'est publiee ; braces ne sert qu'a
// Tailwind 3 pendant la compilation, avec les motifs fixes de
// tailwind.config.js. Rien ne part dans le navigateur ni sur le serveur.
// A retirer des qu'une version corrigee sort, ou au passage a Tailwind 4.
//
// Usage : npm audit --json > audit-npm.json ; node .github/audit-npm.mjs < audit-npm.json

const IGNORES = new Set(['GHSA-vfj7-8cjw-p6xm']);
const GRAVES = new Set(['high', 'critical']);

let texte = '';
for await (const morceau of process.stdin) texte += morceau;

let rapport;
try {
    rapport = JSON.parse(texte);
} catch {
    console.error('Audit npm illisible : la CI echoue par prudence.');
    process.exit(1);
}

// npm audit en panne (registre injoignable...) : pas de rapport, donc
// rien ne prouve l'absence de faille.
if (rapport.error || !rapport.vulnerabilities || !rapport.metadata) {
    console.error('Audit npm incomplet : la CI echoue par prudence.');
    process.exit(1);
}

const failles = rapport.vulnerabilities;

// Les avis d'origine d'un paquet : ses propres avis, plus ceux des paquets
// dont il herite la faille (chokidar -> braces).
function avis(nom, vus = new Set()) {
    if (vus.has(nom) || !failles[nom]) return new Set();
    vus.add(nom);
    const trouves = new Set();
    for (const via of failles[nom].via) {
        if (typeof via === 'string') avis(via, vus).forEach((id) => trouves.add(id));
        else trouves.add(via.url?.split('/').pop() ?? String(via.source));
    }
    return trouves;
}

const bloquantes = Object.entries(failles)
    .filter(([, f]) => GRAVES.has(f.severity))
    // Sans avis d'origine identifiable, la faille bloque : on n'ignore que
    // ce qu'on a examine.
    .filter(([nom]) => { const ids = [...avis(nom)]; return ids.length === 0 || ids.some((id) => !IGNORES.has(id)); });

for (const [nom, f] of bloquantes) {
    console.error(`${f.severity} : ${nom} (${[...avis(nom)].join(', ')})`);
}

if (bloquantes.length > 0) {
    console.error(`${bloquantes.length} faille(s) haute(s) ou critique(s) non examinee(s).`);
    process.exit(1);
}

console.log('Audit npm : aucune faille haute ou critique hors des avis examines.');
