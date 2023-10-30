<?php

$apiKey = 'AIzaSyBirL9XMWHNxuVUUGPBdSqu9UxqcY06dVo';

// Read README.md content
$readmeContent = file_get_contents('README.md');
$lines = explode("\n", $readmeContent);
$youtubers = [];

foreach ($lines as $line) {
    $line = trim($line);

    if (empty($line)) {
        continue;
    }

    $youtubeHandle = substr($line, strpos($line, '[@') + 2, strpos($line, ']') - (strpos($line, '[@') + 2));
    $url = substr($line, strpos($line, '(https://') + 9, strpos($line, ')') - (strpos($line, '(https://') + 9));
    $descriptionAndName = substr($line, strpos($line, '**:') + 4);


    $splitPos = strpos($descriptionAndName, ' ‧ ');

    if ($splitPos !== false) {
        $namePart = substr($descriptionAndName, 0, $splitPos);
        $description = substr($descriptionAndName, $splitPos + 5);
    } else {
        $namePart = null;  // Set name part to null
        $description = $descriptionAndName;
    }

    $youtubers[] = compact ('youtubeHandle', 'url', 'namePart', 'description');
}

$total = count($youtubers);
$progress = 0;

foreach ($youtubers as $index => $youtuber) {
    $getChannelIdUrl = 'https://yt.lemnoslife.com/channels?handle=@' . $youtuber['youtubeHandle'];
    $channelIdData = json_decode(file_get_contents($getChannelIdUrl), true);
    $channelId = $channelIdData['items'][0]['id'];
    $json_url = "https://www.googleapis.com/youtube/v3/channels?part=statistics&id={$channelId}&key={$apiKey}";
    $data = json_decode(file_get_contents($json_url), true);
    $followers = $data['items'][0]['statistics']['subscriberCount'];
    echo 'found ' . $followers . ' followers';
    $youtubers[$index]['channelId'] = $channelId;
    $youtubers[$index]['followers'] = $followers;

    $progress++;
    echo "\r[";
    $barSize = (int) round($progress / $total * 50);
    echo str_repeat('⏳', $barSize);
    echo str_repeat(' ', 50 - $barSize);
    echo ']';
}


uasort($youtubers, function($a, $b) {
    return $b['followers'] <=> $a['followers'];
});

$sortedList = '';
foreach ($youtubers as $youtuber) {
    if ($youtuber['namePart'] !== null) {
        $description = "{$youtuber['namePart']} ‧ {$youtuber['description']}";
    } else {
        $description = $youtuber['description'];
    }
    $sortedList .= "- **[@{$youtuber['youtubeHandle']}](https://www.youtube.com/@{$youtuber['youtubeHandle']})**: {$description}\n";
}


file_put_contents('SortedList.md', $sortedList);


