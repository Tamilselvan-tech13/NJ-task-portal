const Database = require('better-sqlite3');
const db = new Database('db/tasks.db');
const id = 8;
const tables = ['notifications', 'daily_reports', 'task_updates', 'tasks'];
for (const t of tables) {
  const cols = db.prepare(`PRAGMA table_info("${t}")`).all().map(r => r.name);
  console.log('TABLE', t, cols);
  for (const c of cols) {
    const row = db.prepare(`SELECT count(*) AS c FROM ${t} WHERE ${c} = ?`).get(id);
    if (row.c > 0) console.log('  col', c, row.c);
  }
}
db.close();
