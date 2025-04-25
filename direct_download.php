<?php
// Get the absolute URL
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$downloadPath = '/download.php';
$downloadUrl = $protocol . $host . $downloadPath;

// Include page header
$pageTitle = "Download PHP Media Manager";
require_once 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0"><i class="fa fa-download"></i> Download PHP Media Manager</h2>
                </div>
                <div class="card-body text-center">
                    <p class="lead mb-4">Click the button below to download the PHP Media Manager package.</p>
                    
                    <div class="download-box p-4 mb-4 bg-light rounded">
                        <h3 class="mb-3">php-media-manager.zip</h3>
                        <p class="text-muted mb-4">Size: <?php echo round(filesize('dist/php-media-manager.zip') / 1024, 2); ?> KB</p>
                        <a href="<?php echo $downloadUrl; ?>" class="btn btn-lg btn-success">
                            <i class="fa fa-download"></i> Download Now
                        </a>
                    </div>
                    
                    <div class="installation-steps text-start mt-5">
                        <h4>Installation Instructions</h4>
                        <ol>
                            <li>Download the package using the button above</li>
                            <li>Unzip the downloaded file</li>
                            <li>Move the PHP Media Manager folder to your desired location</li>
                            <li>Open the folder and double-click on "start.command"</li>
                            <li>Access the interface by navigating to http://localhost:8000 in your browser</li>
                            <li>Configure your Sonarr, Radarr, and SABnzbd API settings</li>
                        </ol>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Homepage
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>