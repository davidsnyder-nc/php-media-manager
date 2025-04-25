import os
import mimetypes
import subprocess
import tempfile
import urllib.parse

# Path to PHP executable
PHP_PATH = "/nix/store/6abnc1cqyn1y6f7nh6v76aa6204mc79z-php-with-extensions-8.2.20/bin/php"

def app(environ, start_response):
    # Determine the path requested
    path_info = environ.get('PATH_INFO', '').lstrip('/')
    
    # Default to index.html if no path is specified
    if not path_info:
        path_info = 'index.html'
    
    # Full path to the file
    file_path = os.path.join(os.getcwd(), path_info)
    
    # If the file exists, serve it
    if os.path.exists(file_path) and os.path.isfile(file_path):
        # For PHP files, execute them with PHP interpreter
        if file_path.endswith('.php'):
            # Prepare query string
            query_string = environ.get('QUERY_STRING', '')
            
            # Set environment variables for PHP
            env = os.environ.copy()
            env['QUERY_STRING'] = query_string
            env['REQUEST_METHOD'] = environ.get('REQUEST_METHOD', 'GET')
            env['SCRIPT_FILENAME'] = file_path
            env['SCRIPT_NAME'] = '/' + path_info
            env['REQUEST_URI'] = '/' + path_info
            
            if query_string:
                env['REQUEST_URI'] += '?' + query_string
                
            # Add form data for POST requests
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
            
            # Execute PHP script
            try:
                if post_data:
                    with tempfile.NamedTemporaryFile(delete=False) as tf:
                        tf.write(post_data)
                        temp_filename = tf.name
                    
                    process = subprocess.Popen(
                        [PHP_PATH, '-f', file_path],
                        stdin=open(temp_filename, 'rb'),
                        stdout=subprocess.PIPE,
                        stderr=subprocess.PIPE,
                        env=env
                    )
                    os.unlink(temp_filename)
                else:
                    process = subprocess.Popen(
                        [PHP_PATH, '-f', file_path],
                        stdout=subprocess.PIPE,
                        stderr=subprocess.PIPE,
                        env=env
                    )
                
                stdout, stderr = process.communicate()
                
                # If there are PHP errors, log them
                if stderr:
                    print(f"PHP Error in {path_info}:", stderr.decode())
                
                # Parse headers from output (if any)
                content = stdout
                headers_end = content.find(b'\r\n\r\n')
                status = '200 OK'  # Default status
                output_headers = []  # Initialize empty headers list
                
                if headers_end != -1:
                    headers_raw = content[:headers_end].decode('utf-8').split('\r\n')
                    
                    for header in headers_raw:
                        if header.startswith('HTTP/'):
                            status_line = header.split(' ', 1)[1]
                            status = status_line
                        else:
                            try:
                                key, value = header.split(':', 1)
                                output_headers.append((key.strip(), value.strip()))
                            except ValueError:
                                # Skip malformed headers
                                pass
                    
                    content = content[headers_end + 4:]
                else:
                    # If no headers found, set default content type
                    output_headers.append(('Content-Type', 'text/html'))
                
                # Add content length
                output_headers.append(('Content-Length', str(len(content))))
                
                start_response(status, output_headers)
                return [content]
                
            except Exception as e:
                print(f"Error executing PHP: {e}")
                status = '500 Internal Server Error'
                response = f"Error executing PHP script: {e}".encode('utf-8')
                headers = [
                    ('Content-Type', 'text/plain'),
                    ('Content-Length', str(len(response)))
                ]
                start_response(status, headers)
                return [response]
        else:
            # For non-PHP files, serve them as static files
            content_type, _ = mimetypes.guess_type(file_path)
            if content_type is None:
                content_type = 'application/octet-stream'
            
            # Read the file
            with open(file_path, 'rb') as f:
                file_content = f.read()
            
            # Send the response
            status = '200 OK'
            headers = [
                ('Content-Type', content_type),
                ('Content-Length', str(len(file_content)))
            ]
            start_response(status, headers)
            
            return [file_content]
    else:
        # If the file doesn't exist, serve a 404 page
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