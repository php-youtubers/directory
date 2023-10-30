<?php

/**
 * Fetches channel statistics for a list of YouTube channel IDs (max 50 ids are permitted).
 */
function fetchSubscriberCounts($ids) {
    $apiKey = getenv('YOUTUBE_API_KEY');
    $baseUrl = "https://youtube.googleapis.com/youtube/v3/channels?key=$apiKey&part=statistics&maxResults=50";

    $idQueryParams = array_map(fn ($id)  => 'id=' . urlencode($id), $ids);
    $idQueryString = implode('&', $idQueryParams);

    $response = file_get_contents($baseUrl . '&' . $idQueryString);
    $response = json_decode($response, true);

    $totalResults = $response['pageInfo']['totalResults'];
    $perPage = $response['pageInfo']['resultsPerPage'];

    $results = $response['items'];

    // Key results by channel ID.
    $results = array_combine(array_column($results, 'id'), $results);

    // Fetch pages until we get all results.
    if ($totalResults > $perPage) {
        $pages = ceil($totalResults / $perPage);
        for ($i = 2; $i <= $pages; $i++) {
            $nextPageToken = $response['nextPageToken'];

            $response = file_get_contents($baseUrl . '&' . $idQueryString . '&pageToken=' . $nextPageToken);
            $response = json_decode($response, true);

            // Key results by channel ID and merge with existing results.
            $results = array_merge(
                $results,
                array_combine(array_column($results, 'id'), $response['items'])
            );
        }
    }

    return $results;
}

/**
 * Formats a subscriber count to a human readable format.
 */
function formatSubscriberCount($count) {
    if ($count > 1000000) {
        return round($count / 1000000, 1) . 'M';
    }

    if ($count > 1000) {
        return round($count / 1000, 1) . 'K';
    }

    return $count;
}

$list = json_decode(file_get_contents('list.json'), true);

// Chunk the list into groups of 50 as that is the maximum number of IDs that can be passed to the API.
$list = array_chunk($list, 50);

// Fetch subscriber counts for each chunk and attach them to the corresponding channel.
foreach ($list as $key => $chunk) {
    $ids = array_column($chunk, 'youtube_id');
    $subscriberCounts = fetchSubscriberCounts($ids);

    foreach ($chunk as $index => $item) {
        $list[$key][$index]['subscriber_count'] = $subscriberCounts[$item['youtube_id']]['statistics']['subscriberCount'];
    }
}

// Flatten the list.
$list = array_merge(...$list);

// Sort the list by subscriber count.
usort($list, function ($a, $b) {
    return $b['subscriber_count'] <=> $a['subscriber_count'];
});

// Build the markdown.
$lines = array_map(function ($item) {
    $line = sprintf("- **[%s](https://www.youtube.com/%s)**: %s",
        $item['youtube_handle'],
        $item['youtube_handle'],
        $item['name'],
    );

    if ($item['position']) {
        $line .= " ‧ " . $item['position'];
    }

    $line .= " (" . formatSubscriberCount($item['subscriber_count']) . " subscribers)";

    return $line;
}, $list);

$markdown = implode("\n", $lines);

// Write the markdown to the README.
file_put_contents('README.md', $markdown);
