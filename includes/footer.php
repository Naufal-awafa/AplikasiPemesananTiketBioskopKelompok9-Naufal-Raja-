</main>

<footer class="site-footer">
    <div class="container">
        <?php $logo = logoFilename(); ?>
        <div class="brand" style="justify-content:center; display:flex; align-items:center; gap:8px;">
            <?php if ($logo): ?>
                <img src="<?= $base ?>assets/img/<?= $logo ?>" alt="Sineverse" style="width:24px; height:24px; border-radius:7px; object-fit:cover;">
            <?php else: ?>
                <?= ikon('film', 24) ?>
            <?php endif; ?>
            <span>Sineverse Cinema</span>
        </div>
        <p>Pesan tiket bioskop online — cepat, mudah, dan tanpa antre.</p>
        <p class="mt-8">&copy; <?= date('Y') ?> Sineverse Cinema. Seluruh hak cipta dilindungi.</p>
        <p class="mt-8" style="font-size:.7rem; opacity:.6;">Data &amp; poster sebagian film dari <a href="https://www.themoviedb.org/" target="_blank" rel="noopener" style="color:inherit;">TMDB</a>. Situs ini tidak didukung atau disahkan oleh TMDB.</p>
    </div>
</footer>

<script src="<?= $base ?>assets/js/script.js"></script>
</body>
</html>
