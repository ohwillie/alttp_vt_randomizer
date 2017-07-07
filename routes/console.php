<?php

use ALttP\Console\Commands\Distribution;
use ALttP\Sprite;

Artisan::command('alttp:romtospr {rom} {output}', function ($rom, $output) {
	if (filesize($rom) == 1048576 || filesize($rom) == 2097152) {
		file_put_contents($output, file_get_contents($rom, false, null, 0x80000, 0x7000)
			. file_get_contents($rom, false, null, 0xDD308, 120));
	}
});

Artisan::command('alttp:sprtopng {sprites}', function($sprites) {
	if (is_dir($sprites)) {
		$sprites = array_map(function($filename) use ($sprites) {
			return "$sprites/$filename";
		}, scandir($sprites));
		$sprites = array_filter($sprites, function($file) {
			return is_readable($file) && filesize($file) == 28792;
		});
	} else {
		if (!is_readable($filename) || filesize($filename) != 28792) {
			return;
		}
		$sprites = [$sprites];
	}
	foreach ($sprites as $spr) {
		$data = array_values(unpack("C*", file_get_contents($spr)));

		$sprite = array_slice($data, 0, 0x7000);
		$palette = array_map(function($bytes) {
			return $bytes[0] + ($bytes[1] << 8);
		}, array_chunk(array_slice($data, 0x7000, 30), 2));

		$im = imagecreatetruecolor(16, 24);
		imagesavealpha($im, true);

		$palettes = [imagecolorallocatealpha($im, 0, 0, 0, 127)];
		foreach ($palette as $color) {
			$palettes[] = imagecolorallocate($im, ($color & 0x1F) * 8, (($color & 0x3E0) >> 5) * 8, (($color & 0x7C00) >> 10) * 8);
		}
		imagefill($im, 0, 0, $palettes[0]);

		// shadow
		$shadow_color = imagecolorallocate($im, 40, 40, 40);
		$shadow = [
			[0,0,0,1,1,1,1,1,1,0,0,0],
			[0,1,1,1,1,1,1,1,1,1,1,0],
			[1,1,1,1,1,1,1,1,1,1,1,1],
			[1,1,1,1,1,1,1,1,1,1,1,1],
			[0,1,1,1,1,1,1,1,1,1,1,0],
			[0,0,0,1,1,1,1,1,1,0,0,0],
		];
		for ($y = 0; $y < 6; ++$y) {
			for ($x = 0; $x < 12; ++$x) {
				if ($shadow[$y][$x]) {
					imagesetpixel($im, $x + 2, $y + 17, $shadow_color);
				}
			}
		}

		$body = Sprite::load16x16($sprite, 0x4C0);

		for ($x = 0; $x < 16; ++$x) {
			for ($y = 0; $y < 16; ++$y) {
				imagesetpixel($im, $x, $y + 8, $palettes[$body[$x][$y]]);
			}
		}

		$head = Sprite::load16x16($sprite, 0x40);

		for ($x = 0; $x < 16; ++$x) {
			for ($y = 0; $y < 16; ++$y) {
				imagesetpixel($im, $x, $y, $palettes[$head[$x][$y]]);
			}
		}

		$dst = imagecreatetruecolor(16 * 8, 24 * 8);
		imagealphablending($dst, false);
		imagesavealpha($dst, true);
		imagecopyresized($dst, $im, 0, 0, 0, 0, 16 * 8, 24 * 8, 16, 24);

		imagepng($im, "$spr.png");
		imagedestroy($im);
		imagepng($dst, "$spr.lg.png");
		imagedestroy($dst);
	}
});

// this is a dirty hack to get some stats fast
// @TODO: make this a proper command, and clean it up
Artisan::command('alttp:ss {dir} {outdir}', function($dir, $outdir) {
	$files = scandir($dir);
	$out = [
		'items' => [
			'spheres' => [],
		],
		'locations' => [
			'spheres' => [],
		],
	];
	foreach ($files as $file) {
		$data = json_decode(file_get_contents("$dir/$file"), true);
		if (!$data) {
			continue;
		}
		foreach ($data['playthrough'] as $key => $sphere) {
			if (!is_numeric($key)) {
				continue;
			}
			foreach (array_collapse($sphere) as $location => $item) {
				if (strpos($item, 'Bottle') === 0) {
					$item = 'Bottle';
				}
				if (!isset($out['items']['spheres'][$key][$item])) {
					$out['items']['spheres'][$key][$item] = 0;
				}
				if (!isset($out['locations']['spheres'][$key][$location])) {
					$out['locations']['spheres'][$key][$location] = 0;
				}
				++$out['items']['spheres'][$key][$item];
				++$out['locations']['spheres'][$key][$location];
			}
		}
	}
	$items = $out['items']['spheres'];
	$items = Distribution::_assureColumnsExist($items);
	ksortr($items);
	$csv = fopen("$outdir/item_sphere.csv", 'w');
	fputcsv($csv, array_merge(['item'], array_keys(reset($items))));
	foreach ($items as $name => $item) {
		fputcsv($csv, array_merge([$name], $item));
	}
	fclose($csv);

	$locations = $out['locations']['spheres'];
	$locations = Distribution::_assureColumnsExist($locations);
	ksortr($locations);
	$csv = fopen("$outdir/location_sphere.csv", 'w');
	fputcsv($csv, array_merge(['item'], array_keys(reset($locations))));
	foreach ($locations as $name => $location) {
		fputcsv($csv, array_merge([$name], $location));
	}
	fclose($csv);
});
