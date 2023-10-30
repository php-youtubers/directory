import os
import json
import asyncio
import aiohttp
from dotenv import load_dotenv

load_dotenv()

API_KEY = os.getenv('API_KEY')


async def fetch_channel_id(session, handle):
    url = f'https://yt.lemnoslife.com/channels?handle=@{handle}'
    async with session.get(url) as response:
        data = await response.json()
        return data['items'][0]['id']


async def fetch_subscriber_count(session, channel_id):
    url = f"https://www.googleapis.com/youtube/v3/channels?part=statistics&id={channel_id}&key={API_KEY}"
    async with session.get(url) as response:
        data = await response.json()
        return data['items'][0]['statistics']['subscriberCount']


async def fetch_data(youtuber):
    async with aiohttp.ClientSession() as session:
        channel_id = await fetch_channel_id(session, youtuber["youtubeHandle"])
        followers = await fetch_subscriber_count(session, channel_id)
        return channel_id, followers


# Read README.md content
with open('../README.md', 'r') as f:
    lines = f.readlines()

youtubers = []

for line in lines:
    line = line.strip()

    if not line:
        continue

    youtube_handle_start = line.find('[@') + 2
    youtube_handle_end = line.find(']')
    youtube_handle = line[youtube_handle_start:youtube_handle_end]

    url_start = line.find('(https://') + 9
    url_end = line.find(')')
    url = line[url_start:url_end]

    description_and_name_start = line.find('**:') + 4
    description_and_name = line[description_and_name_start:]

    split_pos = description_and_name.find(' ‧ ')

    if split_pos != -1:
        name_part = description_and_name[:split_pos]
        description = description_and_name[split_pos + 5:]
    else:
        name_part = None
        description = description_and_name

    youtubers.append({
        'youtubeHandle': youtube_handle,
        'url': url,
        'namePart': name_part,
        'description': description
    })

loop = asyncio.get_event_loop()
tasks = [fetch_data(youtuber) for youtuber in youtubers]
results = loop.run_until_complete(asyncio.gather(*tasks))

for index, (channel_id, followers) in enumerate(results):
    youtubers[index]['channelId'] = channel_id
    youtubers[index]['followers'] = followers

youtubers.sort(key=lambda x: x['followers'], reverse=True)

sorted_list = ''
for youtuber in youtubers:
    if youtuber['namePart']:
        description = f"{youtuber['namePart']} ‧ {youtuber['description']}"
    else:
        description = youtuber['description']
    sorted_list += f"- **[@{youtuber['youtubeHandle']}](https://www.youtube.com/@{youtuber['youtubeHandle']})**: {description}\n"

with open('../SortedList.md', 'w') as f:
    f.write(sorted_list)
