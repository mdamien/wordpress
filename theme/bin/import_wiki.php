<?php

if (php_sapi_name() !== 'cli') {
	exit(1);
}

require __DIR__.'/../vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

$wiki_base_url = 'https://wiki.nuitdebout.fr/api.php';

$client = new Client();

function wiki_get_cities()
{
	global $wiki_base_url, $client;

	$client->request('GET', $wiki_base_url.'?action=query&generator=categorymembers&gcmtitle=Cat%C3%A9gorie:Ville_NuitDebout&prop=pagecllimit=max&gcmlimit=max&format=json');

	$data = json_decode($client->getResponse()->getContent(), true);

	$exclude = ['en', 'fr', 'pt'];
	$cities = [];
	foreach ($data['query']['pages'] as $page) {
		if (preg_match('#^Villes/(.*)#', $page['title'], $matches)) {
			$city = $matches[1];
			if (!in_array($city, $exclude)) {
				$cities[] = [
					'name' => $city,
					'page_title' => $page['title'],
				];
			}
		}
	}

	return $cities;
}



function wiki_text_extract_places($wiki_text)
{
	if (preg_match('#<h2><span class="mw-headline" id="Lieux"></h2>#', $wiki_text, $matches)) {
		print_r($matches);
	}
}

function wiki_get_city_details($city)
{
	global $wiki_base_url, $client;

	$client->request('GET', $wiki_base_url.'?action=parse&page='.$city['page_title'].'&contentmodel=wikitext&format=json');

	$data = json_decode($client->getResponse()->getContent(), true);

	$wiki_text = $data['parse']['text']['*'];

	$crawler = new DomCrawler($wiki_text);

	// <h2><span class="mw-headline" id="Lieux">Lieux</span><span class="mw-editsection"><span class="mw-editsection-bracket">[</span><a href="/index.php?title=Villes/Auch&amp;veaction=edit&amp;vesection=2" title="Modifier la section : Lieux" class="mw-editsection-visualeditor">modifier</a><span class="mw-editsection-divider"> | </span><a href="/index.php?title=Villes/Auch&amp;action=edit&amp;section=2" title="Modifier la section : Lieux">modifier le wikicode</a><span class="mw-editsection-bracket">]</span></span></h2>
	// <p>Rassemblement place de la République (Parvis de la cathédrale)
	// </p>

	// Extract place from HTML code
	$place = null;
	if (count($crawler->filter('#Lieux')) === 1) {
		$crawler->filter('#Lieux')->parents()->each(function($node) use (&$place) {
			if ($node->getNode(0)->tagName === 'h2') {
				$node->nextAll()->each(function($node, $i) use (&$place) {
					if ($i === 0) {
						$place = $node->text();
					}
				});
			}
		});
	}

	return [
		'name' => $city['name'],
		'place' => $place,
	];
}

$cities = wiki_get_cities();

foreach ($cities as $city) {
	$city_details = wiki_get_city_details($city);
	print_r($city_details);
}