import subprocess
import time
import os
import socket
import signal
import sys

# Path to PHP executable
PHP_PATH = "/nix/store/6abnc1cqyn1y6f7nh6v76aa6204mc79z-php-with-extensions-8.2.20/bin/php"
SERVER_PORT = 5000
php_process = None

def is_port_in_use(port):
    """Check if a port is in use"""
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        return s.connect_ex(('localhost', port)) == 0

def cleanup(signum, frame):
    """Handle cleanup when the script is terminated"""
    if php_process:
        print("Terminating PHP server...")
        php_process.terminate()
    sys.exit(0)

# Register signal handlers for cleanup
signal.signal(signal.SIGINT, cleanup)
signal.signal(signal.SIGTERM, cleanup)

# Kill any existing PHP processes
print("Killing any existing PHP processes...")
subprocess.run(['pkill', '-f', 'php'], stderr=subprocess.DEVNULL)
time.sleep(1)  # Wait for processes to terminate

# Ensure port is free
if is_port_in_use(SERVER_PORT):
    print(f"Port {SERVER_PORT} is still in use. Trying to free it...")
    subprocess.run(['fuser', '-k', f'{SERVER_PORT}/tcp'], stderr=subprocess.DEVNULL)
    time.sleep(1)

# Start the PHP server in the current directory
print(f"Starting PHP server on port {SERVER_PORT}...")
php_process = subprocess.Popen([PHP_PATH, "-S", f"0.0.0.0:{SERVER_PORT}"])

try:
    # Keep the script running while the PHP server is running
    while php_process.poll() is None:
        time.sleep(1)
except KeyboardInterrupt:
    cleanup(None, None)

# If we get here, the PHP server exited
print("PHP server exited. Terminating...")
sys.exit(php_process.returncode)