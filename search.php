<?php
$page_title = '搜索';
require_once __DIR__ . '/includes/tmdb.php';

$tmdb = new TMDB();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

$results = array();
$totalResults = 0;
$totalPages = 0;

if ($q !== '') {
    $searchType = 'multi';
    if ($filter === 'movie') {
        $searchType = 'movie';
    } elseif ($filter === 'tv') {
        $searchType = 'tv';
    }

    $data = $tmdb->search($q, $searchType, $page);
    if (!empty($data['results'])) {
        $results = $data['results'];
        $totalResults = isset($data['total_results']) ? $data['total_results'] : 0;
        $totalPages = isset($data['total_pages']) ? $data['total_pages'] : 1;
    }
}

function search_card($item, $tmdb) {
    $id = $item['id'];
    $type = isset($item['media_type']) ? $item['media_type'] : (isset($item['title']) ? 'movie' : 'tv');
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

function build_search_url($overrides = array()) {
    $params = array(
        'q' => isset($_GET['q']) ? $_GET['q'] : '',
        'filter' => isset($_GET['filter']) ? $_GET['filter'] : 'all'
    );
    $params = array_merge($params, $overrides);
    return 'search.php?' . http_build_query($params);
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="main-content">

    <section class="search-header">
        <div class="container">
            <h1>搜索结果<span class="search-term">「<?php echo clean($q); ?>」</span></h1>
            <p class="text-secondary">
                <?php if ($q !== ''): ?>
                    共找到 <strong><?php echo number_format($totalResults); ?></strong> 个相关结果
                <?php else: ?>
                    请输入搜索关键词
                <?php endif; ?>
            </p>

            <form class="search-form" action="search.php" method="get" style="margin-top:20px;max-width:600px;">
                <input type="text" name="q" placeholder="输入关键词搜索..." value="<?php echo clean($q); ?>" class="search-input" autofocus>
                <button type="submit" class="search-btn" aria-label="搜索">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </button>
            </form>
        </div>
    </section>

    <div class="container">

        <?php if ($q !== ''): ?>
        <div class="search-filters">
            <span class="filter-label">类型筛选：</span>
            <div class="filter-options">
                <a href="<?php echo build_search_url(array('filter' => 'all', 'page' => 1)); ?>" class="filter-chip <?php echo $filter === 'all' ? 'active' : ''; ?>">全部</a>
                <a href="<?php echo build_search_url(array('filter' => 'movie', 'page' => 1)); ?>" class="filter-chip <?php echo $filter === 'movie' ? 'active' : ''; ?>">电影</a>
                <a href="<?php echo build_search_url(array('filter' => 'tv', 'page' => 1)); ?>" class="filter-chip <?php echo $filter === 'tv' ? 'active' : ''; ?>">电视剧</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($q !== '' && empty($results)): ?>
        <div class="search-empty">
            <div class="search-empty-icon">🔍</div>
            <h2>没有找到相关结果</h2>
            <p>尝试使用其他关键词，或检查拼写是否正确</p>
            <a href="index.php" class="btn btn-primary" style="margin-top:20px;">返回首页</a>
        </div>
        <?php elseif (!empty($results)): ?>
        <div class="movie-grid">
            <?php foreach ($results as $item): ?>
                <?php echo search_card($item, $tmdb); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="<?php echo build_search_url(array('page' => $page - 1)); ?>" class="pagination-btn">‹ 上一页</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1):
            ?>
            <a href="<?php echo build_search_url(array('page' => 1)); ?>" class="pagination-btn">1</a>
            <?php if ($startPage > 2): ?>
            <span style="color:var(--muted);padding:0 4px;">...</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?php echo build_search_url(array('page' => $i)); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
            <span style="color:var(--muted);padding:0 4px;">...</span>
            <?php endif; ?>
            <a href="<?php echo build_search_url(array('page' => $totalPages)); ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
            <a href="<?php echo build_search_url(array('page' => $page + 1)); ?>" class="pagination-btn">下一页 ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php elseif ($q === ''): ?>
        <div class="search-empty">
            <div class="search-empty-icon">🎬</div>
            <h2>输入关键词开始搜索</h2>
            <p>搜索电影、电视剧、综艺节目等</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.search-term {
    color: var(--primary);
    margin-left: 6px;
}

.search-header .search-form {
    display: flex;
    align-items: center;
    gap: 0;
}

.search-header .search-form .search-input {
    flex: 1;
    padding: 14px 20px 14px 48px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    color: var(--text);
    font-size: 1rem;
    transition: all var(--transition-fast);
    outline: none;
}

.search-header .search-form .search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.search-header .search-form .search-btn {
    position: absolute;
    right: 8px;
    width: 44px;
    height: 44px;
    background: var(--primary);
    color: var(--background);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.search-header .search-form .search-btn:hover {
    background: var(--primary-hover);
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

.search-empty-icon {
    font-size: 4rem;
    color: var(--muted);
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .search-header h1 {
        font-size: 1.4rem;
    }

    .search-header .search-form {
        max-width: 100%;
    }

    .search-filters {
        gap: 10px;
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