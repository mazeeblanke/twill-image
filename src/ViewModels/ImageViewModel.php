<?php

namespace A17\Twill\Image\ViewModels;

use Spatie\ViewModels\ViewModel;
use A17\Twill\Image\Models\Image;
use A17\Twill\Image\Services\ImageStyles;
use Illuminate\Contracts\Support\Arrayable;

class ImageViewModel extends ViewModel implements Arrayable
{
    /**
     * @var string LAYOUT_FULL_WIDTH Set layout to take full width of container element
     */
    const LAYOUT_FULL_WIDTH = 'fullWidth';

    /**
     * @var string LAYOUT_CONSTRAINED Set layout to take full width of container element up to specified max-width or image original width
     */
    const LAYOUT_CONSTRAINED = 'constrained';

    /**
     * @var string LAYOUT_FIXED Set layout to take a fixed exact width and height
     */
    const LAYOUT_FIXED = 'fixed';

    /**
     * @var string $alt Description of the image
     */
    protected $alt;

    /**
     * @var null|string $backgroundColor Image background color
     */
    protected $backgroundColor;

    /**
     * @var array $data Image source
     */
    protected $data;

    /**
     * @var int $height Height of the image
     */
    protected $height;

    /**
     * @var string $layout One of the available layout "fullWidth", "constrained" or "fixed"
     */
    protected $layout;

    /**
     * @var string $loading <img> loading attribute "lazy" (default) or "eager"
     */
    protected $loading;

    /**
     * @var bool $lqip Display a low-quality placeholder
     */
    protected $lqip;

    /**
     * @var array $sources LQIP image sources attributes
     */
    protected $lqipSources;

    /**
     * @var string $lqipSrc Default LQIP image source url
     */
    protected $lqipSrc;

    /**
     * @var string $sizes Sizes attributes
     */
    protected $sizes;

    /**
     * @var array $sources Image sources attributes
     */
    protected $sources;

    /**
     * @var string $src Default image source url
     */
    protected $src;

    /**
     * @var int $width Width of the image
     */
    protected $width;

    /**
     * @var ImageStyles
     */
    protected $styleService;

    /**
     * @var int $wrapperClass CSS class added to the wrapper element
     */
    protected $wrapperClass;

    /**
     * ImageViewModel format an Image instance attributes to be passed to the image wrapper view.
     *
     * @param Image|array $data Image source
     * @param array $overrides Overrides frontend options
     */
    public function __construct($data, $overrides = [])
    {
        if ($data instanceof Arrayable) {
            $this->data = $data->toArray();
        } else {
            $this->data = $data;
        }

        $this->setAttributes($overrides);
        $this->setImageAttributes();
        $this->setSourcesAttributes();
        $this->setLqipAttributes();

        $this->styleService = new ImageStyles();
        $this->styleService->setup(
            $this->layout,
            $this->backgroundColor,
            $this->width,
            $this->height
        );
    }

    /**
     * Process arguments and apply default values.
     *
     * @param array $overrides
     * @return void
     */
    protected function setAttributes($overrides)
    {
        $this->backgroundColor
            = $overrides['backgroundColor']
            ?? config('twill-image.background_color')
            ?? 'transparent';

        $this->layout
            = $overrides['layout']
            ?? $this->data['layout']
            ?? self::LAYOUT_FULL_WIDTH;

        $this->loading = $overrides['loading'] ?? 'lazy';

        $this->lqip
            = $overrides['lqip']
            ?? config('twill-image.lqip')
            ?? true;

        $this->sizes
            = $overrides['sizes']
            ?? $this->data['sizes']
            ?? $this->defaultSizesAttribute();

        $this->wrapperClass = $overrides['class'] ?? null;

        $this->width = $overrides['width'] ?? null;

        $this->height = $overrides['height'] ?? null;
    }

    /**
     * Set main image attributes
     *
     * @return void
     */
    protected function setImageAttributes()
    {
        $image = $this->data['image'];

        $this->src = $image['src'];
        $this->alt = $image['alt'];
        $this->width = $this->width ?? $image['width'];
        $this->height = $this->height ?? $image['height'];
    }

    /**
     * Construct the sources attributes from the image main and additional sources
     *
     * @return void
     */
    protected function setSourcesAttributes()
    {
        $sources = [];

        if (isset($this->data['sources'])) {
            foreach ($this->data['sources'] as $source) {
                $mediaQuery = $source['mediaQuery'];
                $image = $source['image'];

                $sources[] = $this->buildSourceObject(
                    $image['srcSet'],
                    $image['aspectRatio'],
                    $this->mimeType($image['extension']),
                    $mediaQuery
                );

                if (config('twill-image.webp_support')) {
                    $sources[] = $this->buildSourceObject(
                        $image['srcSetWebp'],
                        $image['aspectRatio'],
                        $this->mimeType("webp"),
                        $mediaQuery
                    );
                }
            }
        }

        $image = $this->data['image'];

        $sources[] = $this->buildSourceObject(
            $image['srcSet'],
            $image['aspectRatio'],
            $this->mimeType($image['extension'])
        );

        if (config('twill-image.webp_support')) {
            $sources[] = $this->buildSourceObject(
                $image['srcSet'],
                $image['aspectRatio'],
                $this->mimeType("webp"),
            );
        }

        $this->sources = $sources;
    }

    protected function buildSourceObject($srcSet, $aspectRatio, $type = null, $mediaQuery = null)
    {
        return array_filter([
            'srcset' => $srcSet,
            'aspectRatio' => $aspectRatio * 100,
            'type' => $type,
            'mediaQuery' => $mediaQuery,
        ]);
    }

    protected function mimeType($extension)
    {
        $types = [
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',
        ];

        if (isset($types[$extension])) {
            return $types[$extension];
        }

        return null;
    }

    /**
     * Create a default sizes attributes when none is passed to the view
     *
     * @return void|string
     */
    protected function defaultSizesAttribute()
    {
        switch ($this->layout) {
            case self::LAYOUT_CONSTRAINED:
                return '(min-width:' .
                    $this->width .
                    'px) ' .
                    $this->width .
                    'px, 100vw';
            case self::LAYOUT_FIXED:
                return $this->width . 'px';
            case self::LAYOUT_FULL_WIDTH:
                return '100vw';
            default:
                return null;
        }
    }

    protected function wrapperClasses()
    {
        $layout = $this->layout;
        $classes = 'twill-image-wrapper';

        if ($layout === self::LAYOUT_CONSTRAINED) {
            $classes = 'twill-image-wrapper twill-image-wrapper-constrained';
        }

        if (isset($this->wrapperClass)) {
            $classes = join(' ', [$classes, $this->wrapperClass]);
        }

        return $classes;
    }

    /**
     * Set LQIP src and sources attributes
     *
     * @return void
     */
    protected function setLqipAttributes()
    {
        if (!$this->lqip) {
            $this->lqipSrc = null;
            return;
        }

        $image = $this->data['image'];

        $this->lqipSrc = $image['lqipBase64'];

        if (!isset($this->data['sources'])) {
            $this->lqipSources = null;
            return;
        }

        $sources = [];

        foreach ($this->data['sources'] as $source) {
            $mediaQuery = $source['mediaQuery'];
            $image = $source['image'];

            $sources[] = $this->buildSourceObject(
                sprintf('%s 1x', $image['lqipBase64']),
                $image['aspectRatio'],
                $this->mimeType("gif"),
                $mediaQuery
            );
        }

        $this->lqipSources = $sources;
    }

    public function toArray(): array
    {
        return array_filter([
            'alt' => $this->alt,
            'aspectRatio' => $this->data['image']['aspectRatio'],
            'height' => $this->height,
            'layout' => $this->layout,
            'loading' => $this->loading,
            'mainStyle' => $this->styleService->main($this->loading),
            'mainSrc' => $this->src,
            'mainSources' => $this->sources,
            'placeholderSrc' => $this->lqipSrc,
            'placeholderSources' => $this->lqipSources,
            'placeholderStyle' => $this->styleService->placeholder(),
            'shouldLoad' => $this->loading === 'eager',
            'sizes' => $this->sizes,
            'width' => $this->width,
            'wrapperClasses' => $this->wrapperClasses(),
            'wrapperStyle' => $this->styleService->wrapper(),
        ]);
    }
}
