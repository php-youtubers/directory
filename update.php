<?php

$apiKey = 'AIzaSyBirL9XMWHNxuVUUGPBdSqu9UxqcY06dVo';

$content = file_get_contents('README.md');
$lines = explode("\n", $content);
$youtubers = [];

function getFollowers($url, $apiKey)
{
    preg_match('/channel_id=([a-zA-Z0-9_-]+)/', get_content_with_retry($url, 3), $matches);

    $channelId = $matches[1];
    $json_url = "https://www.googleapis.com/youtube/v3/channels?part=statistics&id={$channelId}&key={$apiKey}";
    $data = json_decode(get_content_with_retry($json_url, 3), true);

    echo "got {$data['items'][0]['statistics']['subscriberCount']} subs for {$url} \n";
    return $data['items'][0]['statistics']['subscriberCount'];
}

function get_content_with_retry($url, $maxRetries)
{
    $retryCount = 0;
    while ($retryCount < $maxRetries) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); //timeout in seconds

        $content = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($content === false || $info['http_code'] != 200) {
            $retryCount++;
            sleep(5);
        } else {
            return $content;
        }

    }
    throw new Exception('Failed after ' . $maxRetries . ' attempts.');
}

function parseWithFollowers($line, $apiKey): array
{
    $handle = substr($line, strpos($line, '[') + 1, strpos($line, ']') - (strpos($line, '[') + 1));
    $url = substr($line, strpos($line, '(https://') + 1, strpos($line, ')**') - (strpos($line, '(https://') + 1));

    $segments = explode(' ‧ ', substr($line, strpos($line, '**:') + 4));
    $description = null;
    $name = null;

    if (count($segments) === 3) {
        // Line Format: followers ‧ name ‧ description
        $name = $segments[1] ?? null;
        $description = $segments[2] ?? null;
    } elseif (count($segments) === 2) {
        // Line Format: followers ‧ description
        $description = $segments[1] ?? null;
    }

    $followers = getFollowers($url, $apiKey);

    return compact('handle', 'url', 'name', 'description', 'followers');
}

function parseWithoutFollowers($line, $apiKey): array
{
    $handle = substr($line, strpos($line, '[') + 1, strpos($line, ']') - (strpos($line, '[') + 1));
    $url = substr($line, strpos($line, '(https://') + 1, strpos($line, ')**') - (strpos($line, '(https://') + 1));

    $descriptionAndName = substr($line, strpos($line, '**:') + 4);

    $splitPos = strpos($descriptionAndName, ' ‧ ');

    if ($splitPos !== false) {
        $name = substr($descriptionAndName, 0, $splitPos);
        $description = substr($descriptionAndName, $splitPos + 3);
    } else {
        $name = null;
        $description = $descriptionAndName;
    }

    $followers = getFollowers($url, $apiKey);

    return compact('handle', 'url', 'name', 'description', 'followers');
}


foreach ($lines as $line) {
    if (empty($line = trim($line))) {
        continue;
    }

    if (preg_match('/[0-9.]*[KM]? ‧/i', $line)) {
        $youtubers[] = parseWithFollowers($line, $apiKey);
    } else {
        $youtubers[] = parseWithoutFollowers($line, $apiKey);
    }
}

uasort($youtubers, function ($a, $b) {
    return $b['followers'] <=> $a['followers'];
});

function followersCount($count)
{
    if ($count > 1000000) {
        return round($count / 1000000, 1) . 'M';
    }

    if ($count > 1000) {
        return round($count / 1000, 1) . 'K';
    }

    return $count;
}

$sortedList = '';
foreach ($youtubers as $youtuber) {
    if ($youtuber['name'] !== null) {
        $description = followersCount($youtuber['followers']) . " ‧ {$youtuber['name']} ‧ {$youtuber['description']}";
    } else {
        $description = followersCount($youtuber['followers']) . " ‧ " . $youtuber['description'];
    }

    $sortedList .= "- **[{$youtuber['handle']}](https://www.youtube.com/{$youtuber['handle']})**: {$description}\n";
}

file_put_contents('README.md', $sortedList);
