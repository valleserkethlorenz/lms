<?php
session_start();
require_once __DIR__.'/config/dbconnect.php';
require_once __DIR__.'/includes/storageService.php';
$publicPosts = [];
$postResult = $conn->query(
  "SELECT id, title, description, created_at FROM tbl_gallery_posts WHERE is_archived = 0 ORDER BY created_at DESC"
);

if ($postResult) {
  $imageStmt = $conn->prepare(
    "SELECT filename FROM tbl_gallery_images WHERE post_id = ? AND is_archived = 0 ORDER BY display_order, id"
  );

  while ($post = $postResult->fetch_assoc()) {
    $postId = (int) $post['id'];
    $imageStmt->bind_param('i', $postId);
    $imageStmt->execute();
    $images = [];
    $imageResult = $imageStmt->get_result();

    while ($image = $imageResult->fetch_assoc()) {
      $fn = $image['filename'];
      if ($fn !== '') {
        $images[] = StorageService::isCloudinary($fn) ? $fn : basename($fn);
      }
    }

    if ($images) {
      $post['id'] = $postId;
      $post['images'] = $images;
      $publicPosts[] = $post;
    }
  }

  $imageStmt->close();
}

$totalImages = array_sum(array_map(fn($post) => count($post['images']), $publicPosts));

include 'includes/header.php';
include 'includes/bar.php';
?>
<style>
  .public-gallery {
    /* Same canvas as every other page (see includes/header.php) */
    background: #ffffff;
    min-height: 100vh;
  }

  .gallery-main {
    padding: 108px 0 72px;
  }

  .gallery-hero-wrap {
    width: min(92vw, 1500px);
    margin: 0 auto 38px;
  }

  .gallery-grid-wrap {
    width: min(92vw, 1500px);
    margin: 0 auto;
  }

  .gallery-toolbar {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(260px, .85fr);
    align-items: center;
    gap: clamp(18px, 3.2vw, 38px);
    min-height: 420px;
    padding: clamp(24px, 3.6vw, 34px);
    overflow: hidden;
    border: 1px solid rgba(53, 109, 141, .12);
    border-radius: 28px;
    background: linear-gradient(135deg, #ebf9fe 0%, #e8f5fb 42%, #edf9f0 100%);
    box-shadow: 0 22px 48px rgba(19, 48, 71, .12);
    z-index: 1;
  }

  .gallery-toolbar::before {
    content: "";
    position: absolute;
    width: 430px;
    height: 430px;
    right: -168px;
    top: -218px;
    border-radius: 50%;
    background: #bde8f1;
  }

  .gallery-toolbar::after {
    content: "";
    position: absolute;
    width: 190px;
    height: 190px;
    left: 42%;
    bottom: -155px;
    border-radius: 50%;
    background: #d5f0db;
  }

  .gallery-toolbar-copy {
    min-width: 0;
  }

  .gallery-toolbar-copy,
  .gallery-feature {
    position: relative;
    z-index: 1;
  }

  .gallery-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 10px;
    color: #287279;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .13em;
    text-transform: uppercase;
  }

  .gallery-toolbar h1 {
    max-width: 620px;
    margin: 0;
    color: #102b47;
    font-size: clamp(2.15rem, 4.4vw, 3.6rem);
    font-weight: 800;
    letter-spacing: -.06em;
    line-height: 1.03;
  }

  .gallery-toolbar p {
    max-width: 570px;
    margin: 12px 0 0;
    color: #4f6c80;
    font-size: .95rem;
    line-height: 1.7;
  }

  .gallery-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
  }

  .gallery-stat {
    padding: 7px 11px;
    border: 1px solid rgba(119, 170, 191, .55);
    border-radius: 999px;
    background: rgba(255, 255, 255, .78);
    color: #315b72;
    font-size: .76rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
  }

  .gallery-search {
    position: absolute;
    right: clamp(20px, 3vw, 38px);
    bottom: clamp(18px, 2.6vw, 28px);
    width: min(100%, 390px);
    margin-top: 0;
  }

  .gallery-search i {
    position: absolute;
    left: 15px;
    top: 50%;
    z-index: 2;
    transform: translateY(-50%);
    color: #4d6480;
    opacity: .9;
  }

  .gallery-search input {
    height: 48px;
    padding-left: 42px !important;
    border: 1px solid rgba(118, 150, 174, .18);
    border-radius: 14px;
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 12px 30px rgba(3, 24, 40, .12);
    font-size: .88rem;
  }

  .gallery-search input:focus {
    box-shadow: 0 0 0 4px rgba(104, 186, 255, .24), 0 12px 30px rgba(3, 24, 40, .12);
    border-color: rgba(104, 186, 255, .45);
  }

  .gallery-feature {
    min-height: 265px;
    overflow: hidden;
    border-radius: 19px;
    background: #123b5b;
    box-shadow: 0 17px 25px rgba(21, 69, 98, .23);
  }

  .gallery-feature img {
    width: 100%;
    height: 265px;
    object-fit: cover;
    opacity: .86;
  }

  .gallery-feature::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(6, 27, 47, .04), rgba(6, 27, 47, .9));
  }

  .gallery-feature-copy {
    position: absolute;
    z-index: 1;
    right: 20px;
    bottom: 18px;
    left: 20px;
    color: #fff;
  }

  .gallery-feature-label {
    display: inline-block;
    margin-bottom: 5px;
    color: #aee9f3;
    font-size: .69rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
  }

  .gallery-feature-title {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 750;
    line-height: 1.35;
  }

  .gallery-feature-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 265px;
    color: #b9e5ed;
    font-size: 4.5rem;
  }

  .gallery-collection {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    border: 1px solid #cfe0ef;
    border-radius: 20px;
    background: linear-gradient(145deg, #fff 0%, #f2f8fc 100%);
    box-shadow: 0 9px 25px rgba(16, 24, 40, .07);
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
  }

  .gallery-entry {
    display: flex;
  }

  .gallery-collection:hover {
    transform: translateY(-4px);
    border-color: #9bc4e3;
    box-shadow: 0 16px 34px rgba(16, 24, 40, .12);
  }

  .collection-images {
    display: grid;
    height: 310px;
    gap: 3px;
    background: #dfe6ef;
  }

  .collection-images.count-1 {
    grid-template-columns: 1fr;
  }

  .collection-images.count-2 {
    grid-template-columns: 1fr 1fr;
  }

  .collection-images.count-many {
    grid-template-columns: 1.45fr 1fr;
    grid-template-rows: 1fr 1fr;
  }

  .collection-images.count-many .gallery-photo:first-child {
    grid-row: 1/3;
  }

  .gallery-photo {
    position: relative;
    overflow: hidden;
    border: 0;
    padding: 0;
    background: #dce3eb;
    cursor: pointer;
  }

  .gallery-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s, filter .35s;
  }

  .gallery-photo:hover img {
    transform: scale(1.06);
    filter: brightness(.79);
  }

  .photo-more {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(5, 20, 48, .55);
    color: #fff;
    font-size: 1.35rem;
    font-weight: 750;
  }

  .collection-info {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 23px 24px 21px;
    border-top: 3px solid #397cae;
  }

  .collection-title {
    margin: 0 0 8px;
    color: #172033;
    font-size: 1.28rem;
    font-weight: 750;
    letter-spacing: -.02em;
  }

  .collection-description {
    display: -webkit-box;
    overflow: hidden;
    margin: 0 0 17px;
    color: #667085;
    font-size: .9rem;
    line-height: 1.65;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    line-clamp: 3;
  }

  .collection-meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px;
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid #dce9f3;
    color: #55718a;
    font-size: .77rem;
  }

  .collection-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .gallery-empty {
    padding: 80px 20px;
    text-align: center;
    border: 1px dashed #bbc8d7;
    border-radius: 18px;
    background: #fff;
    color: #667085;
  }

  .gallery-empty i {
    display: block;
    margin-bottom: 14px;
    color: #9aabc0;
    font-size: 3rem;
  }

  .gallery-pagination {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 30px;
  }

  .gallery-pagination button {
    width: 40px;
    height: 40px;
    border: 1px solid #cfd8e3;
    border-radius: 9px;
    background: #fff;
    color: #345;
  }

  .gallery-pagination button.active {
    background: #123a63;
    border-color: #123a63;
    color: #fff;
  }

  .gallery-pagination button:disabled {
    opacity: .45;
  }

  .gallery-lightbox .modal-content {
    border: 0 !important;
    background: rgba(3, 11, 24, .98) !important;
    box-shadow: none !important;
  }

  .gallery-lightbox .modal-body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 55px;
  }

  .gallery-lightbox img {
    max-width: 100%;
    max-height: 86vh;
    object-fit: contain;
    box-shadow: 0 16px 50px rgba(0, 0, 0, .45);
  }

  .gallery-lightbox .btn-close {
    position: absolute;
    right: 24px;
    top: 22px;
    z-index: 4;
  }

  @media(max-width:991.98px) {
    .gallery-main {
      padding-top: 115px
    }

    .gallery-toolbar {
      grid-template-columns: 1fr;
      gap: 27px;
      min-height: 380px
    }

    .gallery-search {
      position: static;
      width: 100%;
      margin-top: 12px
    }

    .gallery-feature {
      max-width: 500px;
      width: 100%
    }

    .collection-images {
      height: 230px
    }
  }

  @media(max-width:575.98px) {
    .gallery-main {
      padding-top: 100px
    }

    .gallery-toolbar {
      padding: 27px 23px;
      border-radius: 20px
    }

    .gallery-toolbar h1 {
      font-size: 2.35rem
    }

    .gallery-search {
      width: 100%
    }

    .gallery-feature,
    .gallery-feature img,
    .gallery-feature-fallback {
      min-height: 210px;
      height: 210px
    }

    #galleryGrid {
      --bs-gutter-x: .65rem;
      --bs-gutter-y: .65rem
    }

    .gallery-collection {
      border-radius: 12px
    }

    .collection-images {
      height: 112px;
      gap: 2px
    }

    .collection-info {
      padding: 11px 10px 10px;
      border-top-width: 2px
    }

    .collection-title {
      margin-bottom: 5px;
      font-size: .79rem;
      line-height: 1.35
    }

    .collection-description {
      margin-bottom: 8px;
      font-size: .66rem;
      line-height: 1.45;
      -webkit-line-clamp: 2;
      line-clamp: 2
    }

    .collection-meta {
      padding-top: 8px;
      font-size: .61rem;
      line-height: 1.35
    }

    .collection-meta span:last-child {
      display: none
    }

    .gallery-lightbox .modal-body {
      padding: 45px 12px
    }
  }

</style>

<body class="public-gallery">
  <main class="gallery-main">
    <div class="gallery-hero-wrap">
      <div class="gallery-toolbar">
        <div class="gallery-toolbar-copy"><span class="gallery-eyebrow"><i class="bi bi-stars"></i> The city
            in motion</span>
          <h1>Every season has a story worth sharing.</h1>
          <p>Browse the cheers, the teamwork, and the standout moments captured across our local leagues.</p>
          <div class="gallery-stats"><span class="gallery-stat"><i class="bi bi-collection me-1"></i><span
                id="galleryResultCount"><?= count($publicPosts) ?>
                collection<?= count($publicPosts)===1?'':'s' ?></span></span><span class="gallery-stat"><i
                class="bi bi-images me-1"></i><?= $totalImages ?> photos</span></div>
        </div>
        <div class="gallery-search"><i class="bi bi-search"></i><input type="search" id="gallerySearch"
            class="form-control" placeholder="Find a moment or event" aria-label="Search gallery posts">
        </div>
      </div>
    </div>
    <div class="gallery-grid-wrap">
      <?php if($publicPosts): ?><div class="row g-4" id="galleryGrid">
        <?php foreach($publicPosts as $post): $visibleImages=array_slice($post['images'],0,3);$countClass=count($visibleImages)===1?'count-1':(count($visibleImages)===2?'count-2':'count-many'); ?>
        <div class="col-6 col-md-4 gallery-entry"
          data-title="<?= htmlspecialchars(mb_strtolower($post['title'].' '.$post['description']),ENT_QUOTES,'UTF-8') ?>"
          data-images="<?= htmlspecialchars(json_encode(array_map(fn($fn)=>StorageService::isCloudinary($fn)?$fn:(is_file(__DIR__.'/assets/uploads/'.rawurlencode($fn))?'assets/uploads/'.rawurlencode($fn):'assets/images/default-league.svg'),$post['images'])),ENT_QUOTES,'UTF-8') ?>">
          <article class="gallery-collection">
            <div class="collection-images <?= $countClass ?>">
              <?php foreach($visibleImages as $index=>$fn): $imgPath=StorageService::isCloudinary($fn)?$fn:'assets/uploads/'.rawurlencode($fn); if(!StorageService::isCloudinary($fn)&&!is_file(__DIR__.'/'.$imgPath)) $imgPath='assets/images/default-league.svg'; ?><button type="button"
                class="gallery-photo" data-index="<?= $index ?>"
                aria-label="Open <?= htmlspecialchars($post['title']) ?> image <?= $index+1 ?>"><img
                  src="<?= $imgPath ?>"
                  alt="<?= htmlspecialchars($post['title']) ?>"
                  loading="lazy"
                  onerror="this.onerror=null;this.src='assets/images/default-league.svg';"><?php if($index===2&&count($post['images'])>3): ?><span
                  class="photo-more">+<?= count($post['images'])-3 ?></span><?php endif; ?></button><?php endforeach; ?>
            </div>
            <div class="collection-info">
              <h3 class="collection-title"><?= htmlspecialchars($post['title']) ?></h3>
              <p class="collection-description"><?= nl2br(htmlspecialchars($post['description'])) ?></p>
              <div class="collection-meta"><span><i class="bi bi-images"></i><?= count($post['images']) ?>
                  photo<?= count($post['images'])===1?'':'s' ?></span><span><i
                    class="bi bi-calendar3"></i><?= date('F j, Y',strtotime($post['created_at'])) ?></span>
              </div>
            </div>
          </article>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="gallery-empty d-none" id="gallerySearchEmpty"><i class="bi bi-search"></i>
        <h4>No matching highlights</h4>
        <p class="mb-0">Try a different search term.</p>
      </div>
      <div class="gallery-pagination" id="galleryPagination"></div><?php else: ?><div class="gallery-empty"><i
          class="bi bi-images"></i>
        <h4>No gallery highlights yet</h4>
        <p class="mb-0">Check back soon for community league photos and memorable moments.</p>
      </div><?php endif; ?>
    </div>
  </main>
  <div class="modal fade gallery-lightbox" id="galleryLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
      <div class="modal-content"><button type="button" class="btn-close btn-close-white"
          data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-body"><button type="button" class="btn btn-dark position-absolute start-0 ms-3"
            id="galleryPrevious" aria-label="Previous image"><i class="bi bi-chevron-left"></i></button><img
            id="galleryLightboxImage" src="" alt="Gallery preview"><button type="button"
            class="btn btn-dark position-absolute end-0 me-3" id="galleryNext" aria-label="Next image"><i
              class="bi bi-chevron-right"></i></button></div>
      </div>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>
  <script>
    (() => {
      const entries = [...document.querySelectorAll('.gallery-entry')],
        search = document.getElementById('gallerySearch'),
        pagination = document.getElementById('galleryPagination'),
        empty = document.getElementById('gallerySearchEmpty'),
        count = document.getElementById('galleryResultCount'),
        lightbox = document.getElementById('galleryLightbox'),
        lightboxImage = document.getElementById('galleryLightboxImage'),
        previous = document.getElementById('galleryPrevious'),
        next = document.getElementById('galleryNext');
      const perPage = 6;
      let page = 1,
        filtered = entries,
        currentImages = [],
        currentIndex = 0;

      function render() {
        entries.forEach(entry => entry.classList.add('d-none'));
        const pages = Math.max(1, Math.ceil(filtered.length / perPage));
        page = Math.min(page, pages);
        filtered.slice((page - 1) * perPage, page * perPage).forEach(entry => entry.classList.remove(
          'd-none'));
        if (empty) empty.classList.toggle('d-none', filtered.length !== 0);
        if (count) count.textContent = `${filtered.length} collection${filtered.length===1?'':'s'}`;
        if (!pagination) return;
        pagination.innerHTML = '';
        if (pages <= 1) return;
        for (let i = 1; i <= pages; i++) {
          const button = document.createElement('button');
          button.type = 'button';
          button.textContent = i;
          button.classList.toggle('active', i === page);
          button.setAttribute('aria-label', `Page ${i}`);
          button.addEventListener('click', () => {
            page = i;
            render();
            window.scrollTo({
              top: document.querySelector('.gallery-toolbar').offsetTop - 110,
              behavior: 'smooth'
            });
          });
          pagination.appendChild(button);
        }
      }

      function showImage() {
        lightboxImage.src = currentImages[currentIndex] || '';
        const multiple = currentImages.length > 1;
        previous.classList.toggle('d-none', !multiple);
        next.classList.toggle('d-none', !multiple);
      }
      if (search) search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase();
        filtered = entries.filter(entry => entry.dataset.title.includes(term));
        page = 1;
        render();
      });
      render();
      document.addEventListener('click', event => {
        const photo = event.target.closest('.gallery-photo');
        if (!photo) return;
        const entry = photo.closest('.gallery-entry');
        currentImages = JSON.parse(entry.dataset.images || '[]');
        currentIndex = Number(photo.dataset.index) || 0;
        showImage();
        new bootstrap.Modal(lightbox).show();
      });
      previous?.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
        showImage();
      });
      next?.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % currentImages.length;
        showImage();
      });
      lightbox?.addEventListener('hidden.bs.modal', () => {
        lightboxImage.removeAttribute('src');
        currentImages = [];
      });
    })();

  </script>
</body>

</html>
