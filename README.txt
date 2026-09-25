WatrBX Admin Classic Plus - Backend Command Expansion

This addon keeps the existing Admin Panel and its existing routes/UI.
It does NOT replace the panel with a new top navigation bar.

The added ADMIN COMMAND TOOLKIT is placed in routes/webhandler.php near the
existing admin command routes. It adds backend actions including:

- User search JSON endpoint
- Password reset
- Force logout / session revocation
- Clear inventory
- Remove friendship/request
- Broadcast private message
- Site feed announcements
- Feed pruning
- Expired session/captcha cleanup
- Log pruning
- Bulk asset property editing
- Reset asset sales
- Clear asset votes
- Universe editing
- Universe deletion + visit/badge cleanup
- Job stop/reset/delete
- API key revocation
- Server registry removal
- Account-state reset

Existing Admin Classic Plus functionality is preserved.

Install from the project root:
  unzip -o watrbx-admin-classic-plus-command-expansion.zip

Then restart the PHP server.
