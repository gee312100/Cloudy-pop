# GOON//CONTROL – Cloudy Pop Rebuild

This repository has been rebuilt from scratch as a PHP + MariaDB real-time command game. It delivers a dramatic animated entry sequence, a master/sub control flow, and backend endpoints for authentication, access logs, suspension controls, command queues, and lightweight WebRTC signaling.

## Stack overview
- **Frontend:** HTML5, modern CSS animations, and vanilla ES modules (`public/`).
- **Backend:** PHP 8.3 with Apache and PDO MySQL (`api/`).
- **Database:** MariaDB 11 with a schema initializer (`db/schema.sql`).
- **Container runtime:** Dockerfile-based build (ideal for Coolify) plus `docker-compose.yml` for local development.

## Key gameplay flow implemented
1. **Animated entry:** Sky-blue scene with sun, a lightbulb reveal, then a hard cut to black as angry clouds sweep in and slam the title `GOON//CONTROL`.
2. **Auth gate:** Email + password signup/login.
3. **Role select:**
   - **Master //** creates a 6-digit code.
   - **Sub //** enters that code, must accept webcam + mic permissions, and lands on a black “establishing connection” screen.
4. **Realtime controls:**
   - Master can queue and broadcast `Inhale`, `Hold`, and `Break` timers.
   - Surprise countdown commands.
   - Master chat appears on the sub screen.
   - Commands can be saved and re-applied as named sequences.

> Note: WebRTC is implemented with polling-based signaling endpoints. For production scale or multiple concurrent subs per master, you should move signaling to WebSockets and add TURN infrastructure.

---

## Project layout
- `public/index.html` – entry animation, auth, master/sub UI.
- `public/styles.css` – full visual system and animations.
- `public/app.js` – client state, API integration, queueing, polling, and WebRTC signaling.
- `api/*.php` – JSON endpoints for auth, sessions, commands, chat, sequences, signaling, and admin suspension.
- `db/schema.sql` – MariaDB schema.
- `Dockerfile` – PHP 8.3 Apache build targeting `public/` as the document root.
- `docker-compose.yml` – local app + database orchestration.
- `docs/coolify.md` – Coolify-specific deployment instructions.

---

## Local development with Docker Compose

### 1) Start the stack
```bash
docker compose up --build
```

The app will be available at:
- http://localhost:8080

The database will be available at:
- Host: `127.0.0.1`
- Port: `3307`
- Database: `cloudypop`
- User: `cloudypop`
- Password: `cloudypop`

### 2) Initialize the database
The schema is mounted automatically into `/docker-entrypoint-initdb.d/` and will run on first boot of the database container.

If you need to reset the schema:
```bash
docker compose down -v
docker compose up --build
```

---

## Environment variables
The PHP API reads the following variables:

```bash
DB_HOST=db
DB_PORT=3306
DB_NAME=cloudypop
DB_USER=cloudypop
DB_PASS=cloudypop
```

Copy `.env.example` if you want to manage these explicitly:
```bash
cp .env.example .env
```

---

## Admin suspension workflow
Suspension is handled via the `users.suspended` flag and enforced by `require_auth()`.

To suspend a user you must:
1. Promote a user to admin in the database:
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
   ```
2. Call the admin endpoint:
   - `POST /api/admin-suspend.php`
   - Body:
     ```json
     { "user_id": 42, "suspended": 1 }
     ```

All key events (login, session creation, command polling, etc.) are stored in `access_logs`.

---

## Deployment on Coolify
Coolify works best with the Dockerfile approach in this repo. Follow the full guide here:

👉 `docs/coolify.md`

---

## API surface (high-level)
- Auth: `/api/signup.php`, `/api/login.php`, `/api/logout.php`, `/api/me.php`
- Sessions: `/api/create-session.php`, `/api/join-session.php`
- Commands: `/api/send-command.php`, `/api/fetch-commands.php`
- Chat: `/api/post-chat.php`, `/api/fetch-chats.php`
- Sequences: `/api/save-sequence.php`, `/api/list-sequences.php`, `/api/apply-sequence.php`
- WebRTC signaling: `/api/post-signal.php`, `/api/fetch-signals.php`
- Admin: `/api/admin-suspend.php`

All endpoints expect and return JSON.
