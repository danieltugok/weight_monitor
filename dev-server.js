// Local dev server: serves this folder and forwards api.php to the live site, so you work with the real database.
// Usage: node dev-server.js   then open http://localhost:8000
// Careful: saves and deletes made locally change the LIVE data.
const http = require('http');
const fs = require('fs');
const path = require('path');

const LIVE = process.env.LIVE_URL || 'https://quirky-moccasin-wasp-22.hostingersite.com';
const PORT = process.env.PORT || 8000;
const ROOT = __dirname;
const TYPES = { '.html': 'text/html', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png', '.webmanifest': 'application/manifest+json', '.json': 'application/json' };

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  try {
    if (url.pathname === '/api.php') {
      const body = req.method === 'POST' ? await new Promise(r => { let b = ''; req.on('data', c => b += c); req.on('end', () => r(b)); }) : undefined;
      const r = await fetch(LIVE + '/api.php' + url.search, {
        method: req.method, body,
        headers: { 'Content-Type': req.headers['content-type'] || 'application/json', 'User-Agent': 'weight-monitor-dev' },
      });
      res.writeHead(r.status, { 'Content-Type': 'application/json' });
      return res.end(await r.text());
    }
    const file = path.join(ROOT, url.pathname === '/' ? 'index.html' : decodeURIComponent(url.pathname));
    // Only serve app files from this folder: never config.php, dotfiles, or anything outside it.
    if (!file.startsWith(ROOT + path.sep) || !TYPES[path.extname(file)] || path.basename(file).startsWith('.')) {
      res.writeHead(404); return res.end('Not found');
    }
    const data = await fs.promises.readFile(file);
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] });
    res.end(data);
  } catch (e) {
    res.writeHead(e.code === 'ENOENT' ? 404 : 502, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: e.code === 'ENOENT' ? 'Not found' : 'Could not reach ' + LIVE + ': ' + e.message }));
  }
}).listen(PORT, () => console.log(`Weight Monitor dev: http://localhost:${PORT}  (data from ${LIVE})`));
