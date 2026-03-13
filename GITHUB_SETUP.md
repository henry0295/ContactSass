# 🚀 GitHub Setup & Push Instructions

## Step 1: Create Repository on GitHub

1. Go to https://github.com/new
2. Fill in:
   - **Repository name:** `ContactSass`
   - **Description:** `📧 Multi-tenant Email/SMS/Voice broadcasting platform`
   - **Visibility:** `Public` (or `Private` if preferred)
   - **Initialize:** Don't initialize (we already have git)
3. Click **Create repository**

## Step 2: Add Remote and Push

After creating the repository, GitHub will show you a URL like:
```
https://github.com/yourusername/ContactSass.git
```

Run these commands in your terminal:

```bash
# Navigate to project
cd "c:\Users\PT\OneDrive - VOZIP COLOMBIA\Documentos\GitHub\ContactSass"

# Add remote repository
git remote add origin https://github.com/yourusername/ContactSass.git

# Rename branch to main (if needed)
git branch -M main

# Push code to GitHub
git push -u origin main
```

## Step 3: Configure GitHub Secrets (for CI/CD)

For the GitHub Actions CI/CD pipeline to work, add these secrets:

1. Go to **Settings → Secrets and variables → Actions**
2. Add the following secrets:

### Required Secrets:

```
DEPLOY_HOST         = your-server-ip.com
DEPLOY_USER         = deployment_user
DEPLOY_KEY          = (contents of ~/.ssh/id_rsa from deployment server)
```

### Optional Secrets:

```
DOCKER_USERNAME     = your-docker-username
DOCKER_PASSWORD     = your-docker-password
AWS_ACCESS_KEY_ID   = your-aws-key
AWS_SECRET_ACCESS_KEY = your-aws-secret
```

## Step 4: Configure Branch Protection (Optional but Recommended)

1. Go to **Settings → Branches**
2. Click **Add rule**
3. Branch pattern name: `main`
4. Enable:
   - ✅ Require pull request reviews before merging
   - ✅ Require status checks to pass before merging
   - ✅ Require branches to be up to date before merging

This ensures code is tested before merging to production.

## Step 5: Enable GitHub Pages (for Documentation)

1. Go to **Settings → Pages**
2. Select **Deploy from a branch**
3. Choose `main` branch and `/docs` folder
4. Your docs will be available at: `https://yourusername.github.io/ContactSass/`

## Step 6: Verify Everything Works

```bash
# Check remote is configured
git remote -v

# View commit history
git log --oneline

# Check branches
git branch -a
```

Expected output:
```
origin  https://github.com/yourusername/ContactSass.git (fetch)
origin  https://github.com/yourusername/ContactSass.git (push)
main
remotes/origin/main
```

## Step 7: Create Release Tags (Optional)

After pushing, create a release tag for version 1.0.0:

```bash
# Create tag
git tag -a v1.0.0 -m "Production ready release"

# Push tag
git push origin v1.0.0
```

Then create a GitHub Release:
1. Go to **Releases → Draft a new release**
2. Choose tag `v1.0.0`
3. Add release notes
4. Check **Set as latest release**
5. Publish

## Step 8: Setup Actions Secrets for Deployment

For automatic deployments to work, you need:

### 1. Generate SSH Key (on your local machine)

```bash
ssh-keygen -t rsa -b 4096 -f deploy_key -N ""
```

This creates `deploy_key` (private) and `deploy_key.pub` (public)

### 2. Add Public Key to Server

```bash
# SSH into your server
ssh root@your-server.com

# Add public key to authorized_keys
echo "$(cat deploy_key.pub)" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### 3. Add Private Key to GitHub Secrets

1. Copy contents of `deploy_key` (private key)
2. Go to GitHub → Settings → Secrets → Actions → New repository secret
3. Name: `DEPLOY_KEY`
4. Value: (paste entire private key content)
5. Click **Add secret**

## Troubleshooting

### "remote: error: Repository not found"
- Verify repository name and access
- Use HTTPS or SSH (not mixed)
- Check authentication

### "fatal: The remote-tracking branch main is based on remotes/origin/main"
```bash
# Force push (only on initial setup!)
git push -u origin main --force
```

### Actions not running
1. Check Actions tab for error logs
2. Verify branch protection rules
3. Ensure CI/CD workflow file exists at `.github/workflows/ci-cd.yml`

### Deployment failed
1. Check DEPLOY_KEY secret is correct
2. Verify DEPLOY_HOST and DEPLOY_USER
3. Check server has Docker installed
4. Check Docker daemon is running on server

## Next Steps

After pushing to GitHub:

✅ **Verify CI/CD:**
- Go to **Actions** tab
- Check if tests are running
- Monitor deployment pipeline

✅ **Update README:**
- Replace `yourusername` with your actual username in README.md
- Update any GitHub badge URLs

✅ **Invite Collaborators:**
- Go to Settings → Collaborators
- Add team members

✅ **Create Issues:**
- Use GitHub Issues for bug tracking
- Add labels (bug, feature, documentation, etc.)

✅ **Document:**
- Keep docs/ folder updated
- Link from README.md

## Example Workflow

Here's what happens when you push code:

```
1. git push origin main
         ↓
2. GitHub Actions trigger
         ↓
3. Backend tests run (PHP + PostgreSQL)
         ↓
4. Frontend tests run (Node + Vue)
         ↓
5. Build Docker image
         ↓
6. Deploy to production (if all tests pass)
```

## Git Commands Cheat Sheet

```bash
# Create a feature branch
git checkout -b feature/my-feature

# Make changes and commit
git add .
git commit -m "Add my feature"

# Push feature branch
git push origin feature/my-feature

# Create Pull Request on GitHub (GitHub UI)

# After PR is approved and merged
git checkout main
git pull origin main
```

---

**That's it!** 🎉 Your ContactSass repository is now on GitHub with CI/CD ready!

For questions or issues, check the [documentation](README.md) or create a GitHub Issue.
