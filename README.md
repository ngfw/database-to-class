# Database-to-class
Generate PHP Class files according to your Database structure with modern PHP 8.1+ syntax

## Requirements

- PHP 8.1 or higher
- MySQL/MariaDB database
- PDO extension

## Installation

### Via Composer (Recommended)

```sh
composer require ngfw/database-to-class
```

### Manual Installation

1. Clone or download this repository
2. Run `composer install` to generate autoloader
3. Include the autoloader: `require 'vendor/autoload.php';`

## Getting Started

1. Edit `dbconfig.php` to configure your database connection
2. Make sure `GeneratedClasses` directory is writable
```sh
chmod 777 GeneratedClasses
```

## Usage

### With Composer Autoloading (Recommended)

```php
<?php
require 'vendor/autoload.php';

use DatabaseToClass\ClassGenerator;

$generator = new ClassGenerator();
$generator->setTable('users');
$classCode = $generator->buildClass();
file_put_contents('GeneratedClasses/users.php', $classCode);
```

### Legacy Method (Backward Compatible)

The `Classes/` directory still works for backward compatibility:

```php
<?php
include('Classes/ClassGenerator.php');

$generator = new ClassGenerator();
// ... rest of your code
```

## Generate classes via CLI

The new CLI uses Symfony Console for a modern experience with colors, progress bars, and interactive prompts.

### Commands

**Interactive mode** (select table from list):
```sh
php generate.php
```

**List all tables**:
```sh
php generate.php list-tables
```

**Generate specific table**:
```sh
php generate.php generate users
```

**Generate all tables**:
```sh
php generate.php generate --all
```

**Custom output directory**:
```sh
php generate.php generate users --output=app/Models
```

### Features
- 🎨 Colored output for better readability
- 📊 Progress bars for bulk generation
- 🔍 Interactive table selection
- ✅ Validation of table primary keys
- 📋 Relationship detection display

![cli.php view](terminal.gif)

## Generate classes via web interface
if you webserver is not already pointing to Directory where files are located, you can simply run:
```sh
\>$ php -S localhost:8080
```

then open you browser and navigate to `http://localhost:8080`

**Web interface will also generate class usage documentation**

## Features

### Modern CLI with Symfony Console
Professional command-line interface with rich features:
- **Colored output**: Easy-to-read success/error messages
- **Interactive prompts**: Select tables from a list
- **Progress bars**: Visual feedback for bulk operations
- **Table listing**: View all tables with status indicators
- **Flexible options**: Generate single table, all tables, or custom output directory
- **Helpful commands**: Built-in help and command descriptions

### Modern PHP 8.1+ Syntax
Generated classes use cutting-edge PHP features:
- Typed properties for better IDE support and type safety
- Union types (e.g., `int|string`, `bool|int`)
- Strict types declaration for compile-time type checking
- Modern array syntax and null coalescing operators
- Return type declarations on all methods

### Query Builder - Fluent Interface
Build complex queries with an elegant, chainable syntax:

**Available Methods:**
- `where($column, $operator, $value)` - Add WHERE clause
- `orWhere($column, $operator, $value)` - Add OR WHERE clause
- `whereIn($column, $values)` - Add WHERE IN clause
- `orderBy($column, $direction)` - Add ORDER BY clause
- `take($limit)` - Limit number of results
- `skip($offset)` - Skip number of records
- `get()` - Execute query and get all results
- `first()` - Get first result only
- `count()` - Count matching records

**Examples:**

```php
<?php
$user = include("GeneratedClasses/users.php");

// Simple WHERE query
$activeUsers = $user->where('status', 'active')->get();

// Multiple WHERE conditions
$results = $user->where('age', '>', 18)
                ->where('status', 'active')
                ->get();

// OR conditions
$results = $user->where('role', 'admin')
                ->orWhere('role', 'moderator')
                ->get();

// WHERE IN
$results = $user->whereIn('id', [1, 2, 3, 5, 8])->get();

// ORDER BY and LIMIT
$topUsers = $user->where('status', 'active')
                 ->orderBy('points', 'DESC')
                 ->take(10)
                 ->get();

// Pagination
$page2 = $user->orderBy('created_at', 'DESC')
              ->skip(20)
              ->take(10)
              ->get();

// Get first result
$admin = $user->where('role', 'admin')->first();

// Count records
$activeCount = $user->where('status', 'active')->count();

// Complex queries
$results = $user->where('age', '>=', 21)
                ->where('status', 'active')
                ->whereIn('country', ['US', 'CA', 'UK'])
                ->orderBy('last_login', 'DESC')
                ->take(50)
                ->get();
```

**Supported Operators:**
- `=`, `!=`, `<>` - Equality
- `>`, `>=`, `<`, `<=` - Comparison
- `LIKE` - Pattern matching
- `IN` - List membership (via `whereIn()`)

### Lifecycle Hooks / Events
Generated classes include lifecycle hooks that allow you to run custom logic before and after CRUD operations:

**Available Hooks:**
- `beforeValidate()` - Run logic before validation
- `afterValidate()` - Run logic after successful validation
- `beforeAdd()` - Modify data or cancel insert (return `false` to cancel)
- `afterAdd($insertedId)` - Run logic after successful insert
- `beforeUpdate()` - Modify data or cancel update (return `false` to cancel)
- `afterUpdate()` - Run logic after successful update
- `beforeDelete($id)` - Cancel delete operation (return `false` to cancel)
- `afterDelete($id)` - Run logic after successful delete

**Example - Auto-timestamp:**
```php
<?php
// Extend the generated class
class User extends users {
    protected function beforeAdd(): bool {
        // Auto-set created_at timestamp
        $this->created_at = date('Y-m-d H:i:s');
        return true;
    }

    protected function beforeUpdate(): bool {
        // Auto-set updated_at timestamp
        $this->updated_at = date('Y-m-d H:i:s');
        return true;
    }

    protected function afterDelete(int|string $id): void {
        // Log deletion
        error_log("User {$id} was deleted at " . date('Y-m-d H:i:s'));
    }
}
```

**Example - Prevent deletion:**
```php
protected function beforeDelete(int|string $id): bool {
    // Don't allow deleting admin users
    if ($this->role === 'admin') {
        return false; // Cancel the delete
    }
    return true;
}
```

### Automatic Validation
Generated classes include built-in validation based on your database schema:
- **Type validation**: Ensures integers are integers, numbers are numeric, etc.
- **Length validation**: Enforces VARCHAR and CHAR length constraints
- **Required field validation**: Checks NOT NULL columns
- **ENUM validation**: Validates against allowed enum values
- **Easy error handling**: Get detailed validation errors

```php
$user = include("GeneratedClasses/users.php");
$user->email = "invalid-email-that-is-way-too-long-for-the-database-column";
$user->age = "not a number";

if (!$user->validate()) {
    print_r($user->getValidationErrors());
    // Array
    // (
    //     [email] => Array([0] => email exceeds maximum length of 255)
    //     [age] => Array([0] => age must be an integer)
    // )
} else {
    $user->add();
}
```

### Automatic Relationship Detection
The generator automatically detects foreign key relationships and generates methods for easy data access:

- **BelongsTo Relationships**: When your table has a foreign key to another table
- **HasMany Relationships**: When other tables have foreign keys pointing to your table
- **BelongsToMany Relationships**: Many-to-many relationships through junction/pivot tables

#### BelongsTo and HasMany Example

If you have a `posts` table with a `user_id` foreign key to `users` table:

```php
// BelongsTo: Get the user who created a post
$post = include("GeneratedClasses/posts.php");
$postData = $post->get_id(1);
foreach($postData[0] as $key => $value) {
    $post->{$key} = $value;
}
$author = $post->user(); // Returns the related user record

// HasMany: Get all posts by a user
$user = include("GeneratedClasses/users.php");
$userData = $user->get_id(1);
foreach($userData[0] as $key => $value) {
    $user->{$key} = $value;
}
$posts = $user->posts(10); // Returns up to 10 posts by this user
```

#### BelongsToMany (Many-to-Many) Example

The generator automatically detects junction tables and creates many-to-many relationships. For example, with `users`, `roles`, and a `user_roles` junction table:

**Database Structure:**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY,
    name VARCHAR(255)
);

CREATE TABLE roles (
    id INT PRIMARY KEY,
    name VARCHAR(255)
);

CREATE TABLE user_roles (
    user_id INT,
    role_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
```

**Usage:**

```php
<?php
$user = include("GeneratedClasses/users.php");
$role = include("GeneratedClasses/roles.php");

// Load a user
$userData = $user->get_id(1);
foreach($userData[0] as $key => $value) {
    $user->{$key} = $value;
}

// Get all roles for this user
$userRoles = $user->roles(); // Returns array of role records

// Attach a role to the user
$user->attachRoles(2); // Attach role with ID 2

// Detach a role from the user
$user->detachRoles(2); // Remove role with ID 2

// Sync roles (removes all existing, adds new ones)
$user->syncRoles([1, 3, 5]); // User will have only roles 1, 3, and 5

// Works both ways - get users for a role
$roleData = $role->get_id(1);
foreach($roleData[0] as $key => $value) {
    $role->{$key} = $value;
}
$roleUsers = $role->users(); // Returns array of user records with this role
```

**Generated Methods:**
- `roles()` or `users()` - Retrieve related records through junction table
- `attachRoles($id)` - Add a relationship (prevents duplicates)
- `detachRoles($id)` - Remove a relationship
- `syncRoles($ids)` - Replace all relationships with new set

The generated documentation will show all detected relationships and how to use them.

----------
Have fun
