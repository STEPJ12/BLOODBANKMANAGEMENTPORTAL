const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    // Allow baseUrl to be provided via env or fallback to localhost
    baseUrl: process.env.CYPRESS_BASE_URL || process.env.BASE_URL || 'http://localhost/blood',
    setupNodeEvents(on, config) {
      // Add tasks for DB seeding/cleanup so tests can run fully automatically
      const mysql = require('mysql2/promise');
      const bcrypt = require('bcryptjs');

      const dbConfig = {
        host: process.env.DB_HOST || '127.0.0.1',
        port: process.env.DB_PORT ? Number.parseInt(process.env.DB_PORT, 10) : 3306,
        user: process.env.DB_USER || 'root',
        password: process.env.DB_PASS || '',
        database: process.env.DB_NAME || 'blood_bank_portal',
      };

      on('task', {
        // Simple debug task so tests can log server responses to the Node console
        debug(msg) {
          console.log('[cypress-debug]', msg);
          return null;
        },
        async 'db:seed'(opts) {
          const email = opts.email;
          const password = opts.password || 'Password123!';
          const name = opts.name || 'Cypress RedCross Test';
          const phone = opts.phone || '+639171234567';
          if (!email) return { ok: false, error: 'email required' };

          const conn = await mysql.createConnection(dbConfig);
          try {
            const hashed = await bcrypt.hash(password, 10);
            // Insert or update the test user
            const sql = `INSERT INTO redcross_users (name, email, password, phone, created_at)
              VALUES (?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), phone = VALUES(phone)`;
            const [res] = await conn.execute(sql, [name, email, hashed, phone]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },

        async 'db:clear'(opts) {
          const email = opts.email;
          if (!email) return { ok: false, error: 'email required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM redcross_users WHERE email = ?`;
            const [res] = await conn.execute(sql, [email]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        }
        ,
        async 'db:clear_rate_limit'(opts) {
          // opts: { action, ip } - deletes matching rate limit entries
          const action = opts.action || 'login:redcross';
          const ip = opts.ip || '127.0.0.1';
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM rate_limits WHERE ip = ? AND action = ?`;
            const [res] = await conn.execute(sql, [ip, action]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        }
        ,
        async 'db:create_donor'(opts) {
          // opts: { name, email, phone, blood_type, password }
          const name = opts.name || 'Cypress Donor';
          const email = opts.email;
          const phone = opts.phone || '+639171234570';
          const blood_type = opts.blood_type || 'O+';
          const password = opts.password || 'Password123!';
          if (!email) return { ok: false, error: 'email required' };

          const conn = await mysql.createConnection(dbConfig);
          try {
            const hashed = await bcrypt.hash(password, 10);
            const sql = `INSERT INTO donor_users (name, email, password, phone, blood_type, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, NOW(), NOW())`;
            const [res] = await conn.execute(sql, [name, email, hashed, phone, blood_type]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:clear_donor'(opts) {
          const email = opts.email;
          if (!email) return { ok: false, error: 'email required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM donor_users WHERE email = ?`;
            const [res] = await conn.execute(sql, [email]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        }
        ,
        async 'db:create_appointment'(opts) {
          // opts: { donor_id, appointment_date, appointment_time, organization_id }
          const donor_id = opts.donor_id;
          const appointment_date = opts.appointment_date;
          const appointment_time = opts.appointment_time;
          const org_id = opts.organization_id || null;
          if (!donor_id || !appointment_date || !appointment_time) return { ok: false, error: 'donor_id, appointment_date and appointment_time required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `INSERT INTO donor_appointments (donor_id, appointment_date, appointment_time, organization_type, organization_id, status, notes, created_at, updated_at) VALUES (?, ?, ?, 'redcross', ?, 'Pending', ?, NOW(), NOW())`;
            const [res] = await conn.execute(sql, [donor_id, appointment_date, appointment_time, org_id, 'Created by Cypress']);
            await conn.end();
            return { ok: true, insertedId: res.insertId || null };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:get_donor_by_email'(opts) {
          const email = opts.email;
          if (!email) return { ok: false, error: 'email required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `SELECT id, first_name, last_name, email, phone FROM donor_users WHERE email = ? LIMIT 1`;
            const [rows] = await conn.execute(sql, [email]);
            await conn.end();
            return { ok: true, row: rows[0] || null };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:get_appointment_by_id'(opts) {
          const id = opts.id;
          if (!id) return { ok: false, error: 'id required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `SELECT a.*, d.first_name, d.last_name, d.phone, d.email FROM donor_appointments a JOIN donor_users d ON a.donor_id = d.id WHERE a.id = ? LIMIT 1`;
            const [rows] = await conn.execute(sql, [id]);
            await conn.end();
            return { ok: true, row: rows[0] || null };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:clear_appointment'(opts) {
          const id = opts.id;
          if (!id) return { ok: false, error: 'id required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM donor_appointments WHERE id = ?`;
            const [res] = await conn.execute(sql, [id]);
            await conn.end();
            return { ok: true, affectedRows: res.affectedRows };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:create_announcement'(opts) {
          // opts: { title, content, priority }
          const title = opts.title || ('Cypress Announcement ' + Date.now());
          const content = opts.content || 'This is a test announcement created by Cypress.';
          const priority = opts.priority || 'medium';
          const conn = await mysql.createConnection(dbConfig);
          try {
            // Try to insert including priority (may not exist in all schemas)
            const sql = `INSERT INTO announcements (title, content, organization_type, status, priority, created_at, updated_at)
              VALUES (?, ?, 'redcross', 'Active', ?, NOW(), NOW())`;
            const [res] = await conn.execute(sql, [title, content, priority]);
            await conn.end();
            return { ok: true, insertedId: res.insertId || null, title };
          } catch (err) {
            // Fall back to a safer INSERT that doesn't reference optional columns
            try {
              const fallbackSql = `INSERT INTO announcements (title, content, organization_type, status, created_at, updated_at)
                VALUES (?, ?, 'redcross', 'Active', NOW(), NOW())`;
              const [res2] = await conn.execute(fallbackSql, [title, content]);
              await conn.end();
              return { ok: true, insertedId: res2.insertId || null, title };
            } catch (err2) {
              await conn.end();
              return { ok: false, error: err2.message || err.message };
            }
          }
        },
        async 'db:find_announcement'(opts) {
          // opts: { title }
          const title = opts.title;
          if (!title) return { ok: false, error: 'title required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            // Avoid selecting optional columns (like priority) which may not exist in all schemas
            const sql = `SELECT id, title, content, organization_type, status, created_at FROM announcements WHERE title LIKE ? AND organization_type = 'redcross' ORDER BY created_at DESC LIMIT 20`;
            const [rows] = await conn.execute(sql, ['%' + title + '%']);
            await conn.end();
            return { ok: true, rows };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:get_announcement_by_id'(opts) {
          // opts: { id }
          const id = opts.id;
          if (!id) return { ok: false, error: 'id required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `SELECT id, title, content, organization_type, status, created_at FROM announcements WHERE id = ? AND organization_type = 'redcross' LIMIT 1`;
            const [rows] = await conn.execute(sql, [id]);
            await conn.end();
            return { ok: true, row: rows[0] || null };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:clear_announcement'(opts) {
          // opts: { title }
          const title = opts.title;
          if (!title) return { ok: false, error: 'title required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM announcements WHERE title = ? AND organization_type = 'redcross'`;
            const [res] = await conn.execute(sql, [title]);
            await conn.end();
            return { ok: true, affectedRows: res.affectedRows };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        // Negros First user tasks
        async 'db:seed_negrosfirst'(opts) {
          const email = opts.email;
          const password = opts.password || 'Password123!';
          const name = opts.name || 'Cypress Negros First Test';
          const phone = opts.phone || '+639171234568';
          if (!email) return { ok: false, error: 'email required' };

          const conn = await mysql.createConnection(dbConfig);
          try {
            const hashed = await bcrypt.hash(password, 10);
            const sql = `INSERT INTO negrosfirst_users (name, email, password, phone, created_at)
              VALUES (?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), phone = VALUES(phone)`;
            const [res] = await conn.execute(sql, [name, email, hashed, phone]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:clear_negrosfirst'(opts) {
          const email = opts.email;
          if (!email) return { ok: false, error: 'email required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM negrosfirst_users WHERE email = ?`;
            const [res] = await conn.execute(sql, [email]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:get_negrosfirst_by_email'(opts) {
          const email = opts.email;
          if (!email) return { ok: false, error: 'email required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `SELECT id, name, email, phone FROM negrosfirst_users WHERE email = ? LIMIT 1`;
            const [rows] = await conn.execute(sql, [email]);
            await conn.end();
            return { ok: true, row: rows[0] || null };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:get_otp_for_user'(opts) {
          const userId = opts.userId;
          const role = opts.role || 'negrosfirst';
          if (!userId) return { ok: false, error: 'userId required' };
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `SELECT otp_code, expires_at FROM otp_codes WHERE user_id = ? AND user_role = ? AND purpose = 'login' AND used_at IS NULL ORDER BY created_at DESC LIMIT 1`;
            const [rows] = await conn.execute(sql, [userId, role]);
            await conn.end();
            return { ok: true, otp: rows[0]?.otp_code || null, expiresAt: rows[0]?.expires_at || null };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        },
        async 'db:clear_rate_limit_nf'(opts) {
          const action = opts.action || 'login:negrosfirst';
          const ip = opts.ip || '127.0.0.1';
          const conn = await mysql.createConnection(dbConfig);
          try {
            const sql = `DELETE FROM rate_limits WHERE ip = ? AND action = ?`;
            const [res] = await conn.execute(sql, [ip, action]);
            await conn.end();
            return { ok: true };
          } catch (err) {
            await conn.end();
            return { ok: false, error: err.message };
          }
        }
      });

      // return the updated config
      return config;
    },
  },
});
