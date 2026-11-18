    </main>
    </div>
    </div>
    <script>
        (function() {
            var sidebar = document.getElementById('sidebar');
            var btn = document.getElementById('menuBtn');
            var backdrop = document.getElementById('sidebarBackdrop');
            if (!btn || !sidebar) return;

            function toggle() {
                sidebar.classList.toggle('open');
                if (backdrop) backdrop.classList.toggle('show');
            }
            btn.addEventListener('click', toggle);
            if (backdrop) backdrop.addEventListener('click', toggle);
        })();
    </script>
    </body>

    </html>