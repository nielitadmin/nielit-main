# Git Push to GitHub - Quick Guide

## Step 1: Check Git Status

First, check what files have changed:

```bash
git status
```

## Step 2: Add Files to Staging

Add all changed files:

```bash
git add .
```

Or add specific files:

```bash
git add student/register.php student/submit_registration.php
```

## Step 3: Commit Changes

Commit with a descriptive message:

```bash
git commit -m "Fix: Form submission bugs - bind_param, file uploads, validation"
```

Or a more detailed commit message:

```bash
git commit -m "Fix critical form submission bugs

- Fixed invalid bind_param type string (removed spaces)
- Fixed file upload paths to use absolute paths
- Fixed error redirects to include APP_URL and course parameter
- Added server-side validation for passport photo and signature
- Moved submit_registration.php and registration_success.php to student folder
- Updated .gitignore to exclude sensitive files and folders"
```

## Step 4: Push to GitHub

If this is your first push or you haven't set up a remote:

```bash
# Add remote repository (replace with your GitHub repo URL)
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git

# Push to main branch
git push -u origin main
```

If you already have a remote set up:

```bash
# Push to main branch
git push origin main
```

Or if your default branch is master:

```bash
git push origin master
```

## Step 5: Verify

Check on GitHub that your files were pushed successfully.

---

## Common Issues & Solutions

### Issue 1: "fatal: not a git repository"

**Solution**: Initialize git first:
```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
git push -u origin main
```

### Issue 2: "Updates were rejected"

**Solution**: Pull first, then push:
```bash
git pull origin main --rebase
git push origin main
```

### Issue 3: Authentication failed

**Solution**: Use a Personal Access Token instead of password:
1. Go to GitHub → Settings → Developer settings → Personal access tokens
2. Generate new token with "repo" permissions
3. Use token as password when prompted

Or use SSH:
```bash
git remote set-url origin git@github.com:YOUR_USERNAME/YOUR_REPO_NAME.git
```

---

## Quick Commands Summary

```bash
# Check status
git status

# Add all files
git add .

# Commit
git commit -m "Your commit message"

# Push
git push origin main

# View remote URL
git remote -v

# Change remote URL
git remote set-url origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
```

---

## What Files Will Be Pushed?

Based on your `.gitignore`, these will be EXCLUDED:
- `/uploads/` folder
- `/config/config.php` (database credentials)
- `/hrms/`, `/startup/`, `/nielit_jobfair/`, `/__test/` folders
- IDE files (.vscode, .idea)
- OS files (.DS_Store)
- QR codes and course PDFs

Everything else will be pushed to GitHub.

---

## Recommended Commit Message

```bash
git commit -m "Fix: Critical form submission bugs

- Fixed bind_param type string (removed spaces causing SQL failure)
- Added absolute paths for file uploads
- Fixed error redirects with APP_URL and course context
- Added server-side validation for passport photo and signature
- Reorganized registration files into student folder
- All 4 mandatory documents now validated on client and server"
```
