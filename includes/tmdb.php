<?php
// TMDB API集成

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

class TMDB {
    private $apiKey;
    private $baseUrl;
    private $imageUrl;
    private $language = 'zh-CN';
    private $cacheDir;
    
    public function __construct() {
        if (!function_exists('get_config')) {
            require_once __DIR__ . '/functions.php';
        }
        $config = get_config();
        $this->apiKey = $config['tmdb_api_key'];
        $this->baseUrl = $config['tmdb_base_url'];
        $this->imageUrl = $config['tmdb_image_url'];
        $this->cacheDir = __DIR__ . '/../data/tmdb_cache';
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    private function request($endpoint, $params = array()) {
        $params['api_key'] = $this->apiKey;
        $params['language'] = $this->language;
        
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        
        $cacheFile = $this->cacheDir . '/' . md5($url) . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data) {
                return $data;
            }
        }
        
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'header' => "User-Agent: JayMovie/1.0\r\n"
            )
        ));
        
        $response = @file_get_contents($url, false, $context);
        
        if (!$response) {
            if (file_exists($cacheFile)) {
                return json_decode(file_get_contents($cacheFile), true);
            }
            return array();
        }
        
        $data = json_decode($response, true);
        
        if ($data) {
            @file_put_contents($cacheFile, $response);
        }
        
        return $data ? $data : array();
    }
    
    public function get_trending($type = 'all', $page = 1) {
        return $this->request('/trending/' . $type . '/week', array('page' => $page));
    }
    
    public function get_popular_movies($page = 1) {
        return $this->request('/movie/popular', array('page' => $page));
    }
    
    public function get_top_rated_movies($page = 1) {
        return $this->request('/movie/top_rated', array('page' => $page));
    }
    
    public function get_upcoming_movies($page = 1) {
        return $this->request('/movie/upcoming', array('page' => $page));
    }
    
    public function get_now_playing_movies($page = 1) {
        return $this->request('/movie/now_playing', array('page' => $page));
    }
    
    public function get_popular_tv($page = 1) {
        return $this->request('/tv/popular', array('page' => $page));
    }
    
    public function get_top_rated_tv($page = 1) {
        return $this->request('/tv/top_rated', array('page' => $page));
    }
    
    public function get_airing_today_tv($page = 1) {
        return $this->request('/tv/airing_today', array('page' => $page));
    }
    
    public function get_movie_detail($id) {
        return $this->request('/movie/' . $id, array(
            'append_to_response' => 'credits,videos,similar,genre'
        ));
    }
    
    public function get_tv_detail($id) {
        return $this->request('/tv/' . $id, array(
            'append_to_response' => 'credits,videos,similar,genre'
        ));
    }
    
    public function get_tv_season($tvId, $seasonNumber) {
        return $this->request('/tv/' . $tvId . '/season/' . $seasonNumber);
    }
    
    public function get_movie_videos($id) {
        return $this->request('/movie/' . $id . '/videos');
    }
    
    public function get_tv_videos($id) {
        return $this->request('/tv/' . $id . '/videos');
    }
    
    public function get_movie_credits($id) {
        return $this->request('/movie/' . $id . '/credits');
    }
    
    public function get_tv_credits($id) {
        return $this->request('/tv/' . $id . '/credits');
    }
    
    public function get_movie_similar($id) {
        return $this->request('/movie/' . $id . '/similar');
    }
    
    public function get_tv_similar($id) {
        return $this->request('/tv/' . $id . '/similar');
    }
    
    public function search($query, $type = 'multi', $page = 1) {
        return $this->request('/search/' . $type, array(
            'query' => $query,
            'page' => $page,
            'include_adult' => 'false'
        ));
    }
    
    public function get_genres() {
        return array(
            'movie' => $this->request('/genre/movie/list'),
            'tv' => $this->request('/genre/tv/list')
        );
    }
    
    public function get_by_genre($type, $genreId, $page = 1) {
        if ($type === 'movie') {
            return $this->request('/discover/movie', array(
                'with_genres' => $genreId,
                'page' => $page,
                'sort_by' => 'popularity.desc'
            ));
        }
        return $this->request('/discover/tv', array(
            'with_genres' => $genreId,
            'page' => $page,
            'sort_by' => 'popularity.desc'
        ));
    }
    
    public function discover($type, $params = array()) {
        $endpoint = $type === 'movie' ? '/discover/movie' : '/discover/tv';
        return $this->request($endpoint, $params);
    }
    
    public function get_image_url($path, $size = 'w500') {
        if (!$path) {
            return '';
        }
        return $this->imageUrl . '/' . $size . $path;
    }
    
    public function get_poster_url($path, $size = 'w342') {
        return $this->get_image_url($path, $size);
    }
    
    public function get_backdrop_url($path, $size = 'w1280') {
        return $this->get_image_url($path, $size);
    }
    
    public function get_episode_still($tvId, $seasonNumber, $episodeNumber) {
        $season = $this->get_tv_season($tvId, $seasonNumber);
        if ($season && isset($season['episodes'])) {
            foreach ($season['episodes'] as $ep) {
                if ($ep['episode_number'] == $episodeNumber) {
                    return $this->get_image_url($ep['still_path'], 'w300');
                }
            }
        }
        return '';
    }
    
    public function clear_cache() {
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }
}
