# Database-to-class
Generate PHP Class files according to your Database structure

1. Edit `dbconfig.php` to get started.
2. Make sure `GeneratedClasses` directory is writable
```sh
\>$ chmod 777 GeneratedClasses
```

## Generate classes via cli

![cli.php view](terminal.gif)

## Generate classes via web interface
if you webserver is not already pointing to Directory where files are located, you can simply run:
```sh
\>$ php -S localhost:8080
```

then open you browser and navigate to `http://localhost:8080`

**Web interface will also generate class usage documentation**

## Features

### Automatic Relationship Detection
The generator automatically detects foreign key relationships and generates methods for easy data access:

- **BelongsTo Relationships**: When your table has a foreign key to another table
- **HasMany Relationships**: When other tables have foreign keys pointing to your table

#### Example Usage

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

The generated documentation will show all detected relationships and how to use them.

----------
Have fun
