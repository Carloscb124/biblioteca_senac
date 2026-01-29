<?php include("../includes/header.php"); ?>

<form action="salvar.php" method="post">
  <input class="form-control mb-2" name="nome" placeholder="Nome">
  <input class="form-control mb-2" name="email" placeholder="Email">
  <input class="form-control mb-2" type="password" name="senha" placeholder="Senha">
  <select class="form-select mb-2" name="perfil">
    <option value="admin">Admin</option>
    <option value="leitor">Leitor</option>
  </select>
  <button class="btn btn-primary">Salvar</button>
</form>

<?php include("../includes/footer.php"); ?>
