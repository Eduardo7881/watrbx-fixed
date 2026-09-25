WatrBX Admin Panel Plugin
=========================

This addon extends the existing local WatrBX admin panel. It does not replace the whole WatrBX project.

Install from the WatrBX project root:

  unzip -o watrbx-admin-plus.zip

The archive contains:
  routes/webhandler.php
  routes/clienthandler.php
  classes/watrbx/thumbnails.php
  views/admin.php

Open:
  http://127.0.0.1:8080/admin

Features:
- Dashboard counters
- Search users by username/ID
- Grant ROBUX and Tix
- Set Builders Club membership
- Grant/revoke admin permission
- Grant catalog assets to users
- Warnings and timed bans using the existing moderation table
- Send messages as the logged-in admin
- Delete users and their owned items/messages/moderation records
- Create catalog assets with local asset + thumbnail uploads
- Limited / Limited Unique / Featured / remaining fields
- Delete catalog assets and their local files/thumbnails

Requires the existing WatrBX database schema. No SQL migration is required.
