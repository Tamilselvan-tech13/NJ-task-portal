const Database = require('better-sqlite3');
const db = new Database('./db/tasks.db');
const users = db.prepare('SELECT id, username, full_name, role, avatar_color, avatar_url FROM users').all();
console.log(JSON.stringify(users, null, 2));
db.close();
