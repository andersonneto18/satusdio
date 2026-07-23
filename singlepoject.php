if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Shortcode [single_projetos] — página de projeto individual (link limpo,
 * ex: /projects/nome-do-projeto/). Mostra o mesmo conteúdo do modal do
 * [sastudio_gallery] (capa, dados do projeto, descrição, galeria e
 * "Confira outros projetos"), com a MESMA navegação horizontal por
 * painéis (Capa/Dados/Descrição → Galeria → Relacionados) do index.html
 * e do arquive.php — sem modal, sem fetch, dados vêm direto do ACF do
 * post atual (a página inteira É o "track").
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

    // html_entity_decode aqui evita duplicar a codificação (ex: "&" a aparecer
    // literalmente como "&#038;") quando o título já vem com entidades do WP.
    $title    = html_entity_decode( get_the_title($post_id), ENT_QUOTES, 'UTF-8' );
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

    // capa: vídeo (hover_gif) se existir, senão a imagem principal —
    // estática, sem slideshow (a galeria completa continua disponível
    // como painéis próprios mais à frente).
    $cover_url      = $hover_gif_url ?: $feat_url;
    $cover_is_video = $hover_gif_url && preg_match('/\.(mp4|webm|mov|ogg)(\?|$)/i', $hover_gif_url);

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
  /* Ativa Cross-Document View Transitions (Chrome/Edge) — ao clicar num
     card em "Confira outros projetos", a miniatura (view-transition-name
     "sp-hero-{ID}" no .sp-rel-card-img-wrap) morfa automaticamente para
     a capa da página seguinte (mesmo nome no #sp-cover-media), dando
     o efeito de zoom sem precisar de JS nem de SPA/modal. Navegadores
     sem suporte (Firefox/Safari) simplesmente navegam sem animação. */
  @view-transition { navigation: auto; }

  /* A página inteira é o "track" horizontal (como o lightbox aberto do
     index.html/arquive.php) — por isso o documento não tem scroll
     vertical próprio, o #sp-viewport é que ocupa o ecrã todo. */
  html, body { overflow: hidden !important; height: 100% !important; }

  /* Header do tema (The7/Elementor "Header Sastudio", classe
     top_panel_custom_header-sastudio — nada a ver com o #nav/#site-logo
     que só existem na home) e o menu mobile fullscreen — nesta página
     (link direto/partilhado de um projeto) ficam escondidos, tal como já
     acontece no lightbox da home quando um projeto está aberto. O
     #sp-close faz de "voltar". */
  header.top_panel_custom_header-sastudio,
  .menu_mobile_overlay,
  .menu_mobile,
  #nav, #site-logo { display: none !important; }

  /* Se este template estiver a ser renderizado dentro de um wrapper com
     scroll/transform próprio (ex: popup do Elementor), isso criaria um
     "containing block" que quebraria o position:fixed do #sp-viewport.
     Neutraliza-se aqui, forçando o wrapper a comportar-se como conteúdo
     normal do documento. */
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

  /* botão "X" de fechar (leva de volta a /projects/), igual ao
     #sg-modal-close/#lb-close — canto superior direito, sem texto. */
  #sp-close {
    position: fixed; top: 2.4rem; right: 1.5rem; z-index: 100010;
    display: flex; align-items: center; justify-content: center;
    width: 46px; height: 46px;
    border-radius: 50%; border: none;
    background: rgba(255,255,255,0.92);
    box-shadow: 0 2px 14px rgba(21,21,18,0.18);
    font-size: 1.5rem; line-height: 1; cursor: pointer;
    color: #151512; text-decoration: none;
  }
  @media (max-width: 700px) {
    #sp-close { top: 0.9rem; right: 0.9rem; width: 42px; height: 42px; }
  }

  /* ── Navegação horizontal entre painéis (Capa/Dados/Descrição →
     Galeria → Relacionados), igual ao index.html/arquive.php — #sp-track
     é deslocado via transform:translateX pelo wheel handler; cada
     .sp-panel mantém o seu próprio scroll vertical (texto longo) até
     chegar ao topo/fundo, altura em que o wheel passa a mudar de painel. */
  #sp-viewport {
    position: fixed; inset: 0; z-index: 1;
    background: #fff; overflow: hidden;
  }
  #sp-track { display: flex; height: 100%; will-change: transform; }
  .sp-panel { flex: 0 0 100vw; width: 100vw; height: 100%; background: #fff; }
  .sp-panel-scrollable {
    overflow-y: auto; overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    /* sem isto, o rubber-band/bounce nativo do scroll (trackpad/mouse
       com inércia) fazia estes painéis "saltar" um pouco para baixo e
       voltar sozinhos, mesmo sem conteúdo a mais para rolar */
    overscroll-behavior: contain;
  }
  .sp-panel-scrollable::-webkit-scrollbar { display: none; }

  /* ── BARRA DE PROGRESSO VERTICAL ──
     os painéis navegam na horizontal (translateX), por isso não existe
     scrollbar nativa a indicar o progresso; esta barra fininha à direita
     do ecrã sobe/desce (top-to-bottom) consoante o avanço entre painéis,
     atualizada em JS a par do #sp-track (ver spTick()). */
  #sp-scrollbar {
    position: fixed; top: 0; right: 4px; bottom: 0;
    width: 3px; z-index: 100010;
    pointer-events: none;
  }
  #sp-scrollbar-thumb {
    position: absolute; left: 0; width: 100%;
    background: rgba(21,21,18,0.28);
    border-radius: 999px;
  }

  /* ── Painel principal (Dados | Capa) ──
     título/categoria no topo (#sp-main-top), depois duas colunas lado
     a lado: Dados do projeto e a capa (imagem/vídeo estático, sem
     slideshow). A Descrição já não vive aqui — é o painel seguinte, a
     largura quase total da página (#sp-panel-desc), sem coluna ao lado. */
  #sp-panel-main { position: relative; padding: 4.5rem 5vw 6rem; }
  /* .sp-title-block (não só #sp-main-top): a mesma classe é reutilizada,
     invisível, dentro do painel da Descrição (.sp-desc-spacer) — isto
     garante que o título "Descrição:" fica exatamente à mesma altura
     que "Dados do projeto:" por construção em CSS (mesma marcação =
     mesma altura), sem depender de medir posições em JS. */
  .sp-title-block { max-width: 1800px; margin: 0 auto 3rem; }
  .sp-title-block .sp-meta {
    font-size: 0.68rem; letter-spacing: 0.3em; text-transform: uppercase;
    color: #7a5c3a; margin-bottom: 0.8rem;
  }
  .sp-title-block h1 {
    font-family: 'Inter', sans-serif; font-weight: 300;
    font-size: clamp(1.8rem, 3.2vw, 2.8rem); line-height: 1.05;
    color: #151512; margin: 0; letter-spacing: 0.01em;
  }
  .sp-desc-spacer { visibility: hidden; pointer-events: none; }
  #sp-main-cols {
    display: flex; align-items: start; gap: 4vw;
    max-width: 1800px; margin: 0 auto;
  }
  .sp-col { flex: 1 1 0; min-width: 0; }
  #sp-acf { flex: 0 1 360px; min-width: 240px; }
  /* sticky: a capa fica fixa no ecrã enquanto o utilizador rola para
     ler a lista de Dados do projeto (quando é mais alta que a capa),
     em vez de subir/descer junto com o texto. */
  #sp-cover-col { flex: 1 1 0; min-width: 0; position: sticky; top: 0; }
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
  #sp-cover-media { width: 65%; height: 55vh; margin: 0 auto; }
  #sp-cover-media img,
  #sp-cover-media video {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
  }

  /* ── PAINEL DA DESCRIÇÃO — próprio painel horizontal, texto a
     largura quase total da página (sem coluna ao lado); o utilizador
     roda o rato (navegação horizontal já existente) para chegar a
     este painel e depois à Galeria. ── */
  /* align-items:flex-start (não center) — o padding-top igual ao do
     painel principal (4.5rem) + o .sp-desc-spacer invisível (ver acima)
     fazem o título "Descrição:" ficar à mesma altura do "Dados do
     projeto:" no painel anterior. */
  #sp-panel-desc { display: flex; align-items: flex-start; }
  #sp-content.sp-desc-col {
    width: 100%; max-width: 1300px; margin: 0 auto;
    padding: 4.5rem 8vw 5rem;
  }
  .sp-section-heading {
    font-family: 'Inter', sans-serif !important; font-size: 1rem !important;
    font-weight: 300 !important; white-space: nowrap !important;
    color: #151512; margin: 0 0 2rem; line-height: 1.1;
  }
  .sp-desc { font-size: 1rem; line-height: 1.85; color: rgba(21,21,18,0.82); text-align: justify; }
  .sp-acf-table { width: 100%; }
  .sp-acf-row {
    display: grid; grid-template-columns: 140px 1fr; gap: 1rem;
    padding: 0.85rem 0; border-bottom: 1px solid rgba(21,21,18,0.09);
    align-items: start; font-size: 0.85rem;
  }
  .sp-acf-row:first-child { border-top: 1px solid rgba(21,21,18,0.09); }
  .sp-acf-label { font-weight: 400; color: #151512; }
  .sp-acf-value { color: rgba(21,21,18,0.75); line-height: 1.55; }
  @media (max-width: 900px) {
    #sp-main-cols { flex-direction: column; align-items: stretch; gap: 2.5rem; }
  #sp-acf { flex-basis: auto; min-width: 0; }
    #sp-cover-col { position: static; }
    #sp-content.sp-desc-col { padding: 2.5rem 5vw 3rem; }
    .sp-desc-spacer { display: none; }
  }

  /* ── Galeria — cada painel mostra 2 fotos lado a lado, quase de
     ponta a ponta do ecrã, com um espaço pequeno entre elas (como na
     referência) — em vez de 1 foto pequena centrada com muito vazio
     à volta. Se sobrar 1 foto sozinha (número ímpar), ocupa o painel
     todo (igual ao .sg-photo-panel/.lb-photo-panel). ── */
  .sp-photo-panel {
    display: flex; align-items: stretch; justify-content: center;
    gap: 20px;
    background: #fff;
    padding: 2vh 2vw;
  }
  .sp-photo-item { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; }
  .sp-photo-item img {
    /* object-fit:cover preenche a caixa (pode recortar conforme a
       proporção original) — necessário para as duas fotos ficarem
       com a mesma altura lado a lado, independentemente da orientação. */
    flex: 1 1 auto; min-height: 0; width: 100%;
    object-fit: cover; display: block;
    pointer-events: none; -webkit-user-drag: none;
  }
  @media (max-width: 700px) { .sp-photo-panel { flex-direction: column; gap: 12px; padding: 1.5vh 3vw; } }

  /* ── Outros projetos (relacionados), igual ao #sg-related/#lb-related ── */
  #sp-panel-related { display: flex; align-items: center; }
  #sp-related { padding: 3.5rem 8vw 5rem; width: 100%; }
  #sp-related-label {
    font-size: clamp(1.4rem, 2.4vw, 2rem);
    font-weight: 300; color: #151512;
    margin-bottom: 2rem; display: block; line-height: 1;
  }
  #sp-related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .sp-rel-card { cursor: pointer; text-decoration: none; color: inherit; display: block; }
  .sp-rel-card-img-wrap { overflow: hidden; aspect-ratio: 4/3; border-radius: 16px; }
  .sp-rel-card-img-wrap img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform 0.55s ease;
  }
  .sp-rel-card:hover .sp-rel-card-img-wrap img { transform: scale(1.05); }
  .sp-rel-card-title { font-size: 1rem; margin-top: 0.75rem; color: #151512; }
  .sp-rel-card-sub {
    font-size: 0.65rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(21,21,18,0.4); margin-top: 0.25rem;
  }
  @media (max-width: 700px) {
    #sp-related-grid { grid-template-columns: 1fr; gap: 16px; }
    #sp-related { padding: 2.5rem 5vw 3.5rem; }
  }
</style>

<div id="sp-root">
  <a id="sp-close" href="<?php echo esc_url( home_url('/projects/') ); ?>" aria-label="Fechar">&times;</a>

  <div id="sp-scrollbar"><div id="sp-scrollbar-thumb"></div></div>

  <div id="sp-viewport">
    <div id="sp-track">
      <section id="sp-panel-main" class="sp-panel sp-panel-scrollable">
        <div id="sp-main-top" class="sp-title-block">
          <div class="sp-meta"><?php echo esc_html($meta_line); ?></div>
          <h1><?php echo esc_html($title); ?></h1>
        </div>
        <div id="sp-main-cols">
          <?php if (!empty($meta_fields)): ?>
          <div id="sp-acf" class="sp-col">
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
          <div id="sp-cover-col" class="sp-col">
            <div id="sp-cover-media"<?php echo $cover_is_video ? ' data-video-src="' . esc_url($cover_url) . '"' : ''; ?> style="view-transition-name: sp-hero-<?php echo (int) $post_id; ?>">
              <?php if ($cover_url && !$cover_is_video): ?>
                <img src="<?php echo esc_url($cover_url); ?>" alt=""/>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <section id="sp-panel-desc" class="sp-panel sp-panel-scrollable">
        <div id="sp-content" class="sp-desc-col">
          <div class="sp-title-block sp-desc-spacer" aria-hidden="true">
            <div class="sp-meta"><?php echo esc_html($meta_line); ?></div>
            <h1><?php echo esc_html($title); ?></h1>
          </div>
          <h3 class="sp-section-heading">Descrição:</h3>
          <div class="sp-desc"><?php echo $desc ? $desc : '<p>Sem descrição para este projeto.</p>'; ?></div>
        </div>
      </section>

      <?php foreach (array_chunk($gallery_urls, 2) as $pair): ?>
      <section class="sp-panel sp-panel-scrollable sp-photo-panel">
        <?php foreach ($pair as $url): ?>
        <div class="sp-photo-item"><img src="<?php echo esc_url($url); ?>" loading="lazy" alt=""/></div>
        <?php endforeach; ?>
      </section>
      <?php endforeach; ?>

      <?php if (!empty($related)): ?>
      <section id="sp-panel-related" class="sp-panel sp-panel-scrollable">
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
              $r_sub   = $r_cat ? ($r_cat . ' · ' . $r_year) : $r_year;
              $r_title = html_entity_decode( get_the_title($rp->ID), ENT_QUOTES, 'UTF-8' );
            ?>
              <a class="sp-rel-card" href="<?php echo esc_url(get_permalink($rp->ID)); ?>">
                <div class="sp-rel-card-img-wrap" style="view-transition-name: sp-hero-<?php echo (int) $rp->ID; ?>"><img src="<?php echo esc_url($r_img); ?>" alt="<?php echo esc_attr($r_title); ?>" loading="lazy"/></div>
                <div class="sp-rel-card-title"><?php echo esc_html($r_title); ?></div>
                <div class="sp-rel-card-sub"><?php echo esc_html($r_sub); ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  /* Cria a tag <video> da capa só depois de a página estar carregada,
     para o tema (The7/MediaElement.js) não a apanhar e a embrulhar no
     player nativo dele — se não existir nenhuma <video> quando esse
     script corre, ele não tem nada para "roubar". */
  window.addEventListener('load', function () {
    var cover = document.getElementById('sp-cover-media');
    if (cover && cover.dataset.videoSrc) {
      var vid = document.createElement('video');
      vid.src = cover.dataset.videoSrc;
      vid.muted = true; vid.loop = true; vid.autoplay = true; vid.playsInline = true;
      cover.appendChild(vid);
      vid.play().catch(function () {});
    }
  });

  /* ── Navegação horizontal entre painéis (Capa/Dados/Descrição →
     Galeria → Relacionados), igual ao index.html/arquive.php. Aqui não
     há abrir/fechar de modal — a página inteira é sempre o "track". ── */
  var spTx = 0, spTTx = 0;
  var spScrollbarThumb = document.getElementById('sp-scrollbar-thumb');

  function spBounds() {
    var track = document.getElementById('sp-track');
    var n = track ? Math.max(track.querySelectorAll('.sp-panel').length, 1) : 1;
    return { min: -(n - 1) * window.innerWidth, max: 0 };
  }

  /* barra vertical de progresso — desce à medida que se avança pelos
     painéis horizontais, como substituta visual da scrollbar nativa. */
  function updateSpScrollbar(b) {
    if (!spScrollbarThumb) return;
    var track = document.getElementById('sp-track');
    var n = track ? Math.max(track.querySelectorAll('.sp-panel').length, 1) : 1;
    var progress = b.min !== 0 ? Math.min(1, Math.max(0, spTx / b.min)) : 0;
    var thumbPct = 100 / n;
    spScrollbarThumb.style.height = thumbPct + '%';
    spScrollbarThumb.style.top = (progress * (100 - thumbPct)) + '%';
  }

  (function spTick() {
    var track = document.getElementById('sp-track');
    if (track) {
      var b = spBounds();
      spTTx = Math.max(b.min, Math.min(b.max, spTTx));
      spTx += (spTTx - spTx) * 0.14;
      track.style.transform = 'translateX(' + spTx + 'px)';
      updateSpScrollbar(b);
    }
    requestAnimationFrame(spTick);
  })();

  window.addEventListener('wheel', function (e) {
    var track = document.getElementById('sp-track');
    if (!track) return;

    var scrollable = e.target.closest('.sp-panel-scrollable');
    if (scrollable && scrollable.scrollHeight - scrollable.clientHeight > 24) {
      var atTop     = scrollable.scrollTop <= 0;
      var atBottom  = scrollable.scrollTop + scrollable.clientHeight >= scrollable.scrollHeight - 1;
      var goingDown = e.deltaY > 0;
      if ((goingDown && !atBottom) || (!goingDown && !atTop)) return;
    }

    e.preventDefault();
    var delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    spTTx -= delta * 1.3;
  }, { passive: false });
})();
</script>
    <?php
    return ob_get_clean();
});
