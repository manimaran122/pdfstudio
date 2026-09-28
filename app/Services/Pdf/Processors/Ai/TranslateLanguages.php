<?php

namespace App\Services\Pdf\Processors\Ai;

/**
 * Target languages for Translate PDF. Limited to scripts the output fonts
 * can draw (DejaVu Sans: Latin, Greek, Cyrillic; PyMuPDF's built-in CJK
 * fonts). Right-to-left scripts need shaping support the writer lacks.
 */
final class TranslateLanguages
{
    public const NAMES = [
        'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German',
        'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'pl' => 'Polish',
        'sv' => 'Swedish', 'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish',
        'cs' => 'Czech', 'ro' => 'Romanian', 'hu' => 'Hungarian', 'tr' => 'Turkish',
        'el' => 'Greek', 'ru' => 'Russian', 'uk' => 'Ukrainian', 'vi' => 'Vietnamese',
        'id' => 'Indonesian', 'zh' => 'Chinese (Simplified)', 'ja' => 'Japanese', 'ko' => 'Korean',
    ];
}
