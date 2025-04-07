<?php

if (!isset($argv[1])) {
    die("Usage: php script.php <API_KEY>\n");
}

$apiKey = $argv[1];
$content = file_get_contents('README.md');
$lines = explode("\n", $content);
$youtubers = [];

function getFollowers($url, $apiKey): array
{
    try {
        // Try to get channel ID from the original URL
        try {
            $content = get_content_with_retry($url, 2);
        } catch (Exception $e) {
            $alternativeUrls = generateAlternativeUrls($url);
            foreach ($alternativeUrls as $altUrl) {
                echo "Trying alternative URL: {$altUrl}\n";
                try {
                    $content = get_content_with_retry($altUrl, 1);
                    if (preg_match('/channel_id=([a-zA-Z0-9_-]+)/', $content, $matches)) {
                        // Found a working URL, update the original URL
                        echo "URL corrected: {$url} -> {$altUrl}\n";
                        $url = $altUrl;
                        break;
                    }
                } catch (Exception $e) {
                    echo "Alternative URL failed: {$altUrl}\n";
                    continue;
                }
            }
        }
        if (!preg_match('/channel_id=([a-zA-Z0-9_-]+)/', $content, $matches)) {
            if (!isset($matches[1])) {
                throw new Exception("Could not extract channel ID from {$url}");
            }
        }

        $channelId = $matches[1];
        $json_url = "https://www.googleapis.com/youtube/v3/channels?part=statistics&id={$channelId}&key={$apiKey}";
        $data = json_decode(get_content_with_retry($json_url, 2), true);

        if (!isset($data['items'][0]['statistics']['subscriberCount'])) {
            throw new Exception("Could not get subscriber count for {$url}");
        }

        $subscriberCount = $data['items'][0]['statistics']['subscriberCount'];
        echo "Got {$subscriberCount} subs for {$url}\n";
        return [
            'count' => $subscriberCount,
            'url' => $url // Return the potentially corrected URL
        ];
    } catch (Exception $e) {
        // Log the error
        $errorMessage = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
        file_put_contents('script_errors.log', $errorMessage, FILE_APPEND);
        echo "Error: " . $e->getMessage() . "\n";

        // Return default values
        return [
            'count' => 0,
            'url' => $url
        ];
    }
}

/**
 * Generate alternative URL formats for a YouTube channel
 * 
 * @param string $url Original URL
 * @return array Array of alternative URLs
 */
function generateAlternativeUrls($url)
{
    $alternatives = [];

    // Extract handle or channel ID from URL
    if (preg_match('/@([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $handle = $matches[1];
        // Try /c/ format
        $alternatives[] = "https://www.youtube.com/c/{$handle}";
        // Try /channel/ format if it looks like a channel ID
        if (strlen($handle) > 20) {
            $alternatives[] = "https://www.youtube.com/channel/{$handle}";
        }
        // Try /user/ format
        $alternatives[] = "https://www.youtube.com/user/{$handle}";
    } elseif (preg_match('/\/c\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $handle = $matches[1];
        // Try @ format
        $alternatives[] = "https://www.youtube.com/@{$handle}";
        // Try /user/ format
        $alternatives[] = "https://www.youtube.com/user/{$handle}";
    } elseif (preg_match('/\/channel\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $channelId = $matches[1];
        // Not much we can do with channel IDs, but try @ format if it's not a typical channel ID
        if (strlen($channelId) < 20) {
            $alternatives[] = "https://www.youtube.com/@{$channelId}";
        }
    } elseif (preg_match('/\/user\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $username = $matches[1];
        // Try @ format
        $alternatives[] = "https://www.youtube.com/@{$username}";
        // Try /c/ format
        $alternatives[] = "https://www.youtube.com/c/{$username}";
    }

    return $alternatives;
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
    throw new Exception($url . ' - failed after ' . $maxRetries . ' attempts.');
}

/**
 * Parse a line with followers information
 * 
 * @param string $line Line from README.md
 * @param string $apiKey YouTube API key
 * @return array Parsed information
 */
function parseWithFollowers($line, $apiKey): array
{
    // Extract handle from markdown link format
    $handle = extractHandle($line);

    // Extract URL from markdown link format
    $url = extractUrl($line);

    // Extract segments (followers, name, description)
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

    // Get followers and potentially corrected URL
    $result = getFollowers($url, $apiKey);
    $followers = $result['count'];
    $url = $result['url']; // Use potentially corrected URL

    return compact('handle', 'url', 'name', 'description', 'followers');
}

function extractHandle(string $line): string
{
    if (preg_match('/\[([^\]]+)\]/', $line, $matches)) {
        return $matches[1];
    }
    return '';
}

function extractUrl(string $line): string
{
    if (preg_match('/\(([^)]+)\)/', $line, $matches)) {
        return $matches[1];
    }
    return '';
}

function parseWithoutFollowers(string $line, string $apiKey): array
{
    // Extract handle from markdown link format
    $handle = extractHandle($line);

    // Extract URL from markdown link format
    $url = extractUrl($line);

    // Extract description and name
    $descriptionAndName = substr($line, strpos($line, '**:') + 4);
    $splitPos = strpos($descriptionAndName, ' ‧ ');

    if ($splitPos !== false) {
        $name = substr($descriptionAndName, 0, $splitPos);
        $description = substr($descriptionAndName, $splitPos + 3);
    } else {
        $name = null;
        $description = $descriptionAndName;
    }

    // Get followers and potentially corrected URL
    $result = getFollowers($url, $apiKey);
    $followers = $result['count'];
    $url = $result['url']; // Use potentially corrected URL

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

function formatReadmeContent(array $youtubers): string
{
    $sortedList = '';
    foreach ($youtubers as $youtuber) {
        // Format description with name if available
        if ($youtuber['name'] !== null) {
            $description = followersCount($youtuber['followers']) . " ‧ {$youtuber['name']} ‧ {$youtuber['description']}";
        } else {
            $description = followersCount($youtuber['followers']) . " ‧ " . $youtuber['description'];
        }

        // Use the potentially corrected URL
        $sortedList .= "- **[{$youtuber['handle']}]({$youtuber['url']})**: {$description}\n";
    }
    return $sortedList;
}

// Sort youtubers by follower count
uasort($youtubers, function ($a, $b) {
    return $b['followers'] <=> $a['followers'];
});

// Format and write to README.md
$sortedList = formatReadmeContent($youtubers);
file_put_contents('README.md', $sortedList);

echo "README.md updated successfully with " . count($youtubers) . " youtubers.\n";
