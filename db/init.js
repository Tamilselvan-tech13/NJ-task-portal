const sqlite3 = require("sqlite3").verbose();
const path = require("path");
const fs = require("fs");

function initDb() {
  const DATA_DIR = process.env.DATA_DIR || "./data";

  // Create data directory if it doesn't exist
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }

  const dbPath = path.join(DATA_DIR, "tasks.db");

  const db = new sqlite3.Database(dbPath, (err) => {
    if (err) {
      console.error("Error opening database:", err);
      process.exit(1);
    } else {
      console.log("Connected to SQLite database at:", dbPath);
      initializeTables(db);
    }
  });

  return db;
}

function initializeTables(db) {
  // Users table
  db.run(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT UNIQUE NOT NULL,
      email TEXT UNIQUE NOT NULL,
      password TEXT NOT NULL,
      role TEXT DEFAULT 'user',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
  `);

  // Tasks table
  db.run(`
    CREATE TABLE IF NOT EXISTS tasks (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT,
      status TEXT DEFAULT 'pending',
      assigned_to INTEGER,
      created_by INTEGER NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(assigned_to) REFERENCES users(id),
      FOREIGN KEY(created_by) REFERENCES users(id)
    )
  `);

  // Attendance table
  db.run(`
    CREATE TABLE IF NOT EXISTS attendance (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      date DATE NOT NULL,
      status TEXT DEFAULT 'present',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(user_id) REFERENCES users(id)
    )
  `);

  // Files table
  db.run(`
    CREATE TABLE IF NOT EXISTS files (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      filename TEXT NOT NULL,
      filepath TEXT NOT NULL,
      uploaded_by INTEGER NOT NULL,
      task_id INTEGER,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(uploaded_by) REFERENCES users(id),
      FOREIGN KEY(task_id) REFERENCES tasks(id)
    )
  `);

  console.log("Database tables initialized successfully");
}

module.exports = { initDb };
