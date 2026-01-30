# Railway Deployment Guide for Evento

## Step-by-Step Deployment Process

### 1. Prerequisites
- GitHub account with your project repository
- Railway.app account (free tier available)

### 2. Deploy to Railway

1. **Visit Railway.app**
   - Go to https://railway.app/
   - Sign up/Login with your GitHub account

2. **Create New Project**
   - Click "New Project"
   - Select "Deploy from GitHub repo"
   - Choose your `Evento` repository

3. **Add MySQL Database**
   - In your Railway project dashboard
   - Click "New" → "Database" → "Add MySQL"
   - Railway will provision a MySQL database

### 3. Configure Environment Variables

In your Railway project, go to "Variables" and add:

```
DB_HOST=<your-mysql-host-from-railway>
DB_NAME=<your-database-name>
DB_USER=<your-mysql-user>  
DB_PASS=<your-mysql-password>
DB_PORT=3306
BASE_URL=<your-railway-app-url>
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USER=<your-gmail>
EMAIL_PASS=<your-app-password>
```

### 4. Database Setup

After deployment:

1. **Get Database Credentials**
   - Go to your MySQL service in Railway
   - Copy connection details from "Connect" tab

2. **Run Database Migration**
   - Access your app URL: `https://your-app.up.railway.app/migrate.php`
   - This will set up your database tables

3. **Import Your Data**
   - Use Railway's database console or MySQL client
   - Import your existing data from `u149605981_evento.sql`

### 5. File Upload Setup

Create upload directories:
```bash
mkdir -p public/uploads/profiles
mkdir -p uploads/profiles  
mkdir -p logs
```

### 6. SSL & Security

Railway automatically provides:
- SSL certificates (HTTPS)
- Custom domain support
- Environment isolation

### 7. Testing

1. Visit your Railway app URL
2. Test login functionality
3. Check database connections
4. Verify file uploads work
5. Test email functionality

### 8. Custom Domain (Optional)

1. In Railway dashboard, go to "Settings"
2. Add your custom domain
3. Update DNS records as instructed
4. Update `BASE_URL` environment variable

## Troubleshooting

### Common Issues:

1. **Database Connection Error**
   - Check environment variables
   - Verify MySQL service is running
   - Run `migrate.php` to set up tables

2. **File Upload Issues**
   - Check directory permissions
   - Verify upload paths exist

3. **Email Not Working**
   - Use Gmail App Password (not regular password)
   - Enable 2-factor authentication first

### Monitoring

- Check Railway logs for errors
- Monitor database connections
- Set up error logging

## Cost Considerations

Railway Pricing:
- Hobby Plan: $5/month per service
- MySQL: ~$5/month
- Total: ~$10/month for production app

## Support

For deployment issues:
- Railway Documentation: https://docs.railway.app/
- Railway Discord: https://discord.gg/railway