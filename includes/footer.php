<?php
/**
 * Footer template for Media Manager
 * Contains closing body elements, JavaScript includes, and footer content
 */
?>
    </div><!-- /.main-container -->
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="text-muted">
                <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?> &copy; <?php echo date('Y'); ?> 
                | <a href="https://github.com/sonarr/sonarr" target="_blank">Sonarr</a> 
                | <a href="https://github.com/Radarr/Radarr" target="_blank">Radarr</a> 
                | <a href="https://github.com/sabnzbd/sabnzbd" target="_blank">SABnzbd</a>
            </p>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="js/script.js"></script>
</body>
</html>
