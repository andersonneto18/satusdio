if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcode [single_projetos] — já está colado num widget Shortcode no
 * template "Single" do Elementor (Theme Builder) para o CPT "projects".
 * Por isso /projects/{slug}/ é sempre uma página real do WordPress:
 * URL limpo, funciona em refresh e em link partilhado, sem truques.
 */

function sastudio_first_term_name( $id ) {
    foreach ( get_object_taxonomies( 'projects' ) as $tax ) {
        $terms = get_the_terms( $id, $tax );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            return $terms[0]->name;
        }
    }
    return '';
}

function sastudio_resolve_gallery_urls( $raw ) {
    $urls = [];
    if ( is_array( $raw ) ) {
        foreach ( $raw as $img ) {
            if ( is_array( $img ) && ! empty( $img['url'] ) ) {
                $urls[] = $img['url'];
            } elseif ( is_numeric( $img ) ) {
                $url = wp_get_attachment_url( $img );
                if ( $url ) $urls[] = $url;
            }
        }
    }
    return $urls;
}

add_shortcode('single_projetos', function ( $atts ) {
    $id = get_queried_object_id();
    if ( ! $id ) {
        global $post;
        $id = $post->ID ?? 0;
    }
    if ( ! $id ) return '';

    $title = get_the_title( $id );
    $year  = get_the_date( 'Y', $id );
    $cat   = sastudio_first_term_name( $id );
    $meta  = $cat ? esc_html( $cat . ' · ' . $year ) : esc_html( $year );

    $hero_video = get_field( 'project_video', $id );
    $hero_img   = get_the_post_thumbnail_url( $id, 'full' );

    $hero_media = $hero_video
        ? '<video autoplay muted loop playsinline src="' . esc_url( $hero_video ) . '"></video>'
        : '<img src="' . esc_url( $hero_img ) . '" alt="' . esc_attr( $title ) . '" />';

    $fields = [
        'Cliente'      => get_field( 'cliente',          $id ),
        'Localização'  => get_field( 'project_location', $id ),
        'Área'         => get_field( 'project_area',     $id ),
        'Estado'       => get_field( 'project_status',   $id ),
        'Equipa'       => get_field( 'project_team',      $id ),
        'Programa'     => get_field( 'project_program',   $id ),
    ];
    $rows_html = '';
    foreach ( $fields as $label => $value ) {
        if ( empty( $value ) ) continue;
        $rows_html .= '<div class="sp-row"><span class="sp-row-label">' . esc_html( $label ) . '</span>'
            . '<span class="sp-row-value">' . esc_html( $value ) . '</span></div>';
    }

    $description   = get_field( 'project_descriprion', $id );
    $description_html = $description ? '<p>' . nl2br( esc_html( $description ) ) . '</p>' : '';

    $gallery_urls  = sastudio_resolve_gallery_urls( get_field( 'project_gallery', $id ) );
    $gallery_html  = '';
    foreach ( $gallery_urls as $url ) {
        $gallery_html .= '<div class="sp-gallery-item"><img src="' . esc_url( $url ) . '" alt="" loading="lazy" /></div>';
    }

    $related_html = '';
    $related = get_posts([
        'post_type'      => 'projects',
        'posts_per_page' => 3,
        'post__not_in'   => [ $id ],
        'orderby'        => 'rand',
    ]);
    foreach ( $related as $rp ) {
        $rp_img   = get_the_post_thumbnail_url( $rp->ID, 'large' );
        if ( ! $rp_img ) continue;
        $rp_year  = get_the_date( 'Y', $rp->ID );
        $rp_cat   = sastudio_first_term_name( $rp->ID );
        $rp_meta  = $rp_cat ? esc_html( $rp_cat . ' · ' . $rp_year ) : esc_html( $rp_year );
        $related_html .= '<a class="sp-rel-card" href="' . esc_url( get_permalink( $rp->ID ) ) . '">'
            . '<div class="sp-rel-card-img"><img src="' . esc_url( $rp_img ) . '" alt="' . esc_attr( get_the_title( $rp->ID ) ) . '" loading="lazy" /></div>'
            . '<div class="sp-rel-card-title">' . esc_html( get_the_title( $rp->ID ) ) . '</div>'
            . '<div class="sp-rel-card-sub">' . $rp_meta . '</div>'
            . '</a>';
    }

    $html = <<<'HTML'
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Inter:wght@300;400;600&display=swap" rel="stylesheet" />
    <style>
      html, body { height: auto !important; overflow: auto !important; cursor: auto !important; }
      #sp-root { --sp-bg:#ffffff; --sp-text:#151512; --sp-muted:rgba(21,21,18,0.38); --sp-accent:#7a5c3a;
        font-family:'Inter',sans-serif; background:var(--sp-bg); color:var(--sp-text); }

      #sp-hero { position:relative; width:100%; height:100vh; overflow:hidden; }
      #sp-hero img, #sp-hero video { width:100%; height:100%; object-fit:cover; display:block; }
      #sp-hero-ui { position:absolute; inset:0; display:flex; flex-direction:column; justify-content:flex-end;
        padding:2.4rem 2.6rem; background:linear-gradient(to top, rgba(0,0,0,0.72) 0%, transparent 45%); color:#fff; }
      #sp-back { position:absolute; top:1.8rem; left:2.6rem; font-size:0.58rem; letter-spacing:0.22em; text-transform:uppercase;
        color:rgba(255,255,255,0.75); text-decoration:none; background:none; border:none; cursor:pointer; padding:0; }
      #sp-back:hover { color:#fff; }
      #sp-meta { font-size:0.6rem; letter-spacing:0.3em; text-transform:uppercase; color:rgba(255,255,255,0.6); margin-bottom:0.85rem; }
      #sp-title { font-family:'Cormorant Garamond',serif; font-size:clamp(2.2rem,5.5vw,4.6rem); font-weight:300; letter-spacing:0.02em; line-height:1.05; margin:0; }

      #sp-body { max-width:1080px; margin:0 auto; padding:5rem 5vw 3rem; }
      #sp-desc { font-size:1rem; line-height:1.85; color:rgba(21,21,18,0.8); max-width:70ch; margin-bottom:3rem; }
      #sp-meta-table { max-width:640px; margin-bottom:1rem; }
      .sp-row { display:grid; grid-template-columns:160px 1fr; gap:1rem; padding:0.85rem 0; border-bottom:1px solid rgba(21,21,18,0.09); }
      .sp-row:first-child { border-top:1px solid rgba(21,21,18,0.09); }
      .sp-row-label { font-size:0.8rem; color:var(--sp-text); }
      .sp-row-value { font-size:0.8rem; font-weight:300; color:rgba(21,21,18,0.75); line-height:1.55; }

      #sp-gallery { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; max-width:1080px; margin:3rem auto 0; padding:0 5vw; }
      .sp-gallery-item { overflow:hidden; }
      .sp-gallery-item img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .5s ease; }
      .sp-gallery-item:hover img { transform:scale(1.03); }

      #sp-related { max-width:1440px; margin:5rem auto 0; padding:3rem 5vw 6rem; border-top:1px solid rgba(21,21,18,0.1); }
      #sp-related-label { display:block; font-size:0.7rem; letter-spacing:0.14em; text-transform:uppercase; color:var(--sp-muted); margin-bottom:1.6rem; }
      #sp-related-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.8rem; }
      .sp-rel-card { text-decoration:none; color:inherit; display:block; }
      .sp-rel-card-img { aspect-ratio:3/4; overflow:hidden; background:#e8e7e3; margin-bottom:0.7rem; }
      .sp-rel-card-img img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .55s ease; }
      .sp-rel-card:hover .sp-rel-card-img img { transform:scale(1.06); }
      .sp-rel-card-title { font-size:0.82rem; color:var(--sp-text); }
      .sp-rel-card-sub { font-size:0.7rem; color:var(--sp-muted); margin-top:0.2rem; }

      @media (max-width:900px) {
        #sp-gallery { grid-template-columns:1fr; padding:0 6vw; }
        #sp-related-grid { grid-template-columns:repeat(2,1fr); }
        #sp-body { padding:4rem 6vw 2rem; }
      }
      @media (max-width:560px) {
        #sp-related-grid { grid-template-columns:1fr; }
        .sp-row { grid-template-columns:1fr; gap:0.3rem; }
      }
    </style>

    <div id="sp-root">
      <div id="sp-hero">
        __SP_HERO_MEDIA__
        <div id="sp-hero-ui">
          <button id="sp-back" onclick="history.back()">← Voltar</button>
          <span id="sp-meta">__SP_META__</span>
          <h1 id="sp-title">__SP_TITLE__</h1>
        </div>
      </div>

      <div id="sp-body">
        <div id="sp-desc">__SP_DESCRIPTION__</div>
        <div id="sp-meta-table">__SP_ROWS__</div>
      </div>

      <div id="sp-gallery">__SP_GALLERY__</div>

      <div id="sp-related">
        <span id="sp-related-label">Confira outros projetos</span>
        <div id="sp-related-grid">__SP_RELATED__</div>
      </div>
    </div>
    HTML;

    return strtr( $html, [
        '__SP_HERO_MEDIA__'  => $hero_media,
        '__SP_META__'        => $meta,
        '__SP_TITLE__'       => esc_html( $title ),
        '__SP_DESCRIPTION__' => $description_html,
        '__SP_ROWS__'        => $rows_html,
        '__SP_GALLERY__'     => $gallery_html,
        '__SP_RELATED__'     => $related_html,
    ] );
});
