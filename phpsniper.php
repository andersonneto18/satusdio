if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. Injeta ACF na lista principal (/wp/v2/projects)
 */
add_filter('rest_prepare_projects', function($response, $post, $request) {
    $id   = $post->ID;
    $data = $response->get_data();

    // hover_gif — converte ID para URL
    $hover_gif_raw = get_field('hover_gif', $id);
    $hover_gif_url = '';
    if ( is_numeric($hover_gif_raw) ) {
        $hover_gif_url = wp_get_attachment_url($hover_gif_raw);
    } elseif ( is_array($hover_gif_raw) && !empty($hover_gif_raw['url']) ) {
        $hover_gif_url = $hover_gif_raw['url'];
    } elseif ( is_string($hover_gif_raw) ) {
        $hover_gif_url = $hover_gif_raw;
    }

    // project_gallery — converte para array de URLs
    $gallery_raw  = get_field('project_gallery', $id);
    $gallery_urls = [];
    if (is_array($gallery_raw)) {
        foreach ($gallery_raw as $img) {
            if (is_array($img) && !empty($img['url'])) {
                $gallery_urls[] = $img['url'];
            } elseif (is_numeric($img)) {
                $url = wp_get_attachment_url($img);
                if ($url) $gallery_urls[] = $url;
            }
        }
    }

    $data['acf'] = [
        'hover_gif'           => $hover_gif_url,
        'cliente'             => get_field('cliente',             $id),
        'project_location'    => get_field('project_location',    $id),
        'project_area'        => get_field('project_area',        $id),
        'project_status'      => get_field('project_status',      $id),
        'project_team'        => get_field('project_team',        $id),
        'project_program'     => get_field('project_program',     $id),
        'project_descriprion' => get_field('project_descriprion', $id),
        'project_gallery'     => $gallery_urls,
        'project_video'       => get_field('project_video',       $id),
    ];

    $response->set_data($data);
    return $response;
}, 10, 3);


/**
 * 2. Endpoint individual /sastudio/v1/project/{id}
 */
add_action('rest_api_init', function () {
    register_rest_route('sastudio/v1', '/project/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function ($request) {
            $id   = (int) $request['id'];
            $post = get_post($id);
            if (!$post) {
                return new WP_Error('not_found', 'Não encontrado', ['status' => 404]);
            }

            $cache_key = 'sastudio_project_' . $id;
            $cached    = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }

            // hover_gif — converte ID para URL
            $hover_gif_raw = get_field('hover_gif', $id);
            $hover_gif_url = '';
            if ( is_numeric($hover_gif_raw) ) {
                $hover_gif_url = wp_get_attachment_url($hover_gif_raw);
            } elseif ( is_array($hover_gif_raw) && !empty($hover_gif_raw['url']) ) {
                $hover_gif_url = $hover_gif_raw['url'];
            } elseif ( is_string($hover_gif_raw) ) {
                $hover_gif_url = $hover_gif_raw;
            }

            // project_gallery — converte para array de URLs
            $gallery_raw  = get_field('project_gallery', $id);
            $gallery_urls = [];
            if (is_array($gallery_raw)) {
                foreach ($gallery_raw as $img) {
                    if (is_array($img) && !empty($img['url'])) {
                        $gallery_urls[] = $img['url'];
                    } elseif (is_numeric($img)) {
                        $url = wp_get_attachment_url($img);
                        if ($url) $gallery_urls[] = $url;
                    }
                }
            }

            // HTML scraping só se galeria ACF estiver vazia
            $content = '';
            if (empty($gallery_urls)) {
                $response_http = wp_remote_get(get_permalink($id), [
                    'timeout'   => 20,
                    'sslverify' => false,
                ]);

                if (!is_wp_error($response_http)) {
                    $body = wp_remote_retrieve_body($response_http);

                    libxml_use_internal_errors(true);
                    $dom = new DOMDocument('1.0', 'UTF-8');
                    $dom->loadHTML(mb_convert_encoding($body, 'HTML-ENTITIES', 'UTF-8'));
                    libxml_clear_errors();

                    $xpath     = new DOMXPath($dom);
                    $selectors = [
                        '//div[contains(@class,"elementor-location-single")]',
                        '//main',
                        '//div[@id="main"]',
                        '//div[contains(@class,"entry-content")]',
                        '//article',
                    ];

                    foreach ($selectors as $sel) {
                        $nodes = $xpath->query($sel);
                        if ($nodes && $nodes->length > 0) {
                            $content = $dom->saveHTML($nodes->item(0));
                            break;
                        }
                    }
                }
            }

            $result = [
                'id'      => $id,
                'title'   => get_the_title($id),
                'content' => $content,
                'acf'     => [
                    'hover_gif'           => $hover_gif_url,
                    'cliente'             => get_field('cliente',             $id),
                    'project_location'    => get_field('project_location',    $id),
                    'project_area'        => get_field('project_area',        $id),
                    'project_status'      => get_field('project_status',      $id),
                    'project_team'        => get_field('project_team',        $id),
                    'project_program'     => get_field('project_program',     $id),
                    'project_descriprion' => get_field('project_descriprion', $id),
                    'project_gallery'     => $gallery_urls,
                    'project_video'       => get_field('project_video',       $id),
                ],
            ];

            set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);

            return $result;
        },
    ]);
});


/**
 * 3. Invalida cache ao salvar um projeto
 */
add_action('save_post_projects', function($post_id) {
    delete_transient('sastudio_project_' . $post_id);
});


/**
 * 4. Shortcode [arquive_projetos] — grelha de projetos para a página /projects
 *    Self-contained: CSS + JS próprios, não depende do que está na home.
 *    Cada card liga ao permalink real do projeto (post.link).
 */
add_shortcode('arquive_projetos', function () {
    $api_url = esc_url( add_query_arg(
        [ '_embed' => '', 'per_page' => 100, 'orderby' => 'date', 'order' => 'desc' ],
        get_rest_url( null, 'wp/v2/projects' )
    ) );

    $html = <<<'HTML'
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Inter:wght@300;400;600&display=swap" rel="stylesheet" />
    <style>
      html, body { height: auto !important; overflow: auto !important; cursor: auto !important; }
      #ap-root { --ap-bg:#ffffff; --ap-text:#151512; --ap-muted:rgba(21,21,18,0.38);
        font-family:'Inter',sans-serif; background:var(--ap-bg); color:var(--ap-text); }
      #ap-inner { max-width:1440px; margin:0 auto; padding:5rem 5vw 6rem; }
      #ap-header { display:flex; align-items:flex-end; justify-content:space-between;
        margin-bottom:3rem; padding-bottom:1.5rem; border-bottom:1px solid rgba(21,21,18,0.12); flex-wrap:wrap; gap:1rem; }
      #ap-label { display:block; font-size:0.7rem; letter-spacing:0.14em; text-transform:uppercase; color:var(--ap-muted); margin-bottom:0.75rem; }
      #ap-tagline { font-family:'Cormorant Garamond',serif; font-size:clamp(1.5rem,2.6vw,2.8rem); font-weight:300; line-height:1.2; max-width:26ch; margin:0; }
      #ap-count { font-size:0.7rem; color:var(--ap-muted); letter-spacing:0.06em; flex-shrink:0; }
      #ap-controls { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:2.2rem; flex-wrap:wrap; }
      #ap-filters { display:flex; gap:0.5rem; flex-wrap:wrap; }
      .ap-filter-btn { font-size:0.68rem; letter-spacing:0.1em; text-transform:uppercase; color:var(--ap-muted);
        background:none; border:1px solid rgba(21,21,18,0.18); border-radius:999px; padding:0.38rem 1rem; cursor:pointer; transition:color .2s,border-color .2s,background .2s; }
      .ap-filter-btn:hover, .ap-filter-btn.active { color:var(--ap-text); border-color:var(--ap-text); background:rgba(21,21,18,0.05); }
      #ap-search-wrap { display:flex; align-items:center; gap:0.55rem; border:1px solid rgba(21,21,18,0.18); border-radius:999px; padding:0.38rem 1rem; }
      #ap-search-wrap:focus-within { border-color:var(--ap-text); }
      #ap-search { border:none; outline:none; background:none; font-family:'Inter',sans-serif; font-size:0.72rem; color:var(--ap-text); width:180px; }
      #ap-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:2.4rem 1.6rem; }
      .ap-card { text-decoration:none; color:inherit; display:block; }
      .ap-card-img { aspect-ratio:3/4; overflow:hidden; background:#e8e7e3; margin-bottom:0.7rem; }
      .ap-card-img img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .55s ease; }
      .ap-card:hover .ap-card-img img { transform:scale(1.06); }
      .ap-card-title { font-size:0.82rem; color:var(--ap-text); }
      .ap-card-sub { font-size:0.7rem; color:var(--ap-muted); margin-top:0.2rem; }
      #ap-empty { font-size:0.82rem; color:var(--ap-muted); padding:3rem 0; }
      @media (max-width:1200px){ #ap-grid{ grid-template-columns:repeat(4,1fr);} }
      @media (max-width:900px){ #ap-grid{ grid-template-columns:repeat(3,1fr); gap:1.6rem 1.2rem;} #ap-header{ flex-direction:column; align-items:flex-start;} }
      @media (max-width:560px){ #ap-grid{ grid-template-columns:repeat(2,1fr); gap:1.2rem .8rem;} }
    </style>

    <div id="ap-root">
      <div id="ap-inner">
        <div id="ap-header">
          <div>
            <span id="ap-label">Projetos</span>
            <p id="ap-tagline">Transformamos espaços em experiências únicas</p>
          </div>
          <span id="ap-count"></span>
        </div>
        <div id="ap-controls">
          <div id="ap-filters"></div>
          <div id="ap-search-wrap">
            <input id="ap-search" type="text" placeholder="Pesquisar projetos…" autocomplete="off" />
          </div>
        </div>
        <div id="ap-grid"></div>
        <div id="ap-empty" style="display:none;">Sem projetos para mostrar.</div>
      </div>
    </div>

    <script>
    (function () {
      function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

      var grid    = document.getElementById('ap-grid');
      var count   = document.getElementById('ap-count');
      var filters = document.getElementById('ap-filters');
      var search  = document.getElementById('ap-search');
      var empty   = document.getElementById('ap-empty');
      var allCards = [];

      function filterCards() {
        var q   = search.value.trim().toLowerCase();
        var cat = filters.querySelector('.ap-filter-btn.active');
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

      fetch('__AP_API_URL__')
        .then(function (r) { return r.json(); })
        .then(function (posts) {
          if (!posts || !posts.length) { empty.style.display = 'block'; return; }

          var cats = [];
          posts.forEach(function (post) {
            var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
            var cat = terms[0] ? terms[0].name : '';
            if (cat && cats.indexOf(cat) === -1) cats.push(cat);
          });

          var allBtn = document.createElement('button');
          allBtn.className = 'ap-filter-btn active';
          allBtn.textContent = 'Todos';
          allBtn.dataset.cat = '';
          filters.appendChild(allBtn);
          cats.forEach(function (cat) {
            var btn = document.createElement('button');
            btn.className = 'ap-filter-btn';
            btn.textContent = cat;
            btn.dataset.cat = cat;
            filters.appendChild(btn);
          });
          filters.addEventListener('click', function (e) {
            var btn = e.target.closest('.ap-filter-btn');
            if (!btn) return;
            filters.querySelectorAll('.ap-filter-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            filterCards();
          });
          search.addEventListener('input', filterCards);

          posts.forEach(function (post) {
            var imgUrl = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0]
              ? post._embedded['wp:featuredmedia'][0].source_url : '';
            if (!imgUrl) return;

            var title = post.title.rendered;
            var year  = new Date(post.date).getFullYear();
            var terms = (post._embedded && post._embedded['wp:term']) ? [].concat.apply([], post._embedded['wp:term']) : [];
            var cat   = terms[0] ? terms[0].name : '';
            var sub   = cat ? (cat + ' · ' + year) : String(year);

            var a = document.createElement('a');
            a.className = 'ap-card';
            a.href = post.link;
            a.innerHTML =
              '<div class="ap-card-img"><img src="' + esc(imgUrl) + '" alt="' + esc(title) + '" loading="lazy"/></div>' +
              '<div class="ap-card-title">' + esc(title) + '</div>' +
              '<div class="ap-card-sub">' + esc(sub) + '</div>';

            grid.appendChild(a);
            allCards.push({ el: a, title: title.toLowerCase(), sub: sub.toLowerCase() });
          });

          count.textContent = allCards.length + ' projetos';
        })
        .catch(function (err) {
          console.warn('SASTUDIO projects:', err);
          empty.style.display = 'block';
        });
    })();
    </script>
    HTML;

    return str_replace('__AP_API_URL__', esc_js( $api_url ), $html);
});
