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
    font-family: 'Cormorant Garamond', serif; font-weight: 300;
    font-size: clamp(1.6rem, 3vw, 2.4rem); line-height: 1.15; margin: 0;
  }
  #sg-count { font-size: 0.75rem; color: rgba(21,21,18,0.4); white-space: nowrap; }
  #sg-controls {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem;
  }
  #sg-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
  .sg-filter-btn {
    font-family: inherit; font-size: 0.68rem; letter-spacing: 0.08em;
    text-transform: uppercase; color: rgba(21,21,18,0.55);
    background: none; border: 1px solid rgba(21,21,18,0.18);
    border-radius: 999px; padding: 0.5rem 1.1rem; cursor: pointer;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
  }
  .sg-filter-btn:hover { border-color: #151512; color: #151512; }
  .sg-filter-btn.active { background: #151512; border-color: #151512; color: #fff; }
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
     como no i-mad.com. 3 colunas desktop, 2 tablet, 1 mobile. */
  #sg-grid {
    column-count: 3; column-gap: 20px;
  }
  @media (max-width: 1100px) { #sg-grid { column-count: 2; } }
  @media (max-width: 760px)  { #sg-grid { column-count: 2; column-gap: 12px; } }
  @media (max-width: 480px)  { #sg-grid { column-count: 1; } }
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

  /* ── Imagem "a voar" — efeito de zoom ao clicar, igual ao index.html ── */
  #sg-fly {
    position: fixed; inset: 0; z-index: 100005;
    display: none;
    pointer-events: none;
  }
  #sg-fly img { width: 100%; height: 100%; object-fit: cover; display: block; }

  /* ── Modal — ecrã inteiro, como o lightbox do index.html ── */
  #sg-modal {
    position: fixed; inset: 0; z-index: 100000;
    background: #fff;
    display: none;
    overflow-y: auto; overflow-x: hidden;
  }
  #sg-modal.sg-open { display: block; }
  body.sg-modal-open { overflow: hidden; }
  #sg-modal-inner { position: relative; min-height: 100%; }

  #sg-modal-close {
    position: fixed; top: 1.5rem; right: 1.5rem; z-index: 100010;
    display: flex; align-items: center; justify-content: center;
    width: 46px; height: 46px;
    border-radius: 50%; border: none;
    background: rgba(255,255,255,0.92);
    box-shadow: 0 2px 14px rgba(21,21,18,0.18);
    font-size: 1.5rem; line-height: 1; cursor: pointer;
    color: #151512;
  }

  #sg-modal-hero {
    position: relative;
    width: 100%; height: 100vh;
    overflow: hidden; background: #000;
  }
  #sg-slides { position: absolute; inset: 0; }
  .sg-slide { position: absolute; inset: 0; opacity: 0; }
  .sg-slide.active { opacity: 1; z-index: 1; }
  .sg-slide img, .sg-slide video { width: 100%; height: 100%; object-fit: cover; display: block; }
  #sg-modal-hero-overlay {
    position: absolute; inset: 0; z-index: 2;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 2.4rem clamp(1.2rem, 6vw, 5vw);
    background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 55%);
    pointer-events: none;
  }
  #sg-modal-hero-overlay > * { pointer-events: all; }
  #sg-modal-hero .sg-meta {
    font-size: 0.7rem; letter-spacing: 0.3em; text-transform: uppercase;
    color: rgba(255,255,255,0.75); margin-bottom: 0.8rem;
  }
  #sg-modal-hero h2 {
    font-family: 'Cormorant Garamond', serif; font-weight: 300;
    font-size: clamp(2.2rem, 5.5vw, 4.5rem); line-height: 1.05;
    color: #fff; margin: 0 0 1.5rem; letter-spacing: 0.01em;
  }
  #sg-slide-nav {
    display: flex; align-items: center; gap: 0.9rem;
    align-self: flex-end; margin-left: auto;
  }
  #sg-slide-nav button {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.32);
    color: #fff; font-size: 1.1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
  }
  #sg-slide-nav button:hover { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.75); }
  #sg-slide-count { font-size: 0.48rem; letter-spacing: 0.14em; color: rgba(255,255,255,0.6); min-width: 2.5rem; text-align: center; }

  #sg-modal-content {
    padding: 4.5rem clamp(1.2rem, 6vw, 5vw) 2rem;
  }
  #sg-modal-main {
    display: grid; grid-template-columns: 1fr 1fr;
    align-items: start; gap: 5vw;
    max-width: 1280px; margin: 0 auto;
  }
  .sg-section-heading {
    font-size: clamp(1.4rem, 2.4vw, 2rem); font-weight: 300;
    color: #151512; margin: 0 0 2rem; line-height: 1.1;
  }
  #sg-modal-content .sg-desc { font-size: 1rem; line-height: 1.85; color: rgba(21,21,18,0.82); text-align: justify; }
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
    #sg-modal-main { grid-template-columns: 1fr; gap: 2.5rem; }
  }
  /* ── Galeria horizontal (arrastar para ver mais), igual ao #lb-gallery ── */
  #sg-modal-gallery {
    display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch;
    gap: 6px; margin: 3rem auto 1rem; max-width: 1280px;
    padding: 0 clamp(1.2rem, 6vw, 5vw) 0.6rem; cursor: grab;
    scrollbar-width: thin; scrollbar-color: rgba(21,21,18,0.25) rgba(21,21,18,0.06);
    user-select: none; -webkit-user-select: none;
  }
  #sg-modal-gallery::-webkit-scrollbar { height: 3px; }
  #sg-modal-gallery::-webkit-scrollbar-track { background: rgba(21,21,18,0.06); }
  #sg-modal-gallery::-webkit-scrollbar-thumb { background: rgba(21,21,18,0.25); border-radius: 999px; }
  .sg-gallery-item { flex: 0 0 auto; width: 52vw; }
  .sg-gallery-item-img { overflow: hidden; aspect-ratio: 16/9; border-radius: 4px; }
  .sg-gallery-item-img img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    pointer-events: none; -webkit-user-drag: none;
  }
  @media (max-width: 700px) { .sg-gallery-item { width: 82vw; } }
  #sg-modal-loading {
    display: flex; align-items: center; justify-content: center;
    height: 60vh; font-size: 0.8rem; color: rgba(21,21,18,0.45);
  }

  /* ── Outros projetos (relacionados), igual ao #lb-related ── */
  #sg-related {
    background: #f7f6f4;
    padding: 3.5rem 8vw 5rem;
    border-top: 1px solid rgba(21,21,18,0.08);
  }
  #sg-related-label {
    font-size: clamp(1.4rem, 2.4vw, 2rem);
    font-weight: 300; color: #151512;
    margin-bottom: 2rem; display: block; line-height: 1;
  }
  #sg-related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .sg-rel-card { cursor: pointer; }
  .sg-rel-card-img-wrap { overflow: hidden; aspect-ratio: 4/3; }
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
    <div>
      <span id="sg-label">Projetos</span>
      <p id="sg-tagline">Transformamos espaços em experiências únicas</p>
    </div>
    <span id="sg-count"></span>
  </div>
  <div id="sg-controls">
    <div id="sg-filters"></div>
    <div id="sg-search-wrap">
      <input id="sg-search" type="text" placeholder="Pesquisar projetos…" autocomplete="off" />
    </div>
  </div>
  <div id="sg-grid"></div>
  <div id="sg-empty" style="display:none;">Sem projetos para mostrar.</div>
</div>

<div id="sg-fly"><img id="sg-fly-img" src="" alt="" /></div>

<div id="sg-modal">
  <div id="sg-modal-inner">
    <button id="sg-modal-close" aria-label="Fechar">&times;</button>
    <div id="sg-modal-body"></div>
  </div>
</div>

<script>
(function () {
  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  var WP_API_BASE = 'https://sastudio.brand22creativeagency.pt/wp-json/wp/v2';
  var WP_API      = WP_API_BASE + '/projects?_embed&per_page=100&orderby=date&order=desc';
  var CUSTOM_API  = WP_API_BASE.replace('/wp/v2', '/sastudio/v2');

  var grid    = document.getElementById('sg-grid');
  var count   = document.getElementById('sg-count');
  var filters = document.getElementById('sg-filters');
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
    var q   = search.value.trim().toLowerCase();
    var cat = filters.querySelector('.sg-filter-btn.active');
    cat = cat ? cat.dataset.cat : '';
    var visible = 0;
    allCards.forEach(function (c) {
      var matchQ   = !q || c.title.indexOf(q) !== -1 || c.sub.indexOf(q) !== -1;
      var matchCat = !cat || c.sub.indexOf(cat.toLowerCase()) !== -1;
      var show = matchQ && matchCat;
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

      var slideUrls = [];
      if (hoverGif) slideUrls.push(hoverGif);
      if (featSrc) slideUrls.push(featSrc);
      galleryImgs.forEach(function (u) { if (u) slideUrls.push(u); });

      var html = '';
      html += '<div id="sg-modal-hero">';
      html += '<div id="sg-slides">' + slideUrls.map(function (url, i) {
        var inner = isVideoUrl(url)
          ? '<video src="' + esc(url) + '" muted playsinline preload="' + (i === 0 ? 'auto' : 'none') + '"></video>'
          : '<img src="' + esc(url) + '" alt="" loading="' + (i === 0 ? 'eager' : 'lazy') + '"/>';
        return '<div class="sg-slide' + (i === 0 ? ' active' : '') + '">' + inner + '</div>';
      }).join('') + '</div>';
      html += '<div id="sg-modal-hero-overlay">';
      html += '<div class="sg-meta">' + esc(meta) + '</div>';
      html += '<h2>' + esc(title) + '</h2>';
      if (slideUrls.length > 1) {
        html += '<div id="sg-slide-nav">' +
          '<button id="sg-slide-prev" aria-label="Anterior">&#8249;</button>' +
          '<span id="sg-slide-count"></span>' +
          '<button id="sg-slide-next" aria-label="Seguinte">&#8250;</button>' +
        '</div>';
      }
      html += '</div></div>';
      html += '<div id="sg-modal-content">';
      html += '<div id="sg-modal-main">';
      html += '<div id="sg-content">';
      html += '<h3 class="sg-section-heading">Descrição:</h3>';
      html += '<div class="sg-desc">' + (desc || '<p>Sem descrição para este projeto.</p>') + '</div>';
      html += '</div>';
      if (metaFields.length) {
        html += '<div id="sg-acf">';
        html += '<h3 class="sg-section-heading">Dados do projeto:</h3>';
        html += '<div class="sg-acf-table">' + metaFields.map(function (f) {
          return '<div class="sg-acf-row"><span class="sg-acf-label">' + esc(f.label) + ':</span><span class="sg-acf-value">' + esc(f.value) + '</span></div>';
        }).join('') + '</div>';
        html += '</div>';
      }
      html += '</div>'; /* fim #sg-modal-main */
      if (galleryImgs.length) {
        html += '<div id="sg-modal-gallery">' + galleryImgs.map(function (url) {
          return '<div class="sg-gallery-item"><div class="sg-gallery-item-img"><img src="' + esc(url) + '" loading="lazy" alt=""/></div></div>';
        }).join('') + '</div>';
      }
      html += '</div>';
      html += buildRelatedHtml(post);
      modalBody.innerHTML = html;
      initGalleryDrag();
      wireRelatedClicks();
      initSlideshow(slideUrls);
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

    return '<div id="sg-related">' +
      '<span id="sg-related-label">Confira outros projetos</span>' +
      '<div id="sg-related-grid">' + cards + '</div>' +
      '</div>';
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

  /* ── Slideshow do hero: vídeo (hover_gif) primeiro se existir, depois
     imagem principal e galeria — avanço automático, igual ao buildSlides(). ── */
  var SLIDE_DELAY = 5000;
  var slideInterval = null;
  var currentSlide = 0;

  function isVideoUrl(url) {
    return /\.(mp4|webm|mov|ogg)(\?|$)/i.test(url);
  }

  function stopAutoPlay() {
    if (slideInterval) { clearInterval(slideInterval); slideInterval = null; }
  }
  function startAutoPlay(total) {
    stopAutoPlay();
    if (total < 2) return;
    slideInterval = setInterval(function () {
      goToSlide((currentSlide + 1) % total, 1);
    }, SLIDE_DELAY);
  }

  function goToSlide(n, dir) {
    var slides = document.querySelectorAll('#sg-slides .sg-slide');
    if (!slides.length) return;
    var total = slides.length;
    var newIdx = ((n % total) + total) % total;
    if (newIdx === currentSlide) return;

    var oldVid = slides[currentSlide].querySelector('video');
    if (oldVid) { oldVid.pause(); oldVid.currentTime = 0; }
    slides[currentSlide].classList.remove('active');
    currentSlide = newIdx;
    slides[currentSlide].classList.add('active');
    var newVid = slides[currentSlide].querySelector('video');
    if (newVid) { newVid.currentTime = 0; newVid.play().catch(function () {}); }

    var count = document.getElementById('sg-slide-count');
    if (count) count.textContent = (currentSlide + 1) + ' / ' + total;
  }

  function initSlideshow(urls) {
    stopAutoPlay();
    currentSlide = 0;
    var total = urls.length;
    var count = document.getElementById('sg-slide-count');
    if (count) count.textContent = total > 1 ? ('1 / ' + total) : '';

    var prevBtn = document.getElementById('sg-slide-prev');
    var nextBtn = document.getElementById('sg-slide-next');
    if (prevBtn) prevBtn.addEventListener('click', function (e) { e.stopPropagation(); goToSlide(currentSlide - 1, -1); startAutoPlay(total); });
    if (nextBtn) nextBtn.addEventListener('click', function (e) { e.stopPropagation(); goToSlide(currentSlide + 1, 1); startAutoPlay(total); });

    var firstSlide = document.querySelector('#sg-slides .sg-slide.active');
    var firstVid = firstSlide ? firstSlide.querySelector('video') : null;
    if (firstVid) {
      firstVid.addEventListener('ended', function () {
        goToSlide(1, 1);
        startAutoPlay(total);
      });
      firstVid.play().catch(function () {});
    } else {
      startAutoPlay(total);
    }
  }

  /* ── Drag-to-scroll com inércia na galeria horizontal, igual ao #lb-gallery ──
     Estado partilhado + listeners de window ligados uma única vez, para não
     acumular listeners cada vez que se abre um projeto diferente. */
  var dragEl = null, dragDown = false, dragStartX = 0, dragStartScroll = 0;
  var dragVelX = 0, dragLastX = 0, dragLastT = 0, dragRaf = null;

  function cancelDragMomentum() { if (dragRaf) { cancelAnimationFrame(dragRaf); dragRaf = null; } }
  function dragMomentum() {
    if (!dragEl || Math.abs(dragVelX) < 0.4) return;
    dragEl.scrollLeft += dragVelX;
    dragVelX *= 0.91;
    dragRaf = requestAnimationFrame(dragMomentum);
  }
  window.addEventListener('mousemove', function (e) {
    if (!dragDown || !dragEl) return;
    var now = performance.now();
    var dt  = now - dragLastT || 1;
    dragVelX  = (dragLastX - e.pageX) / dt * 14;
    dragLastX = e.pageX; dragLastT = now;
    dragEl.scrollLeft = dragStartScroll + (dragStartX - e.pageX);
  });
  window.addEventListener('mouseup', function () {
    if (!dragDown || !dragEl) return;
    dragDown = false;
    dragEl.style.cursor = 'grab';
    dragMomentum();
  });

  function initGalleryDrag() {
    var el = document.getElementById('sg-modal-gallery');
    if (!el) return;

    el.addEventListener('mousedown', function (e) {
      cancelDragMomentum();
      dragEl = el;
      dragDown = true;
      dragStartX = e.pageX;
      dragStartScroll = el.scrollLeft;
      dragVelX = 0; dragLastX = e.pageX; dragLastT = performance.now();
      el.style.cursor = 'grabbing';
      e.preventDefault();
    });

    var tX = 0, tS = 0;
    el.addEventListener('touchstart', function (e) {
      cancelDragMomentum();
      tX = e.touches[0].pageX;
      tS = el.scrollLeft;
    }, { passive: true });
    el.addEventListener('touchmove', function (e) {
      el.scrollLeft = tS - (e.touches[0].pageX - tX);
    }, { passive: true });
  }

  function closeModal(opts) {
    opts = opts || {};
    stopAutoPlay();
    modal.classList.remove('sg-open');
    document.body.classList.remove('sg-modal-open');
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

      var cats = [];
      posts.forEach(function (post) {
        var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
        var cat = terms[0] ? terms[0].name : '';
        if (cat && cats.indexOf(cat) === -1) cats.push(cat);
      });

      var allBtn = document.createElement('button');
      allBtn.className = 'sg-filter-btn active';
      allBtn.textContent = 'Todos';
      allBtn.dataset.cat = '';
      filters.appendChild(allBtn);
      cats.forEach(function (cat) {
        var btn = document.createElement('button');
        btn.className = 'sg-filter-btn';
        btn.textContent = cat;
        btn.dataset.cat = cat;
        filters.appendChild(btn);
      });
      filters.addEventListener('click', function (e) {
        var btn = e.target.closest('.sg-filter-btn');
        if (!btn) return;
        filters.querySelectorAll('.sg-filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        filterCards();
      });
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

        var card = document.createElement('div');
        card.className = 'sg-card';
        card.innerHTML =
          '<div class="sg-card-img"' + aspectStyle + '>' +
            '<img src="' + esc(imgUrl) + '" alt="' + esc(title) + '" loading="lazy"/>' +
            '<div class="sg-card-overlay">' +
              '<div class="sg-card-title">' + esc(title) + '</div>' +
              '<div class="sg-card-sub">' + esc(sub) + '</div>' +
            '</div>' +
          '</div>';
        card.addEventListener('click', function () { openModal(post, imgUrl); });
        card.addEventListener('mouseenter', function () { prefetchProject(post.id); });

        grid.appendChild(card);
        allCards.push({ el: card, title: title.toLowerCase(), sub: sub.toLowerCase() });
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
