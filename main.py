import os
import mimetypes

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
        # Get the MIME type of the file
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