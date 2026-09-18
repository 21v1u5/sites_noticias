<?php

namespace App\Support;

/**
 * Inserts an ad block natively into article HTML instead of stacking it
 * before/after the content - Google's own guidance for in-article units is
 * to break up the reading flow, never wall it off. Placement defaults to
 * after the 2nd paragraph so a slot never appears above the fold before any
 * real content, which AdSense flags as a low-value-content placement.
 */
class ArticleAdInjector
{
    public static function insert(string $html, string $adHtml, int $afterParagraph = 2): string
    {
        if (trim($adHtml) === '' || trim($html) === '') {
            return $html;
        }

        $count = 0;

        $result = preg_replace_callback(
            '/<\/p>/i',
            function (array $matches) use ($adHtml, $afterParagraph, &$count) {
                $count++;

                return $matches[0].($count === $afterParagraph ? $adHtml : '');
            },
            $html,
        );

        if ($result === null) {
            return $html;
        }

        // Fewer paragraphs than the target position: append at the end
        // rather than silently dropping the ad slot.
        if ($count > 0 && $count < $afterParagraph) {
            $result .= $adHtml;
        }

        return $result;
    }
}
