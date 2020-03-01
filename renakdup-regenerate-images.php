<?php

namespace RenakDup\MuPlugin\RegenerateImages;

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

use WP_CLI;

(new Main())->init();

/**
 * Class Main
 * Mu-plugin для генерации миниатюр новых размеров через wp-cli команду
 *
 * wp renakdup:generate-thumbnails --count
 * wp renakdup:generate-thumbnails --offset=100 --number=10
 */
class Main
{
    const IMAGE_SIZES_FOR_REGENERATE = [
		'thumbnail',
		'medium',
		'full',
		// etc.
    ];

    public function init()
    {
        add_action('init', [$this, 'addCliCommand']);
    }

    public function addCliCommand()
    {
        WP_CLI::add_command('renakdup:generate-thumbnails', [$this, 'executeCommand']);
    }

    public function getImagesCount()
    {
        global $wpdb;

        $number = $wpdb->get_results(
        	'SELECT COUNT(ID)
            FROM wp_posts
            WHERE post_type = "attachment"
            ORDER BY ID DESC', ARRAY_N
        );

        if (! $number || ! isset($number[0][0])) {
            return false;
        }

        return $number[0][0];
    }

    public function getImages($args = [])
    {
        global $wpdb;

        if (! isset($args['offset']) || ! is_numeric($args['offset'])
            || ! isset($args['number']) || ! is_numeric($args['number'])) {
            WP_CLI::line('================');
            WP_CLI::error('Arguments [offset, number] has errors');
        }

		return $wpdb->get_results(
            'SELECT ID
            FROM wp_posts
            WHERE post_type =  "attachment"
            ORDER BY ID DESC
            LIMIT ' . $args['offset'] . ' , ' . $args['number']
        );
    }

    public function executeCommand($args, $assoc_args = [])
    {
        $start = microtime(true);

        if (! $assoc_args) {
            WP_CLI::line('================');
            WP_CLI::error('Error in command`s arguments');
        }

        if (isset($assoc_args['count'])) {
            WP_CLI::line('================');
            dump('Images count: ' . self::getImagesCount());
            exit;
        }

        $images = self::getImages($assoc_args);

        if (! $images) {
            WP_CLI::line('================');
            WP_CLI::error('Images not found');
        }

        $number_generated = 0;
        $number_images = count($images);

        foreach ($images as $item) {
            self::generateImageThumbnails($item->ID);

            $number_generated++;
            echo "{$number_generated}/{$number_images} Regenerated ID: {$item->ID}" . PHP_EOL;
        }

        $finish = microtime(true);
        $time_total = round($finish - $start, 3);

        WP_CLI::line('================');
        dump('Queries: ' . get_num_queries());
        dump('Time total: ' . $time_total);
        dump('Command arguments: ');
        print_R($assoc_args);
        WP_CLI::success('Command finished');
    }


    /**
     * Генерируем размеры для картинки
     *
     * @param $imageID
     */
    public function generateImageThumbnails($imageID)
    {
        $options = [
            'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
            'launch'     => false,
            'exit_error' => false,
        ];

        if (! $imageID || ! is_numeric($imageID)) {
            return;
        }

        foreach (self::IMAGE_SIZES_FOR_REGENERATE as $size) {
            WP_CLI::runcommand("media regenerate {$imageID} --only-missing --image_size={$size}", $options);
        }
    }
}
