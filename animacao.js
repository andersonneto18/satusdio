function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* Heights base — ciclam pelo número de projetos */
const BASE_H = [
  240, 320, 185, 265, 200, 145,
  285, 190, 310, 215, 165, 295,
  225, 175, 330, 200, 255, 170,
  285, 210, 160, 315, 188, 248,
  168, 305, 220
];

const WP_API_BASE    = 'https://sastudio.brand22creativeagency.pt/wp-json/wp/v2';
const WP_API         = `${WP_API_BASE}/projects?_embed&per_page=100&orderby=date&order=desc`;
const CUSTOM_API     = WP_API_BASE.replace('/wp/v2', '/sastudio/v2');
const projectCache   = new Map();

const gallery = document.getElementById('gallery');
let mx = 0, my = 0;

function prefetchProject(id) {
  if (projectCache.has(id)) return;
  projectCache.set(id, fetch(`${CUSTOM_API}/project/${id}`).then(r => r.json()));
}

/* ── Prefetch silencioso de todos os projetos em fila ── */
let _bgPrefetchStopped = false;

function backgroundPrefetchAll(ids) {
  let i = 0;
  function next() {
    if (_bgPrefetchStopped || i >= ids.length) return;
    const id = ids[i++];
    if (!projectCache.has(id)) {
      projectCache.set(id, fetch(`${CUSTOM_API}/project/${id}`)
        .then(r => r.json())
        .catch(() => null)
      );
    }
    setTimeout(next, 150);
  }
  setTimeout(next, 500);
}

/* ── Ao abrir um projecto, para a fila lenta e carrega tudo em paralelo ── */
function flushPrefetchQueue() {
  _bgPrefetchStopped = true;
  document.querySelectorAll('#gallery .pic').forEach(p => {
    const id = +p.dataset.id;
    if (!id || projectCache.has(id)) return;
    projectCache.set(id, fetch(`${CUSTOM_API}/project/${id}`)
      .then(r => r.json())
      .catch(() => null)
    );
  });
}

/* ── Busca projetos WordPress e constrói os cards ── */
async function fetchProjects() {
  try {
    const res = await fetch(WP_API);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const posts = await res.json();
    if (!posts.length) throw new Error('Sem projetos');

    posts.forEach(post => {
      const media   = post._embedded?.['wp:featuredmedia']?.[0];
      const imgUrl  = media?.source_url;
      if (!imgUrl) return;

      const title = post.title.rendered;
      const year  = new Date(post.date).getFullYear();
      const terms = (post._embedded?.['wp:term'] || []).flat();
      const cat   = terms[0]?.name || '';
      const sub   = cat ? `${cat} · ${year}` : String(year);

      /* proporção real da imagem (largura/altura), vinda do WordPress —
         usada no layout para a caixa encaixar exatamente na imagem,
         sem cortar nada com object-fit:cover */
      const mw = media?.media_details?.width;
      const mh = media?.media_details?.height;
      const aspect = (mw && mh) ? mw / mh : null;

      const el = document.createElement('div');
      el.className    = 'pic';
      el.dataset.id       = post.id;
      el.dataset.href     = post.link;
      el.dataset.sub      = sub;
      if (aspect) el.dataset.aspect = aspect;
      const hoverGif      = post.acf?.hover_gif || '';
      el.dataset.hoverGif = hoverGif;
      el.innerHTML    = `
        <img src="${esc(imgUrl)}" alt="${esc(title)}" loading="lazy"/>
        <div class="pic-dim"></div>
        <div class="pic-overlay"></div>
        <div class="pic-info">
          <div class="pic-title">${esc(title)}</div>
          <div class="pic-sub">${esc(sub)}</div>
        </div>`;
      if (hoverGif && isVideoUrl(hoverGif)) {
        const vid = document.createElement('video');
        vid.className   = 'pic-hover-vid';
        vid.src         = hoverGif;
        vid.muted       = true;
        vid.loop        = true;
        vid.playsInline = true;
        vid.preload     = 'none';
        el.appendChild(vid);
      }
      el.addEventListener('mouseenter', () => prefetchProject(post.id));
      gallery.appendChild(el);
    });
  } catch (err) {
    console.warn('WordPress API:', err);
    const seeds = [10,23,37,45,52,68,71,84,90,103,115,127,132,148,156,162,175,189];
    seeds.forEach((seed, i) => {
      const el = document.createElement('div');
      el.className = 'pic';
      el.innerHTML = `
        <img src="https://picsum.photos/seed/${seed}/800/600" alt="Projeto ${i+1}" loading="eager"/>
        <div class="pic-dim"></div>
        <div class="pic-overlay"></div>
        <div class="pic-info">
          <div class="pic-title">Projeto ${i + 1}</div>
          <div class="pic-sub">Arquitetura · ${2024 - (i % 4)}</div>
        </div>`;
      gallery.appendChild(el);
    });
  }
}

/* ── Inicialização ── */
window.addEventListener('load', async () => {

  await fetchProjects();

  /* recolhe todos os IDs e inicia prefetch silencioso em background */
  const allIds = Array.from(document.querySelectorAll('#gallery .pic'))
    .map(p => +p.dataset.id)
    .filter(Boolean);
  backgroundPrefetchAll(allIds);

  /* Esconde loading e avança */
  document.getElementById('loading').style.display = 'none';

  layoutMasonry();

  /* ── CURSOR ── */
  const cur  = document.getElementById('cursor');
  const curR = document.getElementById('cursor-ring');
  let rx = 0, ry = 0;
  document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; });
  (function tc() {
    cur.style.left = mx + 'px'; cur.style.top = my + 'px';
    rx += (mx - rx) * 0.1; ry += (my - ry) * 0.1;
    curR.style.left = rx + 'px'; curR.style.top = ry + 'px';
    requestAnimationFrame(tc);
  })();
  document.querySelectorAll('.logo,.nav-links a').forEach(el => {
    el.addEventListener('mouseenter', () => document.body.classList.add('on-pic'));
    el.addEventListener('mouseleave', () => document.body.classList.remove('on-pic'));
  });
  const navEl = document.getElementById('nav');
  if (navEl) {
    navEl.addEventListener('mouseenter', () => document.body.classList.add('on-nav'));
    navEl.addEventListener('mouseleave', () => document.body.classList.remove('on-nav'));
  }

  const lbTopBar = document.getElementById('lb-top-bar');
  if (lbTopBar) {
    lbTopBar.addEventListener('mouseenter', () => document.body.classList.add('on-nav'));
    lbTopBar.addEventListener('mouseleave', () => document.body.classList.remove('on-nav'));
  }

  entrance();
});

/* ══════════════════════════════════════════════════
   MASONRY LAYOUT
══════════════════════════════════════════════════ */
const COL_CONFIG = [
  { maxW: 640,  cols: 1, gap: 10, canvasScale: 1    },
  { maxW: 1024, cols: 2, gap: 10, canvasScale: 1.05 },
  { maxW: Infinity, cols: 4, gap: 10, canvasScale: 1.3 },
];
const GALLERY_MARGIN = 24;

function getMasonryConfig() {
  const vw = window.innerWidth;
  return COL_CONFIG.find(c => vw <= c.maxW);
}

/* classifica a imagem pela proporção real, para a composição alternar
   entre horizontal/quadrada/vertical/muito alta em vez de amontoar */
function classifyAspect(aspect) {
  if (!aspect || !isFinite(aspect)) return 'h';
  if (aspect >= 2.0)  return 'pano';
  if (aspect >= 1.15) return 'h';
  if (aspect >= 0.85) return 'sq';
  if (aspect >= 0.55) return 'v';
  return 'tall';
}

let galleryScale = 1;

function layoutMasonry() {
  const { cols, gap, canvasScale } = getMasonryConfig();
  const margin = GALLERY_MARGIN;
  const vw   = window.innerWidth;
  const W    = Math.round(vw * canvasScale);
  gallery.style.width = W + 'px';

  const colW        = (W - margin * 2 - gap * (cols - 1)) / cols;
  const colH        = new Array(cols).fill(margin);
  const colLastType = new Array(cols).fill(null);
  const pics = Array.from(gallery.querySelectorAll('.pic'));

  pics.forEach((pic, idx) => {
    /* usa a proporção real da imagem quando disponível, para a caixa
       encaixar exatamente nela (sem cortar nada); só recorre à altura
       fixa de reserva se não soubermos as dimensões reais */
    const aspect = parseFloat(pic.dataset.aspect);
    const type   = classifyAspect(aspect);
    const h      = aspect ? colW / aspect : BASE_H[idx % BASE_H.length];

    /* prioridade nº1: preencher sempre a coluna mais vazia (nunca deixar
       nenhuma coluna esquecida). O ritmo (evitar repetir tipo, evitar
       duas "muito altas" lado a lado) só desempata entre colunas quase
       empatadas — nunca pesa mais do que a diferença real de altura. */
    const minH = Math.min(...colH);
    const TOLERANCE = 40; // px — só considera "quase tão curta" dentro disto
    const candidates = colH
      .map((height, i) => ({ i, height }))
      .filter(c => c.height <= minH + TOLERANCE)
      .sort((a, b) => a.height - b.height);

    let ci = candidates[0].i;
    let bestScore = -Infinity;
    candidates.forEach(c => {
      let score = -c.height;
      if (colLastType[c.i] === type) score -= 15;
      if (type === 'tall' && (colLastType[c.i - 1] === 'tall' || colLastType[c.i + 1] === 'tall')) {
        score -= 20;
      }
      if (score > bestScore) { bestScore = score; ci = c.i; }
    });

    pic.style.left   = margin + ci * (colW + gap) + 'px';
    pic.style.top    = colH[ci] + 'px';
    pic.style.width  = colW + 'px';
    pic.style.height = h + 'px';

    colH[ci] += h + gap;
    colLastType[ci] = type;
  });

  /* escala a grelha toda para preencher exatamente a altura do ecrã,
     sem sobrar espaço em branco em cima/baixo (edge-to-edge) */
  const naturalH = Math.max(...colH) - gap + margin;
  gallery.style.height = naturalH + 'px';
  galleryScale = window.innerHeight / naturalH;
}

window.addEventListener('resize', () => {
  layoutMasonry();
  gsap.set(gallery.querySelectorAll('.pic'), { x: 0, y: 0 });
});

/* ══════════════════════════════════════════════════
   ENTRANCE — hero → gallery burst
══════════════════════════════════════════════════ */
/* ── MOBILE NAV ── */
(function() {
  const burger    = document.getElementById('nav-burger');
  const navMobile = document.getElementById('nav-mobile');
  if (!burger || !navMobile) return;

  function openMenu() {
    navMobile.style.display = 'flex';
    requestAnimationFrame(() => requestAnimationFrame(() => navMobile.classList.add('open')));
    burger.classList.add('open');
  }
  function closeMenu() {
    navMobile.classList.remove('open');
    burger.classList.remove('open');
    setTimeout(() => { navMobile.style.display = 'none'; }, 300);
  }

  burger.addEventListener('click', e => {
    e.stopPropagation();
    burger.classList.contains('open') ? closeMenu() : openMenu();
  });
  burger.addEventListener('touchend', e => {
    e.preventDefault();
    e.stopPropagation();
    burger.classList.contains('open') ? closeMenu() : openMenu();
  });
  navMobile.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
})();

/* ── Abre o projeto certo se o URL trouxer um link direto (/projects/slug/) ── */
function openProjectFromPath() {
  const targetPath = decodeURIComponent(location.pathname);
  if (!targetPath || targetPath === '/') return;
  const pic = Array.from(document.querySelectorAll('#gallery .pic')).find(p => {
    try { return new URL(p.dataset.href).pathname === targetPath; } catch (_) { return false; }
  });
  if (pic) openProject(pic);
}

function entrance() {
  const pics = document.querySelectorAll('.pic');
  const nav  = document.getElementById('nav');
  const hud  = document.getElementById('hud');
  const hero = document.getElementById('hero');

  gsap.set(pics, {
    scale: 0.08,
    x: () => gsap.utils.random(-140, 140),
    y: () => gsap.utils.random(-90, 90),
  });

  gsap.timeline()
    .to('#h-tag',  { opacity: 1, y: 0, duration: 0.55, ease: 'power2.out' }, 0)
    .to('#h-name', { opacity: 1, y: 0, duration: 0.75, ease: 'power3.out' }, 0.1)
    .to('#h-rule', { width: 120, duration: 0.65, ease: 'power2.inOut' }, 0.55)
    .to('#h-desc', { opacity: 1, y: 0, duration: 0.55, ease: 'power2.out' }, 0.85)
    .call(() => {
      const done = () => {
        if (nav) {
          nav.style.opacity = '1';
          nav.classList.add('ready');
        }
        gsap.to(hud, { opacity: 1, duration: 0.8 });
        setTimeout(() => gsap.to(hud, { opacity: 0, duration: 1 }), 4000);
        initCanvas();
        initParallax();
        /* prefetch primeiros 8 projetos visíveis */
        Array.from(document.querySelectorAll('#gallery .pic'))
          .slice(0, 8)
          .forEach(p => { if (p.dataset.id) prefetchProject(+p.dataset.id); });

        openProjectFromPath();
      };
      if (!pics.length) { done(); return; }
      gsap.to(pics, {
        scale: 1, opacity: 1, x: 0, y: 0,
        duration: 1.6, ease: 'power3.out',
        stagger: { amount: 1.4, from: 'random' },
        onComplete: done
      });
    }, null, 2.2)
    .to(hero, { opacity: 0, duration: 0.75, ease: 'power2.in' }, 2.2)
    .set(hero, { display: 'none' });
}

/* ══════════════════════════════════════════════════
   CANVAS — navegação só horizontal, via scroll (sem arrastar, sem zoom)
══════════════════════════════════════════════════ */
function initCanvas() {
  /* começa sempre encostado ao início (esquerda), nunca centrado */
  let tx = 0, tTx = 0, rawTx = 0;

  function getBounds() {
    const W  = window.innerWidth;
    const gW = gallery.offsetWidth * galleryScale;
    const mg = 55;
    return { xMin: -(gW - W) - mg, xMax: mg };
  }

  /* deixa ir um pouco além do limite, com resistência crescente (efeito elástico) */
  function rubberBand(value, min, max) {
    const RESIST = 0.35;
    if (value < min) return min - (min - value) * RESIST;
    if (value > max) return max + (value - max) * RESIST;
    return value;
  }

  function clamp() {
    const b = getBounds();
    tTx = Math.max(b.xMin, Math.min(b.xMax, tTx));
    rawTx = tTx;
  }

  (function tick() {
    tx += (tTx - tx) * 0.085;
    gallery.style.transform = `translateX(${tx}px) scale(${galleryScale})`;
    requestAnimationFrame(tick);
  })();

  /* scroll do rato move só para os lados; depois de parar de rodar a
     roda, "solta" e encaixa dentro do limite (bounce elástico) — sem
     isto, ao passar do limite ficava preso esticado para sempre */
  let wheelSettleTimer = null;
  window.addEventListener('wheel', e => {
    if (lb.classList.contains('open')) return;
    if (e.target.closest('#projects-list, #about-panel, #contact-panel')) return;
    e.preventDefault();
    rawTx -= e.deltaY;
    const b = getBounds();
    tTx = rubberBand(rawTx, b.xMin, b.xMax);

    clearTimeout(wheelSettleTimer);
    wheelSettleTimer = setTimeout(clamp, 120);
  }, { passive: false });

  /* clique num projeto abre-o */
  document.querySelectorAll('#gallery .pic').forEach(pic => {
    pic.addEventListener('click', () => {
      if (pic.dataset.href) openProject(pic);
    });
  });

  /* toque (mobile) — arrastar move só na horizontal, como um swipe */
  let touchActive = false, pmx = 0;
  window.addEventListener('touchstart', e => {
    if (e.touches.length !== 1) return;
    touchActive = true;
    pmx = e.touches[0].clientX;
    rawTx = tTx;
  }, { passive: true });

  window.addEventListener('touchmove', e => {
    if (!touchActive || e.touches.length !== 1) return;
    e.preventDefault();
    const vx = e.touches[0].clientX - pmx;
    rawTx += vx;
    const b = getBounds();
    tTx = rubberBand(rawTx, b.xMin, b.xMax);
    pmx = e.touches[0].clientX;
  }, { passive: false });

  window.addEventListener('touchend', () => {
    if (!touchActive) return;
    touchActive = false;
    clamp();
  });
}

/* ══════════════════════════════════════════════════
   LIGHTBOX — FULLSCREEN + SLIDESHOW + SCROLL CONTENT
══════════════════════════════════════════════════ */
const lb          = document.getElementById('lightbox');
const lbImg       = document.getElementById('lb-img');
const lbImgEl     = lbImg.querySelector('img');
const lbHoverVid  = document.getElementById('lb-hover-video');

lbImg.addEventListener('mouseenter', () => {
  if (lbHoverVid.src) { lbHoverVid.play(); lbHoverVid.style.opacity = '1'; }
});
lbImg.addEventListener('mouseleave', () => {
  lbHoverVid.style.opacity = '0';
  setTimeout(() => { lbHoverVid.pause(); lbHoverVid.currentTime = 0; }, 400);
});
const lbView      = document.getElementById('lb-view');
const lbSlides    = document.getElementById('lb-slides');
const lbProjTitle = document.getElementById('lb-proj-title');
const lbProjMeta  = document.getElementById('lb-proj-meta');
const lbExtLink   = document.getElementById('lb-ext-link');
const lbClose     = document.getElementById('lb-close');
const lbPrev      = document.getElementById('lb-prev');
const lbNext      = document.getElementById('lb-next');
const lbImgCount  = document.getElementById('lb-img-count');
const lbContent   = document.getElementById('lb-content');
const lbLoader    = document.getElementById('lb-loader');
const lbImageNav  = document.getElementById('lb-image-nav');

let slideImages      = [];
let currentSlide     = 0;
let slideInterval    = null;
let currentFeatSrc   = '';
let currentHoverGif  = '';
const SLIDE_DELAY = 5000;

function startAutoPlay() {
  stopAutoPlay();
  if (slideImages.length < 2) return;
  slideInterval = setInterval(() => {
    const total   = slideImages.length;
    const hasVideo = isVideoUrl(slideImages[0]);
    const start   = hasVideo ? 1 : 0;
    const next    = currentSlide + 1 >= total ? start : currentSlide + 1;
    goToSlide(next, 1);
  }, SLIDE_DELAY);
}

function stopAutoPlay() {
  if (slideInterval) { clearInterval(slideInterval); slideInterval = null; }
}

function isVideoUrl(url) {
  return /\.(mp4|webm|mov|ogg)(\?|$)/i.test(url);
}

function buildSlides(urls) {
  lbSlides.innerHTML = '';
  currentSlide = 0;
  let firstIsVideo = false;
  urls.forEach((url, i) => {
    const slide = document.createElement('div');
    slide.className = 'lb-slide' + (i === 0 ? ' active' : '');
    if (isVideoUrl(url)) {
      const vid = document.createElement('video');
      vid.src = url; vid.muted = true; vid.loop = false; vid.playsInline = true;
      vid.preload = i === 0 ? 'auto' : 'none';
      if (i === 0) {
        firstIsVideo = true;
        vid.addEventListener('ended', () => {
          goToSlide(currentSlide + 1, 1);
          startAutoPlay();
        });
      }
      slide.appendChild(vid);
    } else {
      const img = document.createElement('img');
      img.src = url; img.alt = ''; img.loading = i === 0 ? 'eager' : 'lazy';
      slide.appendChild(img);
    }
    lbSlides.appendChild(slide);
  });
  const first = lbSlides.querySelector('.lb-slide.active');
  if (first) {
    gsap.set(first, { opacity: 1, x: 0 });
    const vid = first.querySelector('video');
    if (vid) vid.play();
  }
  updateCounter();
  if (!firstIsVideo) startAutoPlay();
}

function updateCounter() {
  lbImgCount.textContent = slideImages.length > 1
    ? `${currentSlide + 1} / ${slideImages.length}` : '';
  lbImageNav.style.display = slideImages.length > 1 ? 'flex' : 'none';
}

function goToSlide(n, dir) {
  const slides = lbSlides.querySelectorAll('.lb-slide');
  if (!slides.length) return;
  const total  = slides.length;
  const newIdx = ((n % total) + total) % total;
  if (newIdx === currentSlide) return;

  /* calcula direção se não foi passada */
  if (dir === undefined) {
    const fwd = (newIdx - currentSlide + total) % total;
    dir = fwd <= total / 2 ? 1 : -1;
  }

  const offset = Math.round(window.innerWidth * 0.3);
  const dur    = 0.72;
  const ease   = 'power2.inOut';

  const oldSlide = slides[currentSlide];
  const oldVid   = oldSlide.querySelector('video');
  if (oldVid) { oldVid.pause(); oldVid.currentTime = 0; }
  slides[currentSlide].classList.remove('active');
  currentSlide = newIdx;
  slides[currentSlide].classList.add('active');
  const newVid = slides[currentSlide].querySelector('video');

  /* slide anterior sai */
  gsap.killTweensOf(oldSlide);
  gsap.fromTo(oldSlide,
    { x: 0, opacity: 1 },
    { x: -dir * offset, opacity: 0, duration: dur, ease,
      onComplete: () => gsap.set(oldSlide, { x: 0 }) }
  );

  /* novo slide entra */
  gsap.killTweensOf(slides[currentSlide]);
  gsap.fromTo(slides[currentSlide],
    { x: dir * offset, opacity: 0 },
    { x: 0, opacity: 1, duration: dur, ease,
      onComplete: () => { if (newVid) newVid.play(); } }
  );

  updateCounter();
}

lbPrev.addEventListener('click', e => {
  e.stopPropagation();
  goToSlide(currentSlide - 1, -1);
  startAutoPlay();
});
lbNext.addEventListener('click', e => {
  e.stopPropagation();
  goToSlide(currentSlide + 1, 1);
  startAutoPlay();
});

/* botão Back do browser fecha o lightbox */
window.addEventListener('popstate', () => {
  if (lb.classList.contains('open')) closeProject();
});

/* keyboard navigation */
document.addEventListener('keydown', e => {
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape')      closeProject();
  if (e.key === 'ArrowRight') { goToSlide(currentSlide + 1, 1);  startAutoPlay(); }
  if (e.key === 'ArrowLeft')  { goToSlide(currentSlide - 1, -1); startAutoPlay(); }
});

function openProject(pic) {
  const id      = +pic.dataset.id;
  const href    = pic.dataset.href;
  const title   = pic.querySelector('.pic-title')?.textContent || '';

  /* atualiza o URL discretamente (sem recarregar a página) — dá um link
     partilhável/atualizável, mas sem sair da home nem "saltar" de página */
  try {
    const path = href ? new URL(href).pathname : null;
    if (path) history.pushState({ projectId: id }, title, path);
  } catch (_) {}

  const meta     = pic.dataset.sub || '';
  const featSrc  = pic.querySelector('img').src;
  const hoverGif = pic.dataset.hoverGif || '';
  currentFeatSrc  = featSrc;
  currentHoverGif = hoverGif;

  /* populate UI */
  lbProjTitle.textContent = title;
  lbProjMeta.textContent  = meta;
  if (lbExtLink) lbExtLink.href = href || '#';
  lbContent.classList.remove('visible');
  lbContent.innerHTML     = '';
  lbLoader.style.display  = 'flex';
  lbView.scrollTop        = 0;
  populateRelated(pic);
  flushPrefetchQueue();

  slideImages = hoverGif ? [hoverGif, featSrc] : [featSrc];
  buildSlides(slideImages);

  lbImgEl.src = featSrc;
  lbHoverVid.src = hoverGif || '';
  lbHoverVid.style.opacity = '0';
  gsap.set(lbImg, { clearProps: 'all' });
  gsap.set(lbImg, { display: 'block', scale: 0.02, opacity: 0, transformOrigin: 'center center' });
  gsap.set(lbClose, { opacity: 0, pointerEvents: 'none' });
  lbView.classList.remove('show');
  lb.classList.add('open');

  gsap.to(lbImg, {
    scale: 1, opacity: 1,
    duration: 0.85, ease: 'power2.out',
    onComplete: () => {
      lbView.classList.add('show');
      gsap.to(lbImg, { opacity: 0, duration: 0.2,
        onComplete: () => { lbImg.style.display = 'none'; }
      });
      gsap.to(lbClose, { opacity: 1, pointerEvents: 'all', duration: 0.4, ease: 'power2.out' });
      fetchProjectContent(id);
    }
  });
}

function closeProject() {
  stopAutoPlay();
  history.pushState(null, document.title, '/');
  gsap.to(lbClose, { opacity: 0, pointerEvents: 'none', duration: 0.2 });
  gsap.to(lbView, { opacity: 0, duration: 0.35, ease: 'power2.in',
    onComplete: () => {
      lb.classList.remove('open');
      lbView.classList.remove('show');
      lbView.style.opacity = '';
      lbView.scrollTop = 0;
      lbSlides.innerHTML = '';
      lbContent.innerHTML = '';
      lbContent.classList.remove('visible');
      document.getElementById('lb-related-grid').innerHTML = '';
      gsap.set(lbImg, { clearProps: 'all' });
      lbImg.style.display = 'none';
    }
  });
}

if (lbClose) lbClose.addEventListener('click', closeProject);
document.querySelector('#lb-top-bar .lb-logo')?.addEventListener('click', closeProject);
lbView.addEventListener('mousedown', e => e.stopPropagation());

function populateRelated(currentPic) {
  const grid = document.getElementById('lb-related-grid');
  grid.innerHTML = '';

  const all    = Array.from(document.querySelectorAll('#gallery .pic'));
  const others = all.filter(p => p !== currentPic);
  const picks  = others.sort(() => Math.random() - 0.5).slice(0, 3);

  picks.forEach(pic => {
    const imgSrc = pic.querySelector('img').src;
    const title  = pic.querySelector('.pic-title')?.textContent || '';
    const sub    = pic.dataset.sub || '';

    const card = document.createElement('div');
    card.className = 'lb-rel-card';
    card.innerHTML = `
      <div class="lb-rel-card-img-wrap">
        <img src="${imgSrc}" alt="${title}" loading="lazy"/>
      </div>
      <div class="lb-rel-card-info">
        <div class="lb-rel-card-title">${title}</div>
      </div>`;
    card.addEventListener('click', () => {
      lbView.scrollTop = 0;
      openProject(pic);
    });
    grid.appendChild(card);
  });
}

function buildContentGallery(images, captions = []) {
  const outer = document.createElement('div');
  outer.className = 'lb-content-gallery-outer';

  const grid = document.createElement('div');
  grid.className = 'lb-content-gallery';

  images.forEach((url, i) => {
    const item = document.createElement('div');
    item.className = 'lb-content-gallery-item';

    const imgWrap = document.createElement('div');
    imgWrap.className = 'lb-content-gallery-img';
    const img = document.createElement('img');
    img.src = url; img.alt = captions[i] || '';
    img.loading = i === 0 ? 'eager' : 'lazy';
    imgWrap.appendChild(img);
    item.appendChild(imgWrap);

    if (captions[i]) {
      const cap = document.createElement('div');
      cap.className = 'lb-content-gallery-caption';
      cap.textContent = captions[i];
      item.appendChild(cap);
    }

    grid.appendChild(item);
  });

  outer.appendChild(grid);
  return outer;
}

async function fetchProjectContent(id) {
  lbContent.classList.remove('visible');
  lbContent.innerHTML = '';
  lbLoader.style.display = 'flex';

  const lbAcf     = document.getElementById('lb-acf');
  const lbGallery = document.getElementById('lb-gallery');
  if (lbAcf)     lbAcf.innerHTML     = '';
  if (lbGallery) lbGallery.innerHTML = '';

  try {
    if (!projectCache.has(id)) {
      projectCache.set(id, fetch(`${CUSTOM_API}/project/${id}`).then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      }));
    }
    const data = await projectCache.get(id);
    if (!data) throw new Error('Sem dados do projeto (fetch falhou ou foi bloqueado)');

    const acf = data.acf || {};

    /* ── Descrição (coluna esquerda) ── */
    const _desc = (acf.project_description || acf.project_descriprion || '').trim();
    const descHtml = _desc
      ? (acf.project_description || acf.project_descriprion)
      : '<p class="lb-msg">Sem descrição para este projeto.</p>';
    lbContent.innerHTML = `<h2 class="lb-section-heading">Descrição:</h2>${descHtml}`;

    if (acf.project_video?.trim()) {
      const vw = document.createElement('div');
      vw.className = 'lb-video-wrap';
      vw.innerHTML = `<video src="${acf.project_video}" controls playsinline></video>`;
      lbContent.appendChild(vw);
    }

    /* ── Dados do projeto (coluna direita) ── */
    if (lbAcf) {
      const yearMatch = lbProjMeta.textContent.match(/\d{4}/);
      const year = yearMatch ? yearMatch[0] : '';

      const metaFields = [
        { label: 'Programa',    value: acf.project_program },
        { label: 'Área',        value: acf.project_area },
        { label: 'Localização', value: acf.project_location },
        { label: 'Cliente',     value: acf.cliente },
        { label: 'Equipa',      value: acf.project_team },
        { label: 'Status',      value: acf.project_status },
        { label: 'Ano',         value: year },
      ];
      const filled = metaFields.filter(f => f.value?.trim());
      lbAcf.innerHTML = `
        <h2 class="lb-section-heading">Dados do projeto:</h2>
        <div class="lb-acf-table">
          ${filled.map(f => `
            <div class="lb-acf-row">
              <span class="lb-acf-label">${f.label}:</span>
              <span class="lb-acf-value">${f.value}</span>
            </div>`).join('')}
        </div>`;
      lbAcf.style.display = '';
    }

    /* ── Galeria ── */
    let galleryImgs = [];

    /* 1.º: tenta ACF project_gallery (disponível no cache da lista) */
    if (Array.isArray(acf.project_gallery) && acf.project_gallery.length) {
      galleryImgs = acf.project_gallery.map(url => ({ url, caption: '' }));
    }

    /* 2.º: se galeria ACF vazia e content vazio (dado do cache da lista),
            busca endpoint individual que faz scraping do Elementor */
    if (!galleryImgs.length && !data.content?.trim()) {
      const fresh = await fetch(`${CUSTOM_API}/project/${id}`).then(r => r.json());
      projectCache.set(id, Promise.resolve(fresh));
      if (Array.isArray(fresh.acf?.project_gallery) && fresh.acf.project_gallery.length) {
        galleryImgs = fresh.acf.project_gallery.map(url => ({ url, caption: '' }));
      }
      data.content = fresh.content || '';
    }

    /* 3.º: extrai galeria do HTML Elementor */
    if (!galleryImgs.length && data.content?.trim()) {
      const tmp = document.createElement('div');
      tmp.innerHTML = data.content;

      tmp.querySelectorAll('.elementor-gallery__container').forEach(container => {
        container.querySelectorAll('.e-gallery-image[data-thumbnail]').forEach(el => {
          if (!el.dataset.thumbnail) return;
          const caption = el.closest('a.e-gallery-item')
            ?.dataset.elementorLightboxTitle?.trim() || '';
          galleryImgs.push({ url: el.dataset.thumbnail, caption });
        });
      });
      tmp.querySelectorAll('.e-gallery-image[data-thumbnail]').forEach(el => {
        const url = el.dataset.thumbnail;
        if (url && !galleryImgs.find(i => i.url === url)) {
          const caption = el.closest('a.e-gallery-item')
            ?.dataset.elementorLightboxTitle?.trim() || '';
          galleryImgs.push({ url, caption });
        }
      });
    }

    if (galleryImgs.length) {
      const newSlideImages = [
        ...(currentHoverGif ? [currentHoverGif] : []),
        currentFeatSrc,
        ...galleryImgs.map(i => i.url)
      ];

      /* Se o vídeo inicial ainda está a correr, não o interrompe —
         apenas acrescenta os slides extra ao DOM */
      const activeVid = lbSlides.querySelector('.lb-slide.active video');
      const vidPlaying = activeVid && !activeVid.paused && !activeVid.ended;

      if (vidPlaying) {
        const addFrom = slideImages.length;
        slideImages = newSlideImages;
        newSlideImages.slice(addFrom).forEach(url => {
          const slide = document.createElement('div');
          slide.className = 'lb-slide';
          if (isVideoUrl(url)) {
            const v = document.createElement('video');
            v.src = url; v.muted = true; v.loop = false; v.playsInline = true; v.preload = 'none';
            slide.appendChild(v);
          } else {
            const img = document.createElement('img');
            img.src = url; img.alt = ''; img.loading = 'lazy';
            slide.appendChild(img);
          }
          lbSlides.appendChild(slide);
        });
        updateCounter();
      } else {
        slideImages = newSlideImages;
        buildSlides(slideImages);
      }

      if (lbGallery) {
        lbGallery.innerHTML = galleryImgs.map(({ url, caption }) =>
          `<div class="lb-proj-gallery-item">
            <div class="lb-gallery-img-wrap">
              <img src="${url}" alt="${caption}" loading="lazy"/>
            </div>
            ${caption ? `<div class="lb-gallery-caption">${caption}</div>` : ''}
          </div>`
        ).join('');
      }
    }

  } catch (err) {
    console.error('[SASTUDIO]', err);
    lbContent.innerHTML = '<p class="lb-msg">Não foi possível carregar o conteúdo.</p>';
    if (lbAcf) lbAcf.style.display = 'none';
  } finally {
    lbLoader.style.display = 'none';
    lbContent.classList.add('visible');
  }
}

/* ══════════════════════════════════════════════════
   GALLERY DRAG-TO-SCROLL (com inércia)
══════════════════════════════════════════════════ */
(function () {
  const el = document.getElementById('lb-gallery');
  if (!el) return;

  let isDown = false, startX = 0, startScroll = 0;
  let velX = 0, lastX = 0, lastT = 0, rafId = null;

  function cancelMomentum() {
    if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
  }

  function momentum() {
    if (Math.abs(velX) < 0.4) return;
    el.scrollLeft += velX;
    velX *= 0.91;
    rafId = requestAnimationFrame(momentum);
  }

  el.addEventListener('mousedown', e => {
    cancelMomentum();
    isDown = true;
    startX = e.pageX;
    startScroll = el.scrollLeft;
    velX = 0; lastX = e.pageX; lastT = performance.now();
    el.style.cursor = 'grabbing';
    e.preventDefault();
  });

  window.addEventListener('mousemove', e => {
    if (!isDown) return;
    const now = performance.now();
    const dt  = now - lastT || 1;
    velX      = (lastX - e.pageX) / dt * 14;
    lastX = e.pageX; lastT = now;
    el.scrollLeft = startScroll + (startX - e.pageX);
  });

  window.addEventListener('mouseup', () => {
    if (!isDown) return;
    isDown = false;
    el.style.cursor = 'grab';
    momentum();
  });

  /* touch — inércia nativa do browser */
  let tX = 0, tS = 0;
  el.addEventListener('touchstart', e => {
    cancelMomentum();
    tX = e.touches[0].pageX;
    tS = el.scrollLeft;
  }, { passive: true });
  el.addEventListener('touchmove', e => {
    el.scrollLeft = tS - (e.touches[0].pageX - tX);
  }, { passive: true });
})();

/* ══════════════════════════════════════════════════
   PROJECTS LIST PANEL
══════════════════════════════════════════════════ */
(function () {
  const plPanel = document.getElementById('projects-list');
  const plGrid  = document.getElementById('pl-grid');
  const plCount = document.getElementById('pl-count');
  let plBuilt = false;

  const plSearch  = document.getElementById('pl-search');
  const plFilters = document.getElementById('pl-filters');
  let allCards = [];

  function filterCards() {
    const q   = plSearch ? plSearch.value.trim().toLowerCase() : '';
    const cat = plFilters?.querySelector('.pl-filter-btn.active')?.dataset.cat || '';
    let visible = 0;
    allCards.forEach(({ card, title, sub }) => {
      const matchQ   = !q   || title.includes(q) || sub.includes(q);
      const matchCat = !cat || sub.includes(cat.toLowerCase());
      const show = matchQ && matchCat;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (plCount) plCount.textContent = visible + ' projetos';
  }

  function buildProjectsList() {
    if (plBuilt) return;
    const pics = Array.from(document.querySelectorAll('#gallery .pic'));

    /* coleta categorias únicas para os filtros */
    const cats = [...new Set(
      pics.map(p => (p.dataset.sub || '').split('·')[0].trim()).filter(Boolean)
    )];
    if (plFilters) {
      const allBtn = document.createElement('button');
      allBtn.className = 'pl-filter-btn active';
      allBtn.textContent = 'Todos';
      allBtn.dataset.cat = '';
      plFilters.appendChild(allBtn);
      cats.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'pl-filter-btn';
        btn.textContent = cat;
        btn.dataset.cat = cat;
        plFilters.appendChild(btn);
      });
      plFilters.addEventListener('click', e => {
        const btn = e.target.closest('.pl-filter-btn');
        if (!btn) return;
        plFilters.querySelectorAll('.pl-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterCards();
      });
    }

    if (plSearch) {
      plSearch.addEventListener('input', filterCards);
      /* scroll com mouse na área de pesquisa não fecha o painel */
      plSearch.addEventListener('mousedown', e => e.stopPropagation());
    }

    pics.forEach(pic => {
      const imgEl = pic.querySelector('img');
      if (!imgEl) return;
      const title = (pic.querySelector('.pic-title')?.textContent || '').toLowerCase();
      const sub   = (pic.dataset.sub || '').toLowerCase();

      const card = document.createElement('div');
      card.className = 'pl-card';
      card.innerHTML = `
        <div class="pl-card-img"><img src="${imgEl.src}" alt="${title}" loading="lazy"/></div>
        <div class="pl-card-title">${pic.querySelector('.pic-title')?.textContent || ''}</div>
        <div class="pl-card-sub">${pic.dataset.sub || ''}</div>`;
      card.addEventListener('click', () => {
        closeProjectsList();
        openProject(pic);
      });
      plGrid.appendChild(card);
      allCards.push({ card, title, sub });
    });

    if (plCount) plCount.textContent = pics.length + ' projetos';
    plBuilt = true;
  }

  function openProjectsList() {
    if (typeof closeAbout === 'function') closeAbout();
    document.getElementById('contact-panel')?.classList.remove('open');
    buildProjectsList();
    plPanel.classList.add('open');
    document.querySelectorAll('[id="nav-projects"], .lb-nav-links a, #nav-mobile a').forEach(a => {
      if (a.id === 'nav-projects' || a.textContent.trim().toLowerCase() === 'projects')
        a.classList.add('nav-active');
    });
  }

  function closeProjectsList() {
    plPanel.classList.remove('open');
    document.querySelectorAll('.nav-active').forEach(a => a.classList.remove('nav-active'));
  }

  /* ── About panel ── */
  const aboutPanel = document.getElementById('about-panel');

  function openAbout() {
    closeProjectsList();
    document.getElementById('contact-panel')?.classList.remove('open');
    if (lb.classList.contains('open')) closeProject();
    aboutPanel.classList.add('open');
    document.querySelectorAll('.nav-links a, .lb-nav-links a, #nav-mobile a, .footer-nav a').forEach(a => {
      const t = a.textContent.trim().toLowerCase();
      if (a.classList.contains('nav-about-link') || t === 'about' || t === 'sobre') a.classList.add('nav-active');
    });
  }
  function closeAbout() {
    aboutPanel.classList.remove('open');
    document.querySelectorAll('.nav-active').forEach(a => a.classList.remove('nav-active'));
  }

  aboutPanel.addEventListener('mousedown',  e => e.stopPropagation());
  aboutPanel.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });
  aboutPanel.addEventListener('wheel',      e => e.stopPropagation(), { passive: true });

  document.querySelectorAll('.nav-links a, .lb-nav-links a, #nav-mobile a, .footer-nav a').forEach(a => {
    const t = a.textContent.trim().toLowerCase();
    if (a.classList.contains('nav-about-link') || t === 'about' || t === 'sobre') {
      a.addEventListener('click', e => {
        e.preventDefault();
        aboutPanel.classList.contains('open') ? closeAbout() : openAbout();
      });
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && aboutPanel.classList.contains('open')) closeAbout();
  });

  /* impede que cliques no painel ativem o pan do canvas */
  plPanel.addEventListener('mousedown',  e => e.stopPropagation());
  plPanel.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });

  /* "Projects" agora navega a sério para a página real (deixa de abrir o painel interno) */

  /* Escape fecha o painel */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && plPanel.classList.contains('open')) closeProjectsList();
  });
})();

/* ══════════════════════════════════════════════════
   CONTACT PANEL
══════════════════════════════════════════════════ */
(function () {
  const contactPanel = document.getElementById('contact-panel');
  if (!contactPanel) return;

  function openContact() {
    if (lb.classList.contains('open')) closeProject();
    /* fecha outros painéis */
    document.getElementById('about-panel')?.classList.remove('open');
    document.getElementById('projects-list')?.classList.remove('open');
    document.querySelectorAll('.nav-active').forEach(a => a.classList.remove('nav-active'));

    contactPanel.classList.add('open');
    contactPanel.scrollTop = 0;
    document.querySelectorAll(
      '.nav-links a, .lb-nav-links a, #nav-mobile a, .footer-nav a, .cp-nav-links a'
    ).forEach(a => {
      const t = a.textContent.trim().toLowerCase();
      if (a.classList.contains('nav-contact-link') || t === 'contact' || t === 'contato') a.classList.add('nav-active');
    });
  }

  function closeContact() {
    contactPanel.classList.remove('open');
    document.querySelectorAll('.nav-active').forEach(a => a.classList.remove('nav-active'));
  }

  /* bloqueia propagação para o canvas */
  contactPanel.addEventListener('mousedown',  e => e.stopPropagation());
  contactPanel.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });
  contactPanel.addEventListener('wheel',      e => e.stopPropagation(), { passive: true });

  /* wiring — todos os links "Contact"/"Contato" no site */
  document.querySelectorAll(
    '.nav-links a, .lb-nav-links a, #nav-mobile a, .footer-nav a'
  ).forEach(a => {
    const t = a.textContent.trim().toLowerCase();
    if (a.classList.contains('nav-contact-link') || t === 'contact' || t === 'contato') {
      a.addEventListener('click', e => {
        e.preventDefault();
        contactPanel.classList.contains('open') ? closeContact() : openContact();
      });
    }
  });

  /* Escape */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && contactPanel.classList.contains('open')) closeContact();
  });
})();

/* ══════════════════════════════════════════════════
   HOVER PARALLAX
══════════════════════════════════════════════════ */
function initParallax() {
  let activePic = null;
  let activeVid = null;

  function leaveActivePic() {
    if (!activePic) return;
    const img = activePic.querySelector('img');
    activePic.style.zIndex = '';
    gsap.to(img, { scale: 1.06, duration: 0.85, ease: 'power3.out' });
    if (activeVid) {
      const vid = activeVid;
      gsap.to(vid, { opacity: 0, duration: 0.3, onComplete: () => {
        vid.pause();
        vid.currentTime = 0;
      }});
      activeVid = null;
    }
    document.body.classList.remove('on-pic');
    activePic = null;
  }

  window.addEventListener('mousemove', e => {
    if (document.body.classList.contains('grabbing')) return;

    const el  = document.elementFromPoint(e.clientX, e.clientY);
    const pic = el?.closest('.pic');

    if (pic !== activePic) {
      if (activePic) leaveActivePic();
      /* entra no novo card */
      if (pic) {
        const img = pic.querySelector('img');
        pic.style.zIndex = '100';
        gsap.to(img, { scale: 1.14, duration: 0.7, ease: 'power2.out' });
        document.body.classList.add('on-pic');
        const vid = pic.querySelector('.pic-hover-vid');
        if (vid) {
          activeVid = vid;
          vid.play().catch(() => {});
          gsap.to(vid, { opacity: 1, duration: 0.45, ease: 'power2.out' });
        }
        activePic = pic;
      }
    }
  });

  /* se o rato sair da janela do browser por completo (barra de endereço,
     outra aba, fora do ecrã) ou a página perder o foco, garante que o
     vídeo em hover para na mesma — senão fica "preso" a tocar sozinho */
  document.addEventListener('mouseout', e => {
    if (!e.relatedTarget) leaveActivePic();
  });
  window.addEventListener('blur', leaveActivePic);
}
