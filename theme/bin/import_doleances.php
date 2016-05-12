<?php

if (php_sapi_name() !== 'cli') {
	exit(1);
}

require __DIR__.'/../vendor/autoload.php';

use Goutte\Client;

$client = new Client();
$client->request('GET', 'https://gist.githubusercontent.com/Atala/e4f5ceb6d71dacaf21a71b6ebf023486/raw/5210afab450938e853309e06c449900eda7f8565/doleances.json');

$doleances = json_decode($client->getResponse()->getContent(), true);

foreach ($doleances as $date => $doleances_by_date) {
	foreach ($doleances_by_date as $doleance) {

		$post_title = implode(array_slice(explode(' ', $doleance), 0, 5), ' ').'…';

        $post_params = array(
          'post_date'     => $date,
          'post_title'    => $post_title,
          'post_content'  => $doleance,
          'post_status'   => 'publish',
          'post_author'   => 1,
        );

        $post = wp_insert_post($post_params, true);

        if (is_wp_error($post)) {
        	echo "Error while creating post:\n";
            foreach ($post->get_error_messages() as $error) {
                echo "{$error}\n";
            }
            continue;
        }

        echo "Post \"{$post_title}\" created.\n";
    }
}

