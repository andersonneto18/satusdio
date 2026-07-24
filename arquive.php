if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Shortcode [sastudio_gallery] — independente do index.html/animacao.js/style.css.
 * Não depende de nenhum #id partilhado (nav, hero, lightbox, etc.) — evita os
 * conflitos de fixed/overflow/footer que apareciam ao reaproveitar esse CSS/JS.
 * Usa a mesma REST API do phpsniper.php (wp-json/wp/v2/projects + /sastudio/v2/project/{id}).
 * Cola [sastudio_gallery] no widget Shortcode do Elementor (template "Arquivo Projects").
 */
add_shortcode('sastudio_gallery', function () {
    ob_start();
    ?>
<style>
  /* O CSS partilhado ("Galeria Portfolio", ativo em todo o site) põe
     overflow:hidden no body para a home (app fullscreen sem scroll nativo).
     Nesta página isso bloqueia o scroll normal — contraria-se aqui. */
  html, body { overflow: auto !important; height: auto !important; }

  #sg-root, #sg-root * { box-sizing: border-box; }
  #sg-root {
    font-family: 'Inter', sans-serif;
    color: #151512;
    max-width: 1920px;
    margin: 0 auto;
    padding: 8rem 3vw 5rem;
  }
  #sg-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem;
    margin-bottom: 2.5rem; padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(21,21,18,0.12);
  }
  #sg-label {
    display: block; font-size: 0.7rem; font-weight: 400;
    letter-spacing: 0.14em; text-transform: uppercase;
    color: rgba(21,21,18,0.4); margin-bottom: 0.75rem;
  }
  #sg-tagline {
    font-family: 'Inter', sans-serif; font-weight: 300;
    font-size: clamp(1.6rem, 3vw, 2.4rem); line-height: 1.15; margin: 0;
  }
  #sg-count { font-size: 0.75rem; color: rgba(21,21,18,0.4); white-space: nowrap; }
  #sg-controls {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem;
  }
  /* ── Dropdowns Categoria/Location/Status/Typology, como no i-mad.com ── */
  #sg-dropdowns { display: flex; gap: 0.5rem; flex-wrap: wrap; }
  .sg-dd { position: relative; }
  .sg-dd-btn {
    display: flex; align-items: center; gap: 0.45rem;
    font-family: inherit; font-size: 0.68rem; letter-spacing: 0.08em;
    text-transform: uppercase; color: rgba(21,21,18,0.55);
    background: none; border: 1px solid rgba(21,21,18,0.18);
    border-radius: 999px; padding: 0.5rem 1.1rem; cursor: pointer;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
  }
  .sg-dd-btn:hover { border-color: #151512; color: #151512; }
  .sg-dd.open .sg-dd-btn { background: #151512; border-color: #151512; color: #fff; }
  .sg-dd-icon { font-size: 0.9rem; line-height: 1; }
  .sg-dd-panel {
    position: absolute; top: calc(100% + 8px); left: 0; z-index: 20;
    min-width: 210px;
    background: rgba(28,28,26,0.94);
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    border-radius: 10px;
    padding: 0.5rem 0;
    display: none;
    box-shadow: 0 10px 32px rgba(0,0,0,0.25);
  }
  .sg-dd.open .sg-dd-panel { display: block; }
  .sg-dd-opt {
    display: block; width: 100%; text-align: left;
    background: none; border: none; cursor: pointer;
    font-family: 'Inter', sans-serif; font-size: 0.9rem;
    color: rgba(255,255,255,0.8);
    padding: 0.55rem 1.2rem;
    transition: color 0.15s;
  }
  .sg-dd-opt:hover { color: #fff; }
  .sg-dd-opt.active { color: #fff; font-weight: 600; }

  #sg-search-wrap {
    display: flex; align-items: center; gap: 0.5rem;
    border: 1px solid rgba(21,21,18,0.18); border-radius: 999px;
    padding: 0.5rem 1rem;
  }
  #sg-search {
    border: none; outline: none; background: none;
    font-family: inherit; font-size: 0.75rem; color: #151512; width: 180px;
  }
  /* masonry via colunas CSS — cada card usa a proporção REAL da imagem
     (largura/altura vinda do WordPress), sem esticar nem recortar, tal
     como no i-mad.com. 3 colunas desktop, 2 tablet e telemóvel — com só
     1 coluna a variedade de alturas desaparece (fica tudo empilhado na
     mesma coluna), por isso mantém-se sempre pelo menos 2. */
  #sg-grid {
    column-count: 3; column-gap: 20px;
  }
  @media (max-width: 1100px) { #sg-grid { column-count: 2; } }
  @media (max-width: 760px)  { #sg-grid { column-count: 2; column-gap: 12px; } }
  .sg-card {
    cursor: pointer;
    break-inside: avoid;
    margin-bottom: 20px;
  }
  .sg-card-img {
    position: relative;
    overflow: hidden; background: #e8e7e3;
    border-radius: 16px;
  }
  .sg-card-img img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform 0.3s ease;
  }
  .sg-card:hover .sg-card-img img { transform: scale(1.03); }
  .sg-card-hover-vid {
    position: absolute; inset: 0;
    width: 100%; height: 100%; object-fit: cover;
    opacity: 0; transition: opacity 0.35s ease;
    pointer-events: none;
  }
  /* descrição só aparece ao passar o rato, sobreposta à imagem */
  .sg-card-overlay {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 1.2rem;
    background: linear-gradient(to top, rgba(0,0,0,0.68) 0%, transparent 58%);
    opacity: 0; transition: opacity 0.3s ease;
    pointer-events: none;
  }
  .sg-card:hover .sg-card-overlay { opacity: 1; }
  .sg-card-overlay .sg-card-title { font-size: 0.85rem; color: #fff; }
  .sg-card-overlay .sg-card-sub { font-size: 0.7rem; color: rgba(255,255,255,0.75); margin-top: 0.2rem; }
  #sg-empty { font-size: 0.85rem; color: rgba(21,21,18,0.5); padding: 3rem 0; }

  /* ── Botão de alternar grid/lista, como no i-mad.com ── */
  #sg-view-toggle {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; flex-shrink: 0;
    border: 1px solid rgba(21,21,18,0.18); border-radius: 8px;
    background: none; cursor: pointer; color: #151512;
    transition: border-color 0.2s, background 0.2s;
  }
  #sg-view-toggle:hover { border-color: #151512; }
  #sg-view-toggle.active { background: #151512; color: #fff; border-color: #151512; }
  #sg-view-toggle svg { width: 16px; height: 16px; }

  /* ── Vista em lista ── */
  #sg-list { display: none; }
  #sg-root.is-list-view #sg-grid { display: none; }
  #sg-root.is-list-view #sg-list { display: block; }
  .sg-list-row {
    display: grid;
    grid-template-columns: 90px 1fr 200px 260px 140px;
    align-items: center;
    gap: 1.5rem;
    padding: 1.1rem 0;
    border-bottom: 1px solid rgba(21,21,18,0.1);
    cursor: pointer;
  }
  .sg-list-row:hover { background: rgba(21,21,18,0.03); }
  .sg-list-thumb { width: 90px; height: 64px; border-radius: 8px; overflow: hidden; background: #e8e7e3; }
  .sg-list-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.35s ease; }
  .sg-list-row:hover .sg-list-thumb img { transform: scale(1.08); }
  .sg-list-title { font-size: 0.95rem; color: #151512; }
  .sg-list-cat, .sg-list-loc, .sg-list-year {
    font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase;
    color: rgba(21,21,18,0.5);
  }
  .sg-list-year { text-align: right; }
  @media (max-width: 900px) {
    .sg-list-row { grid-template-columns: 70px 1fr; }
    .sg-list-cat, .sg-list-loc, .sg-list-year { display: none; }
  }

  /* ── Imagem "a voar" — efeito de zoom ao clicar, igual ao index.html ── */
  #sg-fly {
    position: fixed; inset: 0; z-index: 100005;
    display: none;
    pointer-events: none;
  }
  #sg-fly img { width: 100%; height: 100%; object-fit: cover; display: block; }

  /* ── Modal — ecrã inteiro, como o lightbox do index.html ──
     Navegação horizontal entre painéis (Capa/Dados/Descrição →
     Galeria → Relacionados): #sg-track é deslocado via
     transform:translateX pelo wheel handler; cada .sg-panel mantém
     o seu próprio scroll vertical (texto longo) até chegar ao
     topo/fundo, altura em que o wheel passa a mudar de painel. */
  #sg-modal {
    position: fixed; inset: 0; z-index: 100000;
    background: #fff;
    display: none;
    overflow: hidden;
  }
  #sg-modal.sg-open { display: block; }
  body.sg-modal-open { overflow: hidden; }
  #sg-modal-inner { position: relative; height: 100%; }
  #sg-modal-body { height: 100%; }
  #sg-track { display: flex; height: 100%; will-change: transform; }
  .sg-panel { flex: 0 0 100vw; width: 100vw; height: 100%; background: #fff; }
  .sg-panel-scrollable {
    overflow-y: auto; overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    /* sem isto, o rubber-band/bounce nativo do scroll (trackpad/mouse
       com inércia) fazia estes painéis "saltar" um pouco para baixo e
       voltar sozinhos, mesmo sem conteúdo a mais para rolar */
    overscroll-behavior: contain;
  }
  .sg-panel-scrollable::-webkit-scrollbar { display: none; }

  /* ── BARRA DE PROGRESSO VERTICAL ──
     os painéis navegam na horizontal (translateX), por isso não existe
     scrollbar nativa a indicar o progresso; esta barra fininha à direita
     do ecrã sobe/desce (top-to-bottom) consoante o avanço entre painéis,
     atualizada em JS a par do #sg-track (ver sgTick()). */
  #sg-scrollbar {
    position: fixed; top: 0; right: 4px; bottom: 0;
    width: 3px; z-index: 100010;
    pointer-events: none;
    opacity: 0; transition: opacity 0.3s ease;
  }
  #sg-modal.sg-open #sg-scrollbar { opacity: 1; }
  #sg-scrollbar-thumb {
    position: absolute; left: 0; width: 100%;
    background: rgba(21,21,18,0.28);
    border-radius: 999px;
  }

  #sg-modal-close {
    position: fixed; top: 2.4rem; right: 1.5rem; z-index: 100010;
    display: flex; align-items: center; justify-content: center;
    width: 46px; height: 46px;
    border-radius: 50%; border: none;
    background: rgba(255,255,255,0.92);
    box-shadow: 0 2px 14px rgba(21,21,18,0.18);
    font-size: 1.5rem; line-height: 1; cursor: pointer;
    color: #151512;
  }

  /* ── Painel principal (Dados | Capa) ──
     título/categoria no topo (#sg-main-top), depois duas colunas lado
     a lado: Dados do projeto e a capa (imagem/vídeo estático, sem
     slideshow). A Descrição já não vive aqui — é o painel seguinte, a
     largura quase total da página (#sg-panel-desc), sem coluna ao lado. */
  #sg-panel-main { position: relative; padding: 4.5rem 5vw 6rem; }
  /* .sg-title-block (não só #sg-main-top): a mesma classe é reutilizada,
     invisível, dentro do painel da Descrição (.sg-desc-spacer) — isto
     garante que o título "Descrição:" fica exatamente à mesma altura
     que "Dados do projeto:" por construção em CSS (mesma marcação =
     mesma altura), sem depender de medir posições em JS. */
  .sg-title-block { max-width: 1800px; margin: 0 auto 3rem; }
  /* removida a pedido do cliente (categoria · ano acima do título) —
     display:none aqui apaga tanto a versão real como as cópias
     invisíveis usadas para alinhar Descrição/Galeria com o título. */
  .sg-title-block .sg-meta { display: none; }
  .sg-title-block h1 {
    font-family: 'Inter', sans-serif; font-weight: 300;
    font-size: clamp(1.8rem, 3.2vw, 2.8rem); line-height: 1.05;
    color: #151512; margin: 0; letter-spacing: 0.01em;
  }
  .sg-desc-spacer { visibility: hidden; pointer-events: none; }
  #sg-main-cols {
    display: flex; align-items: start; gap: 4vw;
    max-width: 1800px; margin: 0 auto;
  }
  .sg-col { flex: 1 1 0; min-width: 0; }
  #sg-acf { flex: 0 1 360px; min-width: 240px; }
  /* sticky: a capa fica fixa no ecrã enquanto o utilizador rola para
     ler a lista de Dados do projeto (quando é mais alta que a capa),
     em vez de subir/descer junto com o texto. */
  #sg-cover-col { flex: 1 1 0; min-width: 0; position: sticky; top: 0; }
  /* sem aspect-ratio/object-fit:cover fixo — a capa usa sempre a
     proporção real da imagem/vídeo (largura 100%, altura automática),
     para nunca cortar nada, seja qual for a orientação. */
  /* mais pequena que a coluna toda, centrada — não colada às bordas */
  /* max-height evita que imagens muito altas (proporção vertical)
     empurrem o painel para além do ecrã, obrigando a rolar — a
     imagem encolhe (mantendo a proporção real, sem cortar) até caber
     nessa altura, mesmo que fique mais estreita que os 65% da largura. */
  /* altura FIXA (não max-height) — todas as capas ficam com a mesma
     altura, para os cards ficarem visualmente consistentes entre
     projetos; object-fit:cover ajusta a imagem a essa caixa (pode
     recortar as margens conforme a proporção original). */
  #sg-cover-media { width: 65%; height: 55vh; margin: 0 auto; position: relative; }
  #sg-cover-media img,
  #sg-cover-media video {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
  }
  /* transição (crossfade) pelas imagens da galeria depois de o vídeo
     de hover acabar — ver startSgCoverSlideshow */
  .sg-cover-slide {
    position: absolute; inset: 0;
    background-size: cover; background-position: center;
    opacity: 0; transition: opacity 1.2s ease;
  }
  .sg-cover-slide.active { opacity: 1; }

  /* ── PAINEL DA DESCRIÇÃO — próprio painel horizontal, texto a
     largura quase total da página (sem coluna ao lado); o utilizador
     roda o rato (navegação horizontal já existente) para chegar a
     este painel e depois à Galeria. ── */
  /* align-items:flex-start (não center) — o padding-top igual ao do
     painel principal (4.5rem) + o .sg-desc-spacer invisível (ver acima)
     fazem o título "Descrição:" ficar à mesma altura do "Dados do
     projeto:" no painel anterior. */
  #sg-panel-desc { display: flex; align-items: flex-start; }
  #sg-content.sg-desc-col {
    width: 100%; max-width: 1300px; margin: 0 auto;
    padding: 4.5rem 3vw 5rem;
  }
  .sg-section-heading {
    font-family: 'Inter', sans-serif !important; font-size: 1rem !important;
    font-weight: 300 !important; white-space: nowrap !important;
    color: #151512; margin: 0 0 2rem; line-height: 1.1;
  }
  .sg-desc { font-size: 1rem; line-height: 1.85; color: rgba(21,21,18,0.82); text-align: justify; }
  .sg-acf-table { width: 100%; }
  .sg-acf-row {
    display: grid; grid-template-columns: 140px 1fr; gap: 1rem;
    padding: 0.85rem 0; border-bottom: 1px solid rgba(21,21,18,0.09);
    align-items: start; font-size: 0.85rem;
  }
  .sg-acf-row:first-child { border-top: 1px solid rgba(21,21,18,0.09); }
  .sg-acf-label { font-weight: 400; color: #151512; }
  .sg-acf-value { color: rgba(21,21,18,0.75); line-height: 1.55; }
  @media (max-width: 900px) {
    #sg-main-cols { flex-direction: column; align-items: stretch; gap: 2.5rem; }
    #sg-acf { flex-basis: auto; min-width: 0; }
    #sg-cover-col { position: static; }
    #sg-content.sg-desc-col { padding: 2.5rem 5vw 3rem; }
    .sg-desc-spacer { display: none; }
  }
  /* ── Galeria — cada painel mostra 2 fotos lado a lado, quase de
     ponta a ponta do ecrã, com um espaço pequeno entre elas (como na
     referência) — em vez de 1 foto pequena centrada com muito vazio
     à volta. Se sobrar 1 foto sozinha (número ímpar), ocupa o painel
     todo. ── */
  /* o painel usa a MESMA estrutura de topo do painel principal
     (padding-top 4.5rem + .sg-title-block invisível, reaproveitando o
     truque do .sg-desc-spacer) para as fotos começarem exatamente na
     mesma altura (linha de cima) que a imagem central — como ambas têm
     55vh de altura, a linha de baixo também fica alinhada por
     construção, sem precisar de medir nada em JS. */
  .sg-photo-panel {
    display: flex; flex-direction: column;
    padding: 4.5rem 2vw 2vh;
    background: #fff;
  }
  .sg-photo-row { display: flex; align-items: flex-start; justify-content: center; gap: 20px; }
  /* mesma altura da imagem central (capa, #sg-cover-media: 55vh) —
     fica alinhada com ela; a largura preenche o espaço disponível
     lado a lado (2 por painel), recortando via object-fit:cover. */
  .sg-photo-item { flex: 1 1 0; min-width: 0; height: 55vh; display: flex; flex-direction: column; }
  .sg-photo-item img {
    /* object-fit:cover preenche a caixa (pode recortar conforme a
       proporção original) — necessário para as duas fotos ficarem
       com a mesma altura lado a lado, independentemente da orientação. */
    width: 100%; height: 100%;
    object-fit: cover; display: block;
    pointer-events: none; -webkit-user-drag: none;
  }
  @media (max-width: 700px) {
    .sg-photo-panel { padding: 1.5vh 3vw; }
    .sg-photo-panel .sg-desc-spacer { display: none; }
    .sg-photo-row { flex-direction: column; gap: 12px; }
    .sg-photo-item { height: 40vh; }
  }
  #sg-modal-loading {
    display: flex; align-items: center; justify-content: center;
    height: 60vh; font-size: 0.8rem; color: rgba(21,21,18,0.45);
  }

  /* ── Outros projetos (relacionados), igual ao #lb-related ── */
  #sg-panel-related { display: flex; align-items: center; }
  #sg-related {
    padding: 3.5rem 8vw 5rem;
    width: 100%;
  }
  #sg-related-label {
    font-size: clamp(1.4rem, 2.4vw, 2rem);
    font-weight: 300; color: #151512;
    margin-bottom: 2rem; display: block; line-height: 1;
  }
  #sg-related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .sg-rel-card { cursor: pointer; }
  .sg-rel-card-img-wrap { overflow: hidden; aspect-ratio: 4/3; border-radius: 16px; }
  .sg-rel-card-img-wrap img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform 0.55s ease;
  }
  .sg-rel-card:hover .sg-rel-card-img-wrap img { transform: scale(1.05); }
  .sg-rel-card-title { font-size: 1rem; margin-top: 0.75rem; color: #151512; }
  .sg-rel-card-sub {
    font-size: 0.65rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(21,21,18,0.4); margin-top: 0.25rem;
  }
  @media (max-width: 700px) {
    #sg-related-grid { grid-template-columns: 1fr; gap: 16px; }
    #sg-related { padding: 2.5rem 5vw 3.5rem; }
  }
</style>

<div id="sg-root">
  <div id="sg-header">
    
    <span id="sg-count"></span>
  </div>
  <div id="sg-controls">
    <div style="display:flex; align-items:center; gap:0.7rem; flex-wrap:wrap;">
      <div id="sg-dropdowns"></div>
      <button id="sg-view-toggle" type="button" aria-label="Alternar vista em lista">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
      </button>
    </div>
    <div style="display:flex; align-items:center; gap:0.7rem;">
      <div id="sg-search-wrap">
        <input id="sg-search" type="text" placeholder="Pesquisar projetos…" autocomplete="off" />
      </div>
    </div>
  </div>
  <div id="sg-grid"></div>
  <div id="sg-list"></div>
  <div id="sg-empty" style="display:none;">Sem projetos para mostrar.</div>
</div>

<div id="sg-fly"><img id="sg-fly-img" src="" alt="" /></div>

<div id="sg-modal">
  <div id="sg-modal-inner">
    <button id="sg-modal-close" aria-label="Fechar">&times;</button>
    <div id="sg-modal-body"></div>
  </div>
  <div id="sg-scrollbar"><div id="sg-scrollbar-thumb"></div></div>
</div>

<script>
(function () {
  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  var WP_API_BASE = 'https://sastudio.brand22creativeagency.pt/wp-json/wp/v2';
  var WP_API      = WP_API_BASE + '/projects?_embed&per_page=100&orderby=date&order=desc';
  var CUSTOM_API  = WP_API_BASE.replace('/wp/v2', '/sastudio/v2');

  var grid    = document.getElementById('sg-grid');
  var list    = document.getElementById('sg-list');
  var viewToggle = document.getElementById('sg-view-toggle');
  var count   = document.getElementById('sg-count');
  var search  = document.getElementById('sg-search');
  var empty   = document.getElementById('sg-empty');
  var modal   = document.getElementById('sg-modal');
  var modalBody = document.getElementById('sg-modal-body');
  var modalClose = document.getElementById('sg-modal-close');
  var fly     = document.getElementById('sg-fly');
  var flyImg  = document.getElementById('sg-fly-img');

  var allCards = [];
  var allPosts = [];
  var projectCache = {};
  var listBuilt = false;

  /* ── Vista em lista (como no i-mad.com): miniatura, título, categoria,
     localização e ano — construída uma vez a partir de allPosts. ── */
  function buildListView() {
    if (listBuilt) return;
    listBuilt = true;
    list.innerHTML = allPosts.map(function (post) {
      var imgUrl = featuredUrl(post);
      var title  = post.title.rendered;
      var year   = new Date(post.date).getFullYear();
      var terms  = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
      var cat    = terms[0] ? terms[0].name : '';
      var loc    = (post.acf && post.acf.project_location) ? post.acf.project_location : '';
      return '<div class="sg-list-row" data-id="' + post.id + '">' +
        '<div class="sg-list-thumb"><img src="' + esc(imgUrl) + '" alt="' + esc(title) + '" loading="lazy"/></div>' +
        '<div class="sg-list-title">' + esc(title) + '</div>' +
        '<div class="sg-list-cat">' + esc(cat) + '</div>' +
        '<div class="sg-list-loc">' + esc(loc) + '</div>' +
        '<div class="sg-list-year">' + year + '</div>' +
      '</div>';
    }).join('');
    list.querySelectorAll('.sg-list-row').forEach(function (row) {
      row.addEventListener('click', function () {
        var id = parseInt(row.dataset.id, 10);
        var post = allPosts.filter(function (p) { return p.id === id; })[0];
        if (post) openModal(post, featuredUrl(post));
      });
    });
  }

  if (viewToggle) {
    viewToggle.addEventListener('click', function () {
      var isList = document.getElementById('sg-root').classList.toggle('is-list-view');
      viewToggle.classList.toggle('active', isList);
      if (isList) buildListView();
    });
  }

  /* ── Filtros em dropdown: Categoria / Location / Status / Typology, como
     no i-mad.com — cada um lê os valores únicos (categoria vem da
     taxonomia, os outros dos campos ACF) e deixa escolher um valor por
     dropdown, com "Todos" a limpar esse dropdown especificamente. Os
     quatro combinam entre si (AND). ── */
  var activeFilters = { category: '', location: '' };

  function makeDropdown(key, label, values) {
    var wrap = document.createElement('div');
    wrap.className = 'sg-dd';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sg-dd-btn';
    btn.innerHTML = esc(label) + ' <span class="sg-dd-icon">+</span>';

    var panel = document.createElement('div');
    panel.className = 'sg-dd-panel';

    function selectValue(val, optEl) {
      activeFilters[key] = val;
      panel.querySelectorAll('.sg-dd-opt').forEach(function (o) { o.classList.remove('active'); });
      optEl.classList.add('active');
      filterCards();
    }

    var allOpt = document.createElement('button');
    allOpt.type = 'button';
    allOpt.className = 'sg-dd-opt active';
    allOpt.textContent = 'Todos';
    allOpt.addEventListener('click', function (e) { e.stopPropagation(); selectValue('', allOpt); });
    panel.appendChild(allOpt);

    values.forEach(function (val) {
      var opt = document.createElement('button');
      opt.type = 'button';
      opt.className = 'sg-dd-opt';
      opt.textContent = val;
      opt.addEventListener('click', function (e) { e.stopPropagation(); selectValue(val, opt); });
      panel.appendChild(opt);
    });

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var wasOpen = wrap.classList.contains('open');
      document.querySelectorAll('.sg-dd.open').forEach(function (d) { d.classList.remove('open'); });
      if (!wasOpen) wrap.classList.add('open');
      btn.querySelector('.sg-dd-icon').textContent = wasOpen ? '+' : '—';
    });

    wrap.appendChild(btn);
    wrap.appendChild(panel);
    return wrap;
  }

  function buildDropdowns() {
    var host = document.getElementById('sg-dropdowns');
    if (!host || host.children.length) return;

    var cats = [];
    allPosts.forEach(function (post) {
      var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
      var cat = terms[0] ? terms[0].name : '';
      if (cat && cats.indexOf(cat) === -1) cats.push(cat);
    });
    if (cats.length) host.appendChild(makeDropdown('category', 'Categoria', cats));

    var fieldsMap = [
      { key: 'location', label: 'Localização', field: 'project_location' }
    ];
    fieldsMap.forEach(function (f) {
      var values = [];
      allPosts.forEach(function (post) {
        var v = post.acf && post.acf[f.field] ? String(post.acf[f.field]).trim() : '';
        if (v && values.indexOf(v) === -1) values.push(v);
      });
      if (values.length) host.appendChild(makeDropdown(f.key, f.label, values));
    });

    document.addEventListener('click', function () {
      document.querySelectorAll('.sg-dd.open').forEach(function (d) {
        d.classList.remove('open');
        var icon = d.querySelector('.sg-dd-icon');
        if (icon) icon.textContent = '+';
      });
    });
  }

  /* ── Link direto (limpo) para um projeto — usa o permalink real do WordPress
     (post.link, ex: /projects/nome-do-projeto/) em vez de query string, para
     poder ser aberto/partilhado noutros locais e coincidir com a página
     "Single Project" real. Ao abrir um projeto o URL muda; ao fechar, volta
     ao URL do arquivo; o botão voltar/avançar do browser também funciona. ── */
  var ARCHIVE_URL = window.location.href;
  function featuredUrl(post) {
    return post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]
      ? post._embedded['wp:featuredmedia'][0].source_url : '';
  }
  function normalizePath(url) {
    try { return new URL(url, window.location.href).pathname.replace(/\/+$/, ''); } catch (e) { return url; }
  }
  function findPostByPath(path) {
    return allPosts.filter(function (p) { return p.link && normalizePath(p.link) === path; })[0];
  }
  function pushProjectUrl(post) {
    if (!post.link) return;
    history.pushState({ sgProject: post.id }, '', post.link);
  }
  function popProjectUrl() {
    history.pushState(null, '', ARCHIVE_URL);
  }

  /* ── Prefetch: ao passar o rato num card, e em fila lenta para todos os
     outros em segundo plano, para o clique abrir instantaneamente. ── */
  function prefetchProject(id) {
    if (projectCache[id]) return;
    projectCache[id] = fetch(CUSTOM_API + '/project/' + id).then(function (r) { return r.json(); });
  }
  function backgroundPrefetchAll(ids) {
    var i = 0;
    function next() {
      if (i >= ids.length) return;
      prefetchProject(ids[i++]);
      setTimeout(next, 150);
    }
    setTimeout(next, 500);
  }

  function filterCards() {
    var q = search.value.trim().toLowerCase();
    var visible = 0;
    allCards.forEach(function (c) {
      var matchQ   = !q || c.title.indexOf(q) !== -1 || c.sub.indexOf(q) !== -1;
      var matchCat = !activeFilters.category || c.category === activeFilters.category;
      var matchLoc = !activeFilters.location || c.location === activeFilters.location;
      var show = matchQ && matchCat && matchLoc;
      c.el.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    count.textContent = visible + ' projetos';
  }

  function openModal(post, sourceImgSrc, opts) {
    opts = opts || {};
    if (!opts.skipHistory) pushProjectUrl(post);

    var featSrc0 = featuredUrl(post);
    var flySrc = sourceImgSrc || featSrc0;

    function reveal() {
      modalBody.innerHTML = '<div id="sg-modal-loading">A carregar projeto…</div>';
      modal.classList.add('sg-open');
      modal.scrollTop = 0;
      document.body.classList.add('sg-modal-open');
      loadModalContent(post);
    }

    if (flySrc && window.gsap && !opts.instant) {
      flyImg.src = flySrc;
      gsap.set(fly, { clearProps: 'all' });
      gsap.set(fly, { display: 'block', scale: 0.02, opacity: 0, transformOrigin: 'center center' });
      gsap.to(fly, {
        scale: 1, opacity: 1, duration: 0.85, ease: 'power2.out',
        onComplete: function () {
          reveal();
          gsap.to(fly, { opacity: 0, duration: 0.2, onComplete: function () { fly.style.display = 'none'; } });
        }
      });
    } else {
      reveal();
    }
  }

  function loadModalContent(post) {
    var id = post.id;
    var featSrc = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]
      ? post._embedded['wp:featuredmedia'][0].source_url : '';
    var title = post.title.rendered;
    var year  = new Date(post.date).getFullYear();
    var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
    var cat   = terms[0] ? terms[0].name : '';
    var meta  = cat ? (cat + ' · ' + year) : String(year);

    var req = projectCache[id]
      ? Promise.resolve(projectCache[id])
      : fetch(CUSTOM_API + '/project/' + id).then(function (r) { return r.json(); });

    req.then(function (data) {
      projectCache[id] = data;
      var acf = data.acf || {};
      var desc = (acf.project_description || acf.project_descriprion || '').trim();

      var metaFields = [
        { label: 'Programa',    value: acf.project_program },
        { label: 'Área',        value: acf.project_area },
        { label: 'Localização', value: acf.project_location },
        { label: 'Cliente',     value: acf.cliente },
        { label: 'Equipa',      value: acf.project_team },
        { label: 'Status',      value: acf.project_status },
        { label: 'Ano',         value: String(year) },
      ].filter(function (f) { return f.value && String(f.value).trim(); });

      var galleryImgs = Array.isArray(acf.project_gallery) ? acf.project_gallery : [];
      var hoverGif = (acf.hover_gif || '').trim();

      /* capa: vídeo (hover_gif) se existir, tocado uma vez (sem loop);
         quando acaba, passa a fazer transição (crossfade) pelas imagens
         da galeria (ver wireCoverSlideshow, chamado depois de inserir
         este HTML no DOM). Sem vídeo, mostra só a imagem de destaque. */
      var coverUrl     = hoverGif || featSrc;
      var coverIsVideo = !!hoverGif && isVideoUrl(hoverGif);
      var coverHtml    = coverIsVideo
        ? '<video src="' + esc(coverUrl) + '" muted autoplay playsinline></video>'
        : '<img src="' + esc(coverUrl) + '" alt=""/>';

      var titleBlockHtml = '<div class="sg-meta">' + esc(meta) + '</div><h1>' + esc(title) + '</h1>';

      var html = '<div id="sg-track">';
      html += '<section id="sg-panel-main" class="sg-panel sg-panel-scrollable">';
      html += '<div id="sg-main-top" class="sg-title-block">' + titleBlockHtml + '</div>';
      html += '<div id="sg-main-cols">';
      if (metaFields.length) {
        html += '<div id="sg-acf" class="sg-col">';
        html += '<h3 class="sg-section-heading">Dados do projeto:</h3>';
        html += '<div class="sg-acf-table">' + metaFields.map(function (f) {
          return '<div class="sg-acf-row"><span class="sg-acf-label">' + esc(f.label) + ':</span><span class="sg-acf-value">' + esc(f.value) + '</span></div>';
        }).join('') + '</div>';
        html += '</div>';
      }
      html += '<div id="sg-cover-col" class="sg-col"><div id="sg-cover-media">' + (coverUrl ? coverHtml : '') + '</div></div>';
      html += '</div>'; /* fim #sg-main-cols */
      html += '</section>';
      html += '<section id="sg-panel-desc" class="sg-panel sg-panel-scrollable">';
      html += '<div id="sg-content" class="sg-desc-col">';
      html += '<div class="sg-title-block sg-desc-spacer" aria-hidden="true">' + titleBlockHtml + '</div>';
      html += '<h3 class="sg-section-heading">Descrição:</h3>';
      html += '<div class="sg-desc">' + (desc || '<p>Sem descrição para este projeto.</p>') + '</div>';
      html += '</div>';
      html += '</section>';
      if (galleryImgs.length) {
        /* 2 fotos por painel, lado a lado (ver .sg-photo-panel) — se
           sobrar 1 foto sozinha (número ímpar), ocupa o painel todo */
        var photoPanelsHtml = '';
        for (var gi = 0; gi < galleryImgs.length; gi += 2) {
          var pair = galleryImgs.slice(gi, gi + 2);
          photoPanelsHtml += '<section class="sg-panel sg-panel-scrollable sg-photo-panel">' +
            '<div class="sg-title-block sg-desc-spacer" aria-hidden="true">' + titleBlockHtml + '</div>' +
            '<div class="sg-photo-row">' +
            pair.map(function (url) {
              return '<div class="sg-photo-item"><img src="' + esc(url) + '" loading="lazy" alt=""/></div>';
            }).join('') +
            '</div></section>';
        }
        html += photoPanelsHtml;
      }
      html += buildRelatedHtml(post);
      html += '</div>'; /* fim #sg-track */
      modalBody.innerHTML = html;
      wireRelatedClicks();
      if (window.resetSgTrack) window.resetSgTrack();
      wireCoverSlideshow(galleryImgs);
    }).catch(function () {
      modalBody.innerHTML = '<div id="sg-modal-content"><h2>' + esc(title) + '</h2><p>Não foi possível carregar o conteúdo.</p></div>';
    });
  }

  /* ── Outros projetos (relacionados), igual ao populateRelated() ── */
  function buildRelatedHtml(currentPost) {
    var others = allPosts.filter(function (p) { return p.id !== currentPost.id; });
    var picks = others.sort(function () { return Math.random() - 0.5; }).slice(0, 3);
    if (!picks.length) return '';

    var cards = picks.map(function (post) {
      var imgUrl = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]
        ? post._embedded['wp:featuredmedia'][0].source_url : '';
      if (!imgUrl) return '';
      var title = post.title.rendered;
      var year  = new Date(post.date).getFullYear();
      var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
      var cat   = terms[0] ? terms[0].name : '';
      var sub   = cat ? (cat + ' · ' + year) : String(year);

      return '<div class="sg-rel-card" data-id="' + post.id + '">' +
        '<div class="sg-rel-card-img-wrap"><img src="' + esc(imgUrl) + '" alt="' + esc(title) + '" loading="lazy"/></div>' +
        '<div class="sg-rel-card-title">' + esc(title) + '</div>' +
        '<div class="sg-rel-card-sub">' + esc(sub) + '</div>' +
        '</div>';
    }).join('');

    return '<section id="sg-panel-related" class="sg-panel sg-panel-scrollable"><div id="sg-related">' +
      '<span id="sg-related-label">Confira outros projetos</span>' +
      '<div id="sg-related-grid">' + cards + '</div>' +
      '</div></section>';
  }

  function wireRelatedClicks() {
    document.querySelectorAll('#sg-related-grid .sg-rel-card').forEach(function (card) {
      card.addEventListener('click', function () {
        var id = parseInt(card.dataset.id, 10);
        var post = allPosts.filter(function (p) { return p.id === id; })[0];
        if (!post) return;
        var imgUrl = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]
          ? post._embedded['wp:featuredmedia'][0].source_url : '';
        openModal(post, imgUrl);
      });
    });
  }

  function isVideoUrl(url) {
    return /\.(mp4|webm|mov|ogg)(\?|$)/i.test(url);
  }

  /* transição (crossfade) pelas imagens da galeria depois de o vídeo
     de hover da capa acabar (ver coverIsVideo/coverHtml acima). */
  var sgCoverSlideshowTimer = null;
  function stopSgCoverSlideshow() {
    if (sgCoverSlideshowTimer) { clearInterval(sgCoverSlideshowTimer); sgCoverSlideshowTimer = null; }
  }
  function startSgCoverSlideshow(images) {
    stopSgCoverSlideshow();
    var coverMedia = document.getElementById('sg-cover-media');
    if (!coverMedia || !images || images.length < 2) return;
    coverMedia.innerHTML = '';
    var slides = images.map(function (url, i) {
      var el = document.createElement('div');
      el.className = 'sg-cover-slide' + (i === 0 ? ' active' : '');
      el.style.backgroundImage = 'url("' + url + '")';
      coverMedia.appendChild(el);
      return el;
    });
    var idx = 0;
    sgCoverSlideshowTimer = setInterval(function () {
      slides[idx].classList.remove('active');
      idx = (idx + 1) % slides.length;
      slides[idx].classList.add('active');
    }, 4000);
  }
  function wireCoverSlideshow(images) {
    stopSgCoverSlideshow();
    var coverMedia = document.getElementById('sg-cover-media');
    var vid = coverMedia ? coverMedia.querySelector('video') : null;
    if (!vid) return;
    vid.addEventListener('ended', function () { startSgCoverSlideshow(images); });
  }

  /* ── Navegação horizontal entre painéis (Capa/Dados/Descrição →
     Galeria → Relacionados), igual ao index.html — #sg-track é
     recriado a cada abertura de projeto (modalBody.innerHTML), por
     isso é preciso voltar a procurá-lo em cada frame/evento em vez
     de o guardar numa variável fixa. ── */
  var sgTx = 0, sgTTx = 0;

  function sgBounds() {
    var track = document.getElementById('sg-track');
    var n = track ? Math.max(track.querySelectorAll('.sg-panel').length, 1) : 1;
    return { min: -(n - 1) * window.innerWidth, max: 0 };
  }

  var sgScrollbarThumb = document.getElementById('sg-scrollbar-thumb');

  /* barra vertical de progresso — desce à medida que se avança pelos
     painéis horizontais, como substituta visual da scrollbar nativa. */
  function updateSgScrollbar(b) {
    if (!sgScrollbarThumb) return;
    var track = document.getElementById('sg-track');
    var n = track ? Math.max(track.querySelectorAll('.sg-panel').length, 1) : 1;
    var progress = b.min !== 0 ? Math.min(1, Math.max(0, sgTx / b.min)) : 0;
    var thumbPct = 100 / n;
    sgScrollbarThumb.style.height = thumbPct + '%';
    sgScrollbarThumb.style.top = (progress * (100 - thumbPct)) + '%';
  }

  (function sgTick() {
    var track = document.getElementById('sg-track');
    if (track) {
      var b = sgBounds();
      sgTTx = Math.max(b.min, Math.min(b.max, sgTTx));
      sgTx += (sgTTx - sgTx) * 0.14;
      track.style.transform = 'translateX(' + sgTx + 'px)';
      updateSgScrollbar(b);
    }
    requestAnimationFrame(sgTick);
  })();

  window.addEventListener('wheel', function (e) {
    if (!modal.classList.contains('sg-open')) return;
    var track = document.getElementById('sg-track');
    if (!track) return;

    var scrollable = e.target.closest('.sg-panel-scrollable');
    if (scrollable && scrollable.scrollHeight - scrollable.clientHeight > 24) {
      var atTop     = scrollable.scrollTop <= 0;
      var atBottom  = scrollable.scrollTop + scrollable.clientHeight >= scrollable.scrollHeight - 1;
      var goingDown = e.deltaY > 0;
      if ((goingDown && !atBottom) || (!goingDown && !atTop)) return;
    }

    e.preventDefault();
    var delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    sgTTx -= delta * 1.3;
  }, { passive: false });

  window.resetSgTrack = function () {
    sgTx = 0; sgTTx = 0;
    var track = document.getElementById('sg-track');
    if (track) track.style.transform = 'translateX(0px)';
  };

  function closeModal(opts) {
    opts = opts || {};
    modal.classList.remove('sg-open');
    document.body.classList.remove('sg-modal-open');
    stopSgCoverSlideshow();
    if (!opts.skipHistory) popProjectUrl();
  }
  modalClose.addEventListener('click', function () { closeModal(); });
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

  /* ── Botão voltar/avançar do browser: abre/fecha consoante o URL atual ── */
  window.addEventListener('popstate', function () {
    var path = normalizePath(window.location.href);
    var post = findPostByPath(path);
    if (post) {
      openModal(post, featuredUrl(post), { skipHistory: true, instant: true });
    } else if (modal.classList.contains('sg-open')) {
      closeModal({ skipHistory: true });
    }
  });

  fetch(WP_API)
    .then(function (r) { return r.json(); })
    .then(function (posts) {
      if (!posts || !posts.length) { empty.style.display = 'block'; return; }
      allPosts = posts;
      buildDropdowns();
      search.addEventListener('input', filterCards);

      posts.forEach(function (post) {
        var media = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0];
        var imgUrl = media ? media.source_url : '';
        if (!imgUrl) return;

        var title = post.title.rendered;
        var year  = new Date(post.date).getFullYear();
        var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
        var cat   = terms[0] ? terms[0].name : '';
        var sub   = cat ? (cat + ' · ' + year) : String(year);

        /* proporção real da imagem (largura/altura do WordPress) — cada
           card fica com a altura correspondente à foto real, sem esticar
           nem recortar, tal como no i-mad.com */
        var mw = media && media.media_details ? media.media_details.width : 0;
        var mh = media && media.media_details ? media.media_details.height : 0;
        var aspectStyle = (mw && mh) ? ' style="aspect-ratio:' + mw + '/' + mh + '"' : '';

        /* vídeo em hover (campo ACF hover_gif), igual ao index.html — ao
           passar o rato num card, se houver vídeo associado, mostra-o por
           cima da imagem em vez do fallback estático */
        var hoverGif = (post.acf && post.acf.hover_gif) ? String(post.acf.hover_gif).trim() : '';
        var hoverVidHtml = (hoverGif && isVideoUrl(hoverGif))
          ? '<video class="sg-card-hover-vid" muted loop playsinline preload="none" src="' + esc(hoverGif) + '"></video>'
          : '';

        var card = document.createElement('div');
        card.className = 'sg-card';
        card.innerHTML =
          '<div class="sg-card-img"' + aspectStyle + '>' +
            '<img src="' + esc(imgUrl) + '" alt="' + esc(title) + '" loading="lazy"/>' +
            hoverVidHtml +
            '<div class="sg-card-overlay">' +
              '<div class="sg-card-title">' + esc(title) + '</div>' +
              '<div class="sg-card-sub">' + esc(sub) + '</div>' +
            '</div>' +
          '</div>';
        card.addEventListener('click', function () { openModal(post, imgUrl); });
        card.addEventListener('mouseenter', function () { prefetchProject(post.id); });

        var hoverVidEl = card.querySelector('.sg-card-hover-vid');
        if (hoverVidEl) {
          card.addEventListener('mouseenter', function () {
            hoverVidEl.currentTime = 0;
            hoverVidEl.play().catch(function () {});
            hoverVidEl.style.opacity = '1';
          });
          card.addEventListener('mouseleave', function () {
            hoverVidEl.style.opacity = '0';
            setTimeout(function () { hoverVidEl.pause(); }, 300);
          });
        }

        grid.appendChild(card);
        allCards.push({
          el: card, title: title.toLowerCase(), sub: sub.toLowerCase(),
          category: cat,
          location: (post.acf && post.acf.project_location) ? String(post.acf.project_location).trim() : ''
        });
      });

      count.textContent = allCards.length + ' projetos';
      backgroundPrefetchAll(posts.map(function (p) { return p.id; }));
    })
    .catch(function (err) {
      console.warn('SASTUDIO gallery:', err);
      empty.textContent = 'Não foi possível carregar os projetos.';
      empty.style.display = 'block';
    });
})();
</script>
    <?php
    return ob_get_clean();
});
