<?php

//creating an endpoint
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$parts = explode("/", $path);
$queries = parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY);
parse_str($queries, $queryParams);
$user = NULL;
$forked = true;

if($queries && array_key_exists('username', $queryParams)) {
    $user = $queryParams['username'];

}
else {
    header("HTTP/1.0 404 Not Found");
    echo "Required query parameter 'username' is missing";
    exit();
}
if($queries && array_key_exists('forked', $queryParams)) {
    $forked = $queryParams['forked'] != 1 ? false : true;
}

function getNextPageUrl($linkHeader) {
    $pattern = '/<([^>]+)>;\s*rel="next"/i';
    preg_match($pattern, $linkHeader, $matches);
    return isset($matches[1]) ? $matches[1] : null;
}

$ch = curl_init();

$url = 'https://api.github.com/users/' . urlencode($user) . '/repos';

$headers = [
    //"Authorization: Bearer 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",
    "Accept: application/vnd.github+json",
    "X-GitHub-Api-Version: 2022-11-28"
];

$link = NULL;

$header_callback = function($ch, $header) use (&$link) {
    $len = strlen($header);
    $parts = explode(":", $header, 2);
    if (count($parts) < 2) {   
        return $len;
    } 
    if($parts[0] == 'link') {
         $link = getNextPageUrl($parts[1]);
    }

    return $len;
};


//this array will hold the aggregate response
$responses = [];

$total_repos = 0;
$stargazers_count = 0;
$fork_count = 0;
$size = .0;
$languages = [];

while($url != NULL) {
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => "kartikeya-bhatt",
        CURLOPT_HEADERFUNCTION => $header_callback
    ]);

    $response = curl_exec($ch);

    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status_code != 200) {
        echo "HTTP request failed (Username not found): " , $status_code;
        curl_close($ch);
        exit();
    }

    curl_close($ch);

    $data = ((array) json_decode($response, true));
    foreach($data as $repo) {
        if($repo["fork"] == 1 && !$forked) {
            continue;
        }
        $total_repos += 1;
        $stargazers_count += $repo["stargazers_count"];
        $fork_count += $repo["forks_count"];
        $size += $repo["size"];
        if($repo["language"] != null) {
            $languages[] = $repo["language"];
        }
    }

    $url = $link;
}


$average_size = $total_repos == 0 ? 0 : $size / $total_repos;

$counts = array_count_values($languages);
$sorted_languages = [];
arsort($counts);
foreach ($counts as $string => $count) {
    $current = array('language' => $string, 'count' => $count);
    $sorted_languages[] = $current;
}

$payload = array(
    'total_repos' => $total_repos,
    'stargazers' => $stargazers_count,
    'fork count' => $fork_count,
    'average size' => $average_size,
    'languages' => $sorted_languages
);

$json = json_encode($payload);
header('Content-Type: application/json');
echo $json;

