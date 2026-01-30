</main>

<footer class="footer-clean">
  <div class="container footer-clean__inner">
    <small>© <?= date("Y") ?> Sistema de Biblioteca • Gestão de Acervo e Empréstimos</small>

  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  setTimeout(() => {
    const alertEl = document.querySelector('.flash-msg');
    if (alertEl && window.bootstrap) {
      bootstrap.Alert.getOrCreateInstance(alertEl).close();
    }
  }, 3000);
</script>

</body>
</html>
