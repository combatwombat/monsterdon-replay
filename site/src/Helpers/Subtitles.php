<?php

namespace App\Helpers;

class Subtitles extends \RTF\Base {

    private $videoWidth = 1920;
    private $videoHeight = 1080;
    private $chatWidth = 400;  // Width of chat column
    private $chatHeight = 1080; // long comments overlap the bottom anyway for now

    private $fontSize = 30; // font size in pixels
    private $characterWidthRatio = 0.45; // guess character width based on 20px font size
    private $lineHeightRatio = 1;     // guess height of one line
    private $topMargin = 20;   // Distance from top of screen

    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * generate .ass subtitle
     * @param $toots array [['name', 'content', time_delta], [...], ...]
     * @param $title string movie title
     * @param $movieDuration string movie duration in seconds
     * @return string
     */
    public function generate($toots, $title, $movieDuration) {
        $output = $this->header($title);
        $output .= $this->styles();
        $output .= "[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";

        /*
        go through each second of the movie.
        generate the subtitles for the current time.
        if they change compared to the last second, add end time to the current subtitles, start new subtitle

        generating subtitles for a time duration:
        get toots that happen <= the current time
        for each toot, create subtitle string. name in a unique color, content in white after it on one line
        break string into lines. guess character width, use $chatWidth. break words only if necessary
        keep track of vertical position of the last added toot. stop if we reach the bottom of the screen

        */

        $oldCurrentToots = [];
        $startTime = 0;
        $endTime = 0;
        for ($second = 1; $second <= $movieDuration; $second++) {
            $endTime = $second;

            $currentToots = [];
            foreach ($toots as $toot) {
                if ($toot['time_delta'] <= $second) {
                    $currentToots[] = $toot;
                }
            }

            // toots have changed. generate new subtitles
            if ($currentToots != $oldCurrentToots) {
                $output .= $this->generateSubtitles($currentToots, $startTime, $endTime);
                $startTime = $endTime;
                $oldCurrentToots = $currentToots;
            }



        }

        // generate the last subtitle
        $output .= $this->generateSubtitles($oldCurrentToots, $startTime, $endTime);

        return $output;
    }

    /**
     * Generate subtitles for a given time range
     * @param $toots
     * @param $startTime
     * @param $endTime
     * @return string dialog line
     */
    private function generateSubtitles($toots, $startTime, $endTime) {
        /*
         for each toot, create subtitle string. name in a unique color on one line, content in another colorwhite after it on one line
        break string into lines. guess character width, use $chatWidth. break words only if necessary
        keep track of vertical position of the last added toot. stop if we reach the bottom of the screen
         */

        $maxWidthCharacters = floor($this->chatWidth / ($this->fontSize * $this->characterWidthRatio));
        $output = '';
        $linesHeight = 0;
        foreach ($toots as $toot) {
            $name = str_replace(['\\', '{', '}'], ['\\\\', '\\{', '\\}'], $toot['name']);
            $content = str_replace(['\\', '{', '}'], ['\\\\', '\\{', '\\}'], $toot['content']);

            $linesHeight += $this->fontSize * $this->lineHeightRatio;

            // break content into paragraphs
            $paragraphs = explode("\n", $content);

            // break each paragraph into lines so that each line doesn't exceed the chat width. break up single words if necessary

            $contentLines = [];
            foreach ($paragraphs as $paragraph) {
                $words = explode(' ', $paragraph);
                $currentLine = '';


                foreach ($words as $word) {
                    // if adding this word exceeds max width
                    if (strlen($currentLine . ' ' . $word) > $maxWidthCharacters) {
                        // if the current line is empty and the word is longer than max width
                        if ($currentLine === '') {
                            // split the long word
                            $chunks = str_split($word, $maxWidthCharacters);
                            foreach ($chunks as $chunk) {
                                $contentLines[] = $chunk;
                            }
                        } else {
                            // add the current line and start a new one
                            $contentLines[] = trim($currentLine);
                            $currentLine = $word;
                        }
                    } else {
                        // add word to current line
                        $currentLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                    }
                }

                // add the last line if not empty
                if (trim($currentLine) !== '') {
                    $contentLines[] = trim($currentLine);
                }

                // add a blank line between paragraphs (except for the last paragraph)
                if ($paragraph !== end($paragraphs)) {
                    $contentLines[] = '';
                }
            }

            $nameString = $this->getColorForString($name) . $name;
            $contentString = '{\\c&HFFFFFF&}' . implode('\\N', $contentLines);

            $output .= $nameString . '\\N' . $contentString . '\\N\\N';

            $linesHeight += (1 + count($contentLines)) * ($this->fontSize * $this->lineHeightRatio);

            if ($linesHeight > $this->chatHeight) {
                break;
            }

        }

        $xPos = 20; // left aligned.
        // right-aligned would be: $this->videoWidth - $this->chatWidth - 20;
        // but that needs adjustments for different aspect ratios

        $yPos = $this->topMargin;

        $dialogLine =   "Dialogue: 0," .
                        $this->formatTime($startTime) .
                        "," .
                        $this->formatTime($endTime) .
                        ",Default,,0,0,0,,{\\pos(".$xPos.",".$yPos.")}{\\an7}$output\n";

        return $dialogLine;

    }

    /**
     * get a unique color in .ass format for a given string, based on its hash. same hash, same color
     * @param $string string
     * @return string color like {\\c&H3498DB&}
     */
    private function getColorForString($string) {
        $hash = md5($string);

        $redMin = 50;
        $redMax = 255;

        $greenMin = 50;
        $greenMax = 255;

        $blueMin = 50;
        $blueMax = 255;

        $red = hexdec(substr($hash, 0, 2)) * ($redMax - $redMin) / 255 + $redMin;
        $green = hexdec(substr($hash, 2, 2)) * ($greenMax - $greenMin) / 255 + $greenMin;
        $blue = hexdec(substr($hash, 4, 2)) * ($blueMax - $blueMin) / 255 + $blueMin;

        return sprintf('{\\c&H%02X%02X%02X&}', $red, $green, $blue);

    }

    private function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf("%d:%02d:%02d.00", $hours, $minutes, $secs);
    }

    private function header($title) {
        return "[Script Info]
Title: {$title}
ScriptType: v4.00+
Collisions: Normal
PlayResX: {$this->videoWidth}
PlayResY: {$this->videoHeight}

";
    }

    private function styles() {
        return "[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Arial,".$this->fontSize.",&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,2,0,2,10,10,10,1

";
    }
}