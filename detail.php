<?php
if (isset($_GET['ajax']) && $_GET['ajax'] === 'favorite') {
    require_once __DIR__ . '/includes/tmdb.php';
    header('Content-Type: application/json; charset=utf-8');
    if (!is_logged_in()) {
        echo json_encode(array('success' => false, 'message' => '需要登录'));
        exit;
    }
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $mediaId = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;
    $mediaType = isset($_POST['media_type']) ? $_POST['media_type'] : 'movie';
    $userId = $_SESSION['user_id'];
    if ($action === 'add' && $mediaId) {
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $poster = isset($_POST['poster']) ? $_POST['poster'] : '';
        $result = add_favorite($userId, $mediaId, $mediaType, $title, $poster);
        echo json_encode(array('success' => $result !== false));
        exit;
    } elseif ($action === 'remove' && $mediaId) {
        $result = remove_favorite($userId, $mediaId);
        echo json_encode(array('success' => true));
        exit;
    }
    echo json_encode(array('success' => false, 'message' => '无效的操作'));
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'season') {
    require_once __DIR__ . '/includes/tmdb.php';
    header('Content-Type: application/json; charset=utf-8');
    $tvId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $seasonNum = isset($_GET['season']) ? (int)$_GET['season'] : 1;
    if (!$tvId) {
        echo json_encode(array('success' => false, 'message' => '参数错误'));
        exit;
    }
    $tmdb = new TMDB();
    $season = $tmdb->get_tv_season($tvId, $seasonNum);
    if ($season && isset($season['episodes'])) {
        $episodes = array();
        foreach ($season['episodes'] as $ep) {
            $episodes[] = array(
                'episode_number' => $ep['episode_number'],
                'season_number' => $ep['season_number'],
                'name' => isset($ep['name']) ? $ep['name'] : '',
                'still' => !empty($ep['still_path']) ? $tmdb->get_image_url($ep['still_path'], 'w300') : '',
                'runtime' => isset($ep['runtime']) ? $ep['runtime'] : 0
            );
        }
        echo json_encode(array('success' => true, 'episodes' => $episodes));
    } else {
        echo json_encode(array('success' => false, 'episodes' => array()));
    }
    exit;
}

$page_title = '影视详情';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();
$db = get_db();
$current_user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'movie';

if (!$id || !in_array($type, array('movie', 'tv'))) {
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>错误</title><style>body{background:#04070d;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;margin:0}</style></head><body><div style="text-align:center"><h1 style="color:#ef4444">无效的请求</h1><p>参数错误，请返回首页</p><a href="index.php" style="color:#01b4e4">返回首页</a></div></body></html>';
    exit;
}

if ($type === 'movie') {
    $detail = $tmdb->get_movie_detail($id);
    $similar = $tmdb->get_movie_similar($id);
} else {
    $detail = $tmdb->get_tv_detail($id);
    $similar = $tmdb->get_tv_similar($id);
}

if (empty($detail) || (isset($detail['status_code']) && $detail['status_code'] === 34)) {
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>未找到</title><style>body{background:#04070d;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;margin:0}</style></head><body><div style="text-align:center"><h1 style="color:#ef4444">未找到</h1><p>该内容可能已被移除</p><a href="index.php" style="color:#01b4e4">返回首页</a></div></body></html>';
    exit;
}

$title = isset($detail['title']) ? $detail['title'] : (isset($detail['name']) ? $detail['name'] : '');
$originalTitle = isset($detail['original_title']) ? $detail['original_title'] : (isset($detail['original_name']) ? $detail['original_name'] : '');
$rating = isset($detail['vote_average']) ? round($detail['vote_average'], 1) : 0;
$genres = isset($detail['genres']) ? $detail['genres'] : array();
$overview = isset($detail['overview']) ? $detail['overview'] : '';
$poster = $tmdb->get_poster_url(isset($detail['poster_path']) ? $detail['poster_path'] : '', 'w342');
$backdrop = $tmdb->get_backdrop_url(isset($detail['backdrop_path']) ? $detail['backdrop_path'] : '', 'w1280');

$releaseYear = '';
if (isset($detail['release_date']) && $detail['release_date']) {
    $releaseYear = substr($detail['release_date'], 0, 4);
} elseif (isset($detail['first_air_date']) && $detail['first_air_date']) {
    $releaseYear = substr($detail['first_air_date'], 0, 4);
}

$runtime = '';
if ($type === 'movie') {
    $runtime = isset($detail['runtime']) ? $detail['runtime'] : 0;
} else {
    $seasonsCount = isset($detail['number_of_seasons']) ? $detail['number_of_seasons'] : 0;
    $episodesCount = isset($detail['number_of_episodes']) ? $detail['number_of_episodes'] : 0;
}

$originCountry = '';
if ($type === 'movie' && isset($detail['production_countries']) && !empty($detail['production_countries'])) {
    $originCountry = $detail['production_countries'][0]['iso_3166_1'];
} elseif ($type === 'tv' && isset($detail['origin_country']) && !empty($detail['origin_country'])) {
    $originCountry = $detail['origin_country'][0];
}

$isChineseContent = ($originCountry === 'CN' || $originCountry === 'CHN');

$hasMandarinDubbing = false;
if (isset($detail['videos']['results']) && !empty($detail['videos']['results'])) {
    foreach ($detail['videos']['results'] as $video) {
        if (isset($video['iso_639_1']) && ($video['iso_639_1'] === 'zh' || $video['iso_639_1'] === 'cmn' || $video['iso_639_1'] === 'cn')) {
            $hasMandarinDubbing = true;
            break;
        }
    }
}

$showDubbing = (!$isChineseContent && $hasMandarinDubbing);

$cast = isset($detail['credits']['cast']) ? array_slice($detail['credits']['cast'], 0, 12) : array();
$crew = isset($detail['credits']['crew']) ? array_filter($detail['credits']['crew'], function($c) {
    return isset($c['department']) && $c['department'] === 'Directing';
}) : array();

$isFavorited = false;
if ($current_user) {
    $isFavorited = is_favorited($current_user['id'], $id);
}

$defaultSeason = 1;
if ($type === 'tv' && isset($detail['seasons']) && !empty($detail['seasons'])) {
    $defaultSeason = $detail['seasons'][0]['season_number'];
}

$seasonData = null;
if ($type === 'tv') {
    $seasonData = $tmdb->get_tv_season($id, $defaultSeason);
}

$similarItems = array();
if (!empty($similar['results'])) {
    $similarItems = array_slice($similar['results'], 0, 12);
}

$playSource = get_default_source();

$extra_js = '';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="main-content">

    <section class="detail-hero">
        <?php if ($backdrop): ?>
        <div class="detail-hero-bg" style="background-image:url('<?php echo clean($backdrop); ?>');"></div>
        <?php else: ?>
        <div class="detail-hero-bg" style="background:linear-gradient(135deg,var(--secondary),var(--surface));"></div>
        <?php endif; ?>

        <div class="container">
            <button class="btn btn-ghost btn-sm" onclick="history.back()" style="margin-bottom:24px;">← 返回</button>

            <div class="detail-content">
                <div class="detail-poster">
                    <?php if ($poster): ?>
                    <img src="<?php echo clean($poster); ?>" alt="<?php echo clean($title); ?>" onerror="this.style.background='var(--surface)';this.removeAttribute('src');">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--muted);font-size:4rem;">🎬</div>
                    <?php endif; ?>
                </div>

                <div class="detail-info">
                    <h1 class="detail-title"><?php echo clean($title); ?></h1>

                    <?php if ($originalTitle && $originalTitle !== $title): ?>
                    <p class="text-secondary" style="margin-bottom:12px;">又名: <?php echo clean($originalTitle); ?></p>
                    <?php endif; ?>

                    <div class="detail-meta">
                        <?php if ($rating > 0): ?>
                        <span class="rating-badge">★ <?php echo number_format($rating, 1); ?></span>
                        <?php endif; ?>
                        <?php if ($releaseYear): ?>
                        <span class="meta-item">📅 <?php echo clean($releaseYear); ?></span>
                        <?php endif; ?>
                        <?php if ($type === 'movie' && $runtime): ?>
                        <span class="meta-item">⏱ <?php echo $runtime; ?> 分钟</span>
                        <?php elseif ($type === 'tv'): ?>
                        <?php if ($seasonsCount): ?>
                        <span class="meta-item">📺 <?php echo $seasonsCount; ?> 季</span>
                        <?php endif; ?>
                        <?php if ($episodesCount): ?>
                        <span class="meta-item">🎞 <?php echo $episodesCount; ?> 集</span>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($originCountry): ?>
                        <span class="meta-item">🌍 <?php echo clean($originCountry); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($genres)): ?>
                    <div class="detail-genres">
                        <?php foreach ($genres as $genre): ?>
                        <span class="tag"><?php echo clean($genre['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($showDubbing): ?>
                    <div class="dubbing-selector" id="dubbingSelector">
                        <span class="dubbing-option active" data-dubbing="mandarin">普通话</span>
                        <span class="dubbing-option" data-dubbing="original">原声</span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-actions">
                        <button class="btn btn-primary btn-lg" id="watchBtn" data-media-id="<?php echo $id; ?>" data-media-type="<?php echo $type; ?>" data-season="<?php echo $type === 'tv' ? $defaultSeason : 0; ?>" data-episode="1">
                            ▶ 立即观看
                        </button>
                        <?php if ($current_user): ?>
                        <button class="btn btn-secondary btn-lg favorite-btn-large" id="favoriteBtn" data-media-id="<?php echo $id; ?>" data-media-type="<?php echo $type; ?>" data-title="<?php echo clean($title); ?>" data-poster="<?php echo clean($poster); ?>" data-favorited="<?php echo $isFavorited ? '1' : '0'; ?>">
                            <?php echo $isFavorited ? '★ 已收藏' : '☆ 收藏'; ?>
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-lg" id="favoriteBtnLogin" style="opacity:0.7;">
                            ☆ 收藏
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($overview): ?>
                    <p class="detail-overview"><?php echo clean($overview); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $rating > 0 ? number_format($rating, 1) : '—'; ?></div>
                    <div class="stat-label">评分</div>
                </div>
                <?php if ($type === 'movie' && $runtime): ?>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $runtime; ?></div>
                    <div class="stat-label">时长（分钟）</div>
                </div>
                <?php elseif ($type === 'tv'): ?>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $seasonsCount ?: 0; ?></div>
                    <div class="stat-label">季数</div>
                </div>
                <?php if ($episodesCount): ?>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $episodesCount; ?></div>
                    <div class="stat-label">总集数</div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($detail['popularity'])): ?>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($detail['popularity'], 0); ?></div>
                    <div class="stat-label">热度</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($cast)): ?>
    <div class="container">
        <section class="section" id="cast-section">
            <div class="section-title">
                <h2>演员阵容</h2>
            </div>
            <div class="scroll-row-wrapper">
                <button class="scroll-nav-btn prev" data-cast-scroll-left aria-label="向左滚动">‹</button>
                <div class="scroll-row-content" id="castScroll">
                    <?php foreach ($cast as $person): ?>
                    <div class="cast-card" style="flex:0 0 auto;width:140px;">
                        <div class="cast-avatar" style="border-radius:50%;aspect-ratio:1;overflow:hidden;background:var(--card);margin-bottom:10px;">
                            <?php if (!empty($person['profile_path'])): ?>
                            <img src="<?php echo $tmdb->get_image_url($person['profile_path'], 'w185'); ?>" alt="<?php echo clean($person['name']); ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.background='var(--surface)';this.removeAttribute('src');">
                            <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--muted);font-size:2rem;">👤</div>
                            <?php endif; ?>
                        </div>
                        <div class="cast-name"><?php echo clean($person['name']); ?></div>
                        <div class="cast-character">饰 <?php echo clean(isset($person['character']) ? $person['character'] : ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="scroll-nav-btn next" data-cast-scroll-right aria-label="向右滚动">›</button>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <?php if ($type === 'tv' && !empty($detail['seasons'])): ?>
    <div class="container">
        <section class="section" id="episodes-section">
            <div class="season-selector">
                <span class="season-label">选择季：</span>
                <div class="season-dropdown">
                    <select id="seasonSelect">
                        <?php foreach ($detail['seasons'] as $s): ?>
                        <option value="<?php echo $s['season_number']; ?>" <?php echo $s['season_number'] == $defaultSeason ? 'selected' : ''; ?>>
                            第 <?php echo $s['season_number']; ?> 季
                            <?php if (isset($s['episode_count'])): ?>（<?php echo $s['episode_count']; ?> 集）<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($seasonData && !empty($seasonData['episodes'])): ?>
            <div class="episodes-grid" id="episodesGrid">
                <?php foreach ($seasonData['episodes'] as $ep): ?>
                <div class="episode-card" data-episode="<?php echo $ep['episode_number']; ?>" data-title="<?php echo clean(isset($ep['name']) ? $ep['name'] : ''); ?>" data-season="<?php echo $ep['season_number']; ?>" style="cursor:pointer;">
                    <div class="episode-thumb">
                        <?php if (!empty($ep['still_path'])): ?>
                        <img src="<?php echo $tmdb->get_image_url($ep['still_path'], 'w300'); ?>" alt="" onerror="this.style.background='var(--card)';this.removeAttribute('src');">
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--card);color:var(--muted);">🎞</div>
                        <?php endif; ?>
                        <div class="play-overlay">▶</div>
                        <div style="position:absolute;top:4px;left:4px;padding:2px 6px;background:rgba(0,0,0,0.7);border-radius:4px;font-size:0.75rem;font-weight:700;">
                            E<?php echo $ep['episode_number']; ?>
                        </div>
                    </div>
                    <div class="episode-info">
                        <div class="episode-number">第 <?php echo $ep['episode_number']; ?> 集</div>
                        <div class="episode-title" title="<?php echo clean(isset($ep['name']) ? $ep['name'] : ''); ?>"><?php echo clean(isset($ep['name']) ? $ep['name'] : ''); ?></div>
                        <?php if (isset($ep['runtime']) && $ep['runtime']): ?>
                        <div class="episode-duration"><?php echo $ep['runtime']; ?> 分钟</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📺</div>
                <h3>暂无剧集信息</h3>
                <p>该剧集的具体信息尚未收录</p>
            </div>
            <?php endif; ?>
        </section>
    </div>
    <?php elseif ($type === 'movie'): ?>
    <div class="container">
        <section class="section" id="watch-section">
            <div class="section-title">
                <h2>观看</h2>
            </div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <button class="btn btn-primary btn-lg" id="watchBtnLarge">▶ 立即观看</button>
                <?php if ($showDubbing): ?>
                <div class="dubbing-selector" style="margin-bottom:0;align-self:center;">
                    <span class="dubbing-option active" data-dubbing="mandarin">普通话</span>
                    <span class="dubbing-option" data-dubbing="original">原声</span>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <?php if (!empty($similarItems)): ?>
    <div class="container">
        <section class="section" id="similar-section">
            <div class="section-title">
                <h2>相关推荐</h2>
            </div>
            <div class="movie-grid">
                <?php foreach ($similarItems as $item): ?>
                <?php
                    $sId = $item['id'];
                    $sTitle = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
                    $sPoster = $tmdb->get_poster_url(isset($item['poster_path']) ? $item['poster_path'] : '', 'w342');
                    $sRating = isset($item['vote_average']) ? round($item['vote_average'], 1) : '0.0';
                    $sYear = '';
                    if (isset($item['release_date']) && $item['release_date']) {
                        $sYear = substr($item['release_date'], 0, 4);
                    } elseif (isset($item['first_air_date']) && $item['first_air_date']) {
                        $sYear = substr($item['first_air_date'], 0, 4);
                    }
                    $sType = isset($item['media_type']) ? $item['media_type'] : $type;
                ?>
                <a href="detail.php?id=<?php echo $sId; ?>&type=<?php echo $sType; ?>" class="movie-card">
                    <div class="poster">
                        <?php if ($sPoster): ?>
                        <img src="<?php echo clean($sPoster); ?>" alt="<?php echo clean($sTitle); ?>" loading="lazy" onerror="this.style.background='var(--surface)';this.removeAttribute('src');">
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--muted);font-size:3rem;">🎬</div>
                        <?php endif; ?>
                        <div class="poster-overlay">
                            <div class="overlay-rating">★ <?php echo number_format($sRating, 1); ?></div>
                            <div class="overlay-title"><?php echo clean($sTitle); ?></div>
                            <div class="overlay-year"><?php echo clean($sYear); ?></div>
                        </div>
                        <div class="card-rating">★ <?php echo number_format($sRating, 1); ?></div>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo clean($sTitle); ?></div>
                        <div class="card-meta">
                            <?php if ($sYear): ?><span><?php echo clean($sYear); ?></span><?php endif; ?>
                            <span><?php echo $sType === 'tv' ? '电视剧' : '电影'; ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <?php endif; ?>

</div>

<?php
$extra_js = '
<script>
(function() {
    var mediaId = ' . $id . ';
    var mediaType = "' . $type . '";
    var currentUser = ' . ($current_user ? '1' : '0') . ';
    var currentFavorited = ' . ($isFavorited ? '1' : '0') . ';
    var currentDubbing = ' . ($showDubbing ? '"mandarin"' : '"original"') . ';
    var playSourceApi = ' . json_encode($playSource['api_url']) . ';
    var parserUrl = ' . json_encode($playSource['parser_url']) . ';

    function showToast(msg, type) {
        var container = document.getElementById("toastContainer");
        if (!container) {
            container = document.createElement("div");
            container.id = "toastContainer";
            container.className = "toast-container";
            document.body.appendChild(container);
        }
        var toast = document.createElement("div");
        toast.className = "toast " + (type || "info");
        toast.innerHTML = \'<div class="toast-icon">✓</div><div class="toast-message">\' + msg + \'</div>\';
        container.appendChild(toast);
        setTimeout(function() {
            toast.style.transition = "opacity 0.3s";
            toast.style.opacity = "0";
            setTimeout(function() { toast.remove(); }, 300);
        }, 2500);
    }

    function openLoginModal() {
        var modal = document.getElementById("loginRequiredModal");
        if (modal) {
            modal.classList.add("active");
        } else {
            window.location.href = "login.php?required=1";
        }
    }

    function goToPlayer(season, episode) {
        var params = "player.php?media_id=" + mediaId + "&media_type=" + mediaType;
        if (season) params += "&season=" + season;
        if (episode) params += "&episode=" + episode;
        if (currentDubbing === "mandarin") params += "&dubbing=1";
        window.location.href = params;
    }

    var watchBtn = document.getElementById("watchBtn");
    if (watchBtn) {
        watchBtn.addEventListener("click", function() {
            if (!currentUser) {
                openLoginModal();
                return;
            }
            goToPlayer(' . ($type === 'tv' ? $defaultSeason : '0') . ', 1);
        });
    }

    var watchBtnLarge = document.getElementById("watchBtnLarge");
    if (watchBtnLarge) {
        watchBtnLarge.addEventListener("click", function() {
            if (!currentUser) {
                openLoginModal();
                return;
            }
            goToPlayer(' . ($type === 'tv' ? $defaultSeason : '0') . ', 1);
        });
    }

    var favBtn = document.getElementById("favoriteBtn");
    if (favBtn) {
        favBtn.addEventListener("click", function() {
            if (!currentUser) {
                openLoginModal();
                return;
            }
            var action = currentFavorited ? "remove" : "add";
            var btn = this;
            btn.style.opacity = "0.7";
            fetch("detail.php?ajax=favorite", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=" + action + "&media_id=" + mediaId + "&media_type=" + mediaType
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.style.opacity = "";
                if (data.success) {
                    if (action === "add") {
                        currentFavorited = 1;
                        btn.textContent = "★ 已收藏";
                        showToast("添加收藏成功", "success");
                    } else {
                        currentFavorited = 0;
                        btn.textContent = "☆ 收藏";
                        showToast("取消收藏成功", "info");
                    }
                } else {
                    showToast(data.message || "操作失败", "error");
                }
            })
            .catch(function() {
                btn.style.opacity = "";
                showToast("网络错误，请重试", "error");
            });
        });
    }

    var favBtnLogin = document.getElementById("favoriteBtnLogin");
    if (favBtnLogin) {
        favBtnLogin.addEventListener("click", openLoginModal);
    }

    var dubbingOptions = document.querySelectorAll(".dubbing-option");
    dubbingOptions.forEach(function(opt) {
        opt.addEventListener("click", function() {
            dubbingOptions.forEach(function(o) { o.classList.remove("active"); });
            this.classList.add("active");
            currentDubbing = this.getAttribute("data-dubbing");
        });
    });

    var seasonSelect = document.getElementById("seasonSelect");
    if (seasonSelect) {
        seasonSelect.addEventListener("change", function() {
            var season = this.value;
            fetch("detail.php?ajax=season&id=" + mediaId + "&season=" + season)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.episodes) {
                    var grid = document.getElementById("episodesGrid");
                    grid.innerHTML = "";
                    data.episodes.forEach(function(ep) {
                        var thumb = ep.still || "";
                        var thumbImg = thumb ? \'<img src="\' + thumb + \'" alt="" onerror="this.style.background=\\\'var(--card)\\\';this.removeAttribute(\\\'src\\\');\">\' : \'<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--card);color:var(--muted);">🎞</div>\';
                        var card = document.createElement("div");
                        card.className = "episode-card";
                        card.setAttribute("data-episode", ep.episode_number);
                        card.setAttribute("data-season", ep.season_number);
                        card.style.cursor = "pointer";
                        card.innerHTML = \'
                            <div class="episode-thumb">
                                \' + thumbImg + \'
                                <div class="play-overlay">▶</div>
                                <div style="position:absolute;top:4px;left:4px;padding:2px 6px;background:rgba(0,0,0,0.7);border-radius:4px;font-size:0.75rem;font-weight:700;">E\' + ep.episode_number + \'</div>
                            </div>
                            <div class="episode-info">
                                <div class="episode-number">第 \' + ep.episode_number + \' 集</div>
                                <div class="episode-title">\' + (ep.name || "") + \'</div>
                            </div>
                        \';
                        card.addEventListener("click", function() {
                            if (!currentUser) {
                                openLoginModal();
                                return;
                            }
                            goToPlayer(ep.season_number, ep.episode_number);
                        });
                        grid.appendChild(card);
                    });
                }
            });
        });
    }

    document.querySelectorAll(".episode-card").forEach(function(card) {
        card.addEventListener("click", function() {
            if (!currentUser) {
                openLoginModal();
                return;
            }
            var season = this.getAttribute("data-season");
            var episode = this.getAttribute("data-episode");
            goToPlayer(season, episode);
        });
    });

    var castScroll = document.getElementById("castScroll");
    if (castScroll) {
        var prevBtn = document.querySelector("[data-cast-scroll-left]");
        var nextBtn = document.querySelector("[data-cast-scroll-right]");
        if (prevBtn) prevBtn.addEventListener("click", function() { castScroll.scrollBy({left: -300, behavior: "smooth"}); });
        if (nextBtn) nextBtn.addEventListener("click", function() { castScroll.scrollBy({left: 300, behavior: "smooth"}); });
    }
})();
</script>';
?>

<?php include __DIR__ . '/includes/footer.php'; ?>