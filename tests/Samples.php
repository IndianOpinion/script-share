<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

/**
 * Short real text in every listed script, used across the tests and by
 * the golden corpus generator. Only code points from Unicode 14 or older.
 */
final class Samples
{
    /** @var array<string, string> ISO code => sample */
    public const BY_SCRIPT = [
        'Deva' => 'भारत की राजधानी नई दिल्ली है',
        'Beng' => 'বাংলা ভাষা আমার মাতৃভাষা',
        'Guru' => 'ਪੰਜਾਬੀ ਮੇਰੀ ਮਾਂ ਬੋਲੀ ਹੈ',
        'Gujr' => 'ગુજરાતી ભાષા સુંદર છે',
        'Orya' => 'ଓଡ଼ିଆ ଭାଷା ବହୁତ ପୁରୁଣା',
        'Taml' => 'தமிழ் ஒரு பழமையான மொழி',
        'Telu' => 'తెలుగు భాష చాలా మధురమైనది',
        'Knda' => 'ಕನ್ನಡ ನಮ್ಮ ಭಾಷೆ',
        'Mlym' => 'മലയാളം ഒരു ദ്രാവിഡ ഭാഷയാണ്',
        'Sinh' => 'සිංහල භාෂාව ලස්සනයි',
        'Olck' => 'ᱥᱟᱱᱛᱟᱲᱤ ᱯᱟᱹᱨᱥᱤ',
        'Mtei' => 'ꯃꯤꯇꯩ ꯃꯌꯦꯛ',
        'Tibt' => 'བོད་ཀྱི་སྐད་ཡིག',
        'Arab' => 'اردو ایک خوبصورت زبان ہے',
        'Latn' => 'The quick brown fox jumps over the lazy dog',
        'Cyrl' => 'Съешь же ещё этих мягких французских булок',
        'Grek' => 'Γαζέες καὶ μυρτιὲς δὲν θὰ βρῶ πιὰ',
        'Hebr' => 'עברית היא שפה שמית',
        'Thai' => 'ภาษาไทยเป็นภาษาราชการ',
        'Hani' => '中文是一种语言',
        'Hira' => 'ひらがなです',
        'Kana' => 'カタカナデス',
        'Hang' => '한국어는 아름답다',
        'Zzzz' => 'Հայերեն ქართული',
    ];

    /** A Hindi news sentence that names an institution in Latin letters. */
    public const HINDI_WITH_NAMES = 'RBI ने बुधवार को रेपो दर में कोई बदलाव नहीं किया और महंगाई पर नज़र रखने की बात कही';

    /** A Telugu reply of well over twenty letters. */
    public const TELUGU = 'తెలుగు భాష చాలా మధురమైనది మరియు ప్రాచీనమైనది కూడా';

    /** A Hindi reply of well over twenty letters. */
    public const HINDI = 'भारत की राजधानी नई दिल्ली है और यह एक बड़ा शहर है';
}
