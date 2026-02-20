</main>

<footer class="footer-clean">
  <div class="container footer-clean__inner">
    <small>© <?= date("Y") ?> Sistema de Biblioteca • Gestão de Acervo e Empréstimos</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Se existir toast na página, mostra automaticamente
  document.addEventListener("DOMContentLoaded", () => {
    const toastEl = document.getElementById("appToast");
    if (toastEl && window.bootstrap) {
      const t = bootstrap.Toast.getOrCreateInstance(toastEl);
      t.show();
    }
  });
</script>

</body>
</html>