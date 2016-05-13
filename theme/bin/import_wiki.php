<?php

if (php_sapi_name() !== 'cli') {
	exit(1);
}

require __DIR__.'/../vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

define('WIKI_BASE_URL', 'https://wiki.nuitdebout.fr/api.php');

function wiki_get_cities()
{
	$client = new Client();
	$client->request('GET', WIKI_BASE_URL
		.'?action=query&generator=categorymembers&gcmtitle=Cat%C3%A9gorie:Ville_NuitDebout&prop=pagecllimit=max&gcmlimit=max&format=json');

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

function wiki_get_city_details($city)
{
	$client = new Client();
	$client->request('GET', WIKI_BASE_URL
		.'?action=parse&page='.$city['page_title'].'&contentmodel=wikitext&format=json');

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

	if ($place == 'Rassemblement sur la place XXXXXXX.') {
		$place = null;
	}

	return [
		'name' => $city['name'],
		'place' => $place,
		'links' => $data['parse']['externallinks'],
		'wiki_url' => 'https://wiki.nuitdebout.fr/wiki/'.$city['page_title'],
	];
}

function get_map_data($city_name)
{
	static $city_map;

	if (empty($city_map)) {
		$geojson = json_decode(file_get_contents(__DIR__.'/nuitdebout.geojson'), true);
		$city_map = [];
		foreach ($geojson['features'] as $feature) {
			$name = $feature['properties']['name'];

			$name = preg_replace('/Nuit ?Debout/i', '', $name);
			$name = str_replace('#', '', $name);
			$name = trim($name);

			if (!empty($name)) {
				$city_map[$name] = [
					'coordinates' => $feature['geometry']['coordinates'],
					'description' => isset($feature['properties']['description']) ? $feature['properties']['description'] : null,
				];
			}
		}
	}

	if (isset($city_map[$city_name])) {
		return $city_map[$city_name];
	}
}

function get_city_page($city_name)
{
	static $city_pages;

	if (empty($city_pages)) {
		$pages = get_pages([
			'child_of' => 17,
			'post_type' => 'page',
			'post_status' => 'publish'
		]);

		$city_pages = [];
		foreach ($pages as $page) {
			$city_pages[$page->post_title] = $page;
		}
	}

	return isset($city_pages[$city_name]) ? $city_pages[$city_name] : null;
}

//////////////////////////////////////////

$parent_id = 17;

$cities = wiki_get_cities();
foreach ($cities as $city) {

	$city = wiki_get_city_details($city);

	echo "Processing {$city['name']}…\n";

	$is_new = false;
	if (!$city_page = get_city_page($city['name'])) {
		echo "- Page does not exist, creating page…\n";
		$is_new = true;

		$post_params = array(
			'post_title'    =>  $city['name'],
			'post_content'  => '',
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_type' => 'page',
			'post_parent' =>  $parent_id,
		);

		$page_id = wp_insert_post($post_params, true);
		if (is_wp_error($page_id)) {
			echo "ERROR:\n";
			foreach ($page_id->get_error_messages() as $error) {
				echo "{$error}\n";
			}
			exit(1);
		}

		$city_page = get_post($page_id);
	} else {
		echo "- Page already exists\n";
	}

	update_post_meta($city_page->ID, '_wp_page_template', 'page-ville.php');
	update_post_meta($city_page->ID, 'wiki_page_url', $city['wiki_url']);

	if ($map_data = get_map_data($city['name'])) {
		echo "- Updating map position\n";
		update_post_meta($city_page->ID, 'map_position', implode(',', $map_data['coordinates']));

		if ($is_new && !empty($map_data['description'])) {
			$city_page->post_content = $map_data['description'];
			wp_update_post($city_page);
		}
	}

}