<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Every image upload in Express POS, defined once (owner, 2026-11-24): the
 * shape it's shown in, the best and smallest size, the formats and the
 * largest file. Used by the upload field (x-image-upload shows the rules
 * and checks a photo before it's sent), by validation (rules(), with
 * messages that say how to fix it), by the Help page "Image sizes", and by
 * shrink(), which brings a photo bigger than its best size down to it so
 * customers on slow mobile data never download more than they need.
 *
 * A photo of any shape is accepted and cropped from the centre where it's
 * shown; only a photo smaller than the minimum is refused, because it
 * would look blurred.
 */
class ImageRules
{
    /** The largest photo anyone may upload (owner, 2026-11-24), in KB. */
    public const MAX_KB = 4096;

    /**
     * label, where it's shown, shape (and ratio, width ÷ height, for the
     * preview frames), best and minimum size in pixels (null: no minimum),
     * accepted formats, largest file in KB, and the shapes it's cropped to
     * on the site (previews: label => ratio).
     */
    public const SLOTS = [
        'menu_item' => [
            'label' => 'Menu item photo',
            'shown' => 'Menu cards, the item page and the home page',
            'shape' => 'Square (1:1)',
            'best' => [1200, 1200],
            'min' => [600, 600],
            'formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => self::MAX_KB,
            'previews' => ['Classic card' => 1, 'Photo-led card' => 4 / 3],
            'tip' => 'Keep the dish in the middle with a little space around it.',
        ],
        'category_hero' => [
            'label' => 'Hero photo (one per category)',
            'shown' => 'The big photo at the top of the home page, and Photo-led\'s category tiles',
            'shape' => 'Wide (16:9)',
            'best' => [1920, 1080],
            'min' => [1280, 720],
            'formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => self::MAX_KB,
            'previews' => ['Computer' => 2.5, 'Phone' => 0.75, 'Category tile' => 4 / 3],
            'tip' => 'Phones show only the middle of the photo: keep the food in the centre, with no words in the picture.',
        ],
        'branch' => [
            'label' => 'Branch photo',
            'shown' => 'Branch cards on the Branches and Contact pages',
            'shape' => 'Wide (16:9)',
            'best' => [1280, 720],
            'min' => [800, 450],
            'formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => self::MAX_KB,
            'previews' => ['Branch card' => 16 / 9],
            'tip' => 'The shop front or inside, taken in daylight.',
        ],
        'promotion_banner' => [
            'label' => 'Promotion banner',
            'shown' => 'The strip above the home page on the days the promotion runs',
            'shape' => 'Wide strip (3:1)',
            'best' => [1500, 500],
            'min' => [900, 300],
            'formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => self::MAX_KB,
            'previews' => ['Banner' => 3],
            'tip' => 'Large, short words only; the headline you type shows under it anyway.',
        ],
        'staff' => [
            'label' => 'Staff photo',
            'shown' => 'Meet our staff, on the About page',
            'shape' => 'Square (1:1)',
            'best' => [800, 800],
            'min' => [400, 400],
            'formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => self::MAX_KB,
            'previews' => ['Staff card' => 1],
            'tip' => 'Head and shoulders, face in the middle.',
        ],
        'logo' => [
            'label' => 'Logo',
            'shown' => 'The website and POS header, receipts and the loading screen',
            'shape' => 'Wide, on a transparent background (PNG)',
            'best' => [800, null],
            'min' => [400, null],
            'formats' => ['png', 'jpg', 'jpeg', 'webp'],
            'max_kb' => 2048,
            'previews' => ['Header' => 4],
            'tip' => 'A PNG with a transparent background looks right on light and dark headers.',
        ],
        'icon' => [
            'label' => 'App icon',
            'shown' => 'The browser tab and a phone\'s home screen',
            'shape' => 'Square (1:1), PNG',
            'best' => [512, 512],
            'min' => [192, 192],
            'formats' => ['png'],
            'max_kb' => 1024,
            'previews' => ['Icon' => 1],
            'tip' => 'Just the symbol or first letter: words are too small to read at this size.',
            'exact_shape' => true,
        ],
        'evidence' => [
            'label' => 'Receipt or damage photo',
            'shown' => 'Only to the manager who checks it; deleted 24 hours after it\'s checked and decided',
            'shape' => 'Any',
            'best' => [1600, 1600],
            'min' => null,
            'formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => self::MAX_KB,
            'previews' => [],
            'tip' => 'Make sure the amounts or the damage can be read.',
        ],
    ];

    /** @return array<string, mixed> */
    public function slot(string $slot): array
    {
        return self::SLOTS[$slot] ?? throw new InvalidArgumentException("Unknown image slot [{$slot}].");
    }

    /**
     * Validation rules for an upload into $slot.
     *
     * @return list<string>
     */
    public function rules(string $slot, bool $required = false): array
    {
        $config = $this->slot($slot);
        $rules = [$required ? 'required' : 'nullable', 'file', 'image', 'mimes:'.implode(',', $config['formats']), 'max:'.$config['max_kb']];

        if ($config['min'] !== null) {
            [$width, $height] = $config['min'];
            $dimensions = array_filter(['min_width' => $width, 'min_height' => $height]);
            if ($config['exact_shape'] ?? false) {
                $dimensions['ratio'] = 1;
            }
            $rules[] = 'dimensions:'.http_build_query($dimensions, '', ',');
        }

        return $rules;
    }

    /**
     * Messages for $field's rules that say exactly what's wrong and how to
     * fix it, for a form request's messages().
     *
     * @return array<string, string>
     */
    public function messages(string $field, string $slot): array
    {
        $config = $this->slot($slot);
        $formats = $this->formatList($config['formats']);

        return [
            "{$field}.required" => __('Choose a photo to upload.'),
            "{$field}.uploaded" => __('The photo didn\'t arrive: it may be bigger than :max. Make it smaller (sending it to yourself on WhatsApp shrinks it) and try again.', ['max' => $this->size($config['max_kb'])]),
            "{$field}.image" => __('That file isn\'t a photo. Choose a :formats image.', ['formats' => $formats]),
            "{$field}.mimes" => __('That file type can\'t be used here. Choose a :formats image.', ['formats' => $formats]),
            "{$field}.max" => __('The photo is bigger than :max. Make it smaller (sending it to yourself on WhatsApp shrinks it) and try again.', ['max' => $this->size($config['max_kb'])]),
            ...($config['min'] !== null ? ["{$field}.dimensions" => $this->dimensionsMessage($config)] : []),
        ];
    }

    /** One line for the upload field and the Help page, e.g. "Square (1:1) · best 1200×1200 px …". */
    public function summary(string $slot): string
    {
        $config = $this->slot($slot);
        $parts = [__($config['shape'])];

        if ($config['min'] !== null) {
            $parts[] = __('best :size', ['size' => $this->pixels($config['best'])]);
            $parts[] = __('at least :size', ['size' => $this->pixels($config['min'])]);
        }
        $parts[] = $this->formatList($config['formats']);
        $parts[] = __('up to :max', ['max' => $this->size($config['max_kb'])]);

        return implode(' · ', $parts);
    }

    /**
     * Brings a stored photo bigger than its slot's best size down to fit
     * it (keeping its shape), turned the right way up, so a 4 MB phone
     * photo becomes a few hundred KB. Leaves smaller photos, and anything
     * GD can't read, exactly as they were.
     */
    public function shrink(string $absolutePath, string $slot): void
    {
        [$maxWidth, $maxHeight] = $this->slot($slot)['best'];
        $info = @getimagesize($absolutePath);

        if (! $info || ! function_exists('imagecreatetruecolor')) {
            return;
        }

        [$width, $height] = $info;
        $orientation = $this->orientation($absolutePath, $info['mime']);
        $sideways = in_array($orientation, [5, 6, 7, 8], true);
        // Limits apply to the photo as it will be seen (after turning).
        [$seenWidth, $seenHeight] = $sideways ? [$height, $width] : [$width, $height];
        $scale = min(1, $maxWidth / $seenWidth, $maxHeight ? $maxHeight / $seenHeight : 1);

        if ($scale >= 1 && $orientation <= 1) {
            return;
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if (! $source) {
            return;
        }

        $source = $this->turn($source, $orientation);
        $newWidth = max(1, (int) round(imagesx($source) * $scale));
        $newHeight = max(1, (int) round(imagesy($source) * $scale));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($source), imagesy($source));

        match ($info['mime']) {
            'image/jpeg' => imagejpeg($target, $absolutePath, 82),
            'image/png' => imagepng($target, $absolutePath, 6),
            'image/webp' => imagewebp($target, $absolutePath, 82),
        };
    }

    /** Shrinks an upload in place before it's stored (private evidence photos). */
    public function shrinkUpload(UploadedFile $file, string $slot): void
    {
        if ($file->getRealPath()) {
            $this->shrink($file->getRealPath(), $slot);
        }
    }

    /** "JPG, PNG or WEBP" for $slot. */
    public function formats(string $slot): string
    {
        return $this->formatList($this->slot($slot)['formats']);
    }

    /** "4 MB", "512 KB". */
    public function size(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : $kb.' KB';
    }

    /** @param  array{0: int, 1: ?int}  $size */
    private function pixels(array $size): string
    {
        return $size[1] ? $size[0].'×'.$size[1].' px' : __(':width px wide', ['width' => $size[0]]);
    }

    /** @param  list<string>  $formats */
    private function formatList(array $formats): string
    {
        $names = collect($formats)->map(fn ($f) => ['jpg' => 'JPG', 'png' => 'PNG', 'webp' => 'WebP'][$f] ?? null)->filter()->unique()->values();

        return $names->count() > 1 ? $names->slice(0, -1)->join(', ').' '.__('or').' '.$names->last() : (string) $names->first();
    }

    /** @param  array<string, mixed>  $config */
    private function dimensionsMessage(array $config): string
    {
        if ($config['exact_shape'] ?? false) {
            return __('The icon must be square and at least :size. Crop it to a square and try again.', ['size' => $this->pixels($config['min'])]);
        }

        return __('The photo is too small: it would look blurred. Use one at least :size (best :best).', [
            'size' => $this->pixels($config['min']),
            'best' => $this->pixels($config['best']),
        ]);
    }

    private function orientation(string $path, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        return (int) (@exif_read_data($path)['Orientation'] ?? 1);
    }

    /** Applies a JPEG's EXIF orientation, so a phone photo isn't stored on its side. */
    private function turn(\GdImage $image, int $orientation): \GdImage
    {
        $image = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }
}
