# Railway Deployment Configuration

## Environment Variables Required

Set these environment variables in your Railway project:

### Database Configuration
- `DB_HOST` - Railway MySQL hostname
- `DB_NAME` - Database name  
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_PORT` - Database port (usually 3306)

### Application Configuration  
- `BASE_URL` - Your Railway app URL (e.g., https://your-app.up.railway.app)
- `EMAIL_HOST` - SMTP host for sending emails
- `EMAIL_PORT` - SMTP port
- `EMAIL_USER` - SMTP username
- `EMAIL_PASS` - SMTP password
- `GOOGLE_CLIENT_ID` - Google OAuth client ID (optional)
- `GOOGLE_CLIENT_SECRET` - Google OAuth client secret (optional)

## Database Setup

1. Create a MySQL database service in Railway
2. Run the database migrations using the SQL files in your project
3. Import your database structure using `u149605981_evento.sql`

## File Upload Configuration

Create these directories on deployment:
- `uploads/profiles/`
- `public/uploads/profiles/`
- `logs/`

## SSL Configuration

Railway provides automatic SSL certificates. Your app will be accessible via HTTPS.