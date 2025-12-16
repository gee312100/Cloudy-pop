# CloudyPop

CloudyPop is a progressive web app (PWA) breathing game with dramatic inhale/hold/exhale reveals, audio cues, and customizable theming. The app runs entirely in the browser (or as an installable PWA) and remembers your preferences between sessions.

## Features
- Installable PWA with offline caching and maskable SVG icon.
- Light/dark theme toggle with sun/moon indicator, accent and background customization, and optional wake-lock while playing.
- Game loop with startup warning, dramatic timer reveal, randomized inhale/hold/exhale durations, per-phase beeps, and cycle/back/skip controls.
- Settings grouped into Time, Cycle, Page, and Music & Audio sections with persistent storage and immediate application on save.
- Music selection from bundled tracks, custom links, or uploads, plus independent sliders for music and sound effects.

## Running locally
1. Serve the static files from the repo root so the service worker and manifest load correctly:
   ```bash
   python -m http.server 8000
   # then open http://localhost:8000
   ```
2. Add the PWA to your device from the browser's install option if desired.

## Docker
Build and run a containerized copy with Nginx:
```bash
docker build -t cloudypop .
docker run -p 8080:8080 cloudypop
# open http://localhost:8080
```
The bundled Nginx config serves `index.html` at the root scope so the service worker and manifest register correctly.

## Controls and settings
- **Top right:** Settings gear, light/dark toggle.
- **Bottom left:** Back (previous stage) and Skip (next stage or restart after last break).
- **Bottom right:** Play/Pause, Mute, sliders for SFX and music volumes.
- **Settings modal:**
  - Time Control: 0–30s number inputs for inhale/hold/exhale, ranges for break/rest and session delay.
  - Cycle Control: number of cycles.
  - Page Control: background color/image upload or link, accent color, wake-lock toggle.
  - Music & Audio: choose bundled tracks, custom link, or upload; volume sliders for music and sound effects.

Saving settings immediately applies them, refreshes the preview values, and stores them in localStorage so they are restored on reload.

## Testing
- Validate manifest formatting:
  ```bash
  python -m json.tool manifest.json
  ```
- (Optional) Run HTML checks such as `npx --yes htmlhint index.html` if your environment allows external package installs.

## Project layout
- `index.html` – main PWA and game logic.
- `manifest.json` – PWA metadata.
- `sw.js` – service worker cache.
- `icons/icon.svg` – maskable app icon.
- `Dockerfile`, `nginx.conf`, `.dockerignore` – container packaging.
