</section>
    </article>
</main>

<footer class="footer text-center text-white py-3">
    <div class="container">
        <p>&copy; <?= date('Y'); ?> <a href="./" class="text-white"><?= $faucetName; ?></a>. All Rights Reserved. Version: <?= $core->getVersion(); ?><br>
        Powered by <a href="https://coolscript.hu" class="text-white">CoolScript</a></p>
        <p>Current server time: <?= date('d-m-Y H:i'); ?></p>
    </div>
</footer>

<!-- Bootstrap 5 és JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");

    // Toggle ikonra kattintáskor a sidebar megnyitása vagy bezárása
    sidebarToggle.addEventListener("click", function () {
        sidebar.classList.toggle("active");
    });

    // Bezárás, ha a toggle ikonra kattintunk, miközben a menü nyitva van
    document.addEventListener("click", function (event) {
        if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
            sidebar.classList.remove("active");
        }
    });
});
</script>

</body>
</html>

