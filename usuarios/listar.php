<?php
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "SELECT * FROM usuarios");
?>

<h2>Usuários</h2>
<a class="btn btn-primary mb-3" href="cadastrar.php">Novo</a>

<table class="table table-hover bg-white">
<?php while ($u = mysqli_fetch_assoc($r)) { ?>
<tr>
  <td><?= $u['nome'] ?></td>
  <td><?= $u['email'] ?></td>
  <td><?= $u['perfil'] ?></td>
  <td>
    <a href="editar.php?id=<?= $u['id'] ?>">Editar</a> |
    <a href="excluir.php?id=<?= $u['id'] ?>">Excluir</a>
  </td>
</tr>
<?php } ?>
</table>

<?php include("../includes/footer.php"); ?>
