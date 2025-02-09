<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Image_Canvas
 *
 * Canvas based creation of images to facilitate different output formats
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This library is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 2.1 of the License, or (at your
 * option) any later version. This library is distributed in the hope that it
 * will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser
 * General Public License for more details. You should have received a copy of
 * the GNU Lesser General Public License along with this library; if not, see
 * <http://www.gnu.org/licenses/>
 *
 * @category  Images
 * @package   Image_Canvas
 * @author    Jesper Veggerby <pear.nosey@veggerby.dk>
 * @author    Stefan Neufeind <pear.neufeind@speedpartner.de>
 * @copyright 2003-2009 The PHP Group
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version   SVN: $Id$
 * @link      http://pear.php.net/package/Image_Canvas
 */

/**
 * This class contains a set of methods related to fonts.
 *
 * These functions are all to be called statically
 *
 * @category  Images
 * @package   Image_Canvas
 * @author    Jesper Veggerby <pear.nosey@veggerby.dk>
 * @author    Stefan Neufeind <pear.neufeind@speedpartner.de>
 * @copyright 2003-2009 The PHP Group
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Image_Canvas
 * @abstract
 */
class Image_Canvas_Font_Tools
{
    // The singleton
    private static $_instance;

    // Properties
    // TODO: visibility
    public $system_font_path;
    public $lib_font_path;
    public $_font_map;  // private
    public $_save_font_map;  // private

    /**
     * Private constructor
     */
    private function __construct()
    {
        // Initialize system_font_path
        $this->system_font_path = [];
        if (isset($_SERVER['SystemRoot'])) {
            // Windows-case: the fonts are in the Fonts/ directory
            $this->system_font_path[] = $_SERVER['SystemRoot'] . '/Fonts/';
        } else {
            // Unix/Mac: try reasonable paths
            $potential_paths = array(
                // Unix
                "/usr/share/fonts/",
                "/usr/share/X11/fonts/Type1/",
                "/usr/share/X11/fonts/TTF/",
                "/usr/local/share/fonts/",
                // Mac
                "/Library/Fonts/",
                "~/Library/Fonts/",
            );
            foreach ($potential_paths as $potential_path) {
                if (is_dir($potential_path)) {
                    $this->system_font_path[] = $potential_path;
                }
            }
        }

        // Initialize library font path
        $this->lib_font_path = dirname(__FILE__) . '/Fonts/';

        // Initialize font_map
        $this->_readFontDB();
        $this->_save_font_map = false;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        // Write DB
        if ($this->_save_font_map) {
            $this->_writeFontDB();
        }
    }

    /**
     * Read the font DB (this called in __construct)
     *
     * @return null
     */
    private function _readFontDB()
    {
        $this->_font_map = array();
        if (file_exists($fontmap = ($this->lib_font_path . 'fontmap.txt'))) {
            $file = fopen($fontmap, 'r');
            while (($data = fgetcsv($file, 0, ',')) !== false) {
                $font_name = $data[0];
                foreach (array_slice($data, 1) as $filename) {
                    $type_pos = strrpos($filename, '.');
                    $type = substr($filename, $type_pos);
                    $this->_font_map[$font_name][$type] = $filename;
                }
            }
            fclose($file);
        }
    }

    /**
     * Write the font DB (this is called on destruction)
     *
     * @return null
     */
    private function _writeFontDB()
    {
        $fontmap = $this->lib_font_path . 'fontmap.txt';
        $file = fopen($fontmap, 'w');
        foreach ($this->_font_map as $font => $formats) {
            $data = array($font) + $formats;
            fputcsv($file, $data, ',');
        }
        fclose($file);
    }

    /**
     * Public method to get the instance
     */
    public static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new Image_Canvas_Font_Tools();
        }
        return self::$_instance;
    }

    /**
     * Maps a font name to an actual font file (fx. a .ttf file)
     *
     * Used to translate names (i.e. 'Courier New' to 'cour.ttf' or
     * '/Windows/Fonts/Cour.ttf')
     *
     * Font names are translated using the tab-separated file
     * Image/Canvas/Tool/fontmap.txt.
     *
     * The translated font-name (or the original if no translation) exists is
     * then returned if it is an existing file, otherwise the file is searched
     * first in the path specified by IMAGE_CANVAS_SYSTEM_FONT_PATH defined in
     * Image/Canvas.php, then in the Image/Canvas/Fonts folder. If a font is
     * still not found and the name is not beginning with a '/' the search is
     * left to the library, otherwise the font is deemed non-existing.
     *
     * @param string $name The name of the font
     * @param string $type The needed file type of the font
     *
     * @return string The filename of the font
     * @static
     */
    function fontMap($name, $type = '.ttf')
    {
        $type = strtolower($type);
        $_fontMap = $this->_font_map;

        if ((isset($_fontMap[$name])) && (isset($_fontMap[$name][$type]))) {
            $filename = $_fontMap[$name][$type];
        } else {
            $filename = $name;
        }

        if (substr($filename, -strlen($type)) !== $type) {
            $filename .= $type;
        }

        $result = false;
        $dirs = array_merge(
            array('.'),
            $this->system_font_path,
            array($this->lib_font_path)
        );
        foreach ($dirs as $dir) {
            $file = $dir . $filename;
            if (file_exists($file)) {
                $result = $file;
                return str_replace('\\', '/', $result);
            }
        }

        return false;
    }

    /**
     * Install a font and update font map
     *
     * @param string $name The font name
     * @param string $path The file containing the font
     * @param string $type The type. If null, infered from extension
     *
     * @return bool Installation successful?
     */
    function installFont($name, $path, $type = null)
    {
        $filename = basename($path);
        // Get type from filename
        if (!$type) {
            $type_pos = strrpos($filename, '.');
            $type = substr($filename, $type_pos);
        }
        $type = strtolower($type);

        if (isset($this->_font_map[$name][$type])) {
            echo('Already there');
            return;
        }

        // Copy file & update map
        $local_path = $this->lib_font_path . $filename;
        copy($path, $local_path);
        $this->_font_map[$name][$type] = $filename;
        $this->_save_font_map = true;
    }

    function installWebFonts()
    {
        // TODO
    }

}

/**
 * This class contains a set of methods related to geometry.
 *
 * These functions are all to be called statically
 *
 * @category  Images
 * @package   Image_Canvas
 * @author    Jesper Veggerby <pear.nosey@veggerby.dk>
 * @author    Stefan Neufeind <pear.neufeind@speedpartner.de>
 * @copyright 2003-2009 The PHP Group
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Image_Canvas
 * @abstract
 */
class Image_Canvas_Geometric_Tools
{
    /**
     * Return the average of 2 points
     *
     * @param double $p1 1st point
     * @param double $p2 2nd point
     *
     * @return double The average of P1 and P2
     * @static
     */
    static function mid($p1, $p2)
    {
        return ($p1 + $p2) / 2;
    }

    /**
     * Mirrors P1 in P2 by a amount of Factor
     *
     * @param double $p1     1st point, point to mirror
     * @param double $p2     2nd point, mirror point
     * @param double $factor Mirror factor, 0 returns $p2, 1 returns a pure
     *   mirror, ie $p1 on the exact other side of $p2
     *
     * @return double $p1 mirrored in $p2 by Factor
     * @static
     */
    static function mirror($p1, $p2, $factor = 1)
    {
        return $p2 + $factor * ($p2 - $p1);
    }

    /**
     * Calculates a Bezier control point, this function must be called for BOTH
     * X and Y coordinates (will it work for 3D coordinates!?)
     *
     * @param double $p1           1st point
     * @param double $p2           Point to
     * @param double $factor       ???
     * @param double $smoothFactor Smooth factor (???)
     *
     * @return double P1 mirrored in P2 by Factor
     * @static
     */
    static function controlPoint($p1, $p2, $factor, $smoothFactor = 0.75)
    {
        $sa = Image_Canvas_Geometric_Tools::mirror($p1, $p2, $smoothFactor);
        $sb = Image_Canvas_Geometric_Tools::mid($p2, $sa);

        $m = Image_Canvas_Geometric_Tools::mid($p2, $factor);

        $pC = Image_Canvas_Geometric_Tools::mid($sb, $m);

        return $pC;
    }

    /**
     * Calculates a Bezier point, this function must be called for BOTH X and Y
     * coordinates (will it work for 3D coordinates!?)
     *
     * @param double $t  A position between $p2 and $p3, value between 0 and 1
     * @param double $p1 Point to use for calculating control points
     * @param double $p2 Point 1 to calculate bezier curve between
     * @param double $p3 Point 2 to calculate bezier curve between
     * @param double $p4 Point to use for calculating control points
     *
     * @return double The bezier value of the point t between $p2 and $p3 using
     *   $p1 and $p4 to calculate control points
     * @static
     */
    static function bezier($t, $p1, $p2, $p3, $p4)
    {
        // (1-t)^3*p1 + 3*(1-t)^2*t*p2 + 3*(1-t)*t^2*p3 + t^3*p4
        return pow(1 - $t, 3) * $p1 +
            3 * pow(1 - $t, 2) * $t * $p2 +
            3 * (1 - $t) * pow($t, 2) * $p3 +
            pow($t, 3) * $p4;
    }

    /**
     * Gets the angle / slope of a line relative to horizontal (left -> right)
     *
     * @param double $x0 The starting x point
     * @param double $y0 The starting y point
     * @param double $x1 The ending x point
     * @param double $y1 The ending y point
     *
     * @return double The angle in degrees of the line
     * @static
     */
    static function getAngle($x0, $y0, $x1, $y1)
    {

        $dx = ($x1 - $x0);
        $dy = ($y1 - $y0);
        $l = sqrt($dx * $dx + $dy * $dy);
        $v = rad2deg(asin(($y0 - $y1) / $l));
        if ($dx < 0) {
            $v = 180 - $v;
        }
        return $v;

    }

}

?>
