// server.js
const express = require('express');
const session = require('express-session');
const fileUpload = require('express-fileupload');
const path = require('path');
const fs = require('fs');
const os = require('os');
const { initDb } = require('./db/init');
const { authUserFromToken } = require('./lib/jwt');

const app = express();
const isDev = process.env.NODE_ENV !== 'production';
const START_PORT = parseInt(process.env.PORT, 10) || 3000;
const DATA_DIR = process.env.DATA_DIR || (process.env.VERCEL ? path.join(os.tmpdir(), 'nj-team-task') : path.join(__dirname, 'db'));
const UPLOADS_DIR = process.env.UPLOADS_DIR || (process.env.VERCEL ? path.join(DATA_DIR, 'uploads') : path.join(__dirname, 'public', 'uploads'));
const SESSIONS_DIR = process.env.SESSIONS_DIR || DATA_DIR;

for (const dir of [DATA_DIR, UPLOADS_DIR, SESSIONS_DIR]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

// Init DB
initDb();

// Middleware
app.use((req, res, next) => {
  req.requestId = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
  res.setHeader('X-Request-Id', req.requestId);
  if (isDev) {
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
    res.setHeader('Pragma', 'no-cache');
    res.setHeader('Expires', '0');
    console.log(`[${req.requestId}] ${req.method} ${req.originalUrl}`);
  }
  next();
});
// Remove upload size limit - unlimited uploads
app.use(express.json({ limit: '50gb' }));
app.use(express.urlencoded({ extended: true, limit: '50gb' }));
app.use(fileUpload({
  limits: { fileSize: 50 * 1024 * 1024 * 1024 }, // 50 GB - effectively unlimited
  abortOnLimit: false,
  responseOnLimit: 'File upload limit reached',
  useTempFiles: true,
  tempFileDir: os.tmpdir()
}));
app.use('/uploads', express.static(UPLOADS_DIR, {
  etag: !isDev,
  lastModified: !isDev,
  maxAge: isDev ? 0 : '1d'
}));
app.use(express.static(path.join(__dirname, 'public'), {
  etag: !isDev,
  lastModified: !isDev,
  maxAge: isDev ? 0 : '1h',
  setHeaders: (res) => {
    if (isDev) {
      res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
    }
  }
}));

// Session
const SQLiteStore = require('connect-sqlite3')(session);
app.use(session({
  store: new SQLiteStore({ db: 'sessions.db', dir: SESSIONS_DIR }),
  secret: process.env.SESSION_SECRET || 'nj-team-task-secret-2024',
  resave: false,
  saveUninitialized: false,
  cookie: { maxAge: 24 * 60 * 60 * 1000 } // 24 hours
}));
app.use(authUserFromToken);

// Routes
app.use('/api/auth', require('./routes/auth'));
app.use('/api/tasks', require('./routes/tasks'));
app.use('/api/users', require('./routes/users'));
app.use('/api/attendance', require('./routes/attendance').router);
app.use('/api/projects', require('./routes/projects'));
app.use('/api/reports', require('./routes/reports'));

// Serve SPA
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Try listening on START_PORT, and increment if port is already in use
function startServer(port, attemptsLeft = 10) {
  const server = app.listen(port);

  server.on('listening', () => {
    console.log(`\n🚀 NJ Team Task Portal running at http://localhost:${port}`);
    console.log(`\n📋 Default credentials:`);
    console.log(`   Admin:  admin / admin123`);
    console.log(`   Users:  navin/navin123, james/james123, priya/priya123`);
    console.log(`           ravi/ravi123, meera/meera123\n`);
  });

  server.on('error', (err) => {
    if (err.code === 'EADDRINUSE' && attemptsLeft > 0) {
      console.warn(`Port ${port} in use, trying ${port + 1}...`);
      // give a short delay then try next port
      setTimeout(() => startServer(port + 1, attemptsLeft - 1), 200);
    } else {
      console.error('Server error:', err);
      process.exit(1);
    }
  });
}

if (require.main === module) {
  startServer(START_PORT);
}

module.exports = app;
