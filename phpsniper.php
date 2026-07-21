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
 * 2. Endpoint individual /sastudio/v2/project/{id}
 */
add_action('rest_api_init', function () {
    register_rest_route('sastudio/v2', '/project/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function ($request) {
            $id   = (int) $request['id'];
            $post = get_post($id);
            if (!$post) {
                return new WP_Error('not_found', 'Não encontrado', ['status' => 404]);
            }

            $cache_key = 'sastudio_project_v3_' . $id;
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
    delete_transient('sastudio_project_v3_' . $post_id);
});
