# 🎓 Evento - College Event Management System

<div align="center">

![Evento Logo](https://img.shields.io/badge/Evento-Event%20Management-6366f1?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**Production-Ready College Event Management System**  
Built with PHP, MySQL, and Premium Glassmorphism UI

[Features](#-features) • [Installation](#-installation) • [Documentation](#-documentation) • [Demo](#-demo-credentials)

</div>

---

## ✨ Features

### 🔐 Authentication & Security
- **Single Registration System** - Register once, access all events
- **Role-Based Access Control** - 6 distinct user roles
- **Secure Authentication** - Password hashing, CSRF protection, XSS prevention
- **Session Management** - Timeout handling, remember me functionality
- **Audit Logging** - Complete activity tracking

### 👥 User Roles

| Role | Capabilities |
|------|-------------|
| 🎓 **Student** | View events, register, track attendance, download certificates |
| 👨‍🏫 **Faculty** | Create events, view registrations, export data |
| 🏛️ **HOD** | Department management, event oversight |
| 🎭 **Club Leader** | Manage club events, customize themes, analytics |
| 👔 **Authority** | View all analytics, system-wide reports |
| 🛡️ **Admin** | Full system control, user management, event approval |

### 📅 Event Management
- **One-Click Registration** - Simple event registration
- **Approval Workflow** - Admin approval required for events
- **Smart Capacity Management** - Auto-close when full
- **Deadline Enforcement** - Registration deadline validation
- **Rich Event Details** - Descriptions, venues, dates, banners
- **Club Association** - Link events to clubs

### 🎨 Premium UI/UX
- **Glassmorphism Design** - Modern liquid-glass aesthetic
- **Dark Mode** - Eye-friendly dark theme
- **Responsive Layout** - Works on all devices
- **Smooth Animations** - Micro-interactions throughout
- **Neon Accents** - Beautiful gradient effects
- **Interactive Components** - Hover effects, transitions

### 📊 Advanced Dashboards

#### Student Dashboard
- Event discovery and registration
- Personal event history
- Attendance tracking
- Upcoming events calendar

#### Faculty/HOD Dashboard
- Create and manage events
- View registrations
- Export attendee data
- Department analytics

#### Club Leader Dashboard
- Club event management
- Custom theme creation
- Registration statistics
- Engagement analytics

#### Admin Dashboard
- System statistics
- Pending event approvals
- User management
- Role assignment
- Audit log access
- System health monitoring

### 🔒 Security Features
- SQL Injection Protection (Prepared Statements)
- XSS Prevention (Input Sanitization)
- CSRF Token Validation
- Secure Password Hashing (Bcrypt)
- File Upload Validation
- Rate Limiting Ready
- IP Address Logging
- User Agent Tracking

### 🚀 Future-Ready Architecture
- QR Code Support (Infrastructure ready)
- Certificate Generation (Tables prepared)
- Email Notifications (Hooks ready)
- API Endpoints (Structure in place)
- Google Login Integration (Placeholder ready)
- PWA Support (Foundation ready)

---

## 📋 Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache (XAMPP, WAMP, LAMP) or Nginx
- **Extensions**: PDO, MySQLi, GD, JSON, MBString
- **Browser**: Modern browser with JavaScript enabled

---

## 🚀 Installation

### Step 1: Clone/Download Project

```bash
# Navigate to your web server directory
cd C:\xampp\htdocs

# The project is already in the 'evento' folder
```

### Step 2: Database Setup

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL

2. **Create Database**
   - Open phpMyAdmin: https://hitanshparikh.tech/evento/phpmyadmin
   - Click "New" to create a database
   - Database name: `evento`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"

3. **Import Schema**
   - Select the `evento` database
   - Click "Import" tab
   - Click "Choose File"
   - Select `C:\xampp\htdocs\evento\database\schema.sql`
   - Click "Go"
   - Wait for success message

### Step 3: Configuration

1. **Database Configuration** (Already configured)
   
   File: `config/database.php`
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Empty for XAMPP default
   define('DB_NAME', 'evento');
   ```

2. **Base URL Configuration** (Update if needed)
   
   File: `config/config.php`
   ```php
   define('BASE_URL', 'https://hitanshparikh.tech/evento');
   ```

### Step 4: Directory Permissions

Create uploads directory:
```bash
mkdir public/uploads
mkdir public/uploads/events
mkdir public/uploads/clubs
mkdir public/uploads/profiles
```

### Step 5: Access the System

- **Homepage**: https://hitanshparikh.tech/evento
- **Login**: https://hitanshparikh.tech/evento/login.php
- **Register**: https://hitanshparikh.tech/evento/register.php

---

## 🔑 Demo Credentials

### Admin Account
```
Email: admin@college.edu
Password: Admin@123
```

### Test Student Account
After installation, register as a student to test the system:
1. Go to https://hitanshparikh.tech/evento/register.php
2. Fill in the registration form
3. Use your college email format
4. Login and explore

---

## 📁 Project Structure

```
evento/
├── admin/                      # Admin dashboard and management
│   ├── dashboard.php
│   ├── users.php
│   ├── events.php
│   └── ...
├── student/                    # Student dashboard
│   ├── dashboard.php
│   ├── events.php
│   └── my-events.php
├── faculty/                    # Faculty dashboard
│   └── dashboard.php
├── club/                       # Club leader dashboard
│   └── dashboard.php
├── api/                        # API endpoints
│   ├── register-event.php
│   ├── approve-event.php
│   └── ...
├── app/
│   ├── helpers/               # Helper classes
│   │   ├── Security.php
│   │   └── Validator.php
│   └── middleware/            # Middleware
│       └── Auth.php
├── config/                    # Configuration files
│   ├── config.php
│   └── database.php
├── database/                  # Database files
│   └── schema.sql
├── public/
│   ├── css/                   # Stylesheets
│   │   ├── auth.css
│   │   ├── dashboard.css
│   │   └── admin.css
│   ├── js/                    # JavaScript files
│   │   ├── auth.js
│   │   ├── dashboard.js
│   │   └── admin.js
│   └── uploads/               # File uploads
├── index.php                  # Entry point
├── login.php                  # Login page
├── register.php               # Registration page
├── logout.php                 # Logout handler
└── README.md                  # This file
```

---

## 🎯 Usage Guide

### For Students

1. **Register**
   - Visit registration page
   - Fill in personal details
   - Use valid college email
   - Create strong password

2. **Discover Events**
   - Browse available events
   - Filter by club/department
   - View event details

3. **Register for Events**
   - Click "Register Now"
   - Instant confirmation
   - Track in "My Events"

4. **Track Attendance**
   - View registered events
   - Check attendance status
   - Download certificates (when available)

### For Faculty/HOD

1. **Create Events**
   - Fill event details
   - Set date and venue
   - Upload banner
   - Submit for approval

2. **Manage Registrations**
   - View registered students
   - Export attendee lists
   - Track attendance

3. **Analytics**
   - View registration trends
   - Department-wise statistics
   - Export reports

### For Club Leaders

1. **Club Management**
   - Create club events
   - Customize club theme
   - Upload club logo

2. **Theme Customization**
   - Set primary color
   - Set accent color
   - Upload background
   - Choose font

3. **Analytics**
   - View club event stats
   - Track member engagement
   - Export data

### For Admins

1. **User Management**
   - View all users
   - Assign/revoke roles
   - Activate/deactivate accounts
   - Delete users

2. **Event Approval**
   - Review pending events
   - Approve/reject events
   - Provide rejection reasons

3. **System Monitoring**
   - View system statistics
   - Check audit logs
   - Monitor activity
   - Generate reports

---

## 🛠️ Configuration Options

### Email Settings (Future)
```php
// config/config.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'hitanshpparikh@gmail.com');
define('SMTP_PASS', 'your-password');
```

### Security Settings
```php
// config/config.php
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);
```

### Application Settings
```php
// config/config.php
define('APP_NAME', 'Evento - College Event Management');
define('ITEMS_PER_PAGE', 12);
```

---

## 🐛 Troubleshooting

### Common Issues

**1. Database Connection Error**
- Verify MySQL is running in XAMPP
- Check database credentials in `config/database.php`
- Ensure database `evento` exists

**2. Page Not Found (404)**
- Check BASE_URL in `config/config.php`
- Ensure mod_rewrite is enabled in Apache
- Verify file paths are correct

**3. Upload Directory Error**
- Create `public/uploads` directory
- Set proper permissions (755)
- Check PHP upload settings in php.ini

**4. Session Errors**
- Ensure session directory exists
- Check session permissions
- Verify session_start() is called

**5. CSS Not Loading**
- Clear browser cache
- Check file paths in HTML
- Verify files exist in public/css

---

## 🔐 Security Best Practices

### Production Deployment

1. **Change Default Admin Password**
   ```sql
   UPDATE users SET password_hash = '$2y$10$YOUR_NEW_HASH' WHERE email = 'admin@college.edu';
   ```

2. **Update Database Credentials**
   - Use strong database password
   - Create dedicated database user
   - Limit user privileges

3. **Enable HTTPS**
   - Get SSL certificate
   - Force HTTPS redirect
   - Update secure cookie settings

4. **Disable Error Display**
   ```php
   // config/config.php
   ini_set('display_errors', 0);
   error_reporting(0);
   ```

5. **Set Strong Session Settings**
   ```php
   ini_set('session.cookie_secure', 1);
   ini_set('session.cookie_httponly', 1);
   ```

6. **Regular Backups**
   - Database backups daily
   - File backups weekly
   - Test restore procedures

7. **Update Dependencies**
   - Keep PHP updated
   - Update MySQL regularly
   - Monitor security advisories

---

## 📚 API Documentation

### Event Registration
```javascript
POST /api/register-event.php
Content-Type: application/json

{
  "event_id": 123
}
```

### Event Approval
```javascript
POST /api/approve-event.php
Content-Type: application/json

{
  "event_id": 123,
  "action": "approve", // or "reject"
  "reason": "Optional rejection reason"
}
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

---

## 📝 License

This project is licensed under the MIT License - see below:

```
MIT License

Copyright (c) 2025 Evento

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 🙏 Acknowledgments

- PHP for robust backend
- MySQL for reliable database
- Inter Font for beautiful typography
- Icons from Heroicons
- Glassmorphism design inspiration

---

## 📞 Support

For issues, questions, or suggestions:

- Create an issue on GitHub
- Email: support@evento.com
- Documentation: Check this README

---

## 🗺️ Roadmap

### Version 1.1 (Planned)
- [ ] Email notifications
- [ ] QR code attendance
- [ ] Certificate generation
- [ ] Google Calendar integration
- [ ] Advanced analytics

### Version 1.2 (Planned)
- [ ] Mobile app (PWA)
- [ ] Payment integration
- [ ] Event feedback system
- [ ] Social media sharing
- [ ] Multi-language support

### Version 2.0 (Future)
- [ ] AI-powered recommendations
- [ ] Video conferencing integration
- [ ] Advanced reporting
- [ ] API for third-party integration
- [ ] White-label solution

---

<div align="center">

**Made with ❤️ for Colleges**

Star ⭐ this repository if you find it helpful!

</div>
