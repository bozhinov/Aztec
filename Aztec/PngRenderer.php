<?php

/*
 * Copyright 2013 Metzli authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Aztec;

class PngRenderer
{
    private $factor;
    private $fgColor;
    private $bgColor;

    public function __construct($factor = 4, $fgColor = [0, 0, 0], $bgColor = [255, 255, 255])
    {
        $this->factor = $factor;
        $this->fgColor = $fgColor;
        $this->bgColor = $bgColor;
    }

    private function allocateColor($im, $color)
    {
        if (!is_array($color) || count($color) != 3) {
            throw new \InvalidArgumentException('Color array has not three components');
        }

        for ($i = 0; $i < count($color); $i++) {
            $color[$i] = intval($color[$i]);
        }
        list($r, $g, $b) = $color;

        return imagecolorallocate($im, $r, $g, $b);
    }

    public function render(array $matrix, int $width)
    {
        $f = $this->factor;
        $im = imagecreatetruecolor($width * $f, $width * $f);
        $fg = $this->allocateColor($im, $this->fgColor);
        $bg = $this->allocateColor($im, $this->bgColor);

        imagefill($im, 0, 0, $bg);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $width; $y++) {
				$bit = (isset($matrix[$x][$y])) ? $matrix[$x][$y] : FALSE;
                if ($bit !== FALSE) {
                    imagefilledrectangle($im, $x * $f, $y * $f, (($x + 1) * $f - 1), (($y + 1) * $f - 1), $fg);
                }
            }
        }

        ob_start();
        imagepng($im);
        $result = ob_get_clean();
        imagedestroy($im);

        return $result;
    }
}
