#!/bin/bash
# Production Deployment Setup Script for Evento
# Run this before deploying to production

echo "🚀 Evento Production Setup"
echo "=========================="
echo ""

# Create logs directory
echo "📁 Creating logs directory..."
mkdir -p logs
chmod 755 logs
echo "✅ Logs directory created"
echo ""

# Create backup directory
echo "📁 Creating backup directory..."
mkdir -p backups
chmod 755 backups
echo "✅ Backup directory created"
echo ""

# Check config file
echo "⚙️ Checking configuration..."
if [ -f "config/config.php" ]; then
    echo "✅ Config file found"
    
    # Check if BASE_URL is updated
    if grep -q "localhost" config/config.php; then
        echo "⚠️ WARNING: BASE_URL still contains 'localhost'"
        echo "   Please update it to your production domain!"
    fi
    
    # Check if email credentials are set
    if grep -q "hitanshpparikh@gmail.com" config/config.php; then
        echo "⚠️ WARNING: Email credentials not updated"
        echo "   Please update MAIL_USERNAME and MAIL_PASSWORD!"
    fi
else
    echo "❌ Config file not found!"
fi
echo ""

# Set file permissions
echo "🔐 Setting secure file permissions..."
chmod 644 config/config.php
chmod 644 config/database.php
echo "✅ File permissions set"
echo ""

echo "📋 Production Checklist:"
echo "========================"
echo "□ Run database migration: database/migrate_email_verification.php"
echo "□ Update BASE_URL in config/config.php"
echo "□ Update email credentials (MAIL_USERNAME, MAIL_PASSWORD)"
echo "□ Set up Gmail App Password"
echo "□ Enable HTTPS (SSL certificate)"
echo "□ Test email sending: database/test-email.php"
echo "□ Review PRODUCTION_DEPLOYMENT.md guide"
echo ""
echo "✨ Setup script completed!"
