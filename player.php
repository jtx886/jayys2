<?php
if (isset($_GET['ajax']) && $_GET['ajax'] === 'history') {
    require_once __DIR__ . '/includes/tmdb.php';
    header('Content-Type: application/json; charset=utf-8');
    if (!is_logged_in()) {
        echo json_encode(array('success' => false));
        exit;
    }
    $tmdbAjax = new TMDB();
    $mediaId = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;
    $mediaType = isset($_POST['media_type']) ? $_POST['media_type'] : 'movie';
    $episode = isset($_POST['episode']) ? (int)$_POST['episode'] : 0;
    $season = isset($_POST['season']) ? (int)$_POST['season'] : 0;
    $progress = isset($_POST['progress']) ? (float)$_POST['progress'] : 0;
    if ($mediaId) {
        $user = current_user();
        $title = '';
        $poster = '';
        if ($mediaType === 'movie') {
            $d = $tmdbAjax->get_movie_detail($mediaId);
        } else {
            $d = $tmdbAjax->get_tv_detail($mediaId);
        }
        $title = isset($d['title']) ? $d['title'] : (isset($d['name']) ? $d['name'] : '');
        $poster = $tmdbAjax->get_poster_url(isset($d['poster_path']) ? $d['poster_path'] : '', 'w342');
        update_watch_history($user['id'], $mediaId, $mediaType, $title, $poster, $episode, $season, $progress);
    }
    echo json_encode(array('success' => true));
    exit;
}

$page_title = '在线观看';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();
$current_user = current_user();

if (!is_logged_in()) {
    $showLoginRequired = true;
} else {
    $showLoginRequired = false;
    if ($current_user && is_user_banned($current_user)) {
        $isBanned = true;
    } else {
        $isBanned = false;
    }
}

$mediaId = isset($_GET['media_id']) ? (int)$_GET['media_id'] : 0;
$mediaType = isset($_GET['media_type']) ? $_GET['media_type'] : 'movie';
$episode = isset($_GET['episode']) ? (int)$_GET['episode'] : 0;
$season = isset($_GET['season']) ? (int)$_GET['season'] : 0;
$dubbing = isset($_GET['dubbing']) ? (int)$_GET['dubbing'] : 0;

if (!$mediaId || !in_array($mediaType, array('movie', 'tv'))) {
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>错误</title><style>body{background:#04070d;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;margin:0}</style></head><body><div style="text-align:center"><h1 style="color:#ef4444">无效的请求</h1><a href="index.php" style="color:#01b4e4">返回首页</a></div></body></html>';
    exit;
}

if ($showLoginRequired || $isBanned) {
    $detail = $tmdb->get_movie_detail($mediaId);
    $title = isset($detail['title']) ? $detail['title'] : (isset($detail['name']) ? $detail['name'] : '未知');
} else {
    if ($mediaType === 'movie') {
        $detail = $tmdb->get_movie_detail($mediaId);
    } else {
        $detail = $tmdb->get_tv_detail($mediaId);
    }

    $title = isset($detail['title']) ? $detail['title'] : (isset($detail['name']) ? $detail['name'] : '未知');
    $originalTitle = isset($detail['original_title']) ? $detail['original_title'] : (isset($detail['original_name']) ? $detail['original_name'] : '');
    $poster = $tmdb->get_poster_url(isset($detail['poster_path']) ? $detail['poster_path'] : '', 'w342');
    $backdrop = $tmdb->get_backdrop_url(isset($detail['backdrop_path']) ? $detail['backdrop_path'] : '', 'w1280');
    $rating = isset($detail['vote_average']) ? round($detail['vote_average'], 1) : 0;

    if ($mediaType === 'tv' && (!$season || !$episode)) {
        $season = 1;
        $episode = 1;
    }
    if ($mediaType === 'movie') {
        $season = 0;
        $episode = 0;
    }

    $playSource = get_default_source();

    $apiUrl = $playSource['api_url'];
    $parserUrl = $playSource['parser_url'];

    $videoUrl = '';
    $errorMsg = '';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('User-Agent: JayMovie/1.0'));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURL_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['url'])) {
            $videoUrl = $data['url'];
        } elseif ($data && is_array($data) && isset($data[0]['url'])) {
            $videoUrl = $data[0]['url'];
        }
    }

    if (!$videoUrl) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'header' => "User-Agent: JayMovie/1.0\r\n"
            )
        ));
        $response2 = @file_get_contents($apiUrl, false, $context);
        if ($response2) {
            $data2 = json_decode($response2, true);
            if ($data2 && isset($data2['url'])) {
                $videoUrl = $data2['url'];
            } elseif ($data2 && is_array($data2) && isset($data2[0]['url'])) {
                $videoUrl = $data2[0]['url'];
            }
        }
    }

    if (!$videoUrl) {
        $errorMsg = '视频源获取失败，请稍后重试';
    }

    $embedUrl = '';
    if ($videoUrl) {
        $embedUrl = $parserUrl . urlencode($videoUrl);
    }

    if ($mediaType === 'tv' && isset($detail['seasons'])) {
        $availableSeasons = $detail['seasons'];
        $numberOfSeasons = count($availableSeasons);
        $numberOfEpisodes = isset($detail['number_of_episodes']) ? $detail['number_of_episodes'] : 0;
        $currentSeasonData = $tmdb->get_tv_season($mediaId, $season);
        $seasonEpisodes = isset($currentSeasonData['episodes']) ? $currentSeasonData['episodes'] : array();
        $episodeCountInSeason = count($seasonEpisodes);
    } else {
        $availableSeasons = array();
        $numberOfSeasons = 0;
        $numberOfEpisodes = 0;
        $seasonEpisodes = array();
        $episodeCountInSeason = 0;
    }

    if ($mediaType === 'movie') {
        $similar = $tmdb->get_movie_similar($mediaId);
    } else {
        $similar = $tmdb->get_tv_similar($mediaId);
    }
    $similarItems = !empty($similar['results']) ? array_slice($similar['results'], 0, 6) : array();

    update_watch_history(
        $current_user['id'],
        $mediaId,
        $mediaType,
        $title,
        $poster,
        $episode,
        $season,
        0
    );
}

$extra_js = '';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<?php if ($showLoginRequired): ?>
<div style="min-height:calc(100vh - var(--header-height));display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--background);">
    <div style="text-align:center;max-width:480px;">
        <div style="font-size:5rem;margin-bottom:24px;">🔒</div>
        <h1 style="margin-bottom:12px;">需要登录</h1>
        <p style="color:var(--text-secondary);margin-bottom:24px;">观看此内容需要登录账号</p>
        <div style="display:flex;gap:12px;justify-content:center;">
            <a href="login.php" class="btn btn-primary btn-lg">立即登录</a>
            <a href="register.php" class="btn btn-outline btn-lg">注册账号</a>
        </div>
    </div>
</div>
<?php elseif ($isBanned): ?>
<div style="min-height:calc(100vh - var(--header-height));display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--background);">
    <div style="text-align:center;max-width:480px;">
        <div style="font-size:5rem;margin-bottom:24px;">🚫</div>
        <h1 style="color:var(--danger);margin-bottom:12px;">账号已被封禁</h1>
        <p style="color:var(--text-secondary);margin-bottom:24px;">您的账号已被封禁，无法观看视频</p>
        <a href="logout.php" class="btn btn-primary">退出登录</a>
    </div>
</div>
<?php else: ?>

<div class="player-page">
    <div class="player-container" id="playerContainer">
        <?php if ($embedUrl): ?>
        <iframe
            src="<?php echo clean($embedUrl); ?>"
            allowfullscreen
            allow="autoplay; fullscreen"
            style="width:100%;height:100%;border:0;background:#000;"
            id="videoPlayer">
        </iframe>
        <?php elseif ($errorMsg): ?>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;background:#000;">
            <div style="font-size:4rem;">❌</div>
            <h2 style="color:var(--danger);margin:0;"><?php echo clean($errorMsg); ?></h2>
            <p style="color:var(--muted);">视频源可能已失效，请稍后重试</p>
            <a href="detail.php?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="btn btn-primary">返回详情页</a>
        </div>
        <?php else: ?>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#000;">
            <div class="loading-spinner"></div>
        </div>
        <?php endif; ?>

        <div class="player-controls" id="playerControls">
            <div class="player-progress">
                <div class="player-progress-buffered"></div>
                <div class="player-progress-played" style="width:0%;"></div>
            </div>
            <div class="player-buttons">
                <button class="player-btn" id="playBtn" aria-label="播放/暂停">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,4 20,12 6,20" id="playIcon"/></svg>
                </button>
                <div class="player-time">
                    <span id="currentTime">00:00</span> / <span id="totalTime">00:00</span>
                </div>
                <div class="player-spacer"></div>
                <button class="player-btn" id="fullscreenBtn" aria-label="全屏">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="player-info-bar">
        <div class="player-info-title">
            <?php echo clean($title); ?>
            <?php if ($mediaType === 'tv'): ?>
            <span class="text-muted" style="font-weight:400;margin-left:8px;">
                第 <?php echo $season; ?> 季 · 第 <?php echo $episode; ?> 集
            </span>
            <?php endif; ?>
            <?php if ($rating > 0): ?>
            <span class="text-accent" style="margin-left:12px;font-weight:600;">★ <?php echo number_format($rating, 1); ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;">
            <?php if ($mediaType === 'tv' && $episode > 1): ?>
            <a href="player.php?media_id=<?php echo $mediaId; ?>&media_type=tv&season=<?php echo $season; ?>&episode=<?php echo $episode - 1; ?>" class="btn btn-secondary btn-sm">
                ← 上一集
            </a>
            <?php endif; ?>
            <?php if ($mediaType === 'tv' && $episode < $episodeCountInSeason): ?>
            <a href="player.php?media_id=<?php echo $mediaId; ?>&media_type=tv&season=<?php echo $season; ?>&episode=<?php echo $episode + 1; ?>" class="btn btn-primary btn-sm">
                下一集 →
            </a>
            <?php endif; ?>
            <a href="detail.php?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="btn btn-ghost btn-sm">
                ← 返回详情
            </a>
        </div>
    </div>

    <?php if ($mediaType === 'tv' && !empty($seasonEpisodes)): ?>
    <div class="container" style="padding:24px 16px;">
        <div class="section-title" style="margin-bottom:16px;">
            <h2>选集</h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <label class="season-label">季：</label>
                <select id="seasonSelect" class="form-control" style="width:auto;padding:6px 12px;font-size:0.9rem;">
                    <?php foreach ($availableSeasons as $s): ?>
                    <option value="<?php echo $s['season_number']; ?>" <?php echo $s['season_number'] == $season ? 'selected' : ''; ?>>
                        第 <?php echo $s['season_number']; ?> 季
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="episodes-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">
            <?php foreach ($seasonEpisodes as $ep): ?>
            <a href="player.php?media_id=<?php echo $mediaId; ?>&media_type=tv&season=<?php echo $ep['season_number']; ?>&episode=<?php echo $ep['episode_number']; ?>" 
               class="episode-card <?php echo $ep['episode_number'] == $episode ? 'active' : ''; ?>"
               style="<?php echo $ep['episode_number'] == $episode ? 'border-color:var(--primary);background:var(--primary-light);' : ''; ?>">
                <div class="episode-info" style="display:flex;align-items:center;gap:12px;">
                    <div class="episode-number" style="font-size:1rem;">E<?php echo $ep['episode_number']; ?></div>
                    <div class="episode-title" style="flex:1;"><?php echo clean(isset($ep['name']) ? $ep['name'] : ''); ?></div>
                    <?php if ($ep['episode_number'] == $episode): ?>
                    <span class="badge badge-primary" style="font-size:0.7rem;">观看中</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($similarItems)): ?>
    <div class="container" style="padding:24px 16px 60px;">
        <div class="section-title">
            <h2>相关推荐</h2>
        </div>
        <div class="movie-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;">
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
                $sType = isset($item['media_type']) ? $item['media_type'] : $mediaType;
            ?>
            <a href="detail.php?id=<?php echo $sId; ?>&type=<?php echo $sType; ?>" class="movie-card">
                <div class="poster">
                    <?php if ($sPoster): ?>
                    <img src="<?php echo clean($sPoster); ?>" alt="<?php echo clean($sTitle); ?>" loading="lazy">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--muted);font-size:2rem;">🎬</div>
                    <?php endif; ?>
                    <div class="poster-overlay">
                        <div class="overlay-rating">★ <?php echo number_format($sRating, 1); ?></div>
                        <div class="overlay-title"><?php echo clean($sTitle); ?></div>
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
    </div>
    <?php endif; ?>

</div>

<?php
$extra_js = '
<script>
(function() {
    var container = document.getElementById("playerContainer");
    if (!container) return;

    var iframe = document.getElementById("videoPlayer");
    var controls = document.getElementById("playerControls");
    var playBtn = document.getElementById("playBtn");
    var fullscreenBtn = document.getElementById("fullscreenBtn");
    var progress = document.querySelector(".player-progress");

    var isDragging = false;

    function toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (container.requestFullscreen) {
            container.requestFullscreen();
        }
    }

    if (fullscreenBtn) {
        fullscreenBtn.addEventListener("click", toggleFullscreen);
    }

    if (playBtn) {
        playBtn.addEventListener("click", function() {
            container.classList.toggle("paused");
            var icon = document.getElementById("playIcon");
            if (container.classList.contains("paused")) {
                icon.setAttribute("points", "6,4 20,12 6,20");
            } else {
                icon.setAttribute("points", "6,4 14,4 14,20 6,20");
            }
        });
    }

    container.addEventListener("click", function(e) {
        if (e.target === container || e.target === iframe) {
            container.classList.toggle("paused");
        }
    });

    container.classList.add("paused");

    var seasonSelect = document.getElementById("seasonSelect");
    if (seasonSelect) {
        seasonSelect.addEventListener("change", function() {
            var newSeason = this.value;
            var url = new URL(window.location.href);
            url.searchParams.set("season", newSeason);
            url.searchParams.set("episode", "1");
            window.location.href = url.toString();
        });
    }

    function updateHistory() {
        fetch("player.php?ajax=history", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "media_id=' . $mediaId . '&media_type=' . $mediaType . '&episode=' . $episode . '&season=' . $season . '&progress=0"
        }).catch(function() {});
    }

    var hasUpdated = false;
    setTimeout(function() {
        if (!hasUpdated) {
            hasUpdated = true;
            updateHistory();
        }
    }, 5000);
})();
</script>';
?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>