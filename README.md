# CMSC129-Lab2-MagadanJ_MelchorEJ
# Daily Draft - Personal Journal Application

## Overview
Daily Draft is a web-based personal journaling application developed as a Laboratory 2 project. Built on the Laravel MVC framework, the application provides a structured platform for users to document, categorize, and manage their daily journal entries. 

## Core Features
- **Complete CRUD Operations:** Create, read, update, and delete journal entries.
- **Soft Deletion:** Safely remove entries by moving them to a Trash Bin, with options to either restore them or permanently delete them from the database.
- **Categorization:** Assign entries to specific categories (e.g., Work, School, Personal), which dynamically apply distinct visual themes to the UI.
- **Media Uploads:** Attach and manage image files within individual journal entries.
- **Search and Filtering:** Filter entries by predefined moods, category types, and favorite status, alongside a text-based search for entry titles and content.
- **Form Validation & State Management:** Secure form handling with validation, dynamic unlock/lock logic for update forms, and session-based success notifications.

## Technologies Used
- **Backend Framework:** Laravel (PHP)
- **Database:** PostgreSQL
- **Frontend:** Blade Templating Engine, Tailwind CSS (via CDN)
- **Icons:** Phosphor Icons

## Prerequisites
Ensure your local development environment has the following installed:
- PHP (v8.1 or higher)
- Composer
- PostgreSQL

## Installation and Setup

1. **Clone the repository:**
```bash
git clone [your-repository-url]
cd [your-repository-name]
```

2. **Install PHP dependencies:**
```bash
composer install
```

3. **Configure environment variables:**
    Copy the example environment file and update your database credentials.
```bash
cp .env.example .env
Open the .env file and configure your PostgreSQL connection:
```
```bash 
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```
4. **Generate the application key:**
```bash
php artisan key:generate
```

5.  **Link the storage directory:**
    This is required to properly display uploaded entry images.
```bash
php artisan storage:link
```

6. **Run database migrations and seeders:**
    This will build the database tables and populate them with the initial categories and sample journal entries.
```bash
php artisan migrate:fresh --seed
```
7. **Start the local development server:**

```bash
php artisan serve
```

The application will be accessible at http://localhost:8000.

## Directory Structure Notes
- Controllers: **app/Http/Controllers/EntryController.php**

- Models: **app/Models/Entry.php, app/Models/Category.php**

- Views: **resources/views/entries/ and resources/views/layouts/**

- Database **Migrations: database/migrations/**

- Database Seeders: **database/seeders/DatabaseSeeder.php**

## Sample Project Structure:
```bash
your-lab2-project/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ChatBotController.php       # Chatbot logic
│   │   │   ├── AIAssistantController.php   # AI CRUD operations
│   │   │   └── YourExistingController.php
│   ├── Services/
│   │   ├── AIService.php                   # AI API integration
│   │   ├── PromptService.php               # Prompt engineering
│   │   └── FunctionCallService.php         # Function calling
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── chat/
│   │   │   ├── index.blade.php             # Chat interface
│   │   │   └── components/
│   │   │       └── chat-widget.blade.php   # Chat widget
│   ├── js/
│   │   └── chatbot.js                      # Frontend chat logic
├── routes/
│   ├── web.php                             # Add chat routes
│   └── api.php                             # Optional API routes
├── database/
│   └── seeders/
│       └── DummyDataSeeder.php             # Seed dummy data
├── .env                                    # Add AI API keys
└── README.md                               # Updated docs
```

# Members:
- **Jasmine Magadan**
- **Eleah Joy Melchor**


