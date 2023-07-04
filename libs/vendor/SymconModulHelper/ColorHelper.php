<?php

trait ColorHelper
{
    protected function HexToRGB($value)
    {
        $RGB = [];
        $RGB[0] = (($value >> 16) & 0xFF);
        $RGB[1] = (($value >> 8) & 0xFF);
        $RGB[2] = ($value & 0xFF);
        $this->SendDebug('HexToRGB', 'R: ' . $RGB[0] . ' G: ' . $RGB[1] . ' B: ' . $RGB[2], 0);
        return $RGB;
    }

    protected function RGBToHex($r, $g, $b)
    {
        return ($r << 16) + ($g << 8) + $b;
    }

    protected function RGBToCIE($red, $green, $blue)
    {
        $red = ($red > 0.04045) ? pow(($red + 0.055) / (1.0 + 0.055), 2.4) : ($red / 12.92);
        $green = ($green > 0.04045) ? pow(($green + 0.055) / (1.0 + 0.055), 2.4) : ($green / 12.92);
        $blue = ($blue > 0.04045) ? pow(($blue + 0.055) / (1.0 + 0.055), 2.4) : ($blue / 12.92);

        $X = $red * 0.664511 + $green * 0.154324 + $blue * 0.162028;
        $Y = $red * 0.283881 + $green * 0.668433 + $blue * 0.047685;
        $Z = $red * 0.000088 + $green * 0.072310 + $blue * 0.986039;
        $this->SendDebug('RGBToCIE', 'X: ' . $X . ' Y: ' . $Y . ' Z: ' . $Z, 0);

        $cie['x'] = round(($X / ($X + $Y + $Z)), 4);
        $cie['y'] = round(($Y / ($X + $Y + $Z)), 4);

        return $cie;
    }

    protected function CIEToRGB($x, $y, $brightness = 255)
    {
        $z = 1.0 - $x - $y;
        $Y = $brightness / 255;
        $X = ($Y / $y) * $x;
        $Z = ($Y / $y) * $z;

        $red = $X * 1.656492 - $Y * 0.354851 - $Z * 0.255038;
        $green = -$X * 0.707196 + $Y * 1.655397 + $Z * 0.036152;
        $blue = $X * 0.051713 - $Y * 0.121364 + $Z * 1.011530;

        if ($red > $blue && $red > $green && $red > 1.0) {
            $green = $green / $red;
            $blue = $blue / $red;
            $red = 1.0;
        } elseif ($green > $blue && $green > $red && $green > 1.0) {
            $red = $red / $green;
            $blue = $blue / $green;
            $green = 1.0;
        } elseif ($blue > $red && $blue > $green && $blue > 1.0) {
            $red = $red / $blue;
            $green = $green / $blue;
            $blue = 1.0;
        }
        $red = $red <= 0.0031308 ? 12.92 * $red : (1.0 + 0.055) * $red ** (1.0 / 2.4) - 0.055;
        $green = $green <= 0.0031308 ? 12.92 * $green : (1.0 + 0.055) * $green ** (1.0 / 2.4) - 0.055;
        $blue = $blue <= 0.0031308 ? 12.92 * $blue : (1.0 + 0.055) * $blue ** (1.0 / 2.4) - 0.055;

        $red = ceil($red * 255);
        $green = ceil($green * 255);
        $blue = ceil($blue * 255);

        $color = sprintf('#%02x%02x%02x', $red, $green, $blue);

        return $color;
    }

    protected function HUE2RGB($p, $q, $t)
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    }

    protected function HSLToRGB($h, $s, $l)
    {
        if ($s == 0) {
            $r = $l;
            $g = $l;
            $b = $l; // achromatic
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->HUE2RGB($p, $q, $h + 1 / 3);
            $g = $this->HUE2RGB($p, $q, $h);
            $b = $this->HUE2RGB($p, $q, $h - 1 / 3);
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);

        $color = sprintf('#%02x%02x%02x', $red, $green, $blue);
        return $color;
        //return array(round($r * 255), round($g * 255), round($b * 255));
    }

    protected function convertHSL($h, $s, $l, $toHex = true)
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        $r = $l;
        $g = $l;
        $b = $l;
        $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
        if ($v > 0) {
            $m;
            $sv;
            $sextant;
            $fract;
            $vsf;
            $mid1;
            $mid2;

            $m = $l + $l - $v;
            $sv = ($v - $m) / $v;
            $h *= 6.0;
            $sextant = floor($h);
            $fract = $h - $sextant;
            $vsf = $v * $sv * $fract;
            $mid1 = $m + $vsf;
            $mid2 = $v - $vsf;

            switch ($sextant) {
                    case 0:
                          $r = $v;
                          $g = $mid1;
                          $b = $m;
                          break;
                    case 1:
                          $r = $mid2;
                          $g = $v;
                          $b = $m;
                          break;
                    case 2:
                          $r = $m;
                          $g = $v;
                          $b = $mid1;
                          break;
                    case 3:
                          $r = $m;
                          $g = $mid2;
                          $b = $v;
                          break;
                    case 4:
                          $r = $mid1;
                          $g = $m;
                          $b = $v;
                          break;
                    case 5:
                          $r = $v;
                          $g = $m;
                          $b = $mid2;
                          break;
              }
        }
        $r = round($r * 255, 0);
        $g = round($g * 255, 0);
        $b = round($b * 255, 0);

        if ($toHex) {
            $r = ($r < 15) ? '0' . dechex($r) : dechex($r);
            $g = ($g < 15) ? '0' . dechex($g) : dechex($g);
            $b = ($b < 15) ? '0' . dechex($b) : dechex($b);
            return "#$r$g$b";
        } else {
            return "rgb($r, $g, $b)";
        }
    }

    protected function hsv2rgb($hue, $sat, $val)
    {
        $rgb = [0, 0, 0];
        //calc rgb for 100% SV, go +1 for BR-range
        for ($i = 0; $i < 4; $i++) {
            if (abs($hue - $i * 120) < 120) {
                $distance = max(60, abs($hue - $i * 120));
                $rgb[$i % 3] = 1 - (($distance - 60) / 60);
            }
        }
        //desaturate by increasing lower levels
        $max = max($rgb);
        $factor = 255 * ($val / 100);
        for ($i = 0; $i < 3; $i++) {
            //use distance between 0 and max (1) and multiply with value
            $rgb[$i] = round(($rgb[$i] + ($max - $rgb[$i]) * (1 - $sat / 100)) * $factor);
        }
        $rgb['hex'] = sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
        return $rgb;
    }

    protected function RGBtoHSV($R, $G, $B)    // RGB values:    0-255, 0-255, 0-255
    {                                // HSV values:    0-360, 0-100, 0-100
          // Convert the RGB byte-values to percentages
          $R = ($R / 255);
        $G = ($G / 255);
        $B = ($B / 255);

        // Calculate a few basic values, the maximum value of R,G,B, the
        //   minimum value, and the difference of the two (chroma).
        $maxRGB = max($R, $G, $B);
        $minRGB = min($R, $G, $B);
        $chroma = $maxRGB - $minRGB;

        // Value (also called Brightness) is the easiest component to calculate,
        //   and is simply the highest value among the R,G,B components.
        // We multiply by 100 to turn the decimal into a readable percent value.
        $computedV = 100 * $maxRGB;

        // Special case if hueless (equal parts RGB make black, white, or grays)
        // Note that Hue is technically undefined when chroma is zero, as
        //   attempting to calculate it would cause division by zero (see
        //   below), so most applications simply substitute a Hue of zero.
        // Saturation will always be zero in this case, see below for details.
        if ($chroma == 0) {
            return [0, 0, $computedV];
        }

        // Saturation is also simple to compute, and is simply the chroma
        //   over the Value (or Brightness)
        // Again, multiplied by 100 to get a percentage.
        $computedS = 100 * ($chroma / $maxRGB);

        // Calculate Hue component
        // Hue is calculated on the "chromacity plane", which is represented
        //   as a 2D hexagon, divided into six 60-degree sectors. We calculate
        //   the bisecting angle as a value 0 <= x < 6, that represents which
        //   portion of which sector the line falls on.
        if ($R == $minRGB) {
            $h = 3 - (($G - $B) / $chroma);
        } elseif ($B == $minRGB) {
            $h = 1 - (($R - $G) / $chroma);
        } else { // $G == $minRGB
            $h = 5 - (($B - $R) / $chroma);
        }

        // After we have the sector position, we multiply it by the size of
        //   each sector's arc (60 degrees) to obtain the angle in degrees.
        $computedH = 60 * $h;

        return [round($computedH), round($computedS), round($computedV)];
    }
}