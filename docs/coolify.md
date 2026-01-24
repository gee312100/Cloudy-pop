# Coolify deployment guide (Dockerfile + MariaDB)

This guide is optimized for Coolify’s “Dockerfile” deployment flow and the architecture in this repository.

## 0) What you will create in Coolify
You will provision **two services** inside the same Coolify project/environment:
1. **App service** (this repo, built by Dockerfile).
2. **MariaDB service** (managed database container).

The app container will talk to the database over Coolify’s internal network.

---

## 1) Create the MariaDB service first
1. In Coolify, open your **Project → Environment**.
2. Click **+ New Resource**.
3. Choose **Database → MariaDB**.
4. Configure:
   - Database name: `cloudypop`
   - Username: `cloudypop`
   - Password: generate a strong password
   - Root password: generate a strong password
5. Deploy the database.

After deployment, note the internal connection values Coolify provides. You will use them in the app service environment variables.

---

## 2) Create the app service from this repo
1. Click **+ New Resource → Application**.
2. Connect your Git provider/repository.
3. Choose this repo/branch.
4. Build pack / configuration:
   - **Build method:** Dockerfile
   - **Dockerfile path:** `./Dockerfile`
   - **Port:** `80`
   - **Health check path (recommended):** `/api/me.php`

Deploy once to confirm the container builds.

---

## 3) Add environment variables to the app service
In the app service settings, add the following environment variables.

> Use the internal MariaDB host and credentials from the MariaDB service you created.

Required variables:

```bash
DB_HOST=<coolify-mariadb-hostname>
DB_PORT=3306
DB_NAME=cloudypop
DB_USER=cloudypop
DB_PASS=<coolify-mariadb-password>
```

Redeploy the app service after saving environment variables.

---

## 4) Initialize the database schema on Coolify
Coolify’s managed MariaDB will not automatically run `db/schema.sql` from your app repo. You must apply it once.

### Option A (recommended): Coolify terminal into the database
1. Open the MariaDB service.
2. Use the **Terminal** tab.
3. Run:

```bash
mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /tmp/schema.sql
```

Then paste the contents of `db/schema.sql` into `/tmp/schema.sql` using the editor, or use Option B.

### Option B: run the schema from your local machine
From your local machine:

```bash
# 1) Port-forward or expose the MariaDB service in Coolify temporarily.
# 2) Then apply the schema.
mariadb \
  --host <public-db-host> \
  --port <public-db-port> \
  --user cloudypop \
  --password \
  cloudypop < db/schema.sql
```

After the schema is applied, redeploy the app service.

---

## 5) Configure domain + HTTPS
1. In the app service, open **Domains**.
2. Add your domain (e.g., `goon-control.example.com`).
3. Enable HTTPS/Let’s Encrypt.
4. Redeploy.

---

## 6) First-run checklist
After both services are deployed:
1. Visit your domain.
2. Create an account via Signup.
3. Test master flow:
   - Click **Master //**.
   - Confirm a 6-digit code is shown.
4. Test sub flow from another device/browser profile:
   - Login.
   - Click **Sub //**.
   - Enter the code.
   - Accept webcam + mic permissions.
5. Test controls:
   - Queue inhale/hold/break timers.
   - Broadcast queue.
   - Send surprise.
   - Send chat.

---

## Operational notes / recommendations
### Realtime + WebRTC
- This implementation uses **polling** for commands/chat/signaling.
- For production scale, consider:
  - Replacing polling with WebSockets.
  - Adding TURN servers for WebRTC reliability (especially on mobile networks).

### Moderation and abuse handling
- The `access_logs` table captures key user and control events.
- Suspension is enforced centrally in `require_auth()`.
- You should build a small admin UI later that reads `access_logs` and calls `/api/admin-suspend.php`.

### Backups
- Enable scheduled backups for the MariaDB service in Coolify.
- Validate restore procedures before going live.
