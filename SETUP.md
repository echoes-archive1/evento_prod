# 🚀 Quick Setup Guide - Evento

## Step-by-Step Installation (5 Minutes)

### 1️⃣ Start XAMPP
- Open XAMPP Control Panel
- Click "Start" for **Apache**
- Click "Start" for **MySQL**
- Wait for green indicators

### 2️⃣ Create Database
1. Open browser: `https://hitanshparikh.tech/evento/phpmyadmin`
2. Click **"New"** on left sidebar
3. Database name: `evento`
4. Collation: `utf8mb4_unicode_ci`
5. Click **"Create"**

### 3️⃣ Import Schema
1. Click on `evento` database
2. Click **"Import"** tab at top
3. Click **"Choose File"**
4. Navigate to: `C:\xampp\htdocs\evento\database\schema.sql`
5. Click **"Go"** at bottom
6. Wait for ✅ success message

### 4️⃣ Create Upload Directories
Open Command Prompt in `C:\xampp\htdocs\evento` and run:
```bash
mkdir public\uploads
mkdir public\uploads\events
mkdir public\uploads\clubs
mkdir public\uploads\profiles
```

Or create manually via File Explorer.

### 5️⃣ Access the System
Open browser and visit:
- **Login**: https://hitanshparikh.tech/evento/login.php
- **Register**: https://hitanshparikh.tech/evento/register.php

---

## 🔑 Default Admin Login
```
Email: admin@college.edu
Password: Admin@123
```

**⚠️ IMPORTANT**: Change this password after first login!

---

## ✅ Quick Test Checklist

- [ ] Can access login page
- [ ] Can login with admin credentials
- [ ] Admin dashboard loads correctly
- [ ] Can register new student account
- [ ] Student dashboard shows events
- [ ] Can create test event (as faculty/admin)
- [ ] Can approve event (as admin)
- [ ] Student can register for event

---

## 🐛 Common Issues & Solutions

### Issue: "Database connection failed"
**Solution**: 
- Check if MySQL is running in XAMPP
- Verify database name is `evento`
- Check credentials in `config/database.php`

### Issue: "Page not found" or blank page
**Solution**:
- Clear browser cache
- Check BASE_URL in `config/config.php`
- Ensure Apache is running

### Issue: "Permission denied" for uploads
**Solution**:
- Create `public/uploads` directory
- Right-click folder → Properties → Security
- Give full control to IUSR and IIS_IUSRS

### Issue: CSS not loading
**Solution**:
- Hard refresh: `Ctrl + Shift + R`
- Check if files exist in `public/css/`
- Verify BASE_URL is correct

---

## 📞 Need Help?

1. Check the full README.md
2. Review error logs in XAMPP
3. Check PHP error log
4. Verify all requirements are met

---

## 🎯 Next Steps After Installation

### As Admin:
1. Change default password
2. Create clubs
3. Add faculty members
4. Assign club leaders
5. Review system settings

### As Faculty:
1. Create test event
2. Upload event banner
3. Set registration deadline
4. Wait for admin approval

### As Student:
1. Complete profile
2. Browse events
3. Register for events
4. Check "My Events"

---

## 🔒 Security Checklist for Production

- [ ] Change admin password
- [ ] Update database password
- [ ] Enable HTTPS
- [ ] Disable error display
- [ ] Set secure cookie flags
- [ ] Implement rate limiting
- [ ] Regular backups
- [ ] Update BASE_URL
- [ ] Review file permissions
- [ ] Enable audit logging

---

**🎉 Enjoy using Evento!**
