# Multi-Platform Content Distribution System

A comprehensive web application that enables posting content (text, images, videos, datasets) to multiple social media and data platforms through a unified interface. Supports both manual and scheduled posting.

## Features

- **Multi-Platform Posting**: Post to Facebook, LinkedIn, YouTube, TikTok, and Kaggle simultaneously
- **Content Types**: Support for text posts, images, videos, and datasets
- **Scheduled Posting**: Schedule posts for future dates/times using Laravel Queues
- **Platform Management**: Connect and manage multiple platform accounts
- **Post History**: Track all posted content with status per platform
- **User Authentication**: Secure authentication using Laravel Sanctum

## Technology Stack

- **Laravel** - PHP web framework with API endpoints
- **Vue.js** - Progressive JavaScript framework for frontend
- **MySQL** - Database for storing application data
- **Redis** - Cache and queue driver for scheduled posts
- **Laravel Sanctum** - API authentication
- **Laravel Queues** - Background job processing for scheduled posts

## Project Structure

```
content-distribution-system/
├── app/
│   ├── Http/
│   │   ├── Controllers/  # API controllers
│   │   └── Middleware/   # Middleware
│   ├── Models/           # Eloquent models
│   ├── Jobs/             # Queue jobs for scheduled posts
│   ├── Services/         # Platform integration services
│   └── Providers/        # Service providers
├── resources/
│   ├── js/
│   │   ├── components/   # Vue components
│   │   ├── pages/        # Page components
│   │   ├── router/       # Vue Router configuration
│   │   └── app.js        # Vue app entry point
│   └── views/
│       └── app.blade.php # Main Blade template
├── routes/
│   ├── api.php           # API routes
│   └── web.php           # Web routes
├── database/
│   ├── migrations/       # Database migrations
│   └── seeders/          # Database seeders
├── storage/
│   └── app/
│       └── uploads/      # Media storage
├── .ddev/                # DDEV configuration
├── composer.json         # PHP dependencies
└── package.json          # Node dependencies
```

## Installation

### Prerequisites

- Docker Desktop (Windows/Mac) or Docker Engine (Linux)
- DDEV - [Install DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)

### Quick Start (Single Command Installation)

1. Navigate to project root:
```bash
cd content-distribution-system
```

2. Start DDEV (this will automatically set up the environment):
```bash
ddev start
```

3. Install all dependencies and set up the project:
```bash
ddev composer install && ddev npm install && ddev artisan key:generate && ddev artisan migrate
```

That's it! The application is now ready to use.

### Manual Setup Steps (if needed)

If you prefer to run commands individually:

1. **Start DDEV:**
```bash
ddev start
```

2. **Install PHP dependencies:**
```bash
ddev composer install
```

3. **Install Node dependencies:**
```bash
ddev npm install
```

4. **Create environment file:**
```bash
ddev exec cp .env.example .env
```

5. **Generate application key:**
```bash
ddev artisan key:generate
```

6. **Run database migrations:**
```bash
ddev artisan migrate
```

7. **Build frontend assets (for development):**
```bash
ddev npm run dev
```

Or for production:
```bash
ddev npm run build
```

8. **Start queue worker (for scheduled posts):**
```bash
ddev artisan queue:work
```

### Access the Application

After installation, access the application at:
- **Application**: https://content-distribution-system.ddev.site
- **MailHog** (for testing emails): https://content-distribution-system.ddev.site:8025
- **phpMyAdmin** (database management): https://content-distribution-system.ddev.site:8036

**Note:** DDEV automatically configures:
- PHP 8.2+ with all required extensions
- MySQL database
- Redis for caching and queues
- Node.js and npm for frontend assets
- All necessary services in Docker containers

## Running the Application

### Development Mode

1. **Start DDEV:**
```bash
ddev start
```

2. **Build frontend assets (watch mode):**
```bash
ddev npm run dev
```

3. **Start queue worker (for scheduled posts):**
```bash
ddev artisan queue:work
```

4. **Access the application:**
   - Application: https://content-distribution-system.ddev.site
   - API endpoints: https://content-distribution-system.ddev.site/api

### Production Build

1. **Build frontend assets:**
```bash
ddev npm run build
```

2. **Clear and cache configuration:**
```bash
ddev artisan config:cache
ddev artisan route:cache
ddev artisan view:cache
```

### Common DDEV Commands

```bash
# Start DDEV
ddev start

# Stop DDEV
ddev stop

# View logs
ddev logs

# Execute commands in container
ddev exec <command>

# Run Laravel commands
ddev artisan <command>

# Run Composer commands
ddev composer <command>

# Run npm commands
ddev npm <command>
```

## Platform Setup

### Facebook

**Option 1: OAuth Login (Recommended - Requires App ID/Secret)**

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create an app and get App ID and App Secret
3. Add to your `.env` file:
   ```
   FACEBOOK_APP_ID=your-app-id
   FACEBOOK_APP_SECRET=your-app-secret
   FACEBOOK_REDIRECT_URI=https://content-distribution-system.ddev.site/api/platforms/facebook/oauth/callback
   ```
4. **Configure Facebook App Settings** (IMPORTANT):
   - Go to **Settings** → **Basic**
   - Add to **App Domains**: `content-distribution-system.ddev.site`
   - Add **Website** platform with **Site URL**: `https://content-distribution-system.ddev.site`
   - Go to **Settings** → **Advanced**
   - Add to **Valid OAuth Redirect URIs**: `https://content-distribution-system.ddev.site/api/platforms/facebook/oauth/callback`
   - Click **Save Changes**
5. In Settings page, click "Login with Facebook" button
6. Grant permissions and your Page will be automatically connected

**Option 2: Manual Credentials**

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create an app (optional, only needed if you want to use App Secret)
3. Generate a Page Access Token with `pages_manage_posts` permission:
   - Go to [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
   - Select your app
   - Generate User Access Token with permissions: `pages_manage_posts`, `pages_show_list`
   - Use the token to get your Page Access Token: `GET /me/accounts`
   - Copy the Page Access Token from the response
4. Get your Page ID:
   - Go to your Facebook Page
   - Click "About" in the left sidebar
   - Find "Page ID" and copy it
5. In Settings page, enter:
   - `page_id`: Your Facebook Page ID
   - `access_token`: Page Access Token
   - `app_secret`: App Secret (optional)

### LinkedIn

1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Create an app and get Client ID and Client Secret
3. Complete OAuth flow to get access token
4. Get your Person URN (format: `urn:li:person:xxxxx`)
5. In Settings page, enter:
   - `access_token`: OAuth access token
   - `person_urn`: Your LinkedIn Person URN

### YouTube

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project and enable YouTube Data API v3
3. Create OAuth 2.0 credentials (or use Service Account)
4. Download credentials JSON file
5. In Settings page, paste the entire JSON content in `credentials` field

### TikTok

1. Go to [TikTok for Developers](https://developers.tiktok.com/)
2. Apply for Marketing API access (requires business verification)
3. Get access token and advertiser ID
4. In Settings page, enter:
   - `access_token`: Marketing API access token
   - `advertiser_id`: Your advertiser ID

### Kaggle

1. Go to [Kaggle Account Settings](https://www.kaggle.com/settings)
2. Scroll to API section and click "Create New API Token"
3. This will download a `kaggle.json` file
4. Open the file and extract:
   - `username`: Your Kaggle username
   - `key`: Your Kaggle API token
5. In Settings page, enter:
   - `KAGGLE_USERNAME`: Your Kaggle username
   - `KAGGLE_API_TOKEN`: Your Kaggle API token (the "key" from kaggle.json)

**Note:** For automatic dataset uploads, you can optionally install Kaggle CLI:
```bash
pip install kaggle
```
If Kaggle CLI is not available, the system will provide instructions for manual upload via the Kaggle website.

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login and get token
- `GET /api/auth/me` - Get current user info

### Posts
- `POST /api/posts/` - Create and publish post
- `GET /api/posts/` - List all posts
- `GET /api/posts/{id}` - Get post details

### Schedules
- `POST /api/schedules/` - Create scheduled post
- `GET /api/schedules/` - List scheduled posts
- `DELETE /api/schedules/{id}` - Delete scheduled post

### Platforms
- `POST /api/platforms/{platform}/connect` - Connect platform
- `GET /api/platforms/` - List connected platforms
- `DELETE /api/platforms/{platform}/disconnect` - Disconnect platform

### Media
- `POST /api/media/upload` - Upload media file

## Usage

1. **Register/Login**: Create an account or login
2. **Connect Platforms**: Go to Settings and connect your platform accounts
3. **Create Post**: 
   - Go to Create Post page
   - Enter content, select platforms, optionally add media
   - Choose to post immediately or schedule for later
4. **View History**: Check Dashboard for post history and scheduled posts

## Security Considerations

- API credentials are encrypted in the database
- Use strong `APP_KEY` in production
- Enable HTTPS in production
- Store sensitive environment variables securely
- Regularly rotate API tokens

## Development

### Running Tests

```bash
# Run PHPUnit tests
ddev artisan test

# Run frontend tests (when implemented)
ddev npm test
```

### Building for Production

```bash
# Build frontend assets
ddev npm run build

# Optimize Laravel
ddev artisan config:cache
ddev artisan route:cache
ddev artisan view:cache
```

### Vue.js Development

The Vue.js frontend is integrated into Laravel and uses Laravel's API endpoints. Vue components are located in `resources/js/components/` and pages in `resources/js/pages/`. The main entry point is `resources/js/app.js`.

To develop with hot reload:
```bash
ddev npm run dev
```

## Troubleshooting

### Queue not executing scheduled posts
- Ensure Redis is running (automatically handled by DDEV)
- Check queue worker is running: `ddev artisan queue:work`
- Verify scheduled time is in the future
- Check queue logs: `ddev artisan queue:failed`

### Platform connection fails
- Verify API credentials are correct
- Check token expiration
- Ensure required permissions are granted
- Check Laravel logs: `ddev logs`

### File upload errors
- Check file size limits in `php.ini` (DDEV configurable)
- Verify file type is supported
- Ensure storage directory has write permissions: `ddev exec chmod -R 775 storage`

### DDEV Issues
- Restart DDEV: `ddev restart`
- Rebuild containers: `ddev restart -y`
- Check DDEV status: `ddev describe`

## License

This project is provided as-is for educational and development purposes.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
