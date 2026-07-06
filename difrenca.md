Como os dois sites buscam dados do WordPress
OBSERVARRIBAS usa uma abordagem chamada server-side rendering. Quando o utilizador abre a página, o servidor PHP vai diretamente à base de dados do WordPress, recolhe todos os dados, monta o HTML completo e envia tudo pronto para o browser. O utilizador vê o conteúdo imediatamente, sem esperar por nenhum pedido extra. Os filtros funcionam via AJAX — quando o utilizador clica num filtro, o browser pede ao servidor que filtre e devolva o novo HTML, também já montado.

SASTUDIO usa uma abordagem diferente chamada client-side rendering. O servidor envia uma página praticamente vazia, e é o JavaScript no browser que trata de tudo. Primeiro faz um pedido à REST API do WordPress para obter a lista de projetos, constrói os cards dinamicamente e apresenta a galeria. Quando o utilizador clica num projeto, é feito um segundo pedido para obter o conteúdo detalhado desse projeto — descrição, campos ACF, galeria de fotos — e o lightbox é preenchido sem nunca sair da página.

A grande diferença está no momento em que os dados chegam: no OBSERVARRIBAS chegam antes de o utilizador ver qualquer coisa, no SASTUDIO chegam depois, em pedidos separados. Para compensar, o SASTUDIO tem um sistema de prefetch — quando o utilizador passa o rato sobre um projeto, o pedido já é feito em segundo plano, e ao clicar os dados estão prontos na memória, tornando a abertura praticamente instantânea.

Nenhuma das abordagens é universalmente melhor. O OBSERVARRIBAS é ideal para páginas de conteúdo com filtros simples. O SASTUDIO é ideal para experiências com animações fluidas e navegação sem recarregar a página, onde a ilusão de velocidade é tão importante quanto a velocidade real.










Pelo que descreves, não estás a desenvolver um tema WordPress tradicional. Estás a usar um construtor como Code Snippets/Code Sniper (ou semelhante), onde inseres HTML, CSS, JS e PHP em blocos, e a página Single Project está vazia.

O que deves fazer depende de como os projetos são guardados.

Se os projetos já são Posts ou um Custom Post Type no WordPress

Na página vazia não deves colar o HTML da Home. Deves criar um template que leia o projeto atual.

A lógica é:

Home
   ↓
Clique no projeto
   ↓
/project/nome-do-projeto
   ↓
Single Project
   ↓
Mostra o título, imagens e descrição desse projeto
Na página "Single Project"