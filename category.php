<?php
$page_title = '分类浏览';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();

$type = isset($_GET['type']) ? $_GET['type'] : 'movie';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'popularity';
$genreId = isset($_GET['genre']) ? (int)$_GET['genre'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$allowedTypes = array('movie', 'tv', 'variety', 'anime');
if (!in_array($type, $allowedTypes)) {
    $type = 'movie';
}

$typeNames = array(
    'movie' => '电影',
    'tv' => '电视剧',
    'variety' => '综艺节目',
    'anime' => '动漫'
);

$sortNames = array(
    'popularity' => '热度',
    'rating' => '评分',
    'release_date' => '上映日期'
);

$genreList = array();
if ($type === 'movie') {
    $genresData = $tmdb->get_genres();
    $genreList = isset($genresData['genres']) ? $genresData['genres'] : array();
} elseif ($type === 'tv') {
    $genresData = $tmdb->get_genres();
    $genreList = isset($genresData['tv']['genres']) ? $genresData['tv']['genres'] : array();
}

$results = array();
$totalResults = 0;
$totalPages = 1;

$sortBy = 'popularity.desc';
if ($sort === 'rating') {
    $sortBy = 'vote_average.desc';
} elseif ($sort === 'release_date') {
    if ($type === 'tv') {
        $sortBy = 'first_air_date.desc';
    } else {
        $sortBy = 'primary_release_date.desc';
    }
}

$discoverType = ($type === 'tv' || $type === 'variety') ? 'tv' : 'movie';

$params = array(
    'sort_by' => $sortBy,
    'page' => $page,
    'include_adult' => 'false'
);

if ($genreId > 0 && in_array($type, array('movie', 'tv'))) {
    $params['with_genres'] = $genreId;
}

if ($type === 'variety') {
    $params['with_genres'] = 10764;
    $discoverType = 'tv';
}

if ($type === 'anime') {
    $movieParams = array(
        'sort_by' => $sortBy,
        'page' => $page,
        'with_genres' => 16,
        'include_adult' => 'false'
    );
    $tvParams = array(
        'sort_by' => $sortBy,
        'page' => $page,
        'with_genres' => 16,
        'include_adult' => 'false'
    );

    $movieData = $tmdb->discover('movie', $movieParams);
    $tvData = $tmdb->discover('tv', $tvParams);

    $movieResults = !empty($movieData['results']) ? $movieData['results'] : array();
    $tvResults = !empty($tvData['results']) ? $tvData['results'] : array();

    $allResults = array();
    foreach ($movieResults as $item) {
        $item['_source_type'] = 'movie';
        $allResults[] = $item;
    }
    foreach ($tvResults as $item) {
        $item['_source_type'] = 'tv';
        $allResults[] = $item;
    }

    usort($allResults, function ($a, $b) use ($sortBy) {
        if (strpos($sortBy, 'popularity') !== false) {
            $va = isset($a['popularity']) ? $a['popularity'] : 0;
            $vb = isset($b['popularity']) ? $b['popularity'] : 0;
            return $vb <=> $va;
        } elseif (strpos($sortBy, 'vote_average') !== false) {
            $va = isset($a['vote_average']) ? $a['vote_average'] : 0;
            $vb = isset($b['vote_average']) ? $b['vote_average'] : 0;
            return $vb <=> $va;
        } else {
            $da = isset($a['release_date']) ? $a['release_date'] : (isset($a['first_air_date']) ? $a['first_air_date'] : '');
            $db = isset($b['release_date']) ? $b['release_date'] : (isset($b['first_air_date']) ? $b['first_air_date'] : '');
            return strcmp($db, $da);
        }
    });

    $results = array_slice($allResults, 0, 20);
    $totalResults = (isset($movieData['total_results']) ? $movieData['total_results'] : 0) + (isset($tvData['total_results']) ? $tvData['total_results'] : 0);
    $totalPages = max(
        isset($movieData['total_pages']) ? $movieData['total_pages'] : 1,
        isset($tvData['total_pages']) ? $tvData['total_pages'] : 1
    );
} else {
    $data = $tmdb->discover($discoverType, $params);
    if (!empty($data['results'])) {
        $results = $data['results'];
        $totalResults = isset($data['total_results']) ? $data['total_results'] : 0;
        $totalPages = isset($data['total_pages']) ? $data['total_pages'] : 1;
    }
}

function category_card($item, $tmdb) {
    $id = $item['id'];
    $type = isset($item['media_type']) ? $item['media_type'] : (isset($item['_source_type']) ? $item['_source_type'] : (isset($item['title']) ? 'movie' : 'tv'));
    if ($type !== 'tv') {
        $type = 'movie';
    }

    $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '未知');
    $poster = $tmdb->get_poster_url(isset($item['poster_path']) ? $item['poster_path'] : '', 'w342');
    $rating = isset($item['vote_average']) ? round($item['vote_average'], 1) : 0;
    $year = '';
    if (isset($item['release_date']) && $item['release_date']) {
        $year = substr($item['release_date'], 0, 4);
    } elseif (isset($item['first_air_date']) && $item['first_air_date']) {
        $year = substr($item['first_air_date'], 0, 4);
    }

    $titleAttr = clean($title);
    $posterAttr = clean($poster);
    $ratingText = number_format($rating, 1);
    $yearText = clean($year);
    $typeText = $type === 'tv' ? '电视剧' : '电影';

    return '
    <a href="detail.php?id=' . (int)$id . '&type=' . urlencode($type) . '" class="movie-card">
        <div class="poster">
            ' . ($poster ? '<img src="' . $posterAttr . '" alt="' . $titleAttr . '" loading="lazy" onerror="this.style.background=\'var(--surface)\';this.removeAttribute(\'src\');">' : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--muted);font-size:3rem;">🎬</div>') . '
            <div class="poster-overlay">
                <div class="overlay-rating">★ ' . htmlspecialchars($ratingText) . '</div>
                <div class="overlay-title">' . $titleAttr . '</div>
                <div class="overlay-year">' . $yearText . '</div>
            </div>
            <div class="card-rating">★ ' . htmlspecialchars($ratingText) . '</div>
            <span class="media-badge ' . ($type === 'tv' ? 'badge-info' : 'badge-primary') . '">' . $typeText . '</span>
        </div>
        <div class="card-body">
            <div class="card-title" title="' . $titleAttr . '">' . $titleAttr . '</div>
            <div class="card-meta">
                ' . ($year ? '<span>' . $yearText . '</span>' : '') . '
                <span>' . $typeText . '</span>
            </div>
        </div>
    </a>';
}

function build_category_url($overrides = array()) {
    $params = array(
        'type' => isset($_GET['type']) ? $_GET['type'] : 'movie',
        'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'popularity',
        'genre' => isset($_GET['genre']) ? $_GET['genre'] : 0,
        'page' => isset($_GET['page']) ? $_GET['page'] : 1
    );
    $params = array_merge($params, $overrides);
    if (isset($params['genre']) && $params['genre'] == 0) {
        unset($params['genre']);
    }
    if (isset($params['page']) && $params['page'] <= 1) {
        unset($params['page']);
    }
    return 'category.php?' . http_build_query($params);
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="main-content">

    <div class="container">

        <nav class="breadcrumb">
            <a href="index.php">首页</a>
            <span class="breadcrumb-sep">/</span>
            <span class="breadcrumb-current"><?php echo clean($typeNames[$type]); ?></span>
            <?php if ($genreId > 0): ?>
            <span class="breadcrumb-sep">/</span>
            <span class="breadcrumb-current"><?php
                $genreName = '';
                foreach ($genreList as $g) {
                    if ($g['id'] == $genreId) { $genreName = $g['name']; break; }
                }
                echo clean($genreName ?: '未知分类');
            ?></span>
            <?php endif; ?>
        </nav>

        <header class="category-header">
            <h1 class="category-title"><?php echo clean($typeNames[$type]); ?></h1>
            <p class="category-count">
                共 <strong><?php echo number_format($totalResults); ?></strong> 部作品
                <?php if ($genreId > 0 && $genreName): ?>
                · 分类：<?php echo clean($genreName); ?>
                <?php endif; ?>
            </p>
        </header>

        <div class="category-toolbar">
            <div class="category-sort">
                <span class="sort-label">排序：</span>
                <div class="sort-options">
                    <?php foreach ($sortNames as $key => $label): ?>
                    <a href="<?php echo build_category_url(array('sort' => $key, 'page' => 1)); ?>" class="sort-btn <?php echo $sort === $key ? 'active' : ''; ?>">
                        <?php echo clean($label); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (in_array($type, array('movie', 'tv')) && !empty($genreList)): ?>
            <div class="genre-filter">
                <span class="sort-label">分类：</span>
                <div class="genre-options">
                    <a href="<?php echo build_category_url(array('genre' => 0, 'page' => 1)); ?>" class="filter-chip <?php echo $genreId === 0 ? 'active' : ''; ?>">全部</a>
                    <?php foreach ($genreList as $g): ?>
                    <a href="<?php echo build_category_url(array('genre' => $g['id'], 'page' => 1)); ?>" class="filter-chip <?php echo $genreId == $g['id'] ? 'active' : ''; ?>">
                        <?php echo clean($g['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($results)): ?>
        <div class="search-empty">
            <div class="search-empty-icon">📭</div>
            <h2>暂无内容</h2>
            <p>当前分类下没有相关作品，请尝试其他分类或排序</p>
            <a href="category.php?type=<?php echo $type === 'movie' ? 'tv' : 'movie'; ?>" class="btn btn-primary" style="margin-top:20px;">
                浏览<?php echo $type === 'movie' ? '电视剧' : '电影'; ?>
            </a>
        </div>
        <?php else: ?>

        <div class="movie-grid">
            <?php foreach ($results as $item): ?>
                <?php echo category_card($item, $tmdb); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="<?php echo build_category_url(array('page' => $page - 1)); ?>" class="pagination-btn">‹ 上一页</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1):
            ?>
            <a href="<?php echo build_category_url(array('page' => 1)); ?>" class="pagination-btn">1</a>
            <?php if ($startPage > 2): ?>
            <span style="color:var(--muted);padding:0 4px;">...</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?php echo build_category_url(array('page' => $i)); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
            <span style="color:var(--muted);padding:0 4px;">...</span>
            <?php endif; ?>
            <a href="<?php echo build_category_url(array('page' => $totalPages)); ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
            <a href="<?php echo build_category_url(array('page' => $page + 1)); ?>" class="pagination-btn">下一页 ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<style>
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: calc(var(--header-height) + 24px);
    margin-bottom: 16px;
    font-size: 0.9rem;
    color: var(--muted);
}

.breadcrumb a {
    color: var(--text-secondary);
    transition: color var(--transition-fast);
}

.breadcrumb a:hover {
    color: var(--primary);
}

.breadcrumb-sep {
    color: var(--muted);
}

.breadcrumb-current {
    color: var(--text);
    font-weight: 500;
}

.category-toolbar {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.category-sort {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.sort-options {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.sort-btn {
    padding: 8px 18px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-full);
    font-size: 0.9rem;
    font-weight: 500;
    transition: all var(--transition-fast);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.sort-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.sort-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--background);
}

.genre-filter {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}

.genre-options {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.media-badge {
    position: absolute;
    bottom: 12px;
    right: 12px;
    padding: 3px 10px;
    border-radius: var(--radius-sm);
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
}

@media (max-width: 768px) {
    .breadcrumb {
        font-size: 0.8rem;
        padding-top: calc(var(--header-height) + 16px);
    }

    .category-title {
        font-size: 1.5rem;
    }

    .category-toolbar {
        gap: 12px;
    }

    .sort-btn {
        padding: 6px 14px;
        font-size: 0.8rem;
    }

    .filter-chip {
        padding: 5px 12px;
        font-size: 0.8rem;
    }

    .pagination-btn {
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        font-size: 0.85rem;
    }
}
</style>

<?php
$extra_js = '';
?>

<?php include __DIR__ . '/includes/footer.php'; ?>