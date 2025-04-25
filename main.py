import os
import subprocess
import sys

def app(environ, start_response):
    """
    This is a simple WSGI application that starts a PHP server in the background
    and returns a simple message. The actual work is done by the PHP server.
    """
    # Start the PHP server in a subprocess
    try:
        php_path = "/nix/store/6abnc1cqyn1y6f7nh6v76aa6204mc79z-php-with-extensions-8.2.20/bin/php"
        subprocess.Popen([php_path, "-S", "0.0.0.0:8000"])
        print("PHP server started on port 8000")
    except Exception as e:
        print(f"Error starting PHP server: {e}")
    
    # Return a simple message
    status = '200 OK'
    headers = [('Content-type', 'text/html; charset=utf-8')]
    start_response(status, headers)
    
    return [b"""
    <html>
    <head>
        <title>PHP Server Bridge</title>
        <meta http-equiv="refresh" content="0;url=http://0.0.0.0:8000/index.php">
    </head>
    <body>
        <h1>PHP Server Started</h1>
        <p>Redirecting to PHP application...</p>
    </body>
    </html>
    """]

if __name__ == "__main__":
    # This code will be executed when this script is run directly
    php_path = "/nix/store/6abnc1cqyn1y6f7nh6v76aa6204mc79z-php-with-extensions-8.2.20/bin/php"
    subprocess.run([php_path, "-S", "0.0.0.0:8000"])