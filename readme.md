# 📚 Sistema de Gestão de Biblioteca

Sistema web para **gestão de acervo, usuários, empréstimos e relatórios de uma biblioteca**, desenvolvido em **PHP**, **MySQL** e **Bootstrap**.

Projeto desenvolvido no **SENAC**, estruturado também para **portfólio profissional**, com foco em organização de código, usabilidade e visual limpo.

---

## 🖥️ Demonstração do Sistema

> Tela real do sistema em funcionamento:

![Sistema de Biblioteca](image.png)

---

## 🧠 Funcionalidades

### 📖 Acervo
- Cadastro, edição e exclusão de livros  
- Controle de disponibilidade  
- Listagem organizada em tabela  

### 👥 Usuários
- Cadastro e gerenciamento de usuários  
- Perfis de acesso (Admin / Leitor)  
- Listagem clara e objetiva  

### 🔄 Empréstimos
- Registro de empréstimos  
- Data prevista de devolução  
- Identificação automática de atrasos  
- Status visual:
  - 🟦 Aberto
  - 🟩 Devolvido
  - 🟥 Atrasado

### 📊 Relatórios
- Painel com indicadores (KPIs)  
- Livros mais emprestados  
- Empréstimos por período  
- Empréstimos em atraso  
- Histórico por usuário  
- Gráficos interativos  

---

## 🎨 Interface e Design

- Layout responsivo com **Bootstrap 5**  
- Tema visual próprio (verde e bege)  
- Dashboard com cards e gráficos  
- CSS **modularizado** para facilitar manutenção  
- Componentes reutilizáveis (tabelas, badges, botões)  

---

## 🧩 Estrutura do Projeto

> Estrutura corrigida considerando `conexao.php` na raiz.

```text
biblioteca_senac/
│
├── assets/
|   └── reader.png
│   └── css/
│       ├── base.css
│       ├── layout.css
│       ├── header.css
│       ├── footer.css
│       ├── tables.css
│       ├── forms.css
│       ├── components.css
│       ├── dashboard.css
│       ├── reports.css
│       ├── hero.css
│       ├── responsive.css
│       └── style.css
|     
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── flash.php
│
├── livros/
├── usuarios/
├── emprestimos/
├── relatorios/
│
├── conexao.php
├── index.php
├── image.png
└── README.md
```

---

## 🛠️ Tecnologias Utilizadas

- PHP 8+
- MySQL
- Bootstrap 5
- Bootstrap Icons
- Chart.js
- HTML5 / CSS3
- JavaScript

---

## 🚀 Como executar o projeto

### Pré-requisitos
- XAMPP (ou similar)
- PHP 8+
- MySQL
- Navegador moderno

### Passos

1. Clone o repositório:
   ```bash
   git clone https://github.com/Carloscb124/biblioteca_senac.git
   ```

2. Mova o projeto para a pasta do servidor (XAMPP):
   ```text
   C:\xampp\htdocs\biblioteca_senac
   ```

3. Crie o banco de dados no MySQL (ex: `biblioteca_senac`) e importe o SQL

4. Configure a conexão em:
   ```php
   // conexao.php (na raiz do projeto)
   ```

5. Acesse no navegador:
   ```text
   http://localhost/biblioteca_senac
   ```

---

## 📌 Status do Projeto

- ✔️ Funcional  
- 🚧 Em desenvolvimento contínuo  

---

## 🔮 Melhorias Futuras

- Sistema de login com autenticação  
- Controle de permissões por perfil  
- Exportação de relatórios (PDF / Excel)  
- Filtros avançados e paginação  
- Histórico de ações do sistema  

---

## 👨‍💻 Autor

**Carlos Eduardo**
