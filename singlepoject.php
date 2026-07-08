if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Shortcode [single_projetos] — página de projeto individual (link limpo,
 * ex: /projects/nome-do-projeto/). Mostra o mesmo conteúdo que o modal do
 * [sastudio_gallery] (hero com vídeo/imagens, descrição, dados do projeto,
 * galeria horizontal e "Confira outros projetos"), mas como página normal
 * (sem modal, sem fetch — os dados vêm direto do ACF do post atual).
 * Cola [single_projetos] no template Elementor "Single Project".
 */
add_shortcode('single_projetos', function () {
    $post_id = get_queried_object_id();
    if ( ! $post_id ) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    if ( ! $post_id ) return '';

    // hover_gif — converte ID para URL
    $hover_gif_raw = get_field('hover_gif', $post_id);
    $hover_gif_url = '';
    if ( is_numeric($hover_gif_raw) ) {
        $hover_gif_url = wp_get_attachment_url($hover_gif_raw);
    } elseif ( is_array($hover_gif_raw) && !empty($hover_gif_raw['url']) ) {
        $hover_gif_url = $hover_gif_raw['url'];
    } elseif ( is_string($hover_gif_raw) ) {
        $hover_gif_url = $hover_gif_raw;
    }

    // project_gallery — converte string "id,id,id" ou array para lista de URLs
    $gallery_raw  = get_field('project_gallery', $post_id);
    $gallery_urls = [];
    if (is_array($gallery_raw)) {
        foreach ($gallery_raw as $img) {
            if (is_array($img) && !empty($img['url'])) {
                $gallery_urls[] = $img['url'];
            } elseif (is_numeric($img)) {
                $url = wp_get_attachment_url($img);
                if ($url) $gallery_urls[] = $url;
            } elseif (is_string($img) && $img) {
                $gallery_urls[] = $img;
            }
        }
    } elseif (is_string($gallery_raw) && $gallery_raw !== '') {
        $ids = array_filter(array_map('trim', explode(',', $gallery_raw)));
        foreach ($ids as $img_id) {
            if (is_numeric($img_id)) {
                $url = wp_get_attachment_url((int) $img_id);
                if ($url) $gallery_urls[] = $url;
            }
        }
    }

    $title    = get_the_title($post_id);
    $year     = get_the_date('Y', $post_id);
    $feat_url = get_the_post_thumbnail_url($post_id, 'full');

    // categoria: primeira taxonomia associada ao post type "projects" que tenha termos neste post
    $cat_name = '';
    foreach ( get_object_taxonomies('projects') as $tax ) {
        $terms = get_the_terms($post_id, $tax);
        if ( $terms && ! is_wp_error($terms) && ! empty($terms) ) {
            $cat_name = $terms[0]->name;
            break;
        }
    }
    $meta_line = $cat_name ? ($cat_name . ' · ' . $year) : $year;

    $desc = trim( (string) get_field('project_descriprion', $post_id) );

    $meta_fields = array_filter([
        ['label' => 'Programa',    'value' => get_field('project_program',  $post_id)],
        ['label' => 'Área',        'value' => get_field('project_area',     $post_id)],
        ['label' => 'Localização', 'value' => get_field('project_location', $post_id)],
        ['label' => 'Cliente',     'value' => get_field('cliente',          $post_id)],
        ['label' => 'Equipa',      'value' => get_field('project_team',     $post_id)],
        ['label' => 'Status',      'value' => get_field('project_status',   $post_id)],
        ['label' => 'Ano',         'value' => $year],
    ], function ($f) { return $f['value'] && trim((string) $f['value']) !== ''; });

    // slides: vídeo (hover_gif) primeiro se existir, depois imagem principal, depois galeria
    $slide_urls = [];
    if ($hover_gif_url) $slide_urls[] = $hover_gif_url;
    if ($feat_url)      $slide_urls[] = $feat_url;
    foreach ($gallery_urls as $u) { if ($u) $slide_urls[] = $u; }

    // relacionados: 3 projetos aleatórios, excluindo o atual
    $related = get_posts([
        'post_type'      => 'projects',
        'posts_per_page' => 3,
        'post__not_in'   => [$post_id],
        'orderby'        => 'rand',
        'post_status'    => 'publish',
    ]);

    ob_start();
    ?>
<style>
  @view-transition { navigation: auto; }

  /* O CSS partilhado ("Galeria Portfolio", ativo em todo o site) põe
     overflow:hidden no body para a home (app fullscreen sem scroll nativo).
     Nesta página isso bloqueia o scroll normal — contraria-se aqui. */
  html, body { overflow: auto !important; height: auto !important; }

  /* Se este template estiver a ser renderizado dentro de um wrapper com
     scroll próprio (ex: popup do Elementor), isso cria uma segunda
     scrollbar e bloqueia o scroll normal da página. Neutraliza-se aqui,
     forçando o wrapper a comportar-se como conteúdo normal do documento. */
  .elementor-popup-modal,
  .dialog-widget-content,
  .dialog-message,
  .elementor-popup-modal__content,
  div[data-elementor-type="popup"] {
    position: static !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    width: auto !important;
    transform: none !important;
  }
  .dialog-lightbox-widget,
  .elementor-popup-modal .dialog-lightbox-widget {
    background: none !important;
    position: static !important;
  }

  #sp-root, #sp-root * { box-sizing: border-box; }
  #sp-root { font-family: 'Inter', sans-serif; color: #151512; }

  #sp-back {
    position: fixed; top: 1.5rem; left: 1.5rem; z-index: 100010;
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 1.1rem; border-radius: 999px; border: none;
    background: rgba(255,255,255,0.92); box-shadow: 0 2px 14px rgba(21,21,18,0.18);
    font-size: 0.75rem; letter-spacing: 0.04em; color: #151512; text-decoration: none;
  }

  #sp-hero {
    position: relative; width: 100%; height: 100vh; overflow: hidden; background: #000;
  }
  #sp-slides { position: absolute; inset: 0; }
  .sp-slide { position: absolute; inset: 0; opacity: 0; }
  .sp-slide.active { opacity: 1; z-index: 1; }
  .sp-slide img, .sp-slide video { width: 100%; height: 100%; object-fit: cover; display: block; }
  #sp-hero-overlay {
    position: absolute; inset: 0; z-index: 2;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 2.4rem clamp(1.2rem, 6vw, 5vw);
    background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 55%);
    pointer-events: none;
  }
  #sp-hero-overlay > * { pointer-events: all; }
  #sp-hero .sp-meta {
    font-size: 0.7rem; letter-spacing: 0.3em; text-transform: uppercase;
    color: rgba(255,255,255,0.75); margin-bottom: 0.8rem;
  }
  #sp-hero h1 {
    font-family: 'Cormorant Garamond', serif; font-weight: 300;
    font-size: clamp(2.2rem, 5.5vw, 4.5rem); line-height: 1.05;
    color: #fff; margin: 0 0 1.5rem; letter-spacing: 0.01em;
  }
  #sp-slide-nav { display: flex; align-items: center; gap: 0.9rem; align-self: flex-end; margin-left: auto; }
  #sp-slide-nav button {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.32);
    color: #fff; font-size: 1.1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
  }
  #sp-slide-nav button:hover { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.75); }
  #sp-slide-count { font-size: 0.48rem; letter-spacing: 0.14em; color: rgba(255,255,255,0.6); min-width: 2.5rem; text-align: center; }

  #sp-content { padding: 4.5rem clamp(1.2rem, 6vw, 5vw) 2rem; max-width: 1440px; margin: 0 auto; }
  #sp-main { display: grid; grid-template-columns: 1fr 1fr; align-items: start; gap: 5vw; max-width: 1280px; margin: 0 auto; }
  .sp-section-heading { font-size: clamp(1.4rem, 2.4vw, 2rem); font-weight: 300; color: #151512; margin: 0 0 2rem; line-height: 1.1; }
  #sp-content .sp-desc { font-size: 1rem; line-height: 1.85; color: rgba(21,21,18,0.82); text-align: justify; }
  .sp-acf-table { width: 100%; }
  .sp-acf-row {
    display: grid; grid-template-columns: 140px 1fr; gap: 1rem;
    padding: 0.85rem 0; border-bottom: 1px solid rgba(21,21,18,0.09);
    align-items: start; font-size: 0.85rem;
  }
  .sp-acf-row:first-child { border-top: 1px solid rgba(21,21,18,0.09); }
  .sp-acf-label { font-weight: 400; color: #151512; }
  .sp-acf-value { color: rgba(21,21,18,0.75); line-height: 1.55; }
  @media (max-width: 900px) { #sp-main { grid-template-columns: 1fr; gap: 2.5rem; } }

  #sp-gallery {
    display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch;
    gap: 6px; margin: 3rem auto 1rem; max-width: 1280px;
    padding: 0 clamp(1.2rem, 6vw, 5vw) 0.6rem; cursor: grab;
    scrollbar-width: thin; scrollbar-color: rgba(21,21,18,0.25) rgba(21,21,18,0.06);
    user-select: none; -webkit-user-select: none;
  }
  #sp-gallery::-webkit-scrollbar { height: 3px; }
  #sp-gallery::-webkit-scrollbar-track { background: rgba(21,21,18,0.06); }
  #sp-gallery::-webkit-scrollbar-thumb { background: rgba(21,21,18,0.25); border-radius: 999px; }
  .sp-gallery-item { flex: 0 0 auto; width: 52vw; }
  .sp-gallery-item-img { overflow: hidden; aspect-ratio: 16/9; border-radius: 4px; }
  .sp-gallery-item-img img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; -webkit-user-drag: none; }
  @media (max-width: 700px) { .sp-gallery-item { width: 82vw; } }

  #sp-related { background: #f7f6f4; padding: 3.5rem 8vw 5rem; border-top: 1px solid rgba(21,21,18,0.08); }
  #sp-related-label { font-size: clamp(1.4rem, 2.4vw, 2rem); font-weight: 300; color: #151512; margin-bottom: 2rem; display: block; line-height: 1; }
  #sp-related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .sp-rel-card { cursor: pointer; text-decoration: none; color: inherit; display: block; }
  .sp-rel-card-img-wrap { overflow: hidden; aspect-ratio: 4/3; }
  .sp-rel-card-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.55s ease; }
  .sp-rel-card:hover .sp-rel-card-img-wrap img { transform: scale(1.05); }
  .sp-rel-card-title { font-size: 1rem; margin-top: 0.75rem; color: #151512; }
  .sp-rel-card-sub { font-size: 0.65rem; letter-spacing: 0.16em; text-transform: uppercase; color: rgba(21,21,18,0.4); margin-top: 0.25rem; }
  @media (max-width: 700px) {
    #sp-related-grid { grid-template-columns: 1fr; gap: 16px; }
    #sp-related { padding: 2.5rem 5vw 3.5rem; }
  }
</style>

<div id="sp-root">
  <a id="sp-back" href="<?php echo esc_url( home_url('/projects/') ); ?>">&#8592; Voltar aos projetos</a>

  <div id="sp-hero">
    <div id="sp-slides">
      <?php foreach ($slide_urls as $i => $url):
        $is_video = preg_match('/\.(mp4|webm|mov|ogg)(\?|$)/i', $url);
      ?>
        <div class="sp-slide<?php echo $i === 0 ? ' active' : ''; ?>"<?php echo $is_video ? ' data-video-src="' . esc_url($url) . '"' : ''; ?>>
          <?php if (!$is_video): ?>
            <img src="<?php echo esc_url($url); ?>" alt="" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"/>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div id="sp-hero-overlay">
      <div class="sp-meta"><?php echo esc_html($meta_line); ?></div>
      <h1><?php echo esc_html($title); ?></h1>
      <?php if (count($slide_urls) > 1): ?>
        <div id="sp-slide-nav">
          <button id="sp-slide-prev" aria-label="Anterior">&#8249;</button>
          <span id="sp-slide-count"></span>
          <button id="sp-slide-next" aria-label="Seguinte">&#8250;</button>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div id="sp-content">
    <div id="sp-main">
      <div id="sp-desc-wrap">
        <h3 class="sp-section-heading">Descrição:</h3>
        <div class="sp-desc"><?php echo $desc ? $desc : '<p>Sem descrição para este projeto.</p>'; ?></div>
      </div>
      <?php if (!empty($meta_fields)): ?>
      <div id="sp-acf">
        <h3 class="sp-section-heading">Dados do projeto:</h3>
        <div class="sp-acf-table">
          <?php foreach ($meta_fields as $f): ?>
            <div class="sp-acf-row">
              <span class="sp-acf-label"><?php echo esc_html($f['label']); ?>:</span>
              <span class="sp-acf-value"><?php echo esc_html($f['value']); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($gallery_urls)): ?>
    <div id="sp-gallery">
      <?php foreach ($gallery_urls as $url): ?>
        <div class="sp-gallery-item">
          <div class="sp-gallery-item-img"><img src="<?php echo esc_url($url); ?>" loading="lazy" alt=""/></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($related)): ?>
  <div id="sp-related">
    <span id="sp-related-label">Confira outros projetos</span>
    <div id="sp-related-grid">
      <?php foreach ($related as $rp):
        $r_img = get_the_post_thumbnail_url($rp->ID, 'large');
        if (!$r_img) continue;
        $r_year = get_the_date('Y', $rp->ID);
        $r_cat  = '';
        foreach ( get_object_taxonomies('projects') as $tax ) {
          $r_terms = get_the_terms($rp->ID, $tax);
          if ( $r_terms && ! is_wp_error($r_terms) && ! empty($r_terms) ) { $r_cat = $r_terms[0]->name; break; }
        }
        $r_sub = $r_cat ? ($r_cat . ' · ' . $r_year) : $r_year;
      ?>
        <a class="sp-rel-card" href="<?php echo esc_url(get_permalink($rp->ID)); ?>">
          <div class="sp-rel-card-img-wrap"><img src="<?php echo esc_url($r_img); ?>" alt="<?php echo esc_attr(get_the_title($rp->ID)); ?>" loading="lazy"/></div>
          <div class="sp-rel-card-title"><?php echo esc_html(get_the_title($rp->ID)); ?></div>
          <div class="sp-rel-card-sub"><?php echo esc_html($r_sub); ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  /* Cria as tags <video> só depois de a página estar carregada, para o
     tema (The7/MediaElement.js) não as apanhar e as embrulhar no player
     nativo dele — se não existir nenhuma <video> quando esse script corre,
     ele não tem nada para "roubar". */
  window.addEventListener('load', function () {
    document.querySelectorAll('#sp-slides .sp-slide[data-video-src]').forEach(function (slide) {
      var vid = document.createElement('video');
      vid.src = slide.dataset.videoSrc;
      vid.muted = true;
      vid.playsInline = true;
      vid.preload = slide.classList.contains('active') ? 'auto' : 'none';
      slide.appendChild(vid);
    });
    initSlideshow();
  });

  var SLIDE_DELAY = 5000;
  var slideInterval = null;
  var currentSlide = 0;

  function initSlideshow() {

  function stopAutoPlay() { if (slideInterval) { clearInterval(slideInterval); slideInterval = null; } }
  function startAutoPlay(total) {
    stopAutoPlay();
    if (total < 2) return;
    slideInterval = setInterval(function () { goToSlide((currentSlide + 1) % total); }, SLIDE_DELAY);
  }
  function goToSlide(n) {
    var slides = document.querySelectorAll('#sp-slides .sp-slide');
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

    var countEl = document.getElementById('sp-slide-count');
    if (countEl) countEl.textContent = (currentSlide + 1) + ' / ' + total;
  }

  var slides = document.querySelectorAll('#sp-slides .sp-slide');
  var total = slides.length;
  var countEl = document.getElementById('sp-slide-count');
  if (countEl) countEl.textContent = total > 1 ? ('1 / ' + total) : '';

  var prevBtn = document.getElementById('sp-slide-prev');
  var nextBtn = document.getElementById('sp-slide-next');
  if (prevBtn) prevBtn.addEventListener('click', function (e) { e.stopPropagation(); goToSlide(currentSlide - 1); startAutoPlay(total); });
  if (nextBtn) nextBtn.addEventListener('click', function (e) { e.stopPropagation(); goToSlide(currentSlide + 1); startAutoPlay(total); });

  var firstSlide = slides[0];
  var firstVid = firstSlide ? firstSlide.querySelector('video') : null;
  if (firstVid) {
    firstVid.addEventListener('ended', function () { goToSlide(1); startAutoPlay(total); });
    firstVid.play().catch(function () {});
  } else {
    startAutoPlay(total);
  }

  /* ── Drag-to-scroll com inércia na galeria horizontal ── */
  var el = document.getElementById('sp-gallery');
  if (el) {
    var down = false, startX = 0, startScroll = 0, velX = 0, lastX = 0, lastT = 0, raf = null;

    function cancelMomentum() { if (raf) { cancelAnimationFrame(raf); raf = null; } }
    function momentum() {
      if (Math.abs(velX) < 0.4) return;
      el.scrollLeft += velX;
      velX *= 0.91;
      raf = requestAnimationFrame(momentum);
    }
    el.addEventListener('mousedown', function (e) {
      cancelMomentum();
      down = true; startX = e.pageX; startScroll = el.scrollLeft;
      velX = 0; lastX = e.pageX; lastT = performance.now();
      el.style.cursor = 'grabbing';
      e.preventDefault();
    });
    window.addEventListener('mousemove', function (e) {
      if (!down) return;
      var now = performance.now();
      var dt = now - lastT || 1;
      velX = (lastX - e.pageX) / dt * 14;
      lastX = e.pageX; lastT = now;
      el.scrollLeft = startScroll + (startX - e.pageX);
    });
    window.addEventListener('mouseup', function () {
      if (!down) return;
      down = false;
      el.style.cursor = 'grab';
      momentum();
    });
    var tX = 0, tS = 0;
    el.addEventListener('touchstart', function (e) { cancelMomentum(); tX = e.touches[0].pageX; tS = el.scrollLeft; }, { passive: true });
    el.addEventListener('touchmove', function (e) { el.scrollLeft = tS - (e.touches[0].pageX - tX); }, { passive: true });
  }
  } /* fim initSlideshow */
})();
</script>
    <?php
    return ob_get_clean();
});
