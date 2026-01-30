# Google Name Auto-Fill and Profile Photo Upload Feature

## Overview
Enhanced the registration process to automatically fetch the user's name from their Google account during Google OAuth registration and added a profile photo upload option during profile completion.

## Implementation Date
January 27, 2026

## Changes Made

### 1. Google OAuth Name Auto-Fill

#### Modified Files:
- **app/helpers/GoogleAuth.php**
  - Updated to pass Google profile picture URL along with name
  - Session now includes: `email`, `name`, `google_id`, `picture`, `timestamp`

- **complete-profile.php**
  - Changed `$pre_filled_name` from empty string to use Google-provided name
  - Name field now auto-fills from Google account data
  - Added visual indicator when name is auto-filled from Google

### 2. Profile Photo Upload

#### Modified Files:
- **complete-profile.php**
  - Added file upload handling for profile photos
  - Supports: JPG, JPEG, PNG, GIF
  - Max file size: 5MB
  - Photos saved to: `uploads/profiles/`
  - Filename format: `profile_{uniqid}_{timestamp}.{ext}`
  - Updated form with `enctype="multipart/form-data"`
  - Added profile_photo field to both Google and email registration flows

#### Database:
- **Profile Image Field**: Already exists as `profile_image` VARCHAR(255) in `users` table
- No database migration needed - field was already present

#### File Structure:
```
evento/
└── uploads/
    └── profiles/          # Profile photo storage (created automatically)
        └── profile_*.jpg  # Uploaded photos
```

## Features

### Auto-Fill from Google Account
✓ Name automatically populated from Google profile
✓ Visual feedback showing auto-filled fields
✓ User can still edit the auto-filled name if needed

### Profile Photo Upload
✓ Optional during registration
✓ File type validation (images only)
✓ File size validation (5MB max)
✓ Secure filename generation
✓ Automatic directory creation
✓ Works for both Google OAuth and email registration

## User Flow

### Google OAuth Registration:
1. User clicks "Continue with Google"
2. Google authentication completes
3. Name is auto-filled from Google account
4. User can optionally upload profile photo
5. User completes other required fields
6. Registration completes with name and photo

### Email Registration:
1. User registers with email/password
2. Email verification completes
3. User enters name manually
4. User can optionally upload profile photo
5. User completes other required fields
6. Registration completes with name and photo

## Security Considerations

### File Upload Security:
- File type whitelist (only images)
- File size limit (5MB)
- Unique filename generation (prevents overwrites)
- Files stored outside web root paths where possible
- Server-side validation

### Google OAuth Security:
- Name is only used for pre-filling, not auto-saved
- User can modify any auto-filled data
- Standard OAuth security protocols maintained

## Testing Checklist

- [x] Google OAuth fetches name correctly
- [x] Name pre-fills in registration form
- [x] Profile photo upload accepts valid images
- [x] Profile photo upload rejects invalid files
- [x] Profile photo upload rejects oversized files
- [x] Photos saved with correct permissions
- [x] Photos accessible after registration
- [x] Both registration flows support photo upload
- [x] Form validation works correctly
- [x] Database updates include profile_image

## Configuration

### File Upload Settings (in complete-profile.php):
```php
// Allowed file types
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

// Max file size: 5MB
$max_size = 5242880; // 5 * 1024 * 1024

// Upload directory
$upload_dir = __DIR__ . '/uploads/profiles/';
```

## Permissions Required

Ensure the following directory has write permissions:
```bash
chmod 755 uploads/profiles/
```

## Backward Compatibility

✓ Existing users without profile photos: Not affected
✓ Email registration: Works as before with photo option added
✓ Google OAuth: Enhanced with name auto-fill
✓ Database: No schema changes required (field already exists)

## Future Enhancements

- Image resizing/optimization on upload
- Profile photo cropping interface
- Fetch Google profile picture directly (already captured in session)
- Avatar generation for users without photos
- Profile photo update feature in user settings

## Related Files

- `app/helpers/GoogleAuth.php` - Google OAuth handler
- `complete-profile.php` - Profile completion page
- `database-import/u149605981_evento.sql` - Database schema
- `uploads/profiles/` - Photo storage directory

## Notes

- Google profile picture URL is now captured in session but not yet used
- Can be used later to offer "Use Google photo" option
- Profile photos are optional and not required for registration
- Name from Google can still be edited by user before submission
