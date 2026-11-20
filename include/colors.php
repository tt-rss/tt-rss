<?php
namespace Colors;

require_once __DIR__ . '/../lib/floIcon.php';

function _resolve_htmlcolor(string $color): string {
	$htmlcolors =  ["aliceblue" => "#f0f8ff",
		"antiquewhite" => "#faebd7",
		"aqua" => "#00ffff",
		"aquamarine" => "#7fffd4",
		"azure" => "#f0ffff",
		"beige" => "#f5f5dc",
		"bisque" => "#ffe4c4",
		"black" => "#000000",
		"blanchedalmond" => "#ffebcd",
		"blue" => "#0000ff",
		"blueviolet" => "#8a2be2",
		"brown" => "#a52a2a",
		"burlywood" => "#deb887",
		"cadetblue" => "#5f9ea0",
		"chartreuse" => "#7fff00",
		"chocolate" => "#d2691e",
		"coral" => "#ff7f50",
		"cornflowerblue" => "#6495ed",
		"cornsilk" => "#fff8dc",
		"crimson" => "#dc143c",
		"cyan" => "#00ffff",
		"darkblue" => "#00008b",
		"darkcyan" => "#008b8b",
		"darkgoldenrod" => "#b8860b",
		"darkgray" => "#a9a9a9",
		"darkgrey" => "#a9a9a9",
		"darkgreen" => "#006400",
		"darkkhaki" => "#bdb76b",
		"darkmagenta" => "#8b008b",
		"darkolivegreen" => "#556b2f",
		"darkorange" => "#ff8c00",
		"darkorchid" => "#9932cc",
		"darkred" => "#8b0000",
		"darksalmon" => "#e9967a",
		"darkseagreen" => "#8fbc8f",
		"darkslateblue" => "#483d8b",
		"darkslategray" => "#2f4f4f",
		"darkslategrey" => "#2f4f4f",
		"darkturquoise" => "#00ced1",
		"darkviolet" => "#9400d3",
		"deeppink" => "#ff1493",
		"deepskyblue" => "#00bfff",
		"dimgray" => "#696969",
		"dimgrey" => "#696969",
		"dodgerblue" => "#1e90ff",
		"firebrick" => "#b22222",
		"floralwhite" => "#fffaf0",
		"forestgreen" => "#228b22",
		"fuchsia" => "#ff00ff",
		"gainsboro" => "#dcdcdc",
		"ghostwhite" => "#f8f8ff",
		"gold" => "#ffd700",
		"goldenrod" => "#daa520",
		"gray" => "#808080",
		"grey" => "#808080",
		"green" => "#008000",
		"greenyellow" => "#adff2f",
		"honeydew" => "#f0fff0",
		"hotpink" => "#ff69b4",
		"indianred " => "#cd5c5c",
		"indigo " => "#4b0082",
		"ivory" => "#fffff0",
		"khaki" => "#f0e68c",
		"lavender" => "#e6e6fa",
		"lavenderblush" => "#fff0f5",
		"lawngreen" => "#7cfc00",
		"lemonchiffon" => "#fffacd",
		"lightblue" => "#add8e6",
		"lightcoral" => "#f08080",
		"lightcyan" => "#e0ffff",
		"lightgoldenrodyellow" => "#fafad2",
		"lightgray" => "#d3d3d3",
		"lightgrey" => "#d3d3d3",
		"lightgreen" => "#90ee90",
		"lightpink" => "#ffb6c1",
		"lightsalmon" => "#ffa07a",
		"lightseagreen" => "#20b2aa",
		"lightskyblue" => "#87cefa",
		"lightslategray" => "#778899",
		"lightslategrey" => "#778899",
		"lightsteelblue" => "#b0c4de",
		"lightyellow" => "#ffffe0",
		"lime" => "#00ff00",
		"limegreen" => "#32cd32",
		"linen" => "#faf0e6",
		"magenta" => "#ff00ff",
		"maroon" => "#800000",
		"mediumaquamarine" => "#66cdaa",
		"mediumblue" => "#0000cd",
		"mediumorchid" => "#ba55d3",
		"mediumpurple" => "#9370db",
		"mediumseagreen" => "#3cb371",
		"mediumslateblue" => "#7b68ee",
		"mediumspringgreen" => "#00fa9a",
		"mediumturquoise" => "#48d1cc",
		"mediumvioletred" => "#c71585",
		"midnightblue" => "#191970",
		"mintcream" => "#f5fffa",
		"mistyrose" => "#ffe4e1",
		"moccasin" => "#ffe4b5",
		"navajowhite" => "#ffdead",
		"navy" => "#000080",
		"oldlace" => "#fdf5e6",
		"olive" => "#808000",
		"olivedrab" => "#6b8e23",
		"orange" => "#ffa500",
		"orangered" => "#ff4500",
		"orchid" => "#da70d6",
		"palegoldenrod" => "#eee8aa",
		"palegreen" => "#98fb98",
		"paleturquoise" => "#afeeee",
		"palevioletred" => "#db7093",
		"papayawhip" => "#ffefd5",
		"peachpuff" => "#ffdab9",
		"peru" => "#cd853f",
		"pink" => "#ffc0cb",
		"plum" => "#dda0dd",
		"powderblue" => "#b0e0e6",
		"purple" => "#800080",
		"red" => "#ff0000",
		"rosybrown" => "#bc8f8f",
		"royalblue" => "#4169e1",
		"saddlebrown" => "#8b4513",
		"salmon" => "#fa8072",
		"sandybrown" => "#f4a460",
		"seagreen" => "#2e8b57",
		"seashell" => "#fff5ee",
		"sienna" => "#a0522d",
		"silver" => "#c0c0c0",
		"skyblue" => "#87ceeb",
		"slateblue" => "#6a5acd",
		"slategray" => "#708090",
		"slategrey" => "#708090",
		"snow" => "#fffafa",
		"springgreen" => "#00ff7f",
		"steelblue" => "#4682b4",
		"tan" => "#d2b48c",
		"teal" => "#008080",
		"thistle" => "#d8bfd8",
		"tomato" => "#ff6347",
		"turquoise" => "#40e0d0",
		"violet" => "#ee82ee",
		"wheat" => "#f5deb3",
		"white" => "#ffffff",
		"whitesmoke" => "#f5f5f5",
		"yellow" => "#ffff00",
		"yellowgreen" => "#9acd32"];

	$color = strtolower($color);

	return $htmlcolors[$color] ?? $color;
}

/**
 * RGB >> HSL
 *
 * @param array{0: int, 1: int, 2: int} $rgb
 *
 * @return array{0: float, 1: float, 2: float}
 */
function _color_rgb2hsl(array $rgb): array {
  $r = $rgb[0]; $g = $rgb[1]; $b = $rgb[2];
  $min = min($r, min($g, $b)); $max = max($r, max($g, $b));
  $delta = $max - $min; $l = ($min + $max) / 2; $s = 0;
  if ($l > 0 && $l < 1) {
    $s = $delta / ($l < 0.5 ? (2 * $l) : (2 - 2 * $l));
  }
  $h = 0;
  if ($delta > 0) {
    if ($max == $r && $max != $g) $h += ($g - $b) / $delta;
    if ($max == $g && $max != $b) $h += (2 + ($b - $r) / $delta);
    if ($max == $b && $max != $r) $h += (4 + ($r - $g) / $delta);
    $h /= 6;
  } return [$h, $s, $l];
}

/**
 * HSL >> RGB
 *
 * @param array{0: float, 1: float, 2: float} $hsl
 *
 * @return array{0: int, 1: int, 2: int}
 */
function _color_hsl2rgb($hsl): array {
  $h = $hsl[0]; $s = $hsl[1]; $l = $hsl[2];
  $m2 = ($l <= 0.5) ? $l * ($s + 1) : $l + $s - $l*$s;
  $m1 = $l * 2 - $m2;
  return [_color_hue2rgb($m1, $m2, $h + 0.33333),
               _color_hue2rgb($m1, $m2, $h),
               _color_hue2rgb($m1, $m2, $h - 0.33333)];
}

### Helper function for _color_hsl2rgb().
function _color_hue2rgb(float $m1, float $m2, float $h): int {
	$rv = $m1;

	$h = ($h < 0) ? $h + 1 : (($h > 1) ? $h - 1 : $h);

	if ($h * 6 < 1) {
		$rv = $m1 + ($m2 - $m1) * $h * 6;
	} else if ($h * 2 < 1) {
		$rv = $m2;
	} else if ($h * 3 < 2) {
		$rv = $m1 + ($m2 - $m1) * (0.66666 - $h) * 6;
	}

	return (int) round($rv);
}

/**
 * Convert a hex color into an RGB triplet.
 *
 * @return array{0: int, 1: int, 2: int}
 */
function _color_unpack(string $hex, bool $normalize = false): array {
	$hex = str_starts_with($hex, '#') ? substr($hex, 1) : _resolve_htmlcolor($hex);

	if (strlen($hex) == 4) {
		$hex = $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
	}

	$c = hexdec($hex);
	$out = [];

	for ($i = 16; $i >= 0; $i -= 8) {
		$out[] = (($c >> $i) & 0xFF) / ($normalize ? 255 : 1);
	}

	return $out;
}

/**
 * Convert an RGB triplet to a hex color.
 *
 * @param array{0: int, 1: int, 2: int} $rgb
 */
function _color_pack(array $rgb, bool $normalize = false): string {
	$out = 0;

	foreach ($rgb as $k => $v) {
		$out |= (($v * ($normalize ? 255 : 1)) << (16 - $k * 8));
	}

	return '#'. str_pad(dechex($out), 6, '0', STR_PAD_LEFT);
}

/**
 * @param array{0: int, 1: int, 2: int} $arr
 *
 * @return array{0: float, 1: float, 2: float}
 */
function rgb2hsl(array $arr): array {
	$r = $arr[0];
	$g = $arr[1];
	$b = $arr[2];

   $var_R = ($r / 255);
   $var_G = ($g / 255);
   $var_B = ($b / 255);

   $var_Min = min($var_R, $var_G, $var_B);
   $var_Max = max($var_R, $var_G, $var_B);
   $del_Max = $var_Max - $var_Min;

   $v = $var_Max;

   if ($del_Max == 0) {
      $h = 0;
      $s = 0;
   } else {
      $s = $del_Max / $var_Max;

      $del_R = ((($var_Max - $var_R ) / 6 ) + ($del_Max / 2 ) ) / $del_Max;
      $del_G = ((($var_Max - $var_G ) / 6 ) + ($del_Max / 2 ) ) / $del_Max;
      $del_B = ((($var_Max - $var_B ) / 6 ) + ($del_Max / 2 ) ) / $del_Max;

			if ($var_R == $var_Max) {
				$h = $del_B - $del_G;
			} else if ($var_G == $var_Max) {
				$h = (1 / 3 ) + $del_R - $del_B;
			} else {
				// ($var_B == $var_Max)
				$h = (2 / 3 ) + $del_G - $del_R;
			}

      if ($h < 0) $h++;
      if ($h > 1) $h--;
   }

   return [$h, $s, $v];
}

/**
 * @param array{0: float, 1: float, 2: float} $arr
 *
 * @return array{0: int, 1: int, 2: int}
 */
function hsl2rgb(array $arr): array {
	$h = $arr[0];
	$s = $arr[1];
	$v = $arr[2];

    if($s == 0) {
        $r = $g = $B = $v * 255;
    } else {
        $var_H = $h * 6;
        $var_i = floor($var_H );
        $var_1 = $v * (1 - $s );
        $var_2 = $v * (1 - $s * ($var_H - $var_i ) );
        $var_3 = $v * (1 - $s * (1 - ($var_H - $var_i ) ) );

        if       ($var_i == 0) { $var_R = $v     ; $var_G = $var_3  ; $var_B = $var_1 ; }
        else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $v      ; $var_B = $var_1 ; }
        else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $v      ; $var_B = $var_3 ; }
        else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $v     ; }
        else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $v     ; }
        else                   { $var_R = $v     ; $var_G = $var_1  ; $var_B = $var_2 ; }

        $r = (int) round($var_R * 255);
        $g = (int) round($var_G * 255);
        $B = (int) round($var_B * 255);
    }
    return [$r, $g, $B];
}

/**
 * @return array<int, string>|null
*/
function colorPalette(string $imageFile, int $numColors, int $granularity = 5): ?array {
	$granularity = max(1, abs($granularity));
	$colors = [];

	$size = @getimagesize($imageFile);
	$img = null;

	// to enable .ico support place floIcon.php into lib/
	if (strtolower($size['mime']) == 'image/vnd.microsoft.icon') {
		if (class_exists("floIcon")) {
			$ico = new \floIcon();
			@$ico->readICO($imageFile);

			if(count($ico->images) == 0) {
				return null;
			} else {
				$img = @$ico->images[count($ico->images)-1]->getImageResource();
			}
		} else {
			return null;
		}
	} else if ($size[0] > 0 && $size[1] > 0) {
		$img = @imagecreatefromstring(file_get_contents($imageFile));
	}

	if (!$img) {
		return null;
	}

	for($x = 0; $x < $size[0]; $x += $granularity) {
		for($y = 0; $y < $size[1]; $y += $granularity) {
			$thisColor = imagecolorat($img, $x, $y);
			$rgb = imagecolorsforindex($img, $thisColor);
			$red = round(round(($rgb['red'] / 0x33)) * 0x33);
			$green = round(round(($rgb['green'] / 0x33)) * 0x33);
			$blue = round(round(($rgb['blue'] / 0x33)) * 0x33);
			$thisRGB = sprintf('%02X%02X%02X', $red, $green, $blue);

			if(array_key_exists($thisRGB, $colors)) {
				$colors[$thisRGB]++;
			} else {
				$colors[$thisRGB] = 1;
			}
		}
	}

	arsort($colors);
	return array_slice(array_keys($colors), 0, $numColors);
}

	function calculate_avg_color(string $iconFile): string {
		$palette = colorPalette($iconFile, 4, 4);

		if (is_array($palette)) {
			foreach ($palette as $p) {
				$hsl = rgb2hsl(_color_unpack("#$p"));

				// TODO: fix this and drop the ignore
				if ($hsl[1] > 0.25 && $hsl[2] > 0.25 &&
					/** @phpstan-ignore smaller.alwaysFalse, booleanAnd.alwaysFalse */
					!($hsl[0] >= 0 && $hsl[0] < 0.01 && $hsl[1] < 0.01) &&
					!($hsl[0] >= 0 && $hsl[0] < 0.01 && $hsl[2] > 0.99)) {

					return _color_pack(hsl2rgb($hsl));
				}
			}
		}
		return '';
	}

