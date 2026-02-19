# 📚 Biblioteca SENAC

Sistema web de gerenciamento de biblioteca desenvolvido em PHP + MySQL para controle de acervo, empréstimos, usuários e administração interna.

## 🎥 Demonstração

![Demo do sistema](demo.gif)

---

## 🚀 Funcionalidades

### 📖 Livros
- Cadastro e edição de livros
- Busca por ISBN
- Classificação CDD
- Sinopse, editora e assuntos
- Controle de quantidade total e disponível
- Marcar item como perdido
- Ativar/desativar livros

### 🔄 Empréstimos
- Cadastro de empréstimos
- Devolução e renovação
- Marcar item como perdido
- Controle de status
- Histórico de movimentações

### 🔄 Relatorios
- Ultimos dados de livros/usuarios
- Livros mais Emprestados/perdidos
- Exportação e Importação de dados 


### 👥 Usuários e Funcionários
- Login e autenticação
- Cadastro de funcionários
- Reset de senha
- Ativação/desativação

### 📧 E-mails (SMTP)
O sistema usa SMTP para envio de e-mails (confirmação, recuperação de senha, etc).

Arquivo de configuração:
```
includes/mailer.php
```

Exemplo (Gmail SMTP):
```php
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'seu_email@gmail.com';
$mail->Password   = 'senha_de_app';
$mail->SMTPSecure = 'tls';
$mail->Port       = 587;
```

⚠️ Use senha de app e nunca suba credenciais reais para o GitHub.

---

## 🧠 Tecnologias

- PHP
- MySQL
- HTML, CSS, JavaScript
- Bootstrap
- XAMPP (ambiente local)

---

## 🗂 Estrutura do Projeto

```
biblioteca_senac/
├── auth/
├── livros/
├── emprestimos/
├── funcionarios/
├── includes/
├── database/
├── assets/
├── relatorios/
├── usuarios/
├── conexao.php
├── index.php
└── demo.gif
```

---

## ⚙️ Instalação (passo a passo)

### 1) Instalar o ambiente
1. Instale o **XAMPP**.
2. Abra o painel do XAMPP e inicie:
   - Apache
   - MySQL

### 2) Copiar o projeto
1. Extraia ou clone o projeto.
2. Coloque a pasta dentro de:
```
C:\xampp\htdocs\
```

### 3) Criar o banco de dados
1. Acesse:
```
http://localhost/phpmyadmin
```
2. Crie um banco chamado:
```
biblioteca_senac
```
3. Importe o arquivo:
```
database/biblioteca_senac.sql
```

### 4) Configurar conexão com o banco
Abra o arquivo:
```
conexao.php
```

Exemplo:
```php
$conn = mysqli_connect("localhost", "root", "", "biblioteca_senac");
```

### 5) Rodar o sistema
Abra no navegador:
```
http://localhost/biblioteca_senac
```

---

## 🧪 Dicas rápidas de uso

- Cadastre livros primeiro.
- Depois cadastre leitores/funcionários.
- Faça empréstimos e acompanhe pelo dashboard.
- Itens perdidos atualizam o acervo automaticamente.

---

## 👨‍💻 Autor

Carlos — estudante de TI e desenvolvimento web.

---

