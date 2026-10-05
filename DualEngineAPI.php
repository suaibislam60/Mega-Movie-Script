<?php
// =========================================================
//  MONEY PRINTING ENGINE V3.1 (DOMAIN UPDATE)
//  - Added: filmy4webmoviesdownload.blogspot.com
//  - Auto IMDB ID Extraction (Fixes 404)
//  - SuperEmbed Server-Side Resolver
//  - GoDrive/2Embed Exact Pattern Logic
//  - Hybrid Cache (Long for Data, Short for Links)
// =========================================================

// 1. SECURITY: DOMAIN LOCKING
$ALLOWED_ORIGINS = [
    'https://filmyzillamoviedownloadlink.blogspot.com',
    'https://bollyflixmoviedownload.blogspot.com',
    'https://vegamoviedownloadhd.blogspot.com',
    'https://hindidubbedmoviesdownloadhd.blogspot.com',
    'https://khatrimazaorgmoviedownload.blogspot.com',
    'https://filmy4webmoviesdownload.blogspot.com' // NEW DOMAIN ADDED
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');
} else {
    // Fallback for development/testing if needed, else block
    // header('HTTP/1.1 403 Forbidden'); exit; 
}

header('Content-Type: application/json');
header('X-Robots-Tag: noindex, nofollow'); 

// 2. CONFIGURATION
define('TMDB_API_KEY', 'XXXXXXXX_XXXXXXX_XXX');
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');
define('CACHE_PATH', __DIR__ . '/cache/');

// 3. CACHE SYSTEM (Auto-Garbage Collection)
function getCached($key, $duration = 3600) {
    $file = CACHE_PATH . md5($key) . '.json';
    if (file_exists($file)) {
        if ((time() - filemtime($file)) < $duration) {
            return json_decode(file_get_contents($file), true);
        } else { @unlink($file); }
    }
    return null;
}

function saveCached($key, $data) {
    if (!is_dir(CACHE_PATH)) mkdir(CACHE_PATH, 0755, true);
    // Garbage collection: 5% chance
    if (rand(1, 100) <= 5) {
        $files = glob(CACHE_PATH . '*');
        $now = time();
        foreach ($files as $f) {
            if (is_file($f) && ($now - filemtime($f) >= 86400)) @unlink($f);
        }
    }
    file_put_contents(CACHE_PATH . md5($key) . '.json', json_encode($data));
}

// 4. ROUTER
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? ''; // TMDB ID
$page = $_GET['page'] ?? 1;
$query = $_GET['query'] ?? '';
$type = $_GET['type'] ?? 'movie';

switch ($action) {
    case 'discover': serveDiscover($page, $type); break;
    case 'search': serveSearch($query); break;
    case 'details': serveDetails($id, $type); break;
    case 'stream_sources': serveStreamSources($id, $type, $_GET['s'] ?? 1, $_GET['e'] ?? 1); break;
    case 'season_details': serveSeasonDetails($id, $_GET['s'] ?? 1); break;
    default: echo json_encode(['status' => 'error']);
}

// =================== LOGIC FUNCTIONS ===================

function serveDiscover($page, $type) {
    $cacheKey = "disc_{$type}_{$page}_" . http_build_query($_GET);
    $cached = getCached($cacheKey, 3600);
    if ($cached) { echo json_encode($cached); return; }

    $endpoint = ($type === 'tv') ? '/discover/tv' : '/discover/movie';
    $params = [
        'api_key' => TMDB_API_KEY, 'page' => $page, 'language' => 'en-US',
        'sort_by' => $_GET['sort'] ?? 'popularity.desc',
        'include_adult' => false, 'with_genres' => $_GET['genre'] ?? ''
    ];
    if(!empty($_GET['year'])) {
        if($type === 'tv') $params['first_air_date_year'] = $_GET['year'];
        else $params['primary_release_year'] = $_GET['year'];
    }
    
    $data = fetchAPI($endpoint, $params);
    if ($data) saveCached($cacheKey, $data);
    echo json_encode($data);
}

function serveSearch($query) {
    $params = ['api_key' => TMDB_API_KEY, 'query' => $query, 'include_adult' => false];
    $data = fetchAPI('/search/multi', $params);
    if(isset($data['results'])) {
        $data['results'] = array_values(array_filter($data['results'], function($item) {
            return isset($item['media_type']) && ($item['media_type'] == 'movie' || $item['media_type'] == 'tv');
        }));
    }
    echo json_encode($data);
}

function serveDetails($id, $type) {
    $cacheKey = "det_{$type}_{$id}";
    $cached = getCached($cacheKey, 86400); // 24 Hr Cache for details
    if ($cached) { echo json_encode($cached); return; }

    $endpoint = ($type === 'tv') ? "/tv/{$id}" : "/movie/{$id}";
    $params = ['api_key' => TMDB_API_KEY, 'append_to_response' => 'videos,credits,similar,recommendations'];
    
    $data = fetchAPI($endpoint, $params);
    if ($data) saveCached($cacheKey, $data);
    echo json_encode($data);
}

function serveSeasonDetails($id, $season) {
    $cacheKey = "sea_{$id}_{$season}";
    $cached = getCached($cacheKey, 86400);
    if ($cached) { echo json_encode($cached); return; }
    
    $data = fetchAPI("/tv/{$id}/season/{$season}", ['api_key' => TMDB_API_KEY]);
    if ($data) saveCached($cacheKey, $data);
    echo json_encode($data);
}

// *** THE MONEY MAKER: V3.0 STREAMING LOGIC ***
function serveStreamSources($id, $type, $season, $episode) {
    // 1. Fetch External IDs (We need IMDB ID for 2Embed/GoDrive)
    $externalIds = fetchAPI("/{$type}/{$id}/external_ids", ['api_key' => TMDB_API_KEY]);
    $imdbId = $externalIds['imdb_id'] ?? null;

    $servers = [];

    // --- SERVER 1: VidSrc (Reliable, uses TMDB) ---
    // Pattern: vidsrc.xyz/embed/movie/{tmdb}
    $url1 = "https://vidsrc.xyz/embed/" . ($type === 'tv' ? "tv/{$id}/{$season}/{$episode}" : "movie/{$id}");
    $servers[] = ['label' => 'Server 1 (Fast HD)', 'icon' => '🚀', 'data' => base64_encode($url1)];

    // --- SERVER 2: SuperEmbed (The Redirector) ---
    // Logic: Server-side CURL to resolve the final link
    $finalSuperEmbed = resolveSuperEmbed($imdbId, $id, $season, $episode, $type);
    if ($finalSuperEmbed) {
        $servers[] = ['label' => 'Server 2 (Multi-Lang)', 'icon' => '🌍', 'data' => base64_encode($finalSuperEmbed)];
    } else {
        // Fallback to VidSrc.to if SuperEmbed fails
        $fallback = "https://vidsrc.to/embed/" . ($type === 'tv' ? "tv/{$id}/{$season}/{$episode}" : "movie/{$id}");
        $servers[] = ['label' => 'Server 2 (Backup)', 'icon' => '⚡', 'data' => base64_encode($fallback)];
    }

    // --- SERVER 3: 2Embed (Strict IMDB Pattern) ---
    // Pattern: 2embed.cc/embed/{imdb}
    if ($imdbId) {
        $url3 = "https://www.2embed.cc/embed/{$imdbId}";
        if ($type === 'tv') $url3 .= "&s={$season}&e={$episode}";
        $servers[] = ['label' => 'Server 3 (Stable)', 'icon' => '🛡️', 'data' => base64_encode($url3)];
    }

    // --- SERVER 4: GoDrive (User Specific Pattern) ---
    // Movie: player.php?imdb={imdb}
    // TV: player.php?type=series&tmdb={tmdb}&season={s}&episode={e}
    $url4 = "";
    if ($type === 'movie' && $imdbId) {
        $url4 = "https://godriveplayer.com/player.php?imdb={$imdbId}";
    } elseif ($type === 'tv') {
        $url4 = "https://godriveplayer.com/player.php?type=series&tmdb={$id}&season={$season}&episode={$episode}";
    }
    
    if ($url4) {
        $servers[] = ['label' => 'Server 4 (Premium)', 'icon' => '💎', 'data' => base64_encode($url4)];
    }

    echo json_encode([
        'status' => 'success',
        'type' => $type,
        'imdb_id' => $imdbId,
        'servers' => $servers
    ]);
}

// --- HELPER: Resolve SuperEmbed via CURL ---
function resolveSuperEmbed($imdb, $tmdb, $s, $e, $type) {
    if (!$imdb && !$tmdb) return null;
    
    // Construct the request URL based on user's PHP snippet logic
    $qs = [
        'video_id' => $imdb,
        'tmdb' => $tmdb,
        'player_sources_toggle_type' => 2
    ];
    if ($type === 'tv') {
        $qs['s'] = $s;
        $qs['e'] = $e;
    }
    
    $target = "https://getsuperembed.link/?" . http_build_query($qs);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow Redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Max 3 seconds wait
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Fake User Agent to look like a browser
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $response = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    // If we got a valid URL back that isn't the source
    if ($finalUrl && filter_var($finalUrl, FILTER_VALIDATE_URL) && $finalUrl != $target) {
        return $finalUrl;
    }
    return null;
}

function fetchAPI($endpoint, $params) {
    $url = TMDB_BASE_URL . $endpoint . '?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return ($res) ? json_decode($res, true) : null;
}
?>