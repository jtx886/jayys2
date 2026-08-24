<?php
$page_title = '首页';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();

$trending = $tmdb->get_trending('movie');
$popularMovies = $tmdb->get_popular_movies();
$popularTv = $tmdb->get_popular_tv();
$topRated = $tmdb->get_top_rated_movies();
$upcoming = $tmdb->get_upcoming_movies();

$heroItems = array();
if (!empty($trending['results'])) {
    $heroItems = array_slice($trending['results'], 0, 5);
}

$randomIndex = -1;
if (!empty($heroItems)) {
    $randomIndex = array_rand($heroItems);
}

function card_html($item, $tmdb) {
    $id = $item['id'];
    $type = isset($item['media_type']) ? $item['media_type'] : 'movie';
    if ($type === 'tv') {
        $type = 'tv';
    } else {
        $type = 'movie';
    }
    $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
    $poster = $tmdb->get_poster_url(isset($item['poster_path']) ? $item['poster_path'] : '', 'w342');
    $rating = isset($item['vote_average']) ? round($item['vote_average'], 1) : '0.0';
    $year = '';
    if (isset($item['release_date']) && $item['release_date']) {
        $year = substr($item['release_date'], 0, 4);
    } elseif (isset($item['first_air_date']) && $item['first_air_date']) {
        $year = substr($item['first_air_date'], 0, 4);
    }
    $titleAttr = clean($title);
    $posterAttr = clean($poster);
    return '
    <a href="detail.php?id=' . (int)$id . '&type=' . urlencode($type) . '" class="movie-card" data-movie-card>
        <div class="poster">
            ' . ($poster ? '<img src="' . $posterAttr . '" alt="' . $titleAttr . '" loading="lazy" onerror="this.style.background=\'var(--surface)\';this.removeAttribute(\'src\');">' : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--muted);font-size:3rem;">🎬</div>') . '
            <div class="poster-overlay">
                <div class="overlay-rating">★ ' . htmlspecialchars($rating) . '</div>
                <div class="overlay-title">' . $titleAttr . '</div>
                <div class="overlay-year">' . htmlspecialchars($year) . '</div>
            </div>
            <div class="card-rating">★ ' . htmlspecialchars($rating) . '</div>
        </div>
        <div class="card-body">
            <div class="card-title" title="' . $titleAttr . '">' . $titleAttr . '</div>
            <div class="card-meta">
                ' . ($year ? '<span>' . htmlspecialchars($year) . '</span>' : '') . '
                <span>' . ($type === 'tv' ? '电视剧' : '电影') . '</span>
            </div>
        </div>
    </a>';
}

function scroll_row_html($items, $tmdb, $rowId) {
    $html = '<div class="scroll-row-content" id="' . clean($rowId) . '" data-horizontal-row>';
    foreach ($items as $item) {
        $html .= card_html($item, $tmdb);
    }
    $html .= '</div>';
    return $html;
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="main-content">

    <?php if (!empty($heroItems)): ?>
    <section class="hero-carousel">
        <div class="hero" id="heroCarousel" data-carousel>
            <?php foreach ($heroItems as $i => $item): ?>
            <?php
                $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
                $backdrop = $tmdb->get_backdrop_url(isset($item['backdrop_path']) ? $item['backdrop_path'] : '', 'w1280');
                $rating = isset($item['vote_average']) ? round($item['vote_average'], 1) : '0.0';
                $overview = isset($item['overview']) ? $item['overview'] : '';
                $type = isset($item['media_type']) ? $item['media_type'] : 'movie';
                $id = $item['id'];
                $isActive = ($i === $randomIndex);
            ?>
            <div class="hero-slide<?php echo $isActive ? ' active' : ''; ?>" data-slide="<?php echo $i; ?>" style="<?php echo $isActive ? '' : 'display:none;'; ?>">
                <?php if ($backdrop): ?>
                <div class="hero-bg" style="background-image:url('<?php echo clean($backdrop); ?>');"></div>
                <?php else: ?>
                <div class="hero-bg" style="background:linear-gradient(135deg,var(--secondary),var(--surface));"></div>
                <?php endif; ?>
                <div class="container">
                    <div class="hero-content">
                        <span class="hero-badge">
                            <?php echo $type === 'tv' ? '📺 热门电视剧' : '🎬 热门电影'; ?>
                        </span>
                        <h1 class="hero-title"><?php echo clean($title); ?></h1>
                        <div class="hero-meta">
                            <span class="rating">★ <?php echo htmlspecialchars($rating); ?></span>
                            <?php if (isset($item['release_date']) && $item['release_date']): ?>
                            <span><?php echo substr($item['release_date'], 0, 4); ?></span>
                            <?php elseif (isset($item['first_air_date']) && $item['first_air_date']): ?>
                            <span><?php echo substr($item['first_air_date'], 0, 4); ?></span>
                            <?php endif; ?>
                            <span><?php echo $type === 'tv' ? '电视剧' : '电影'; ?></span>
                        </div>
                        <p class="hero-description"><?php echo clean(mb_strlen($overview) > 200 ? mb_substr($overview, 0, 200) . '...' : $overview); ?></p>
                        <div class="hero-actions">
                            <a href="detail.php?id=<?php echo (int)$id; ?>&type=<?php echo $type === 'tv' ? 'tv' : 'movie'; ?>" class="btn btn-primary btn-lg">
                                ▶ 立即播放
                            </a>
                            <a href="detail.php?id=<?php echo (int)$id; ?>&type=<?php echo $type === 'tv' ? 'tv' : 'movie'; ?>" class="btn btn-secondary btn-lg">
                                ℹ 详情
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="hero-nav">
                <button class="hero-nav-btn prev" id="heroPrev" data-carousel-prev aria-label="上一个">‹</button>
                <button class="hero-nav-btn next" id="heroNext" data-carousel-next aria-label="下一个">›</button>
            </div>

            <div class="hero-dots">
                <?php foreach ($heroItems as $i => $item): ?>
                <button class="hero-dot<?php echo ($i === $randomIndex) ? ' active' : ''; ?>" data-dot="<?php echo $i; ?>" aria-label="第<?php echo $i + 1; ?>个"></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php else: ?>
    <section class="hero" style="min-height:400px;background:linear-gradient(135deg,var(--secondary),var(--surface));display:flex;align-items:center;justify-content:center;text-align:center;">
        <div class="container">
            <h1 class="hero-title">欢迎来到 Jay影视</h1>
            <p class="text-secondary">正在加载精彩内容...</p>
        </div>
    </section>
    <?php endif; ?>

    <div class="container">

        <section class="section" id="popular-movies">
            <div class="section-title">
                <h2>热门电影</h2>
                <a href="category.php?type=movie" class="btn btn-ghost btn-sm">查看更多 ›</a>
            </div>
            <div class="scroll-row" data-scroll-row>
                <div class="scroll-row-wrapper">
                    <button class="scroll-nav-btn prev" data-scroll-left aria-label="向左滚动">‹</button>
                    <?php
                        $items = !empty($popularMovies['results']) ? $popularMovies['results'] : array();
                        echo scroll_row_html($items, $tmdb, 'row-popular-movies');
                    ?>
                    <button class="scroll-nav-btn next" data-scroll-right aria-label="向右滚动">›</button>
                </div>
                <?php if (empty($items)): ?>
                <div class="page-loader">
                    <div class="loading-spinner"></div>
                    <p class="page-loader-text">加载中...</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section" id="popular-tv">
            <div class="section-title">
                <h2>热门电视剧</h2>
                <a href="category.php?type=tv" class="btn btn-ghost btn-sm">查看更多 ›</a>
            </div>
            <div class="scroll-row" data-scroll-row>
                <div class="scroll-row-wrapper">
                    <button class="scroll-nav-btn prev" data-scroll-left aria-label="向左滚动">‹</button>
                    <?php
                        $items = !empty($popularTv['results']) ? $popularTv['results'] : array();
                        echo scroll_row_html($items, $tmdb, 'row-popular-tv');
                    ?>
                    <button class="scroll-nav-btn next" data-scroll-right aria-label="向右滚动">›</button>
                </div>
                <?php if (empty($items)): ?>
                <div class="page-loader">
                    <div class="loading-spinner"></div>
                    <p class="page-loader-text">加载中...</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section" id="top-rated">
            <div class="section-title">
                <h2>高分经典</h2>
                <a href="category.php?type=movie&sort=rating" class="btn btn-ghost btn-sm">查看更多 ›</a>
            </div>
            <div class="scroll-row" data-scroll-row>
                <div class="scroll-row-wrapper">
                    <button class="scroll-nav-btn prev" data-scroll-left aria-label="向左滚动">‹</button>
                    <?php
                        $items = !empty($topRated['results']) ? $topRated['results'] : array();
                        echo scroll_row_html($items, $tmdb, 'row-top-rated');
                    ?>
                    <button class="scroll-nav-btn next" data-scroll-right aria-label="向右滚动">›</button>
                </div>
                <?php if (empty($items)): ?>
                <div class="page-loader">
                    <div class="loading-spinner"></div>
                    <p class="page-loader-text">加载中...</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section" id="upcoming">
            <div class="section-title">
                <h2>即将上映</h2>
                <a href="category.php?type=movie&sort=upcoming" class="btn btn-ghost btn-sm">查看更多 ›</a>
            </div>
            <div class="scroll-row" data-scroll-row>
                <div class="scroll-row-wrapper">
                    <button class="scroll-nav-btn prev" data-scroll-left aria-label="向左滚动">‹</button>
                    <?php
                        $items = !empty($upcoming['results']) ? $upcoming['results'] : array();
                        echo scroll_row_html($items, $tmdb, 'row-upcoming');
                    ?>
                    <button class="scroll-nav-btn next" data-scroll-right aria-label="向右滚动">›</button>
                </div>
                <?php if (empty($items)): ?>
                <div class="page-loader">
                    <div class="loading-spinner"></div>
                    <p class="page-loader-text">加载中...</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (empty($popularMovies['results']) && empty($popularTv['results']) && empty($topRated['results']) && empty($upcoming['results'])): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎬</div>
            <h3>暂无数据</h3>
            <p>TMDB API 暂无数据，请稍后再试</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.hero-carousel {
    position: relative;
    margin-top: var(--header-height);
}
.hero-slide {
    position: relative;
    min-height: 600px;
    overflow: hidden;
}
.hero-slide .hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transform: scale(1.05);
    transition: transform 8s ease-out;
}
.hero-slide .hero-bg::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, var(--background) 0%, rgba(4, 7, 13, 0.9) 40%, rgba(4, 7, 13, 0.5) 70%, rgba(4, 7, 13, 0) 100%);
}
.hero-slide .hero-bg::before {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 200px;
    background: linear-gradient(to top, var(--background), transparent);
}
.hero-slide .container {
    position: relative;
    z-index: 2;
}
.hero-slide.active .hero-bg {
    transform: scale(1.08);
}
.hero-nav {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    transform: translateY(-50%);
    display: flex;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 4;
    pointer-events: none;
}
.hero-nav-btn {
    pointer-events: auto;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: 300;
    cursor: pointer;
    border: 1px solid var(--border);
    transition: all var(--transition-fast);
}
.hero-nav-btn:hover {
    background: var(--primary);
    color: var(--background);
    border-color: var(--primary);
    transform: scale(1.1);
}
@media (max-width: 768px) {
    .hero-slide {
        min-height: 500px;
    }
    .hero-nav {
        padding: 0 8px;
    }
    .hero-nav-btn {
        width: 40px;
        height: 40px;
        font-size: 1.5rem;
    }
}
</style>

<?php
$extra_js = '
<script>
(function() {
    var carousel = document.getElementById("heroCarousel");
    if (!carousel) return;

    var slides = carousel.querySelectorAll(".hero-slide");
    var dots = carousel.querySelectorAll(".hero-dot");
    var currentIndex = 0;
    var autoPlayTimer = null;
    var totalSlides = slides.length;

    function showSlide(index) {
        slides.forEach(function(s) {
            s.classList.remove("active");
            s.style.display = "none";
        });
        if (dots.length > 0) {
            dots.forEach(function(d) { d.classList.remove("active"); });
        }
        var target = slides[index];
        if (target) {
            target.style.display = "";
            target.classList.add("active");
        }
        if (dots[index]) {
            dots[index].classList.add("active");
        }
        currentIndex = index;
    }

    function nextSlide() {
        showSlide((currentIndex + 1) % totalSlides);
    }

    function prevSlide() {
        showSlide((currentIndex - 1 + totalSlides) % totalSlides);
    }

    function startAutoPlay() {
        stopAutoPlay();
        autoPlayTimer = setInterval(nextSlide, 6000);
    }

    function stopAutoPlay() {
        if (autoPlayTimer) {
            clearInterval(autoPlayTimer);
            autoPlayTimer = null;
        }
    }

    var nextBtn = document.getElementById("heroNext");
    var prevBtn = document.getElementById("heroPrev");
    if (nextBtn) nextBtn.addEventListener("click", function() { nextSlide(); startAutoPlay(); });
    if (prevBtn) prevBtn.addEventListener("click", function() { prevSlide(); startAutoPlay(); });

    dots.forEach(function(dot) {
        dot.addEventListener("click", function() {
            var idx = parseInt(dot.getAttribute("data-dot"));
            if (!isNaN(idx)) {
                showSlide(idx);
                startAutoPlay();
            }
        });
    });

    carousel.addEventListener("mouseenter", stopAutoPlay);
    carousel.addEventListener("mouseleave", startAutoPlay);

    var startX = 0;
    carousel.addEventListener("touchstart", function(e) {
        startX = e.touches[0].clientX;
        stopAutoPlay();
    }, { passive: true });
    carousel.addEventListener("touchend", function(e) {
        var diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) nextSlide(); else prevSlide();
        }
        startAutoPlay();
    });

    showSlide(' . (int)$randomIndex . ');
    startAutoPlay();
})();
</script>';
?>

<?php include __DIR__ . '/includes/footer.php'; ?>