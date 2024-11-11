<?php

namespace App\Helpers;

class Subtitles extends \RTF\Base {
    private $videoWidth = 1920;
    private $videoHeight = 1080;
    private $chatWidth = 400;  // Width of chat column
    private $lineHeight = 40;  // Reduced height per message for better spacing
    private $visibleLines = 12;
    private $displayDuration = 30;
    private $topMargin = 20;   // Distance from top of screen

    public function __construct($container) {
        $this->container = $container;
    }

    public function generate($toots, $title) {
        $output = $this->header($title);
        $output .= $this->styles();
        $output .= "[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";

        // Group messages by timestamp to handle multiple messages per second
        $messagesByTime = [];
        foreach ($toots as $toot) {
            $time = $toot['time_delta'];
            if (!isset($messagesByTime[$time])) {
                $messagesByTime[$time] = [];
            }
            $messagesByTime[$time][] = $toot;
        }

        // Process messages
        foreach ($messagesByTime as $time => $timeToots) {
            // For messages at the same timestamp, stack them vertically
            foreach ($timeToots as $index => $toot) {
                $startTime = $this->formatTime($toot['time_delta']);
                $endTime = $this->formatTime($toot['time_delta'] + $this->displayDuration);

                // Calculate how many newer message groups would push this down
                $newerGroups = 0;
                foreach ($messagesByTime as $laterTime => $laterToots) {
                    if ($laterTime > $time && $laterTime < $time + $this->displayDuration) {
                        $newerGroups += count($laterToots);
                    }
                }

                // Escape special characters
                $username = str_replace(['\\', '{', '}'], ['\\\\', '\\{', '\\}'], $toot['account']['display_name']);
                $content = str_replace(['\\', '{', '}'], ['\\\\', '\\{', '\\}'], $toot['content']);

                // Calculate vertical position
                // Start from top margin and account for both newer groups and position within current group
                $yPos = $this->topMargin + ($newerGroups * $this->lineHeight) + ($index * $this->lineHeight);

                // Don't show if pushed too far down
                if ($newerGroups < $this->visibleLines) {
                    $text = $this->formatMessage($username, $content, $yPos);
                    $output .= $this->dialogueLine($startTime, $endTime, $text);
                }
            }
        }

        return $output;
    }

    private function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf("%d:%02d:%02d.00", $hours, $minutes, $secs);
    }

    private function formatMessage($username, $content, $yPos) {
        // Position at right side of screen, with slight margin
        $xPos = $this->videoWidth - $this->chatWidth + 20; // 20px margin from right edge

        return sprintf(
            "{\\pos(%d,%d)}{\\an7}". // an7 means align to top-left
            "{\\c&H3498DB&}%s\\N".   // Username in blue
            "{\\c&HFFFFFF&}%s",      // Content in white
            $xPos,
            $yPos,
            $username,
            $content
        );
    }

    private function dialogueLine($start, $end, $text) {
        return "Dialogue: 0,$start,$end,Default,,0,0,0,,$text\n";
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
Style: Default,Arial,20,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,2,0,2,10,10,10,1

";
    }
}