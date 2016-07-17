# Database-to-class
Generate PHP Class files according to your Database structure

1. Edit `dbconfig.php` to get started.
2. Make sure `GeneratedClasses` directory is writable 
```sh
\>$ chmod 777 GeneratedClasses
```

## Generate classes vie cli

![cli.php view](terminal.gif)

## Generate classes vie web interface
if you webserver is not already pointing to Directory where files are located, you can simply run:
```sh
\>$ php -S localhost:8080
```

then open you browser and navigate to `http://localhost:8080`

**Web interface will also generate, class usage documentation**


----------
Have fun
