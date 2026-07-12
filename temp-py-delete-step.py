import sqlite3
conn = sqlite3.connect(r'd:/nj document/nj-team-task/nj-team-task/db/tasks.db')
conn.execute('PRAGMA foreign_keys = ON')
cur = conn.cursor()
userId = 8
steps = [
    ('notifications', 'DELETE FROM notifications WHERE user_id = ?', (userId,)),
    ('daily_reports', 'DELETE FROM daily_reports WHERE user_id = ?', (userId,)),
    ('task_updates_user', 'DELETE FROM task_updates WHERE user_id = ?', (userId,)),
    ('task_updates_task', 'DELETE FROM task_updates WHERE task_id IN (SELECT id FROM tasks WHERE assigned_to = ? OR assigned_by = ?)', (userId, userId)),
    ('tasks', 'DELETE FROM tasks WHERE assigned_to = ? OR assigned_by = ?', (userId, userId)),
    ('users', 'DELETE FROM users WHERE id = ?', (userId,)),
]
for name, sql, params in steps:
    try:
        cur.execute(sql, params)
        conn.commit()
        print(name, 'deleted', cur.rowcount)
    except sqlite3.IntegrityError as e:
        print('FAILED on', name, e)
        break
conn.close()
