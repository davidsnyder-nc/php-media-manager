import os
import mimetypes
import subprocess
import tempfile
import urllib.parse

# Path to PHP executable
PHP_PATH = "/nix/store/6abnc1cqyn1y6f7nh6v76aa6204mc79z-php-with-extensions-8.2.20/bin/php"

def app(environ, start_response):
    # Get the request path and query string
    path_info = environ.get('PATH_INFO', '/')
    query_string = environ.get('QUERY_STRING', '')
    
    # If this is a PHP file or no extension (possibly a route), use router.php
    if path_info.endswith('.php') or '.' not in os.path.basename(path_info) or path_info == '/':
        # Set up environment for PHP
        env = os.environ.copy()
        env['QUERY_STRING'] = query_string
        env['REQUEST_METHOD'] = environ.get('REQUEST_METHOD', 'GET')
        env['SCRIPT_NAME'] = '/router.php'
        env['REQUEST_URI'] = path_info
        
        if query_string:
            env['REQUEST_URI'] += '?' + query_string
            
        # Handle POST data
        if environ.get('REQUEST_METHOD') == 'POST':
            content_length = int(environ.get('CONTENT_LENGTH', 0))
            if content_length > 0:
                post_data = environ['wsgi.input'].read(content_length)
                env['CONTENT_LENGTH'] = str(content_length)
                env['CONTENT_TYPE'] = environ.get('CONTENT_TYPE', '')
            else:
                post_data = b''
        else:
            post_data = None
            
        try:
            # Execute router.php with PHP
            router_path = os.path.join(os.getcwd(), 'router.php')
            
            if post_data:
                # Create a temporary file for POST data
                with tempfile.NamedTemporaryFile(delete=False) as tf:
                    tf.write(post_data)
                    temp_filename = tf.name
                
                process = subprocess.Popen(
                    [PHP_PATH, '-f', router_path],
                    stdin=open(temp_filename, 'rb'),
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    env=env
                )
                os.unlink(temp_filename)
            else:
                process = subprocess.Popen(
                    [PHP_PATH, '-f', router_path],
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    env=env
                )
                
            stdout, stderr = process.communicate()
            
            # Log PHP errors
            if stderr:
                print(f"PHP Error: {stderr.decode()}")
                
            # Extract headers and content
            headers_end = stdout.find(b'\r\n\r\n')
            
            if headers_end != -1:
                headers_section = stdout[:headers_end].decode('utf-8')
                content = stdout[headers_end + 4:]
                
                # Parse headers
                headers_list = []
                status = '200 OK'
                
                for line in headers_section.split('\r\n'):
                    if line.startswith('HTTP/'):
                        status = line.split(' ', 1)[1]
                    elif ':' in line:
                        key, value = line.split(':', 1)
                        headers_list.append((key.strip(), value.strip()))
            else:
                content = stdout
                status = '200 OK'
                headers_list = [('Content-Type', 'text/html')]
            
            # Add content length if not already present
            if not any(h[0].lower() == 'content-length' for h in headers_list):
                headers_list.append(('Content-Length', str(len(content))))
                
            start_response(status, headers_list)
            return [content]
            
        except Exception as e:
            print(f"Error executing PHP router: {e}")
            status = '500 Internal Server Error'
            response = f"Error executing PHP router: {e}".encode('utf-8')
            headers = [
                ('Content-Type', 'text/plain'),
                ('Content-Length', str(len(response)))
            ]
            start_response(status, headers)
            return [response]
            
    else:
        # For non-PHP static files, serve them directly
        file_path = os.path.join(os.getcwd(), path_info.lstrip('/'))
        
        if os.path.exists(file_path) and os.path.isfile(file_path):
            content_type, _ = mimetypes.guess_type(file_path)
            if content_type is None:
                content_type = 'application/octet-stream'
                
            with open(file_path, 'rb') as f:
                file_content = f.read()
                
            headers = [
                ('Content-Type', content_type),
                ('Content-Length', str(len(file_content)))
            ]
            start_response('200 OK', headers)
            return [file_content]
        else:
            # File not found
            status = '404 Not Found'
            response = b"""
            <!DOCTYPE html>
            <html>
            <head>
                <title>404 - File Not Found</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    h1 {
                        color: #d9534f;
                    }
                    .back-link {
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <h1>404 - File Not Found</h1>
                <p>The requested file could not be found on this server.</p>
                <p>The PHP Media Manager is meant to be downloaded and run locally on your Mac.</p>
                <div class="back-link">
                    <a href="/">Go back to homepage</a>
                </div>
            </body>
            </html>
            """
            headers = [
                ('Content-Type', 'text/html'),
                ('Content-Length', str(len(response)))
            ]
            start_response(status, headers)
            return [response]