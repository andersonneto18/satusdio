function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

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
      const media  = post._embedded?.['wp:featuredmedia']?.[0];
      const imgUrl = media?.source_url;
      if (!imgUrl) return;

      const title = post.title.rendered;
      const year  = new Date(post.date).getFullYear();
      const terms = (post._embedded?.['wp:term'] || []).flat();
      const cat   = terms[0]?.name || '';
      const sub   = cat ? `${cat} · ${year}` : String(year);

      /* proporção real da imagem (largura/altura), vinda do WordPress —
         usada no layout para a caixa encaixar exatamente na imagem,
         criando a variedade de alturas sem cortar nada */
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
/* approxCols = quantas colunas cabem à largura normal do ecrã — usado só
   para calcular a largura de referência de uma coluna. */
const BREAKPOINTS = [
  { maxW: 480,  gap: 8,  approxCols: 2 },
  { maxW: 768,  gap: 8,  approxCols: 3 },
  { maxW: 1024, gap: 10, approxCols: 3 },
  { maxW: Infinity, gap: 10, approxCols: 4 },
];

function getMasonryConfig() {
  const vw = window.innerWidth;
  return BREAKPOINTS.find(c => vw <= c.maxW);
}

/* Cada foto usa sempre a SUA proporção real (dataset.aspect, vinda das
   dimensões reais do WordPress) para decidir a altura: altura = largura
   da coluna ÷ aspect. Fotos em retrato ficam mais compridas na
   vertical, fotos em paisagem mais compridas na horizontal — nunca há
   corte (object-fit: cover não recorta nada porque a caixa já é feita
   à medida da proporção real). */
const DEFAULT_ASPECT = 4 / 3;

/* Para cada coluna: decide que fotos entram (na ordem em que aparecem,
   usando a largura da coluna como referência) até chegar perto da
   altura do ecrã, depois reescala a coluna toda (largura + cada altura,
   no mesmo fator) para fechar exatamente essa altura — sem isto sobrava
   um vão em branco no fundo sempre que a próxima foto não coubesse
   certinha. Como largura e altura escalam sempre juntas, a proporção
   real de cada foto mantém-se perfeita (nunca corta, nunca distorce).
   Novas colunas abrem-se à direita, reveladas ao fazer scroll
   horizontal. */
function layoutMasonry() {
  const { gap, approxCols } = getMasonryConfig();
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const baseColW = (vw - gap * (approxCols + 1)) / approxCols;
  const targetH  = vh - gap * 2;

  const pics = Array.from(gallery.querySelectorAll('.pic'));

  let idx = 0, x = gap;

  while (idx < pics.length) {
    const group = [];
    let sumH = 0;
    while (idx < pics.length) {
      const pic    = pics[idx];
      const aspect = parseFloat(pic.dataset.aspect) || DEFAULT_ASPECT;
      const h      = baseColW / aspect;
      /* já tem pelo menos 1 foto e esta próxima passaria bastante da
         altura do ecrã — fica para a coluna seguinte (a não ser que seja
         a última foto de todas, aí tem de entrar nesta coluna na mesma) */
      if (group.length && sumH + h + group.length * gap > targetH && idx < pics.length - 1) break;
      group.push({ pic, h });
      sumH += h;
      idx++;
      if (sumH + (group.length - 1) * gap >= targetH) break;
    }

    const nGaps = Math.max(group.length - 1, 0) * gap;
    const scale = (targetH - nGaps) / sumH;
    const colW  = baseColW * scale;

    let y = gap;
    group.forEach(({ pic, h }) => {
      const scaledH = h * scale;
      pic.style.left   = x + 'px';
      pic.style.top    = y + 'px';
      pic.style.width  = colW + 'px';
      pic.style.height = scaledH + 'px';
      y += scaledH + gap;
    });

    x += colW + gap;
  }

  gallery.style.width  = x + 'px';
  gallery.style.height = vh + 'px';
}

window.addEventListener('resize', () => {
  layoutMasonry();
  gsap.set(gallery.querySelectorAll('.pic'), { x: 0, y: 0 });
});

/* ══════════════════════════════════════════════════
   ENTRANCE — hero → gallery burst
══════════════════════════════════════════════════ */
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
  const hud  = document.getElementById('hud');
  const zl   = document.getElementById('zoom-label');
  const hero = document.getElementById('hero');

  gsap.set(pics, {
    scale: 0.08,
    x: () => gsap.utils.random(-140, 140),
    y: () => gsap.utils.random(-90, 90),
  });

  gsap.timeline()
    .to('#h-name', { opacity: 1, y: 0, duration: 0.75, ease: 'power3.out' }, 0.1)
    .call(() => {
      const done = () => {
        gsap.to([hud, zl], { opacity: 1, duration: 0.8 });
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
   CANVAS — zoom (scroll), pan (drag), wobble
══════════════════════════════════════════════════ */
function initCanvas() {
  const pics = document.querySelectorAll('.pic');
  const zl   = document.getElementById('zoom-label');

  /* começa encostado à esquerda (sem centrar) — as colunas seguintes
     só aparecem ao rolar, tal como na referência */
  const initCx = 0;

  let s = 1, tx = initCx, ty = 0;
  let tS = 1, tTx = initCx, tTy = 0;
  let rawTx = initCx, rawTy = 0;
  let drag = false, dragMoved = false;
  let dragOriginTx = 0, dragOriginTy = 0, dragStartX = 0, dragStartY = 0;

  /* puxão elástico ao arrastar: o deslocamento nunca passa muito de
     ELASTIC_MAX, e ao soltar volta sempre para onde estava antes —
     arrastar é só um efeito, não navega permanentemente */
  const ELASTIC_MAX = 90;
  function elasticPull(delta) {
    return ELASTIC_MAX * delta / (ELASTIC_MAX + Math.abs(delta));
  }

  function getBounds(sc) {
    const W  = window.innerWidth;
    const H  = window.innerHeight;
    const gW = gallery.offsetWidth;
    const gH = gallery.offsetHeight;
    const mg = 55;
    /* xMax/yMax = 0: nunca deixa ver espaço em branco antes da primeira
       coluna (só há folga elástica no fim da galeria, não no início) */
    const xMin = -(gW * sc - W) - mg;
    const xMax = 0;
    const yMin = -(gH * sc - H) - mg;
    const yMax = 0;
    return { xMin, xMax, yMin, yMax };
  }

  function clamp() {
    const b = getBounds(tS);
    if (drag) return; /* enquanto arrasta, o próprio mousemove/touchmove aplica o elástico */
    tTx = Math.max(b.xMin, Math.min(b.xMax, tTx));
    tTy = Math.max(b.yMin, Math.min(b.yMax, tTy));
    rawTx = tTx; rawTy = tTy;
  }

  (function tick() {
    const now = performance.now();

    clamp();
    const lf = 0.085;
    s  += (tS  - s)  * lf;
    tx += (tTx - tx) * lf;
    ty += (tTy - ty) * lf;

    gallery.style.transform = `translate(${tx}px,${ty}px) scale(${s})`;

    zl.textContent = Math.round(s * 100) + '%';
    requestAnimationFrame(tick);
  })();

  function clampS(v) { return Math.max(0.8, Math.min(2.5, v)); }

  function zoomAt(cx, cy, factor) {
    const ns = clampS(tS * factor);
    tTx = cx - (cx - tTx) * (ns / tS);
    tTy = cy - (cy - tTy) * (ns / tS);
    tS  = ns;
    clamp();
  }

  window.addEventListener('wheel', e => {
    if (lb.classList.contains('open')) return;
    if (e.target.closest('#projects-list, #about-panel')) return;
    e.preventDefault();

    /* pinch-to-zoom no trackpad (ou ctrl+scroll) continua a fazer zoom */
    if (e.ctrlKey) {
      zoomAt(e.clientX, e.clientY, e.deltaY < 0 ? 1.1 : 0.91);
      return;
    }

    /* scroll normal revela mais imagens, deslocando a galeria na horizontal —
       usa o maior delta (vertical do rato ou horizontal do trackpad).
       o clamp() do tick já corrige os limites a cada frame (sem drag=true),
       por isso nunca fica "preso" fora dos limites. */
    const delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    rawTx -= delta * 1.3;
    tTx = rawTx;
  }, { passive: false });

  window.addEventListener('mousedown', e => {
    if (e.target.closest('nav')) return;
    drag = true; dragMoved = false;
    dragOriginTx = tTx; dragOriginTy = tTy;
    dragStartX = e.clientX; dragStartY = e.clientY;
    document.body.classList.add('grabbing');
    document.body.classList.remove('on-pic');
  });

  window.addEventListener('mousemove', e => {
    if (!drag) return;
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;
    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) dragMoved = true;
    tTx = dragOriginTx + elasticPull(dx);
    tTy = dragOriginTy + elasticPull(dy);
  });

  window.addEventListener('mouseup', e => {
    if (!drag) return;
    drag = false;
    if (!dragMoved) {
      const pic = e.target.closest('.pic');
      if (pic?.dataset.href) openProject(pic);
    }
    /* solta o elástico — volta sempre para onde estava antes de arrastar */
    tTx = dragOriginTx; tTy = dragOriginTy;
    rawTx = tTx; rawTy = tTy;
    document.body.classList.remove('grabbing');
  });

  let lastDist = 0;
  window.addEventListener('touchstart', e => {
    if (e.touches.length === 1) {
      drag = true; dragMoved = false;
      dragOriginTx = tTx; dragOriginTy = tTy;
      dragStartX = e.touches[0].clientX; dragStartY = e.touches[0].clientY;
    } else if (e.touches.length === 2) {
      drag = false;
      lastDist = Math.hypot(e.touches[1].clientX - e.touches[0].clientX, e.touches[1].clientY - e.touches[0].clientY);
    }
  }, { passive: true });

  window.addEventListener('touchmove', e => {
    e.preventDefault();
    if (e.touches.length === 1 && drag) {
      const dx = e.touches[0].clientX - dragStartX;
      const dy = e.touches[0].clientY - dragStartY;
      if (Math.abs(dx) > 4 || Math.abs(dy) > 4) dragMoved = true;
      tTx = dragOriginTx + elasticPull(dx);
      tTy = dragOriginTy + elasticPull(dy);
    } else if (e.touches.length === 2) {
      const d  = Math.hypot(e.touches[1].clientX - e.touches[0].clientX, e.touches[1].clientY - e.touches[0].clientY);
      const cx = (e.touches[0].clientX + e.touches[1].clientX) / 2;
      const cy = (e.touches[0].clientY + e.touches[1].clientY) / 2;
      zoomAt(cx, cy, d / lastDist); lastDist = d;
    }
  }, { passive: false });

  window.addEventListener('touchend', () => {
    if (drag) {
      drag = false;
      tTx = dragOriginTx; tTy = dragOriginTy;
      rawTx = tTx; rawTy = tTy;
    }
  });

  window.addEventListener('dblclick', e => {
    if (e.target.closest('nav')) return;
    const reset = Math.abs(tS - 1) > 0.08 || Math.abs(tTx) > 8 || Math.abs(tTy) > 8;
    if (reset) { tS = 1; tTx = 0; tTy = 0; } else { zoomAt(e.clientX, e.clientY, 2.2); }
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
const lbCoverMedia = document.getElementById('lb-cover-media');
const lbProjTitle = document.getElementById('lb-proj-title');
const lbProjMeta  = document.getElementById('lb-proj-meta');
const lbExtLink   = document.getElementById('lb-ext-link');
const lbClose     = document.getElementById('lb-close');
const lbContent   = document.getElementById('lb-content');
const lbLoader    = document.getElementById('lb-loader');

function isVideoUrl(url) {
  return /\.(mp4|webm|mov|ogg)(\?|$)/i.test(url);
}

/* capa do projeto: vídeo (hover_gif) tocado uma vez (sem loop); quando
   acaba, passa a fazer transição (crossfade) pelas imagens da galeria
   — assim que estas estiverem disponíveis (o vídeo normalmente acaba
   antes de a galeria ter sido carregada, por isso os dois "sinais"
   ficam à espera um do outro em maybeStartCoverSlideshow). Sem vídeo,
   mostra só a imagem de destaque, estática. */
let coverGalleryImgs   = [];
let coverVideoEnded    = false;
let coverGalleryReady  = false;
let coverSlideshowTimer = null;

function stopCoverSlideshow() {
  if (coverSlideshowTimer) { clearInterval(coverSlideshowTimer); coverSlideshowTimer = null; }
}

function startCoverSlideshow(images) {
  stopCoverSlideshow();
  if (!images || images.length < 2) return;
  lbCoverMedia.innerHTML = '';
  const slides = images.map((url, i) => {
    const el = document.createElement('div');
    el.className = 'lb-cover-slide' + (i === 0 ? ' active' : '');
    el.style.backgroundImage = `url("${url}")`;
    lbCoverMedia.appendChild(el);
    return el;
  });
  let idx = 0;
  coverSlideshowTimer = setInterval(() => {
    slides[idx].classList.remove('active');
    idx = (idx + 1) % slides.length;
    slides[idx].classList.add('active');
  }, 4000);
}

function maybeStartCoverSlideshow() {
  if (coverVideoEnded && coverGalleryReady) startCoverSlideshow(coverGalleryImgs);
}

function setCoverMedia(featSrc, hoverGif) {
  stopCoverSlideshow();
  coverGalleryImgs = [];
  coverVideoEnded = false;
  coverGalleryReady = false;
  lbCoverMedia.innerHTML = '';
  if (hoverGif && isVideoUrl(hoverGif)) {
    const vid = document.createElement('video');
    vid.src = hoverGif; vid.muted = true; vid.autoplay = true; vid.playsInline = true;
    vid.addEventListener('ended', () => { coverVideoEnded = true; maybeStartCoverSlideshow(); });
    lbCoverMedia.appendChild(vid);
  } else {
    const img = document.createElement('img');
    img.src = featSrc; img.alt = '';
    lbCoverMedia.appendChild(img);
  }
}

/* botão Back do browser fecha o lightbox */
window.addEventListener('popstate', () => {
  if (lb.classList.contains('open')) closeProject();
});

/* keyboard navigation */
document.addEventListener('keydown', e => {
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') closeProject();
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

  /* populate UI */
  lbProjTitle.textContent = title;
  lbProjMeta.textContent  = meta;
  /* cópia invisível do título/meta no painel da Descrição — ver
     .lb-desc-spacer em style.css, garante o alinhamento com "Dados do
     projeto:" só com CSS (mesma marcação = mesma altura). */
  const lbTitleSpacer = document.querySelector('.lb-proj-title-spacer');
  const lbMetaSpacer  = document.querySelector('.lb-proj-meta-spacer');
  if (lbTitleSpacer) lbTitleSpacer.textContent = title;
  if (lbMetaSpacer)  lbMetaSpacer.textContent  = meta;
  if (lbExtLink) lbExtLink.href = href || '#';
  lbContent.classList.remove('visible');
  lbContent.innerHTML     = '';
  lbLoader.style.display  = 'flex';
  lbView.scrollTop        = 0;
  if (window.resetLbTrack) window.resetLbTrack();
  populateRelated(pic);
  flushPrefetchQueue();

  setCoverMedia(featSrc, hoverGif);

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
  history.pushState(null, document.title, '/');
  gsap.to(lbClose, { opacity: 0, pointerEvents: 'none', duration: 0.2 });
  gsap.to(lbView, { opacity: 0, duration: 0.35, ease: 'power2.in',
    onComplete: () => {
      lb.classList.remove('open');
      lbView.classList.remove('show');
      lbView.style.opacity = '';
      lbView.scrollTop = 0;
      stopCoverSlideshow();
      lbCoverMedia.innerHTML = '';
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

  const relatedPanel = document.getElementById('lb-panel-related');
  if (relatedPanel) relatedPanel.style.display = grid.children.length ? '' : 'none';
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

  const lbAcf = document.getElementById('lb-acf');
  if (lbAcf) lbAcf.innerHTML = '';
  document.querySelectorAll('#lb-track .lb-photo-panel').forEach(p => p.remove());

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

    /* disponibiliza as imagens da galeria para a transição da capa
       (ver setCoverMedia/maybeStartCoverSlideshow) assim que o vídeo
       de hover (se existir) tiver terminado */
    coverGalleryImgs = galleryImgs.map(g => g.url);
    coverGalleryReady = true;
    maybeStartCoverSlideshow();

    if (galleryImgs.length) {
      /* cada foto da galeria vira o seu próprio painel horizontal,
         inserido a seguir ao painel da Descrição — sem faixa de scroll
         interna, tal como o resto do #lb-track */
      const descPanel = document.getElementById('lb-panel-desc');
      if (descPanel) {
        /* 2 fotos por painel, lado a lado (ver .lb-photo-panel) — se
           sobrar 1 foto sozinha (número ímpar), ocupa o painel todo */
        const photoPanels = [];
        for (let i = 0; i < galleryImgs.length; i += 2) {
          const pair = galleryImgs.slice(i, i + 2);
          const panel = document.createElement('section');
          panel.className = 'lb-panel lb-panel-scrollable lb-photo-panel';
          panel.innerHTML = `
            <div class="lb-title-block lb-desc-spacer" aria-hidden="true">
              <div class="lb-proj-meta-spacer">${lbProjMeta.textContent}</div>
              <h1 class="lb-proj-title-spacer">${lbProjTitle.textContent}</h1>
            </div>
            <div class="lb-photo-row">` +
            pair.map(({ url, caption }) => `
              <div class="lb-photo-item">
                <img src="${url}" alt="${caption}" loading="lazy"/>
                ${caption ? `<div class="lb-photo-caption">${caption}</div>` : ''}
              </div>`).join('') +
            `</div>`;
          photoPanels.push(panel);
        }
        descPanel.after(...photoPanels);
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
   Usado no carrossel "Confira outros projetos" (#lb-related-grid).
══════════════════════════════════════════════════ */
function initDragScroll(elId) {
  const el = document.getElementById(elId);
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
}
initDragScroll('lb-related-grid');

/* ══════════════════════════════════════════════════
   LIGHTBOX — NAVEGAÇÃO HORIZONTAL ENTRE PAINÉIS
   (Dados/Capa/Descrição → Galeria → Relacionados), ao
   estilo do scroll da home. A roda do rato desloca o
   #lb-track horizontalmente; dentro de um painel com
   overflow vertical (texto longo), o scroll nativo funciona
   normalmente até chegar ao topo/fundo — só aí passa a
   trocar de painel.
══════════════════════════════════════════════════ */
(function () {
  const track = document.getElementById('lb-track');
  if (!track) return;

  const scrollbarThumb = document.getElementById('lb-scrollbar-thumb');

  let tx = 0, tTx = 0;

  function visiblePanels() {
    return Array.from(track.querySelectorAll('.lb-panel')).filter(p => p.style.display !== 'none');
  }

  function bounds() {
    const n = Math.max(visiblePanels().length, 1);
    return { min: -(n - 1) * window.innerWidth, max: 0 };
  }

  function clampTx() {
    const b = bounds();
    tTx = Math.max(b.min, Math.min(b.max, tTx));
  }

  /* barra vertical de progresso — desce à medida que se avança pelos
     painéis horizontais, como substituta visual da scrollbar nativa
     (que não existe aqui, já que a navegação é lateral). */
  function updateScrollbar() {
    if (!scrollbarThumb) return;
    const n = Math.max(visiblePanels().length, 1);
    const b = bounds();
    const progress = b.min !== 0 ? Math.min(1, Math.max(0, tx / b.min)) : 0;
    const thumbPct = 100 / n;
    scrollbarThumb.style.height = thumbPct + '%';
    scrollbarThumb.style.top = (progress * (100 - thumbPct)) + '%';
  }

  (function tick() {
    clampTx();
    tx += (tTx - tx) * 0.14;
    track.style.transform = `translateX(${tx}px)`;
    updateScrollbar();
    requestAnimationFrame(tick);
  })();

  window.addEventListener('wheel', e => {
    if (!lb.classList.contains('open')) return;

    /* dentro de um painel com scroll vertical próprio (texto longo),
       deixa o scroll nativo agir até chegar ao topo/fundo. Um overflow
       residual pequeno (poucos px, ex: arredondamentos de layout) é
       ignorado — sem isto, painéis quase do tamanho do ecrã faziam um
       pequeno scroll "fantasma" antes de mudar de painel. */
    const scrollable = e.target.closest('.lb-panel-scrollable');
    if (scrollable && scrollable.scrollHeight - scrollable.clientHeight > 24) {
      const atTop     = scrollable.scrollTop <= 0;
      const atBottom  = scrollable.scrollTop + scrollable.clientHeight >= scrollable.scrollHeight - 1;
      const goingDown = e.deltaY > 0;
      if ((goingDown && !atBottom) || (!goingDown && !atTop)) return;
    }

    e.preventDefault();
    const delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    tTx -= delta * 1.3;
  }, { passive: false });

  window.resetLbTrack = function () {
    tx = 0; tTx = 0;
    track.style.transform = 'translateX(0px)';
  };
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
    document.querySelectorAll('.lb-nav-links a, .footer-nav a').forEach(a => {
      if (a.textContent.trim().toLowerCase() === 'projects') a.classList.add('nav-active');
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
    document.querySelectorAll('.lb-nav-links a, .footer-nav a').forEach(a => {
      if (a.textContent.trim().toLowerCase() === 'about') a.classList.add('nav-active');
    });
  }
  function closeAbout() {
    aboutPanel.classList.remove('open');
    document.querySelectorAll('.nav-active').forEach(a => a.classList.remove('nav-active'));
  }

  aboutPanel.addEventListener('mousedown',  e => e.stopPropagation());
  aboutPanel.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });
  aboutPanel.addEventListener('wheel',      e => e.stopPropagation(), { passive: true });

  document.querySelectorAll('.lb-nav-links a, .footer-nav a').forEach(a => {
    /* só abre o painel interno se o link não tiver destino real —
       links com href a sério (ex: /about/) navegam normalmente */
    const href = a.getAttribute('href') || '#';
    if (a.textContent.trim().toLowerCase() === 'about' && href === '#') {
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
      '.lb-nav-links a, .footer-nav a, .cp-nav-links a'
    ).forEach(a => {
      if (a.textContent.trim().toLowerCase() === 'contact') a.classList.add('nav-active');
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

  /* wiring — todos os links "Contact" no site (só abre o painel interno
     se o link não tiver destino real) */
  document.querySelectorAll(
    '.lb-nav-links a, .footer-nav a'
  ).forEach(a => {
    const href = a.getAttribute('href') || '#';
    if (a.textContent.trim().toLowerCase() === 'contact' && href === '#') {
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
    activePic.style.zIndex = '';
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
        pic.style.zIndex = '100';
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
